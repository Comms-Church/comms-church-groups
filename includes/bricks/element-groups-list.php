<?php
/**
 * Bricks element — Groups Finder.
 *
 * Thin wrapper over CCGRP_Shortcodes::groups(): the element maps Bricks controls onto
 * the same code path the shortcode uses, so there is exactly one implementation of
 * fetching, caching, filtering, and rendering to keep correct.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class CCGRP_Bricks_Groups_List extends \Bricks\Element {

    public $category     = 'Planning Center';
    public $name         = 'ccgrp-groups-list';
    public $icon         = 'ti-layout-grid2';
    public $css_selector = '.ccgrp';

    public function get_label() { return 'PCO Groups Finder'; }

    /** Bricks calls this when the element is on the page — proper asset loading. */
    public function enqueue_scripts() {
        CCGRP_Renderer::ensure_assets();
    }

    public function set_controls() {
        $this->controls['type'] = array(
            'tab'         => 'content',
            'label'       => esc_html__( 'Group type', 'comms-church-groups' ),
            'type'        => 'text',
            'placeholder' => "e.g. Men's",
            'description' => esc_html__( 'Show only one group type. Leave blank for all. Must match the type name in Planning Center.', 'comms-church-groups' ),
        );
        $this->controls['limit'] = array(
            'tab'         => 'content',
            'label'       => esc_html__( 'Max groups', 'comms-church-groups' ),
            'type'        => 'number',
            'min'         => 0,
            'max'         => 100,
            'default'     => 0,
            'description' => esc_html__( '0 shows every group.', 'comms-church-groups' ),
        );
        $this->controls['columns'] = array(
            'tab'     => 'content',
            'label'   => esc_html__( 'Columns', 'comms-church-groups' ),
            'type'    => 'number',
            'min'     => 1,
            'max'     => 4,
            'default' => 3,
        );
        $this->controls['show_filter'] = array(
            'tab'         => 'content',
            'label'       => esc_html__( 'Search + type filter', 'comms-church-groups' ),
            'type'        => 'checkbox',
            'default'     => true,
            'description' => esc_html__( 'Turn off for a teaser row on a home page.', 'comms-church-groups' ),
        );
        $this->controls['show_image']    = array( 'tab' => 'content', 'label' => esc_html__( 'Show image', 'comms-church-groups' ),    'type' => 'checkbox', 'default' => true );
        $this->controls['show_schedule'] = array( 'tab' => 'content', 'label' => esc_html__( 'Show schedule', 'comms-church-groups' ), 'type' => 'checkbox', 'default' => true );
        $this->controls['show_location'] = array( 'tab' => 'content', 'label' => esc_html__( 'Show location', 'comms-church-groups' ), 'type' => 'checkbox', 'default' => true );
        $this->controls['empty_text'] = array(
            'tab'         => 'content',
            'label'       => esc_html__( 'Empty message', 'comms-church-groups' ),
            'type'        => 'text',
            'placeholder' => esc_html__( 'No groups are open right now — check back soon.', 'comms-church-groups' ),
        );
        $this->controls['accent'] = array(
            'tab'         => 'style',
            'label'       => esc_html__( 'Accent color', 'comms-church-groups' ),
            'type'        => 'color',
            'description' => esc_html__( 'Leave unset to inherit the site design (--cc-color-primary) or the global setting.', 'comms-church-groups' ),
        );
    }

    public function render() {
        $s = $this->settings;

        /* Bricks passes unchecked checkboxes as absent, not false — so an isset() test
           is what distinguishes "off" from "never touched" (which should default on). */
        $bool = function ( $key, $default = true ) use ( $s ) {
            return array_key_exists( $key, $s ) ? ! empty( $s[ $key ] ) : $default;
        };

        $atts = array(
            'type'     => sanitize_text_field( $s['type'] ?? '' ),
            'columns'  => intval( $s['columns'] ?? 3 ),
            'limit'    => intval( $s['limit'] ?? 0 ),
            'filter'   => $bool( 'show_filter' )   ? 'yes' : 'no',
            'image'    => $bool( 'show_image' )    ? 'yes' : 'no',
            'schedule' => $bool( 'show_schedule' ) ? 'yes' : 'no',
            'location' => $bool( 'show_location' ) ? 'yes' : 'no',
        );
        if ( ! empty( $s['empty_text'] ) ) {
            $atts['empty_text'] = sanitize_text_field( $s['empty_text'] );
        }

        $accent = $this->color_value( $s['accent'] ?? null );
        $style  = $accent ? ' style="--ccgrp-accent:' . esc_attr( $accent ) . '"' : '';

        echo "<div {$this->render_attributes( '_root' )}{$style}>";

        if ( function_exists( 'bricks_is_builder' ) && bricks_is_builder() ) {
            /* In the builder, an unconfigured site would render nothing at all, which
               looks like a broken element. Say what's missing instead. */
            $api = new CCGRP_API();
            if ( ! $api->is_configured() ) {
                echo '<div class="ccgrp ccgrp--notice"><p>'
                    . esc_html__( 'Connect Planning Center in Settings → PCO Groups to see groups here.', 'comms-church-groups' )
                    . '</p></div></div>';
                return;
            }
        }

        echo CCGRP_Shortcodes::groups( $atts );
        echo '</div>';
    }

    /** Bricks color controls return an array with hex/rgb/raw keys. */
    private function color_value( $color ) {
        if ( is_array( $color ) ) {
            return sanitize_hex_color( $color['hex'] ?? '' ) ?: '';
        }
        return '';
    }
}
