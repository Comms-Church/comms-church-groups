<?php
/**
 * Plugin Name:       Comms.Church — Planning Center Groups
 * Plugin URI:        https://github.com/Comms-Church/comms-church-groups
 * Description:       A public small-groups finder powered by Planning Center Groups. Browse, filter, and sign up for groups via shortcodes, blocks, and Bricks elements. API credentials stored securely server-side.
 * Version:           0.2.2
 * Author:            Comms.Church
 * Author URI:        https://comms.church
 * License:           GPL-2.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       comms-church-groups
 * Requires at least: 5.8
 * Requires PHP:      7.4
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/*
 * Graceful guard for hosts running PHP older than 7.4.
 * Deactivates cleanly with an admin notice instead of a white-screen fatal.
 */
if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
    add_action( 'admin_notices', function () {
        echo '<div class="notice notice-error"><p><strong>Comms.Church Groups:</strong> This plugin requires PHP 7.4 or higher. Your server is running PHP ' . esc_html( PHP_VERSION ) . '. Please ask your host to upgrade.</p></div>';
    } );
    add_action( 'admin_init', function () {
        deactivate_plugins( plugin_basename( __FILE__ ) );
    } );
    return;
}

define( 'CCGRP_VERSION', '0.2.2' );
define( 'CCGRP_FILE', __FILE__ );
define( 'CCGRP_DIR', plugin_dir_path( __FILE__ ) );
define( 'CCGRP_URL', plugin_dir_url( __FILE__ ) );

require_once CCGRP_DIR . 'includes/polyfills.php';
require_once CCGRP_DIR . 'includes/class-ccgrp-cache.php';
require_once CCGRP_DIR . 'includes/class-ccgrp-api.php';
require_once CCGRP_DIR . 'includes/class-ccgrp-renderer.php';
require_once CCGRP_DIR . 'includes/class-ccgrp-shortcodes.php';
require_once CCGRP_DIR . 'includes/class-ccgrp-bricks.php';
require_once CCGRP_DIR . 'includes/class-ccgrp-blocks.php';

if ( is_admin() ) {
    require_once CCGRP_DIR . 'includes/class-ccgrp-admin.php';
    new CCGRP_Admin();

    /* Updates come from GitHub Releases and surface in the normal Plugins /
       Dashboard → Updates screens. No third-party service involved. */
    require_once CCGRP_DIR . 'includes/class-ccgrp-updater.php';
    new CCGRP_Updater( CCGRP_FILE, CCGRP_VERSION );
}

add_action( 'init', array( 'CCGRP_Shortcodes', 'register' ) );

/* Bricks elements register themselves only when Bricks is present. */
new CCGRP_Bricks();

/* Gutenberg blocks (server-side rendered — no build step). */
new CCGRP_Blocks();

/* Front-end assets are registered here but only enqueued when a shortcode/block
   actually renders, so pages without groups ship no extra CSS/JS. */
add_action( 'wp_enqueue_scripts', function () {
    wp_register_style( 'ccgrp', CCGRP_URL . 'assets/groups.css', array(), CCGRP_VERSION );
    wp_register_script( 'ccgrp', CCGRP_URL . 'assets/groups.js', array(), CCGRP_VERSION, true );
} );
