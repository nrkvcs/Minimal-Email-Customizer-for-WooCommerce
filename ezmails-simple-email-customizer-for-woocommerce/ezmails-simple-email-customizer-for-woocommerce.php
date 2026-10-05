<?php
/**
 * Plugin Name:       EZmails - Simple Email Customizer for WooCommerce
 * Description:       Give every WooCommerce email a fixed, branded header and footer, and customize the subject, heading and content of each email with dynamic order shortcodes.
 * Version:           1.4.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Norbert Kovacs
 * Author URI:        https://kovacs.cloud
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ezmails-simple-email-customizer-for-woocommerce
 * Requires Plugins:  woocommerce
 * WC requires at least: 6.0
 */

defined( 'ABSPATH' ) || exit;

define( 'EZMAILS_VERSION', '1.4.0' );
define( 'EZMAILS_FILE', __FILE__ );
define( 'EZMAILS_PATH', plugin_dir_path( __FILE__ ) );
define( 'EZMAILS_URL', plugin_dir_url( __FILE__ ) );

// Declare compatibility with High-Performance Order Storage.
add_action(
	'before_woocommerce_init',
	function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', EZMAILS_FILE, true );
		}
	}
);

add_action( 'plugins_loaded', 'ezmails_boot' );
add_action( 'plugins_loaded', 'ezmails_maybe_upgrade', 5 );

/**
 * Runs once after the plugin files are updated (upload, replace or auto-update). Put data migrations here.
 */
function ezmails_maybe_upgrade() {
	$old = get_option( 'ezmails_version', '' );
	if ( EZMAILS_VERSION === $old ) {
		return;
	}
	// Settings from pre-release builds were stored under an unprefixed option name; carry them over once.
	if ( false === get_option( 'ezmails_settings' ) ) {
		$legacy = get_option( 'wec_settings' );
		if ( is_array( $legacy ) ) {
			add_option( 'ezmails_settings', $legacy );
		}
	}

	/**
	 * Fires after the plugin was updated to a new version.
	 *
	 * @param string $old Previously stored version ('' on the first run).
	 * @param string $new Current version.
	 */
	do_action( 'ezmails_upgraded', $old, EZMAILS_VERSION );
	update_option( 'ezmails_version', EZMAILS_VERSION, false );
}

function ezmails_boot() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	require_once EZMAILS_PATH . 'includes/class-ezmails-settings.php';
	require_once EZMAILS_PATH . 'includes/class-ezmails-shortcodes.php';
	require_once EZMAILS_PATH . 'includes/class-ezmails-mailer.php';

	EZMAILS_Mailer::instance();

	if ( is_admin() ) {
		require_once EZMAILS_PATH . 'includes/class-ezmails-admin.php';
		EZMAILS_Admin::instance();
	}
}

add_filter(
	'plugin_action_links_' . plugin_basename( __FILE__ ),
	function ( $links ) {
		$url = admin_url( 'admin.php?page=ezmails' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'ezmails-simple-email-customizer-for-woocommerce' ) . '</a>' );
		return $links;
	}
);
