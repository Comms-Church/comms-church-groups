<?php
/**
 * Bricks element — single Group card.
 *
 * For pinning one specific group somewhere (a ministry page, a home-page feature)
 * rather than listing everything. Delegates to CCGRP_Shortcodes::group() so fetching,
 * caching, and rendering stay in one place.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class CCGRP_Bricks_Group_Card extends \Bricks\Element {

    public $category     = 'Planning Center';
    public $name         = 'ccgrp-group-card';
    public $icon         = 'ti-id-badge';
    public $css_selector = '.ccgrp-card';

    public function get_label() { return 'PCO Group Card'; }

    public function enqueue_scripts() {
        /* A single card has no filter bar, so it needs no JS. */
        CCGRP_Renderer::ensure_assets( false );
    }

    public function set_controls() {
        $this->controls['group_id'] = array(
            'tab'         => 'content',
            'label'       => esc_html__( 'Group ID', 'comms-church-groups' ),
            'type'        => 'text',
            'placeholder' => '123456',
            'description' => esc_html__( 'The Planning Center group ID. Find it in the group URL in Planning Center, or via Settings → PCO Groups → API Inspector.', 'comms-church-groups' ),
        );
        $this->controls['show_image']    = array( 'tab' => 'content', 'label' => esc_html__( 'Show image', 'comms-church-groups' ),    'type' => 'checkbox', 'default' => true );
        $this->controls['show_schedule'] = array( 'tab' => 'content', 'label' => esc_html__( 'Show schedule', 'comms-church-groups' ), 'type' => 'checkbox', 'default' => true );
        $this->controls['show_location'] = array( 'tab' => 'content', 'label' => esc_html__( 'Show location', 'comms-church-groups' ), 'type' => 'checkbox', 'default' => true );
        $this->controls['accent'] = array(
            'tab'         => 'style',
            'label'       => esc_html__( 'Accent color', 'comms-church-groups' ),
            'type'        => 'color',
            'description' => esc_html__( 'Leave unset to inherit the site design.', 'comms-church-groups' ),
        );
    }

    public function render() {
        $s  = $this->settings;
        $id = trim( (string) ( $s['group_id'] ?? '' ) );

        $bool = function ( $key, $default = true ) use ( $s ) {
            return array_key_exists( $key, $s ) ? ! empty( $s[ $key ] ) : $default;
        };

        $accent = $this->color_value( $s['accent'] ?? null );
        $style  = $accent ? ' style="--ccgrp-accent:' . esc_attr( $accent ) . '"' : '';

        echo "<div {$this->render_attributes( '_root' )}{$style}>";

        /* Without an ID there is nothing to fetch — in the builder, say so plainly
           rather than rendering an empty box the designer has to debug. */
        if ( '' === $id ) {
            if ( function_exists( 'bricks_is_builder' ) && bricks_is_builder() ) {
                echo '<div class="ccgrp ccgrp--notice"><p>'
                    . esc_html__( 'Add a Group ID to show a group here.', 'comms-church-groups' )
                    . '</p></div>';
            }
            echo '</div>';
            return;
        }

        echo CCGRP_Shortcodes::group( array(
            'id'       => $id,
            'image'    => $bool( 'show_image' )    ? 'yes' : 'no',
            'schedule' => $bool( 'show_schedule' ) ? 'yes' : 'no',
            'location' => $bool( 'show_location' ) ? 'yes' : 'no',
        ) );

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
