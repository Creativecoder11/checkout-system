<?php
defined( 'ABSPATH' ) || exit;

/**
 * Printable A4 invoice.
 *
 * URL: /?nvx_invoice={order_id}&key={order_key}  (add &download=1 to open the print / "Save as PDF" dialog)
 * Access: the order key (same rule as WooCommerce's order-received page), the customer who owns
 * the order, or a shop manager.
 */
class NVX_Invoice {

	public static function init() {
		add_action( 'template_redirect', [ __CLASS__, 'maybe_render' ], 5 );
		add_filter( 'woocommerce_my_account_my_orders_actions', [ __CLASS__, 'account_action' ], 10, 2 );
		add_action( 'woocommerce_admin_order_data_after_order_details', [ __CLASS__, 'admin_link' ] );
	}

	public static function enabled() {
		return 'yes' === NVX_Settings::get( 'invoice_enable' );
	}

	/** Invoice available for this order? (not for failed / cancelled / unpaid-online orders) */
	public static function available( $order ) {
		return self::enabled() && $order && ! $order->has_status( [ 'failed', 'cancelled', 'pending', 'checkout-draft' ] );
	}

	public static function url( $order, $download = false ) {
		$args = [ 'nvx_invoice' => $order->get_id(), 'key' => $order->get_order_key() ];
		if ( $download ) {
			$args['download'] = 1;
		}
		return add_query_arg( $args, home_url( '/' ) );
	}

	public static function number( $order ) {
		$created = $order->get_date_created();
		$year    = $created ? $created->date( 'Y' ) : gmdate( 'Y' );
		$number  = NVX_Settings::get( 'invoice_prefix' ) . $year . '-' . str_pad( (string) $order->get_id(), 6, '0', STR_PAD_LEFT );
		return apply_filters( 'nvx_invoice_number', $number, $order );
	}

	/** Money actually received: online + paid, or any order marked Completed (COD delivered). */
	public static function is_paid( $order ) {
		if ( $order->has_status( 'completed' ) ) {
			return true;
		}
		return $order->is_paid() && NVX_Payment_Result::is_online( $order );
	}

	/** @return array{0:string,1:string} [ badge text, badge class ] */
	public static function badge( $order ) {
		if ( $order->has_status( 'refunded' ) ) {
			return [ 'Refunded', 'is-muted' ];
		}
		if ( $order->has_status( [ 'failed', 'cancelled' ] ) ) {
			return [ wc_get_order_status_name( $order->get_status() ), 'is-muted' ];
		}
		return self::is_paid( $order ) ? [ 'Paid', 'is-paid' ] : [ 'Unpaid', 'is-due' ];
	}

	public static function payment_status( $order ) {
		if ( $order->has_status( 'refunded' ) ) {
			return 'Refunded';
		}
		if ( self::is_paid( $order ) ) {
			return 'Paid in full';
		}
		return 'cod' === $order->get_payment_method() ? 'Pay on delivery' : 'Awaiting payment';
	}

	public static function logo_url() {
		$logo = NVX_Settings::get( 'invoice_logo' );
		if ( ! $logo && ( $id = get_theme_mod( 'custom_logo' ) ) ) {
			$logo = wp_get_attachment_image_url( $id, 'medium' );
		}
		return (string) $logo;
	}

	/** Replace {store} in a settings string. */
	public static function text( $key ) {
		return str_replace( '{store}', get_bloginfo( 'name' ), (string) NVX_Settings::get( $key ) );
	}

	public static function can_view( $order ) {
		$key = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore
		if ( $key && hash_equals( $order->get_order_key(), $key ) ) {
			return true;
		}
		if ( current_user_can( 'edit_shop_orders' ) ) {
			return true;
		}
		return is_user_logged_in() && (int) $order->get_customer_id() === get_current_user_id();
	}

	public static function maybe_render() {
		if ( empty( $_GET['nvx_invoice'] ) || ! self::enabled() ) { // phpcs:ignore
			return;
		}
		$order = wc_get_order( absint( $_GET['nvx_invoice'] ) ); // phpcs:ignore
		if ( ! $order || ! self::can_view( $order ) ) {
			wp_die( esc_html__( 'Invoice not found.', 'neuvix-checkout' ), '', [ 'response' => 404 ] );
		}
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );
		$autoprint = ! empty( $_GET['download'] ); // phpcs:ignore
		$child = trailingslashit( get_stylesheet_directory() ) . 'neuvix-checkout/invoice.php';
		include file_exists( $child ) ? $child : NVX_CO_PATH . 'templates/invoice/invoice.php';
		exit;
	}

	public static function account_action( $actions, $order ) {
		if ( self::available( $order ) ) {
			$actions['nvx-invoice'] = [ 'url' => self::url( $order, true ), 'name' => 'Invoice' ];
		}
		return $actions;
	}

	public static function admin_link( $order ) {
		if ( ! self::enabled() ) {
			return;
		}
		echo '<p class="form-field form-field-wide"><a class="button" target="_blank" href="' . esc_url( self::url( $order ) ) . '">View invoice</a></p>';
	}

	/** "Download Invoice" button for the confirmation screens. */
	public static function button( $order, $class ) {
		if ( ! self::available( $order ) ) {
			return;
		}
		?>
		<a class="<?php echo esc_attr( $class ); ?>" href="<?php echo esc_url( self::url( $order, true ) ); ?>" target="_blank" rel="noopener">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4v11M7 10l5 5 5-5M5 20h14"/></svg>
			Download Invoice
		</a>
		<?php
	}
}
