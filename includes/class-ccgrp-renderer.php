<?php
/**
 * CCGRP_Renderer — HTML for the group finder.
 *
 * Markup is intentionally plain and class-namespaced (.ccgrp-*) so a Bricks/theme
 * stylesheet can restyle it without fighting inline styles. Only the brand accent
 * is injected as a CSS variable, so a site can override it with normal CSS.
 *
 * Everything is escaped at output. PCO group descriptions are author-entered rich
 * text, so they run through wp_kses_post() rather than esc_html() — see render_card().
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class CCGRP_Renderer {

    /**
     * Enqueue front-end assets.
     *
     * Bricks calls this from an element's enqueue_scripts(); shortcodes call it at
     * render time. Both paths are safe to call repeatedly — WP dedupes by handle.
     *
     * @param bool $with_script Only the filter bar needs JS.
     */
    public static function ensure_assets( $with_script = true ) {
        wp_enqueue_style( 'ccgrp' );
        if ( $with_script ) wp_enqueue_script( 'ccgrp' );
    }

    /**
     * Full finder: optional filter bar + responsive card grid.
     *
     * @param array $groups Flattened groups (see CCGRP_API::flatten).
     * @param array $atts   Display options from the shortcode/block.
     */
    public static function render_list( $groups, $atts = array() ) {

        $atts = wp_parse_args( $atts, array(
            'columns'     => 3,
            'show_filter' => true,
            'show_image'  => true,
            'show_schedule' => true,
            'show_location' => true,
            'empty_text'  => __( 'No groups are open right now — check back soon.', 'comms-church-groups' ),
        ) );

        /* Only the filter bar needs JS, so only load it when we render one. */
        self::ensure_assets( (bool) $atts['show_filter'] );

        if ( empty( $groups ) ) {
            return '<div class="ccgrp ccgrp--empty"><p>' . esc_html( $atts['empty_text'] ) . '</p></div>';
        }

        $cols  = max( 1, min( 4, (int) $atts['columns'] ) );
        $style = self::inline_vars();

        ob_start();
        ?>
        <div class="ccgrp" style="<?php echo esc_attr( $style ); ?>">
            <?php if ( $atts['show_filter'] ) echo self::render_filter( $groups ); ?>
            <div class="ccgrp-grid ccgrp-grid--<?php echo (int) $cols; ?>" role="list">
                <?php foreach ( $groups as $g ) echo self::render_card( $g, $atts ); ?>
            </div>
            <p class="ccgrp-noresults" hidden><?php esc_html_e( 'No groups match those filters.', 'comms-church-groups' ); ?></p>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Filter bar. Progressive enhancement: it's a plain <select> + text input that a
     * tiny inline script wires to the cards. With JS off, all groups simply show.
     */
    private static function render_filter( $groups ) {
        $types = array();
        foreach ( $groups as $g ) {
            $name = $g['group_type']['name'] ?? '';
            if ( $name ) $types[ $name ] = true;
        }
        if ( count( $types ) < 2 ) $types = array(); // a lone category isn't worth a control
        ksort( $types );

        ob_start();
        ?>
        <div class="ccgrp-filter">
            <label class="ccgrp-filter__field">
                <span class="screen-reader-text"><?php esc_html_e( 'Search groups', 'comms-church-groups' ); ?></span>
                <input type="search" class="ccgrp-search" placeholder="<?php esc_attr_e( 'Search groups…', 'comms-church-groups' ); ?>" autocomplete="off">
            </label>
            <?php if ( $types ) : ?>
                <label class="ccgrp-filter__field">
                    <span class="screen-reader-text"><?php esc_html_e( 'Filter by type', 'comms-church-groups' ); ?></span>
                    <select class="ccgrp-type">
                        <option value=""><?php esc_html_e( 'All types', 'comms-church-groups' ); ?></option>
                        <?php foreach ( array_keys( $types ) as $t ) : ?>
                            <option value="<?php echo esc_attr( $t ); ?>"><?php echo esc_html( $t ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /** One group card. */
    public static function render_card( $g, $atts = array() ) {
        $atts = wp_parse_args( $atts, array(
            'show_image' => true, 'show_schedule' => true, 'show_location' => true,
        ) );

        $type     = $g['group_type']['name'] ?? '';
        $loc      = $g['location']['name'] ?? ( $g['location']['address'] ?? '' );
        $open     = self::is_open( $g );
        $signup   = $g['signup_url'] ?? '';

        /* Descriptions come from PCO as author-entered HTML. kses_post keeps safe
           formatting (links, emphasis, lists) and strips scripts/iframes. */
        $desc = wp_kses_post( $g['description'] ?? '' );
        $desc = wp_trim_words( wp_strip_all_tags( $desc ), 28, '…' );

        ob_start();
        ?>
        <article class="ccgrp-card" role="listitem"
                 data-name="<?php echo esc_attr( strtolower( $g['name'] . ' ' . $desc . ' ' . $loc ) ); ?>"
                 data-type="<?php echo esc_attr( $type ); ?>">

            <?php if ( $atts['show_image'] && ! empty( $g['image'] ) ) : ?>
                <div class="ccgrp-card__media">
                    <img src="<?php echo esc_url( $g['image'] ); ?>" alt="" loading="lazy" decoding="async">
                </div>
            <?php endif; ?>

            <div class="ccgrp-card__body">
                <?php if ( $type ) : ?>
                    <span class="ccgrp-card__type"><?php echo esc_html( $type ); ?></span>
                <?php endif; ?>

                <h3 class="ccgrp-card__title"><?php echo esc_html( $g['name'] ); ?></h3>

                <?php if ( $desc ) : ?>
                    <p class="ccgrp-card__desc"><?php echo esc_html( $desc ); ?></p>
                <?php endif; ?>

                <ul class="ccgrp-card__meta">
                    <?php if ( $atts['show_schedule'] && ! empty( $g['schedule'] ) ) : ?>
                        <li class="ccgrp-meta ccgrp-meta--when"><?php echo esc_html( $g['schedule'] ); ?></li>
                    <?php endif; ?>
                    <?php if ( $atts['show_location'] && $loc ) : ?>
                        <li class="ccgrp-meta ccgrp-meta--where"><?php echo esc_html( $loc ); ?></li>
                    <?php endif; ?>
                </ul>

                <div class="ccgrp-card__foot">
                    <?php if ( $signup ) : ?>
                        <a class="ccgrp-btn" href="<?php echo esc_url( $signup ); ?>" target="_blank" rel="noopener">
                            <?php echo $open
                                ? esc_html__( 'Join this group', 'comms-church-groups' )
                                : esc_html__( 'View group', 'comms-church-groups' ); ?>
                        </a>
                    <?php elseif ( ! empty( $g['contact_email'] ) ) : ?>
                        <a class="ccgrp-btn" href="mailto:<?php echo esc_attr( $g['contact_email'] ); ?>">
                            <?php esc_html_e( 'Contact leader', 'comms-church-groups' ); ?>
                        </a>
                    <?php endif; ?>
                    <?php if ( ! $open ) : ?>
                        <span class="ccgrp-badge"><?php esc_html_e( 'Closed', 'comms-church-groups' ); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </article>
        <?php
        return ob_get_clean();
    }

    /**
     * Whether a group is accepting signups.
     *
     * PCO exposes enrollment in more than one shape depending on API version, so treat
     * anything explicitly closed/full as closed and default to open — a false "Closed"
     * badge costs a real signup, a false "open" just sends them to PCO which tells them.
     */
    private static function is_open( $g ) {
        $e = strtolower( (string) ( $g['enrollment'] ?? '' ) );
        if ( '' === $e ) return true;
        foreach ( array( 'closed', 'full', 'not_open', 'no_signup' ) as $needle ) {
            if ( false !== strpos( $e, $needle ) ) return false;
        }
        return true;
    }

    /** Brand accent as a CSS custom property so themes can override it normally. */
    private static function inline_vars() {
        $accent = get_option( 'ccgrp_brand_color', '' );
        return $accent ? '--ccgrp-accent:' . esc_attr( $accent ) . ';' : '';
    }

    /** Consistent error box; never leaks credentials or raw API payloads to visitors. */
    public static function render_error( $wp_error, $for_admins_only = true ) {
        if ( $for_admins_only && ! current_user_can( 'manage_options' ) ) {
            return ''; // visitors see nothing rather than a broken-looking page
        }
        return '<div class="ccgrp ccgrp--error"><p><strong>'
            . esc_html__( 'Groups could not be loaded.', 'comms-church-groups' ) . '</strong> '
            . esc_html( $wp_error->get_error_message() ) . '</p></div>';
    }
}
