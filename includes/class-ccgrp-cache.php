<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * CCGRP_Cache
 *
 * Transient cache with three layers of protection:
 *
 * 1. GENERATION-BASED INVALIDATION — every live cache key is salted with a
 *    generation counter stored in wp_options. "Flushing" increments the
 *    counter, instantly orphaning all live entries without SQL DELETEs.
 *    Orphaned transients expire naturally via their TTL.
 *
 * 2. STALE FALLBACK — every successful API response is also stored under a
 *    long-lived (24h), generation-independent stale key. If the API rate
 *    limits us, visitors get slightly old data instead of an error. Stale
 *    copies deliberately survive cache flushes: a flush means "fetch fresh,"
 *    and if that fresh fetch fails, old data still beats no data.
 *
 * 3. CIRCUIT BREAKER — a 429 from PCO trips a 60-second breaker per key,
 *    during which we serve stale and don't hammer the API.
 */
class CCGRP_Cache {

    const DEFAULT_TTL       = 300;
    const MIN_TTL           = 60;
    const CIRCUIT_BREAK_TTL = 60;
    const STALE_TTL         = 86400;
    const GEN_OPTION        = 'ccgrp_cache_generation';

    /** Memoized generation — kept in a class property so flush_all() can bump it in-request. */
    private static $gen = null;

    /** Get current cache generation (memoized per request). */
    private static function generation() {
        if ( self::$gen === null ) {
            self::$gen = (int) get_option( self::GEN_OPTION, 1 );
        }
        return self::$gen;
    }

    /** Live key: salted with generation, so flushes orphan it instantly. */
    private static function live_key( $cache_key ) {
        return 'ccgrp_' . md5( self::generation() . '|' . $cache_key );
    }

    /** Stale key: NOT generation-salted — survives flushes on purpose. */
    private static function stale_key( $cache_key ) {
        return 'ccgrp_s_' . md5( $cache_key );
    }

    /** Breaker key: NOT generation-salted — a rate limit is a rate limit. */
    private static function break_key( $cache_key ) {
        return 'ccgrp_b_' . md5( $cache_key );
    }

    /** Effective TTL: option value with a sane floor (0 would mean "never expire"). */
    private static function ttl( $override = null ) {
        $ttl = $override !== null ? (int) $override : (int) get_option( 'ccgrp_cache_ttl', self::DEFAULT_TTL );
        return max( self::MIN_TTL, $ttl );
    }

    /**
     * Get cached data or fetch fresh via $callback.
     *
     * @param string   $cache_key Logical key (pre-hash).
     * @param callable $callback  Returns array on success or WP_Error.
     * @param int|null $ttl       Optional TTL override in seconds.
     * @return array|WP_Error
     */
    public static function remember( $cache_key, $callback, $ttl = null ) {
        $live = self::live_key( $cache_key );

        $cached = get_transient( $live );
        if ( false !== $cached ) return $cached;

        // Circuit breaker tripped? Serve stale if we have it.
        if ( get_transient( self::break_key( $cache_key ) ) ) {
            $stale = get_transient( self::stale_key( $cache_key ) );
            if ( false !== $stale ) return $stale;
        }

        $fresh = call_user_func( $callback );

        if ( is_wp_error( $fresh ) ) {
            $data = $fresh->get_error_data();
            if ( isset( $data['status'] ) && (int) $data['status'] === 429 ) {
                set_transient( self::break_key( $cache_key ), 1, self::CIRCUIT_BREAK_TTL );
            }
            // On ANY API failure, prefer stale data over an error for visitors.
            $stale = get_transient( self::stale_key( $cache_key ) );
            if ( false !== $stale ) return $stale;
            return $fresh;
        }

        set_transient( $live,                        $fresh, self::ttl( $ttl ) );
        set_transient( self::stale_key( $cache_key ), $fresh, self::STALE_TTL  );

        return $fresh;
    }

    /**
     * Flush ALL live plugin caches instantly by bumping the generation.
     * Stale fallback copies are preserved by design. Orphaned transients
     * expire on their own; we opportunistically clean expired rows too.
     */
    public static function flush_all() {
        self::$gen = self::generation() + 1;
        update_option( self::GEN_OPTION, self::$gen, false );
        self::cleanup_expired();
    }

    /**
     * Flush caches related to one group. Because list views embed many
     * groups, any group change also invalidates lists — and since live
     * keys are generation-salted, the cheapest correct move is a
     * generation bump. Kept as a distinct method so webhook code reads
     * clearly and so this can become more surgical later.
     */
    public static function flush_group( $group_id ) {
        self::flush_all();
    }

    /** Back-compat alias used by webhook/admin code paths. */
    public static function flush_lists() {
        self::flush_all();
    }

    /** Delete only EXPIRED ccpco transients (cheap housekeeping, correct SQL pair-wise). */
    private static function cleanup_expired() {
        global $wpdb;
        $now = time();
        // Find expired timeout rows, then delete both the timeout and its value row.
        $names = $wpdb->get_col( $wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options}
             WHERE option_name LIKE %s AND option_value < %d LIMIT 200",
            $wpdb->esc_like( '_transient_timeout_ccpco' ) . '%',
            $now
        ) );
        foreach ( (array) $names as $timeout_name ) {
            $key = substr( $timeout_name, strlen( '_transient_timeout_' ) );
            delete_transient( $key ); // removes both rows, respects object cache
        }
    }
}
