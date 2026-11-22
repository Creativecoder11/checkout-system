<?php
defined( 'ABSPATH' ) || exit;

/**
 * Plugin's own order emails — sent independently of WooCommerce's email settings.
 *
 * - Admin email  → recipients from settings, on every new order.
 * - Customer email → only when the customer entered a valid email.
 * Sent once per order, when it first reaches Processing / On hold / Completed
 * (so failed or cancelled online payments never send).
 */
class NVX_Emails {

	const SENT_META = '_nvx_emails_sent';

	/** WooCommerce emails replaced by ours (switched off when "Disable WooCommerce order emails" is on). */
	const WC_EMAILS = [ 'new_order', 'customer_processing_order', 'customer_on_hold_order' ];

	public static function init() {
		add_action( 'woocommerce_order_status_changed', [ __CLASS__, 'on_status' ], 20, 3 );
		add_filter( 'woocommerce_order_actions', [ __CLASS__, 'order_action' ] );
		add_action( 'woocommerce_order_action_nvx_resend_emails', [ __CLASS__, 'resend' ] );

		if ( 'yes' === NVX_Settings::get( 'wc_emails_off' ) ) {
			foreach ( self::WC_EMAILS as $id ) {
				add_filter( "woocommerce_email_enabled_{$id}", '__return_false', 99 );
			}
		}
	}

	public static function enabled() {
		return 'yes' === NVX_Settings::get( 'email_enable' );
	}

	public static function on_status( $order_id, $from, $to ) {
		if ( ! self::enabled() || ! in_array( $to, [ 'processing', 'on-hold', 'completed' ], true ) ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order || $order->get_meta( self::SENT_META ) ) {
			return;
		}
		// Mark first so a second status change in the same request can't send twice.
		$order->update_meta_data( self::SENT_META, time() );
		$order->save_meta_data();
		self::send( $order );
	}

	public static function order_action( $actions ) {
		if ( self::enabled() ) {
			$actions['nvx_resend_emails'] = 'Resend order emails (Neuvix: admin + customer)';
		}
		return $actions;
	}

	public static function resend( $order ) {
		self::send( $order );
		$order->add_order_note( 'Neuvix order emails re-sent.' );
	}

	/* ---------- Sending ---------- */

	public static function admin_recipients() {
		$list = preg_split( '/[\s,;]+/', (string) NVX_Settings::get( 'email_admin_to' ) );
		return array_values( array_filter( array_map( 'sanitize_email', (array) $list ), 'is_email' ) );
	}

	protected static function replace( $text, $order ) {
		return strtr( (string) $text, [
			'{store}' => get_bloginfo( 'name' ),
			'{order}' => $order->get_order_number(),
			'{name}'  => $order->get_billing_first_name(),
			'{total}' => html_entity_decode( wp_strip_all_tags( wc_price( $order->get_total(), [ 'currency' => $order->get_currency() ] ) ), ENT_QUOTES, 'UTF-8' ),
		] );
	}

	protected static function headers( $reply_to = '' ) {
		$name    = NVX_Settings::get( 'email_from_name' ) ?: get_bloginfo( 'name' );
		$address = sanitize_email( NVX_Settings::get( 'email_from_address' ) );
		$headers = [ 'Content-Type: text/html; charset=UTF-8' ];
		if ( is_email( $address ) ) {
			$headers[] = 'From: ' . wp_specialchars_decode( $name, ENT_QUOTES ) . ' <' . $address . '>';
		}
		if ( $reply_to && is_email( $reply_to ) ) {
			$headers[] = 'Reply-To: ' . $reply_to;
		}
		return $headers;
	}

	public static function render( $order, $sent_to_admin ) {
		ob_start();
		include NVX_CO_PATH . 'templates/emails/order.php';
		return ob_get_clean();
	}

	public static function send( $order ) {
		if ( 'yes' === NVX_Settings::get( 'email_admin_on' ) && ( $to = self::admin_recipients() ) ) {
			wp_mail(
				$to,
				self::replace( NVX_Settings::get( 'email_admin_subject' ), $order ),
				self::render( $order, true ),
				self::headers( $order->get_billing_email() )
			);
		}

		$customer = $order->get_billing_email();
		if ( 'yes' === NVX_Settings::get( 'email_customer_on' ) && $customer && is_email( $customer ) ) {
			wp_mail(
				$customer,
				self::replace( NVX_Settings::get( 'email_customer_subject' ), $order ),
				self::render( $order, false ),
				self::headers()
			);
		}
	}

	/** Used by the template. */
	public static function text( $key, $order ) {
		return self::replace( NVX_Settings::get( $key ), $order );
	}
}
