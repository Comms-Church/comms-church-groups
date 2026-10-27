<?php
/**
 * CCGRP_Admin — settings, connection test, and the API Inspector.
 *
 * The Inspector exists because PCO versions its API by date and attribute names move
 * between versions. Rather than trusting documentation (or memory) about what a Group
 * looks like, this dumps the real payload from the connected account so the field
 * mapping in CCGRP_API::flatten() can be verified against live data.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class CCGRP_Admin {

    public function __construct() {
        add_action( 'admin_menu', array( $this, 'menu' ) );
        add_action( 'admin_init', array( $this, 'settings' ) );
        add_action( 'admin_notices', array( $this, 'setup_notice' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
    }

    /** WordPress's own color picker for the accent setting. */
    public function enqueue( $hook ) {
        if ( 'settings_page_ccgrp-settings' !== $hook ) return;
        wp_enqueue_style( 'wp-color-picker' );
        wp_enqueue_script( 'wp-color-picker' );
        wp_add_inline_script( 'wp-color-picker', 'jQuery(function($){ $("#ccgrp_brand_color").wpColorPicker(); });' );
    }

    public function menu() {
        add_options_page(
            __( 'PCO Groups', 'comms-church-groups' ),
            __( 'PCO Groups', 'comms-church-groups' ),
            'manage_options',
            'ccgrp-settings',
            array( $this, 'page' )
        );
    }

    public function settings() {
        register_setting( 'ccgrp_settings', 'ccgrp_app_id' );
        register_setting( 'ccgrp_settings', 'ccgrp_cache_ttl' );
        /* A blank or malformed value clears the accent rather than breaking the CSS variable. */
        register_setting( 'ccgrp_settings', 'ccgrp_brand_color', array( 'sanitize_callback' => 'sanitize_hex_color' ) );
        /* The Secret field is rendered empty so the value never reaches the page source;
           submitting it blank keeps the saved one. */
        register_setting( 'ccgrp_settings', 'ccgrp_secret', array(
            'sanitize_callback' => function ( $v ) {
                $v = trim( (string) $v );
                return '' === $v ? get_option( 'ccgrp_secret', '' ) : sanitize_text_field( $v );
            },
        ) );
        /* Credentials changing means every cached payload is suspect. */
        add_action( 'update_option_ccgrp_app_id', array( 'CCGRP_Cache', 'flush_all' ) );
        add_action( 'update_option_ccgrp_secret', array( 'CCGRP_Cache', 'flush_all' ) );
    }

    public function setup_notice() {
        if ( ! current_user_can( 'manage_options' ) ) return;
        $screen = get_current_screen();
        if ( $screen && 'settings_page_ccgrp-settings' === $screen->id ) return;
        if ( get_option( 'ccgrp_app_id' ) && get_option( 'ccgrp_secret' ) ) return;

        echo '<div class="notice notice-info is-dismissible"><p><strong>Comms.Church Groups:</strong> '
            . 'Connect Planning Center to start showing groups. '
            . '<a href="' . esc_url( admin_url( 'options-general.php?page=ccgrp-settings' ) ) . '">Open settings</a>.'
            . '</p></div>';
    }

    public function page() {
        if ( ! current_user_can( 'manage_options' ) ) return;

        $api      = new CCGRP_API();
        $test     = null;
        $inspect  = null;

        /* Connection test — on request, and automatically right after a save so the
           result always describes the credentials that were just entered. */
        if ( isset( $_POST['ccgrp_test'] ) && check_admin_referer( 'ccgrp_test' ) ) {
            $test = $api->test_connection();
        } elseif ( isset( $_GET['settings-updated'] ) && get_option( 'ccgrp_app_id' ) && get_option( 'ccgrp_secret' ) ) {
            $test = $api->test_connection();
        }

        /* API Inspector — dumps one real Group so field mapping can be verified. */
        if ( isset( $_POST['ccgrp_inspect'] ) && check_admin_referer( 'ccgrp_inspect' ) ) {
            $inspect = $api->get( '/groups', array( 'per_page' => 1, 'include' => 'location,group_type' ) );
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Planning Center Groups', 'comms-church-groups' ); ?></h1>

            <form method="post" action="options.php">
                <?php settings_fields( 'ccgrp_settings' ); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th><label for="ccgrp_app_id"><?php esc_html_e( 'Application ID', 'comms-church-groups' ); ?></label></th>
                        <td>
                            <input name="ccgrp_app_id" id="ccgrp_app_id" type="text" class="regular-text"
                                   value="<?php echo esc_attr( get_option( 'ccgrp_app_id', '' ) ); ?>" autocomplete="off">
                            <p class="description">
                                <?php esc_html_e( 'Planning Center → Developer → Personal Access Tokens. The token needs access to Groups.', 'comms-church-groups' ); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="ccgrp_secret"><?php esc_html_e( 'Secret', 'comms-church-groups' ); ?></label></th>
                        <td>
                            <?php $has_secret = '' !== (string) get_option( 'ccgrp_secret', '' ); ?>
                            <input name="ccgrp_secret" id="ccgrp_secret" type="password" class="regular-text" value=""
                                   placeholder="<?php echo $has_secret ? esc_attr__( 'Saved — leave blank to keep', 'comms-church-groups' ) : ''; ?>" autocomplete="new-password">
                            <p class="description"><?php esc_html_e( 'Stored server-side and never shown again, here or to visitors.', 'comms-church-groups' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="ccgrp_cache_ttl"><?php esc_html_e( 'Cache lifetime (seconds)', 'comms-church-groups' ); ?></label></th>
                        <td>
                            <input name="ccgrp_cache_ttl" id="ccgrp_cache_ttl" type="number" min="60" step="30" class="small-text"
                                   value="<?php echo esc_attr( get_option( 'ccgrp_cache_ttl', 300 ) ); ?>">
                            <p class="description"><?php esc_html_e( 'Minimum 60. Groups change rarely — 300–900 is sensible.', 'comms-church-groups' ); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="ccgrp_brand_color"><?php esc_html_e( 'Accent color', 'comms-church-groups' ); ?></label></th>
                        <td>
                            <input name="ccgrp_brand_color" id="ccgrp_brand_color" type="text" class="regular-text" data-default-color="" placeholder="#556136"
                                   value="<?php echo esc_attr( get_option( 'ccgrp_brand_color', '' ) ); ?>">
                            <p class="description"><?php esc_html_e( 'Optional. Leave blank to inherit the theme via CSS.', 'comms-church-groups' ); ?></p>
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>

            <hr>

            <h2><?php esc_html_e( 'Connection', 'comms-church-groups' ); ?></h2>
            <p class="description"><?php esc_html_e( 'Saving new credentials tests them automatically. This button re-tests what is saved, not what is typed above.', 'comms-church-groups' ); ?></p>
            <form method="post">
                <?php wp_nonce_field( 'ccgrp_test' ); ?>
                <button class="button" name="ccgrp_test" value="1"><?php esc_html_e( 'Test saved connection', 'comms-church-groups' ); ?></button>
            </form>
            <?php if ( null !== $test ) : ?>
                <?php if ( is_wp_error( $test ) ) : ?>
                    <div class="notice notice-error inline"><p><?php echo esc_html( $test->get_error_message() ); ?></p></div>
                <?php else : ?>
                    <div class="notice notice-success inline"><p>
                        <?php
                        echo esc_html( null === $test['total']
                            ? __( 'Connected to Planning Center Groups.', 'comms-church-groups' )
                            : sprintf( __( 'Connected — %d groups visible to this token.', 'comms-church-groups' ), (int) $test['total'] ) );
                        ?>
                    </p></div>
                <?php endif; ?>
            <?php endif; ?>

            <hr>

            <h2><?php esc_html_e( 'API Inspector', 'comms-church-groups' ); ?></h2>
            <p class="description">
                <?php esc_html_e( 'Fetches one real group and shows the exact fields Planning Center returns for this account. Use it to confirm the plugin is reading the right attributes — PCO changes field names between API versions.', 'comms-church-groups' ); ?>
            </p>
            <form method="post">
                <?php wp_nonce_field( 'ccgrp_inspect' ); ?>
                <button class="button" name="ccgrp_inspect" value="1"><?php esc_html_e( 'Inspect a group', 'comms-church-groups' ); ?></button>
            </form>
            <?php if ( null !== $inspect ) : ?>
                <?php if ( is_wp_error( $inspect ) ) : ?>
                    <div class="notice notice-error inline"><p><?php echo esc_html( $inspect->get_error_message() ); ?></p></div>
                <?php else :
                    $first  = $inspect['data'][0] ?? null;
                    $mapped = $first ? CCGRP_API::flatten( $first, $inspect['included'] ?? array() ) : null;
                    ?>
                    <?php if ( ! $first ) : ?>
                        <div class="notice notice-warning inline"><p><?php esc_html_e( 'Connected, but this token can see no groups.', 'comms-church-groups' ); ?></p></div>
                    <?php else : ?>
                        <h3><?php esc_html_e( 'What the plugin mapped', 'comms-church-groups' ); ?></h3>
                        <table class="widefat striped" style="max-width:820px">
                            <tbody>
                            <?php foreach ( $mapped as $k => $v ) :
                                if ( 'raw' === $k ) continue;
                                $shown = is_scalar( $v ) || null === $v ? (string) $v : wp_json_encode( $v );
                                $empty = ( '' === $shown || null === $v );
                                ?>
                                <tr>
                                    <td style="width:170px"><code><?php echo esc_html( $k ); ?></code></td>
                                    <td<?php echo $empty ? ' style="color:#b32d2e"' : ''; ?>>
                                        <?php echo $empty ? esc_html__( '— empty (check the raw payload below)', 'comms-church-groups' ) : esc_html( mb_substr( $shown, 0, 220 ) ); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>

                        <h3><?php esc_html_e( 'Raw attributes from Planning Center', 'comms-church-groups' ); ?></h3>
                        <textarea readonly rows="16" style="width:100%;max-width:820px;font-family:monospace;font-size:12px"><?php
                            echo esc_textarea( wp_json_encode(
                                array(
                                    'attributes'    => $first['attributes'] ?? array(),
                                    'relationships' => array_keys( $first['relationships'] ?? array() ),
                                    'included'      => array_map( function ( $i ) { return $i['type'] ?? '?'; }, $inspect['included'] ?? array() ),
                                ),
                                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
                            ) );
                        ?></textarea>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>

            <hr>

            <h2><?php esc_html_e( 'Usage', 'comms-church-groups' ); ?></h2>
            <p><?php esc_html_e( 'Put these anywhere shortcodes run — a page, a Bricks Shortcode element, or a block.', 'comms-church-groups' ); ?></p>
            <table class="widefat striped" style="max-width:820px">
                <tbody>
                    <tr><td><code>[cc_groups]</code></td><td><?php esc_html_e( 'All groups, with search + type filter.', 'comms-church-groups' ); ?></td></tr>
                    <tr><td><code>[cc_groups columns="2" filter="no"]</code></td><td><?php esc_html_e( 'Two columns, no filter bar.', 'comms-church-groups' ); ?></td></tr>
                    <tr><td><code>[cc_groups type="Men&#039;s"]</code></td><td><?php esc_html_e( 'Only groups of one type.', 'comms-church-groups' ); ?></td></tr>
                    <tr><td><code>[cc_groups limit="3" filter="no"]</code></td><td><?php esc_html_e( 'A teaser row for a home page.', 'comms-church-groups' ); ?></td></tr>
                    <tr><td><code>[cc_group id="123"]</code></td><td><?php esc_html_e( 'One specific group.', 'comms-church-groups' ); ?></td></tr>
                </tbody>
            </table>
            <p style="margin-top:32px;padding-top:16px;border-top:1px solid #dcdcde;color:#646970;max-width:820px;">
                <?php
                printf(
                    /* translators: %s: "pay what's fair" link */
                    esc_html__( 'This plugin is free so churches can spend less on their websites. Using it in paid client work? Please %s. It\'s the honor system: no license keys, no checks.', 'comms-church-groups' ),
                    '<a href="https://buy.stripe.com/00w5kD8BF21R6SigP62B201" target="_blank" rel="noopener">' . esc_html__( 'pay what\'s fair', 'comms-church-groups' ) . '</a>'
                );
                ?>
            </p>
        </div>
        <?php
    }
}
