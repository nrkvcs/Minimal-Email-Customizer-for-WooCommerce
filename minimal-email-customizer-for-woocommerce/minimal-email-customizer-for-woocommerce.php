<?php
/**
 * Plugin Name:       Minimal Email Customizer for WooCommerce
 * Description:       Give every WooCommerce email a fixed, branded header and footer, and customize the subject, heading and content of each email with dynamic order shortcodes.
 * Version:           1.3.1
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Norbert Kovacs
 * Author URI:        https://kovacs.cloud
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       minimal-email-customizer-for-woocommerce
 * Requires Plugins:  woocommerce
 * WC requires at least: 6.0
 */

defined( 'ABSPATH' ) || exit;

define( 'MECW_VERSION', '1.3.1' );
define( 'MECW_FILE', __FILE__ );
define( 'MECW_PATH', plugin_dir_path( __FILE__ ) );
define( 'MECW_URL', plugin_dir_url( __FILE__ ) );

// Declare compatibility with High-Performance Order Storage.
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', MECW_FILE, true );
		}
	}
);

add_action( 'plugins_loaded', 'mecw_retire_legacy_copy', 1 );
add_action( 'plugins_loaded', 'mecw_boot' );
add_action( 'admin_init', 'mecw_maybe_upgrade' );
add_action( 'admin_notices', 'mecw_legacy_notice' );

/**
 * Versions before 1.2.0 lived in the folder "wc-email-customizer". If that copy is still active, switch it off
 * so the two copies never run together. Settings are stored in the same option, so nothing is lost.
 */
function mecw_retire_legacy_copy() {
	$legacy = 'wc-email-customizer/wc-email-customizer.php';

	remove_action( 'plugins_loaded', 'wec_boot' );

	if ( ! function_exists( 'is_plugin_active' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	if ( is_plugin_active( $legacy ) ) {
		deactivate_plugins( $legacy, true );
		set_transient( 'mecw_legacy_notice', 1, DAY_IN_SECONDS );
	}
}

function mecw_legacy_notice() {
	if ( ! get_transient( 'mecw_legacy_notice' ) || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	delete_transient( 'mecw_legacy_notice' );
	echo '<div class="notice notice-info is-dismissible"><p>' . esc_html__( 'An older copy of this plugin (folder "wc-email-customizer") was found and deactivated automatically. Your settings were kept. You can safely delete the older copy from the Plugins page.', 'minimal-email-customizer-for-woocommerce' ) . '</p></div>';
}

/**
 * Runs once after the plugin files are updated (upload, replace or auto-update). Put data migrations here.
 */
function mecw_maybe_upgrade() {
	$old = get_option( 'mecw_version', '' );
	if ( MECW_VERSION === $old ) {
		return;
	}
	/**
	 * Fires after the plugin was updated to a new version.
	 *
	 * @param string $old Previously stored version ('' on the first run).
	 * @param string $new Current version.
	 */
	do_action( 'mecw_upgraded', $old, MECW_VERSION );
	update_option( 'mecw_version', MECW_VERSION, false );
}

function mecw_boot() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action(
			'admin_notices',
			function () {
				echo '<div class="notice notice-error"><p>' . esc_html__( 'Minimal Email Customizer for WooCommerce requires WooCommerce to be installed and active.', 'minimal-email-customizer-for-woocommerce' ) . '</p></div>';
			}
		);
		return;
	}

	require_once MECW_PATH . 'includes/class-wec-settings.php';
	require_once MECW_PATH . 'includes/class-wec-shortcodes.php';
	require_once MECW_PATH . 'includes/class-wec-mailer.php';

	MECW_Mailer::instance();

	if ( is_admin() ) {
		require_once MECW_PATH . 'includes/class-wec-admin.php';
		MECW_Admin::instance();
	}
}

add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	function ( $links ) {
		$url = admin_url( 'admin.php?page=wc-email-customizer' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'minimal-email-customizer-for-woocommerce' ) . '</a>' );
		return $links;
	}
);
