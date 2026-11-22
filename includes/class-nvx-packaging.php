<?php
defined( 'ABSPATH' ) || exit;

/**
 * Packaging options rendered as radio cards; adds a cart fee; saves to the order.
 */
class NVX_Packaging {

	const SESSION_KEY = 'nvx_packaging';

	public static function init() {
		add_action( 'woocommerce_after_checkout_billing_form', [ __CLASS__, 'render' ], 20 );
		add_action( 'woocommerce_checkout_update_order_review', [ __CLASS__, 'store_selection' ] );
		add_action( 'woocommerce_cart_calculate_fees', [ __CLASS__, 'add_fee' ] );
		add_action( 'woocommerce_checkout_process', [ __CLASS__, 'validate' ] );
		add_action( 'woocommerce_checkout_create_order', [ __CLASS__, 'save_to_order' ] );
		add_action( 'woocommerce_admin_order_data_after_shipping_address', [ __CLASS__, 'admin_display' ] );
		add_filter( 'woocommerce_get_order_item_totals', [ __CLASS__, 'order_totals_row' ], 10, 2 );
		add_action( 'woocommerce_email_after_order_table', [ __CLASS__, 'email_display' ], 10, 4 );
	}

	/** "Packaging" row in order details, emails and My Account (after Shipping). */
	public static function order_totals_row( $rows, $order ) {
		$label = $order->get_meta( '_nvx_packaging' );
		if ( ! $label ) {
			return $rows;
		}
		$amount = 0;
		foreach ( $order->get_items( 'fee' ) as $fee ) {
			if ( 0 === strpos( $fee->get_name(), 'Packaging:' ) ) {
				$amount = (float) $fee->get_total();
			}
		}
		$row = [ 'nvx_packaging' => [
			'label' => 'Packaging:',
			'value' => esc_html( $label ) . ( $amount > 0 ? ' (' . wp_kses_post( wc_price( $amount, [ 'currency' => $order->get_currency() ] ) ) . ')' : ' (Free)' ),
		] ];
		$pos = array_search( 'shipping', array_keys( $rows ), true );
		if ( false === $pos ) {
			$pos = array_search( 'cart_subtotal', array_keys( $rows ), true );
		}
		return false === $pos ? $rows + $row : array_slice( $rows, 0, $pos + 1, true ) + $row + array_slice( $rows, $pos + 1, null, true );
	}

	public static function email_display( $order, $sent_to_admin, $plain_text, $email ) {
		if ( $plain_text || ! ( $p = $order->get_meta( '_nvx_packaging' ) ) ) {
			return;
		}
		echo '<p style="margin:0 0 16px"><strong>Packaging:</strong> ' . esc_html( $p ) . '</p>';
	}

	public static function options() {
		$out = [];
		foreach ( [ 'poly', 'bag', 'box' ] as $k ) {
			if ( 'yes' !== NVX_Settings::get( "pack_{$k}_on" ) ) {
				continue;
			}
			$out[ $k ] = [
				'label'     => NVX_Settings::get( "pack_{$k}_label" ),
				'price'     => (float) NVX_Settings::get( "pack_{$k}_price" ),
				'free_over' => (float) NVX_Settings::get( "pack_{$k}_free" ),
				'img'       => NVX_Settings::get( "pack_{$k}_img" ) ?: NVX_CO_URL . "assets/img/pack-{$k}.svg",
				'features'  => array_filter( array_map( 'trim', explode( "\n", NVX_Settings::get( "pack_{$k}_feat" ) ) ) ),
			];
		}
		return apply_filters( 'nvx_packaging_options', $out );
	}

	protected static function selected() {
		$opts = self::options();
		$sel  = WC()->session ? WC()->session->get( self::SESSION_KEY ) : null;
		return ( $sel && isset( $opts[ $sel ] ) ) ? $sel : array_key_first( $opts );
	}

	public static function is_free( $o, $subtotal ) {
		return $o['price'] <= 0 || ( $o['free_over'] > 0 && $subtotal >= $o['free_over'] );
	}

