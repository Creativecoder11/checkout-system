<?php
defined( 'ABSPATH' ) || exit;

/**
 * "Payment Successful" / "Payment Cancelled" screens for online payments.
 *
 * Shown on the WooCommerce order-received page:
 *   - online gateway + paid order (processing/completed) → payment-success.php
 *   - failed or cancelled order                          → payment-cancelled.php
 *   - anything else (COD, bank transfer, pending, on-hold) → the regular thank-you design
 */
class NVX_Payment_Result {

	public static function init() {
		add_filter( 'woocommerce_should_clear_cart_after_payment', [ __CLASS__, 'keep_cart' ] );
		add_action( 'woocommerce_cancelled_order', [ __CLASS__, 'after_cancel' ] );
	}

	public static function enabled() {
		return 'yes' === NVX_Settings::get( 'thankyou_enable' ) && 'yes' === NVX_Settings::get( 'result_enable' );
	}

	/** Gateway IDs that are NOT online payments. */
	public static function offline_methods() {
		$ids = array_filter( array_map( 'trim', explode( ',', (string) NVX_Settings::get( 'offline_methods' ) ) ) );
		return apply_filters( 'nvx_offline_payment_methods', $ids );
	}

	public static function is_online( $order ) {
		$method = $order->get_payment_method();
		return $method && ! in_array( $method, self::offline_methods(), true );
	}

	/** @return string 'success' | 'cancelled' | 'default' */
	public static function view( $order ) {
		$view = 'default';
		if ( self::enabled() ) {
			if ( $order->has_status( [ 'failed', 'cancelled' ] ) ) {
				$view = 'cancelled';
			} elseif ( self::is_online( $order ) && $order->is_paid() ) {
				$view = 'success';
			}
		}
		return apply_filters( 'nvx_payment_result_view', $view, $order );
	}

	/** Keep the cart when the customer lands on the order page of a failed/cancelled payment. */
	public static function keep_cart( $clear ) {
		global $wp;
		if ( ! $clear || ! self::enabled() || empty( $wp->query_vars['order-received'] ) ) {
			return $clear;
		}
		$order = wc_get_order( absint( $wp->query_vars['order-received'] ) );
		return ( $order && $order->has_status( [ 'failed', 'cancelled' ] ) ) ? false : $clear;
	}

	/**
	 * Gateways that cancel through WooCommerce's cancel-order link normally send the customer to
	 * the cart with a notice — send them to the "Payment Cancelled" screen instead.
	 */
	public static function after_cancel( $order_id ) {
		if ( ! self::enabled() ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order || ! self::is_online( $order ) ) {
			return;
		}
		wc_clear_notices();
		wp_safe_redirect( $order->get_checkout_order_received_url() );
		exit;
	}

	/* ---------- Helpers used by the templates ---------- */

	public static function date( $order ) {
		$date = $order->get_date_paid() ?: $order->get_date_created();
		return $date ? wc_format_datetime( $date, 'j M Y, h:i A' ) : '';
	}

	public static function address( $order ) {
		$parts = array_unique( array_filter( array_map( 'trim', [
			$order->get_billing_address_1(),
			$order->get_meta( '_nvx_area' ) ?: $order->get_billing_city(),
			$order->get_meta( '_nvx_district' ),
		] ) ) );
		return implode( ', ', $parts );
	}

	/** e.g. "21 – 23 Nov 2026", from the inside/outside delivery-day range in settings. */
	public static function eta( $order ) {
		$inside = NVX_Shipping::is_inside( (string) $order->get_meta( '_nvx_district' ), (string) $order->get_meta( '_nvx_area' ) );
		$range  = (string) NVX_Settings::get( $inside ? 'eta_inside' : 'eta_outside' );
		if ( ! preg_match( '/^\s*(\d+)\s*(?:[-–]\s*(\d+))?\s*$/u', $range, $m ) ) {
			return '';
		}
		$min   = (int) $m[1];
		$max   = isset( $m[2] ) && '' !== $m[2] ? max( $min, (int) $m[2] ) : $min;
		$start = $order->get_date_paid() ?: $order->get_date_created();
		$ts    = $start ? $start->getTimestamp() : time();
		$a     = $ts + $min * DAY_IN_SECONDS;
		$b     = $ts + $max * DAY_IN_SECONDS;

		if ( $min === $max ) {
			return wp_date( 'j M Y', $a );
		}
		if ( wp_date( 'M Y', $a ) === wp_date( 'M Y', $b ) ) {
			return wp_date( 'j', $a ) . ' – ' . wp_date( 'j M Y', $b );
		}
		if ( wp_date( 'Y', $a ) === wp_date( 'Y', $b ) ) {
			return wp_date( 'j M', $a ) . ' – ' . wp_date( 'j M Y', $b );
		}
		return wp_date( 'j M Y', $a ) . ' – ' . wp_date( 'j M Y', $b );
	}

	public static function retry_url( $order ) {
		return $order->needs_payment() ? $order->get_checkout_payment_url() : wc_get_checkout_url();
	}
}
