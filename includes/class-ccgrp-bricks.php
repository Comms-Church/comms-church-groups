<?php
/**
 * CCGRP_Bricks
 *
 * Registers native Bricks Builder elements when Bricks is active. On non-Bricks
 * sites this silently does nothing — the shortcodes remain available everywhere.
 *
 * Mirrors the registration pattern proven in comms-church-pco:
 * \Bricks\Elements::register_element() is given the file path and includes the
 * element class itself.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class CCGRP_Bricks {

    public function __construct() {
        add_action( 'init', array( $this, 'register' ), 20 );
    }

    public function register() {
        if ( ! class_exists( '\Bricks\Elements' ) ) return;

        \Bricks\Elements::register_element( CCGRP_DIR . 'includes/bricks/element-groups-list.php' );
        \Bricks\Elements::register_element( CCGRP_DIR . 'includes/bricks/element-group-card.php' );
    }

    /** True when Bricks (theme or builder context) is present. */
    public static function is_active() {
        return defined( 'BRICKS_VERSION' ) || class_exists( '\Bricks\Elements' );
    }
}
