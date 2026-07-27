<?php
/**
 * CCGRP_Shortcodes
 *
 *   [cc_groups]                      — the finder (filter bar + card grid)
 *   [cc_group id="123"]              — a single group card
 *
 * Both read through CCGRP_Cache, so a page full of groups is one API call at most,
 * and a PCO outage serves stale data rather than an error (see the cache class).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class CCGRP_Shortcodes {

    public static function register() {
        add_shortcode( 'cc_groups', array( __CLASS__, 'groups' ) );
        add_shortcode( 'cc_group',  array( __CLASS__, 'group' ) );
    }

    /**
     * [cc_groups type="Men's" columns="3" limit="0" filter="yes"]
     *
     * `type` filters client-side by group-type name so the cached payload stays
     * shared across pages — filtering server-side would fragment the cache per page.
     */
    public static function groups( $atts ) {
        $atts = shortcode_atts( array(
            'type'      => '',
            'columns'   => 3,
            'limit'     => 0,
            'filter'    => 'yes',
            'image'     => 'yes',
            'schedule'  => 'yes',
            'location'  => 'yes',
            'empty_text'=> '',
        ), $atts, 'cc_groups' );

        $api = new CCGRP_API();
        if ( ! $api->is_configured() ) {
            return self::not_configured_notice();
        }

        $groups = CCGRP_Cache::remember( 'groups_all', function () use ( $api ) {
            $body = $api->get_groups();
            if ( is_wp_error( $body ) ) return $body;
            return CCGRP_API::flatten_all( $body );
        } );

        if ( is_wp_error( $groups ) ) {
            return CCGRP_Renderer::render_error( $groups );
        }

        if ( $atts['type'] ) {
            $want = strtolower( trim( $atts['type'] ) );
            $groups = array_values( array_filter( $groups, function ( $g ) use ( $want ) {
                return strtolower( $g['group_type']['name'] ?? '' ) === $want;
            } ) );
        }

        if ( (int) $atts['limit'] > 0 ) {
            $groups = array_slice( $groups, 0, (int) $atts['limit'] );
        }

        $opts = array(
            'columns'       => $atts['columns'],
            'show_filter'   => self::truthy( $atts['filter'] ),
            'show_image'    => self::truthy( $atts['image'] ),
            'show_schedule' => self::truthy( $atts['schedule'] ),
            'show_location' => self::truthy( $atts['location'] ),
        );
        if ( $atts['empty_text'] !== '' ) $opts['empty_text'] = $atts['empty_text'];

        return CCGRP_Renderer::render_list( $groups, $opts );
    }

    /** [cc_group id="123"] */
    public static function group( $atts ) {
        $atts = shortcode_atts( array(
            'id'       => '',
            'image'    => 'yes',
            'schedule' => 'yes',
            'location' => 'yes',
        ), $atts, 'cc_group' );

        if ( '' === $atts['id'] ) {
            return self::admin_hint( __( '[cc_group] needs an id attribute, e.g. [cc_group id="123"].', 'comms-church-groups' ) );
        }

        $api = new CCGRP_API();
        if ( ! $api->is_configured() ) return self::not_configured_notice();

        $id = $atts['id'];
        $group = CCGRP_Cache::remember( 'group_' . $id, function () use ( $api, $id ) {
            $body = $api->get_group( $id );
            if ( is_wp_error( $body ) ) return $body;
            return CCGRP_API::flatten( $body['data'] ?? array(), $body['included'] ?? array() );
        } );

        if ( is_wp_error( $group ) ) return CCGRP_Renderer::render_error( $group );
        if ( empty( $group ) )       return self::admin_hint( __( 'That group was not found in Planning Center.', 'comms-church-groups' ) );

        wp_enqueue_style( 'ccgrp' );
        return '<div class="ccgrp">' . CCGRP_Renderer::render_card( $group, array(
            'show_image'    => self::truthy( $atts['image'] ),
            'show_schedule' => self::truthy( $atts['schedule'] ),
            'show_location' => self::truthy( $atts['location'] ),
        ) ) . '</div>';
    }

    // ---- helpers ------------------------------------------------------------

    private static function truthy( $v ) {
        return in_array( strtolower( (string) $v ), array( 'yes', 'true', '1', 'on' ), true );
    }

    /** Admin-only nudge; visitors never see configuration chatter. */
    private static function not_configured_notice() {
        return self::admin_hint(
            sprintf(
                /* translators: %s: settings page URL */
                __( 'Planning Center is not connected yet. <a href="%s">Add your API credentials</a>.', 'comms-church-groups' ),
                esc_url( admin_url( 'options-general.php?page=ccgrp-settings' ) )
            ),
            false
        );
    }

    private static function admin_hint( $msg, $escape = true ) {
        if ( ! current_user_can( 'manage_options' ) ) return '';
        return '<div class="ccgrp ccgrp--notice"><p>' . ( $escape ? esc_html( $msg ) : wp_kses_post( $msg ) ) . '</p></div>';
    }
}