	public static function render() {
		$options = self::options();
		if ( ! $options ) {
			return;
		}
		$selected = self::selected();
		$subtotal = WC()->cart->get_subtotal();
		?>
		<div class="nvx-packaging">
			<h3 class="nvx-packaging__title"><?php echo esc_html( apply_filters( 'nvx_packaging_title', 'Choose Your Packaging' ) ); ?></h3>
			<?php foreach ( $options as $key => $o ) : $free = self::is_free( $o, $subtotal ); ?>
				<label class="nvx-pack <?php echo $selected === $key ? 'is-selected' : ''; ?>">
					<input type="radio" name="nvx_packaging" value="<?php echo esc_attr( $key ); ?>" <?php checked( $selected, $key ); ?>>
					<span class="nvx-pack__radio" aria-hidden="true"></span>
					<span class="nvx-pack__img"><img src="<?php echo esc_url( $o['img'] ); ?>" alt=""></span>
					<span class="nvx-pack__body">
						<span class="nvx-pack__name"><?php echo esc_html( $o['label'] ); ?></span>
						<span class="nvx-pack__price">
							<?php if ( $free ) : ?>
								FREE <?php if ( $o['price'] > 0 ) : ?><del><?php echo wp_kses_post( wc_price( $o['price'] ) ); ?></del><?php endif; ?>
							<?php else : ?>
								<?php echo wp_kses_post( wc_price( $o['price'] ) ); ?>
							<?php endif; ?>
						</span>
						<?php if ( $o['free_over'] > 0 ) : ?>
							<span class="nvx-pack__note">FREE on <?php echo wp_kses_post( wc_price( $o['free_over'], [ 'decimals' => 0 ] ) ); ?>+</span>
						<?php endif; ?>
					</span>
					<?php if ( $o['features'] ) : ?>
						<ul class="nvx-pack__features">
							<?php foreach ( $o['features'] as $f ) : ?><li><?php echo esc_html( $f ); ?></li><?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</label>
			<?php endforeach; ?>
		</div>
		<?php
	}

	public static function store_selection( $post_data ) {
		parse_str( $post_data, $data );
		if ( isset( $data['nvx_packaging'] ) && isset( self::options()[ $data['nvx_packaging'] ] ) ) {
			WC()->session->set( self::SESSION_KEY, sanitize_key( $data['nvx_packaging'] ) );
		}
	}

	public static function add_fee( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}
		$opts = self::options();
		if ( ! $opts ) {
			return;
		}
		// On final submit the value arrives via $_POST rather than the session.
		if ( ! empty( $_POST['nvx_packaging'] ) && isset( $opts[ $_POST['nvx_packaging'] ] ) ) { // phpcs:ignore
			WC()->session->set( self::SESSION_KEY, sanitize_key( $_POST['nvx_packaging'] ) ); // phpcs:ignore
		}
		$o      = $opts[ self::selected() ];
		$amount = self::is_free( $o, $cart->get_subtotal() ) ? 0 : $o['price'];
		// Always added (even at 0) so the packaging choice becomes a real line item on the order.
		$cart->add_fee( 'Packaging: ' . $o['label'], $amount, $amount > 0 );
	}

	public static function validate() {
		$opts = self::options();
		if ( $opts && ( empty( $_POST['nvx_packaging'] ) || ! isset( $opts[ $_POST['nvx_packaging'] ] ) ) ) { // phpcs:ignore
			wc_add_notice( 'Please choose a packaging option.', 'error' );
		}
	}

	public static function save_to_order( $order ) {
		$opts = self::options();
		$key  = sanitize_key( $_POST['nvx_packaging'] ?? '' ); // phpcs:ignore
		if ( isset( $opts[ $key ] ) ) {
			$order->update_meta_data( '_nvx_packaging', $opts[ $key ]['label'] );
		}
		if ( WC()->session ) {
			WC()->session->__unset( self::SESSION_KEY );
		}
	}

	public static function admin_display( $order ) {
		if ( $p = $order->get_meta( '_nvx_packaging' ) ) {
			echo '<p><strong>Packaging:</strong> ' . esc_html( $p ) . '</p>';
		}
	}

	public static function customer_display( $order ) {
		if ( $p = $order->get_meta( '_nvx_packaging' ) ) {
			echo '<p class="nvx-order-packaging"><strong>Packaging:</strong> ' . esc_html( $p ) . '</p>';
		}
	}
}
