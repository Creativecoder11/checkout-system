<?php
/**
 * Plugin Name:       Neuvix Checkout
 * Plugin URI:        https://neuvixstudio.com
 * Description:       Custom WooCommerce checkout — Bangladesh delivery address (District → Area from JSON), automatic inside/outside Dhaka delivery charge, packaging options with fees, and a redesigned order summary. Built for WoodMart + Elementor.
 * Version:           1.3.1
 * Author:            Neuvix Studio
 * Text Domain:       neuvix-checkout
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * WC requires at least: 8.0
 * WC tested up to:   9.9
 */

defined( 'ABSPATH' ) || exit;

define( 'NVX_CO_VERSION', '1.3.1' );
define( 'NVX_CO_FILE', __FILE__ );
define( 'NVX_CO_PATH', plugin_dir_path( __FILE__ ) );
define( 'NVX_CO_URL', plugin_dir_url( __FILE__ ) );

// HPOS compatibility declaration.
add_action( 'before_woocommerce_init', function () {
	if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
	}
} );

add_action( 'plugins_loaded', function () {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p><strong>Neuvix Checkout</strong> requires WooCommerce to be installed and active.</p></div>';
		} );
		return;
	}

	require_once NVX_CO_PATH . 'includes/class-nvx-settings.php';
	require_once NVX_CO_PATH . 'includes/class-nvx-locations.php';
	require_once NVX_CO_PATH . 'includes/class-nvx-shipping.php';
	require_once NVX_CO_PATH . 'includes/class-nvx-fields.php';
	require_once NVX_CO_PATH . 'includes/class-nvx-packaging.php';
	require_once NVX_CO_PATH . 'includes/class-nvx-summary.php';
	require_once NVX_CO_PATH . 'includes/class-nvx-assets.php';
	require_once NVX_CO_PATH . 'includes/class-nvx-layout.php';
	require_once NVX_CO_PATH . 'includes/class-nvx-terms.php';
	require_once NVX_CO_PATH . 'includes/class-nvx-performance.php';

	NVX_Settings::init();
	NVX_Shipping::init();
	NVX_Fields::init();
	NVX_Packaging::init();
	NVX_Summary::init();
	NVX_Assets::init();
	NVX_Layout::init();
	NVX_Terms::init();
	NVX_Performance::init();
} );

// Settings link on the Plugins screen.
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( $links ) {
	array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=nvx_checkout' ) ) . '">Settings</a>' );
	return $links;
} );
