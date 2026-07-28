<?php
/**
 * CCGRP_Blocks — two server-side-rendered Gutenberg blocks.
 *
 * No build step: rendering is PHP (reusing the shortcode path), and the editor UI is
 * plain wp.element in assets/blocks.js. Server-side rendering also means the editor
 * preview is the real output, not an approximation that drifts.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class CCGRP_Blocks {

    public function __construct() {
        add_action( 'init', array( $this, 'register_blocks' ) );
        add_action( 'enqueue_block_editor_assets', array( $this, 'editor_assets' ) );
        add_filter( 'block_categories_all', array( $this, 'category' ), 10, 1 );
    }

    /** Own category so the church blocks sit together in the inserter. */
    public function category( $categories ) {
        foreach ( $categories as $c ) {
            if ( 'comms-church' === ( $c['slug'] ?? '' ) ) return $categories;
        }
        array_unshift( $categories, array(
            'slug'  => 'comms-church',
            'title' => __( 'Comms.Church', 'comms-church-groups' ),
        ) );
        return $categories;
    }

    public function register_blocks() {
        if ( ! function_exists( 'register_block_type' ) ) return;

        register_block_type( 'comms-church-groups/groups-list', array(
            'title'           => __( 'PCO Groups Finder', 'comms-church-groups' ),
            'description'     => __( 'Browsable list of Planning Center groups with search and type filter.', 'comms-church-groups' ),
            'category'        => 'comms-church',
            'icon'            => 'groups',
            'render_callback' => array( $this, 'render_groups_list' ),
            'attributes'      => array(
                'type'         => array( 'type' => 'string',  'default' => '' ),
                'columns'      => array( 'type' => 'number',  'default' => 3 ),
                'limit'        => array( 'type' => 'number',  'default' => 0 ),
                'showFilter'   => array( 'type' => 'boolean', 'default' => true ),
                'showImage'    => array( 'type' => 'boolean', 'default' => true ),
                'showSchedule' => array( 'type' => 'boolean', 'default' => true ),
                'showLocation' => array( 'type' => 'boolean', 'default' => true ),
                'showDesc'     => array( 'type' => 'boolean', 'default' => true ),
                'emptyText'    => array( 'type' => 'string',  'default' => '' ),
            ),
            'supports' => array( 'html' => false, 'align' => array( 'wide', 'full' ) ),
        ) );

        register_block_type( 'comms-church-groups/group-card', array(
            'title'           => __( 'PCO Group Card', 'comms-church-groups' ),
            'description'     => __( 'A single Planning Center group.', 'comms-church-groups' ),
            'category'        => 'comms-church',
            'icon'            => 'id-alt',
            'render_callback' => array( $this, 'render_group_card' ),
            'attributes'      => array(
                'groupId'      => array( 'type' => 'string',  'default' => '' ),
                'showImage'    => array( 'type' => 'boolean', 'default' => true ),
                'showSchedule' => array( 'type' => 'boolean', 'default' => true ),
                'showLocation' => array( 'type' => 'boolean', 'default' => true ),
                'showDesc'     => array( 'type' => 'boolean', 'default' => true ),
            ),
            'supports' => array( 'html' => false ),
        ) );
    }

    // ---- render callbacks (delegate to the shortcode path) ------------------

    public function render_groups_list( $attr ) {
        $atts = array(
            'type'     => sanitize_text_field( $attr['type'] ?? '' ),
            'columns'  => intval( $attr['columns'] ?? 3 ),
            'limit'    => intval( $attr['limit'] ?? 0 ),
            'filter'   => ! empty( $attr['showFilter'] )   ? 'yes' : 'no',
            'image'    => ! empty( $attr['showImage'] )    ? 'yes' : 'no',
            'schedule' => ! empty( $attr['showSchedule'] ) ? 'yes' : 'no',
            'location' => ! empty( $attr['showLocation'] ) ? 'yes' : 'no',
            'desc'     => ! empty( $attr['showDesc'] )     ? 'yes' : 'no',
        );
        if ( ! empty( $attr['emptyText'] ) ) {
            $atts['empty_text'] = sanitize_text_field( $attr['emptyText'] );
        }

        $out = CCGRP_Shortcodes::groups( $atts );

        /* In the editor, an unconfigured site renders nothing — which looks broken.
           Explain instead. (Front end deliberately stays silent for visitors.) */
        if ( '' === trim( $out ) && $this->is_editor_request() ) {
            return $this->editor_placeholder();
        }
        return $out;
    }

    public function render_group_card( $attr ) {
        $id = trim( (string) ( $attr['groupId'] ?? '' ) );
        if ( '' === $id ) {
            return $this->is_editor_request()
                ? $this->editor_placeholder( __( 'Choose a group in the block settings.', 'comms-church-groups' ) )
                : '';
        }

        $out = CCGRP_Shortcodes::group( array(
            'id'       => $id,
            'image'    => ! empty( $attr['showImage'] )    ? 'yes' : 'no',
            'schedule' => ! empty( $attr['showSchedule'] ) ? 'yes' : 'no',
            'location' => ! empty( $attr['showLocation'] ) ? 'yes' : 'no',
            'desc'     => ! empty( $attr['showDesc'] )     ? 'yes' : 'no',
        ) );

        if ( '' === trim( $out ) && $this->is_editor_request() ) {
            return $this->editor_placeholder();
        }
        return $out;
    }

    // ---- helpers ------------------------------------------------------------

    /** ServerSideRender previews arrive over the REST API, not a normal page load. */
    private function is_editor_request() {
        return defined( 'REST_REQUEST' ) && REST_REQUEST;
    }

    private function editor_placeholder( $msg = '' ) {
        if ( '' === $msg ) {
            $msg = __( 'Connect Planning Center in Settings → PCO Groups to see groups here.', 'comms-church-groups' );
        }
        return '<div class="ccgrp ccgrp--notice"><p>' . esc_html( $msg ) . '</p></div>';
    }

    /**
     * Editor assets. Group names are passed through so the Group Card block can offer a
     * picker instead of asking a content editor to hunt for a numeric ID in PCO.
     */
    public function editor_assets() {
        $api    = new CCGRP_API();
        $groups = array();

        if ( $api->is_configured() ) {
            $list = CCGRP_Cache::remember( 'editor_groups', function () use ( $api ) {
                $body = $api->get_groups();
                if ( is_wp_error( $body ) ) return $body;
                return CCGRP_API::flatten_all( $body );
            }, 300 );

            if ( ! is_wp_error( $list ) ) {
                foreach ( $list as $g ) {
                    $groups[] = array(
                        'id'   => (string) $g['id'],
                        'name' => $g['name'] !== '' ? $g['name'] : ( 'Group ' . $g['id'] ),
                        'type' => $g['group_type']['name'] ?? '',
                    );
                }
            }
        }

        wp_enqueue_script(
            'ccgrp-blocks',
            CCGRP_URL . 'assets/blocks.js',
            array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-i18n', 'wp-server-side-render' ),
            CCGRP_VERSION,
            true
        );

        $types = array();
        foreach ( $groups as $g ) {
            if ( $g['type'] ) $types[ $g['type'] ] = true;
        }
        ksort( $types );

        wp_localize_script( 'ccgrp-blocks', 'CCGRPBlocks', array(
            'groups'      => $groups,
            'types'       => array_keys( $types ),
            'configured'  => $api->is_configured(),
            'settingsUrl' => admin_url( 'options-general.php?page=ccgrp-settings' ),
        ) );

        /* Load the front-end stylesheet in the editor too, so the preview matches. */
        wp_enqueue_style( 'ccgrp', CCGRP_URL . 'assets/groups.css', array(), CCGRP_VERSION );
    }
}
