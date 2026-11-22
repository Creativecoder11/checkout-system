<?php
defined( 'ABSPATH' ) || exit;

/**
 * Order summary template + payment section tweaks.
 */
class NVX_Summary {

	public static function init() {
		add_filter( 'woocommerce_locate_template', [ __CLASS__, 'locate_template' ], 20, 3 );
		add_action( 'woocommerce_review_order_before_payment', [ __CLASS__, 'payment_title' ] );
		add_filter( 'woocommerce_order_button_text', [ __CLASS__, 'button_text' ] );
		add_filter( 'woocommerce_gateway_icon', [ __CLASS__, 'gateway_icon' ], 20, 2 );
		add_filter( 'gettext', [ __CLASS__, 'rename_steps' ], 10, 3 );
		add_action( 'init', [ __CLASS__, 'thankyou_hooks' ], 20 );
	}

	/** Our thank-you template renders the order itself, so drop WooCommerce's default tables. */
	public static function thankyou_hooks() {
		if ( 'yes' === NVX_Settings::get( 'thankyou_enable' ) ) {
			remove_action( 'woocommerce_thankyou', 'woocommerce_order_details_table', 10 );
		}
	}

	/**
	 * Use the plugin's review-order.php unless the theme overrides it.
	 */
	public static function locate_template( $template, $template_name, $template_path ) {
		if ( 'checkout/review-order.php' === $template_name ) {
			$theme_file = locate_template( [ trailingslashit( $template_path ) . $template_name, $template_name ] );
			return $theme_file ? $theme_file : NVX_CO_PATH . 'templates/checkout/review-order.php'; // Theme override wins.
		}
		if ( 'checkout/thankyou.php' === $template_name && 'yes' === NVX_Settings::get( 'thankyou_enable' ) ) {
			$child = trailingslashit( get_stylesheet_directory() ) . trailingslashit( $template_path ) . $template_name;
			return file_exists( $child ) ? $child : NVX_CO_PATH . 'templates/checkout/thankyou.php'; // Only a child-theme override wins.
		}
		return $template;
	}

	public static function payment_title() {
		$t = NVX_Settings::get( 'payment_title' );
		if ( $t ) {
			echo '<h3 class="nvx-payment__title">' . esc_html( $t ) . '</h3>';
		}
	}

	public static function button_text( $text ) {
		return NVX_Settings::get( 'button_text' ) ?: $text;
	}

	public static function gateway_icon( $icon, $id ) {
		$map = [];
		if ( $u = NVX_Settings::get( 'icon_cod' ) ) {
			$map['cod'] = $u;
		}
		if ( $u = NVX_Settings::get( 'icon_card' ) ) {
			foreach ( array_map( 'trim', explode( ',', NVX_Settings::get( 'icon_card_ids' ) ) ) as $gid ) {
				$map[ $gid ] = $u;
			}
		}
		if ( $u = NVX_Settings::get( 'icon_bkash' ) ) {
			foreach ( array_map( 'trim', explode( ',', NVX_Settings::get( 'icon_bkash_ids' ) ) ) as $gid ) {
				$map[ $gid ] = $u;
			}
		}
		return isset( $map[ $id ] ) ? '<img class="nvx-gateway-icon" src="' . esc_url( $map[ $id ] ) . '" alt="">' : $icon;
	}

	/**
	 * WoodMart checkout steps → Cart › Delivery › Payment.
	 * Adjust source strings if your WoodMart version uses different labels.
	 */
	public static function rename_steps( $translated, $text, $domain ) {
		if ( 'woodmart' !== $domain ) {
			return $translated;
		}
		$map = apply_filters( 'nvx_checkout_step_labels', [
			'Shopping Cart'  => 'Cart',
			'Checkout'       => 'Delivery',
			'Order Complete' => 'Payment',
		] );
		return $map[ $text ] ?? $translated;
	}
}
