<?php
defined( 'ABSPATH' ) || exit;

class NVX_Assets {

	public static function init() {
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'enqueue' ], 20 );
	}

	public static function enqueue() {
		if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
			return;
		}
		if ( is_wc_endpoint_url( 'order-received' ) ) {
			if ( 'yes' === NVX_Settings::get( 'thankyou_enable' ) ) {
				wp_enqueue_style( 'nvx-thankyou', NVX_CO_URL . 'assets/css/thankyou.css', [], NVX_CO_VERSION );
			}
			return;
		}
		if ( 'yes' === NVX_Settings::get( 'load_css' ) ) {
			wp_enqueue_style( 'nvx-checkout', NVX_CO_URL . 'assets/css/checkout.css', [], NVX_CO_VERSION );
			$prefix = NVX_Settings::get( 'phone_prefix' ) ?: '+880';
			$flag   = 'BD' === NVX_Settings::get( 'country' ) ? '🇧🇩 ▾  ' : '';
			wp_add_inline_style( 'nvx-checkout', 'body.nvx-checkout-page .form-row.nvx-phone .woocommerce-input-wrapper::before{content:"' . esc_attr( $flag . $prefix ) . '"}' );
		}
		wp_enqueue_script( 'nvx-checkout', NVX_CO_URL . 'assets/js/checkout.js', [ 'jquery', 'wc-checkout' ], NVX_CO_VERSION, true );
		wp_localize_script( 'nvx-checkout', 'nvxCheckout', [
			'locations'       => NVX_Locations::map(),
			'areaPlaceholder' => 'Select Area',
			'country'         => NVX_Fields::default_country(),
			'i18n'            => [
				'phone' => NVX_Fields::phone_error(),
				'email' => 'Please enter a valid email address (e.g. name@example.com).',
			],
		] );
	}
}
