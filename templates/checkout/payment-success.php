<?php
/**
 * "Payment Successful" screen — online payment completed.
 * Loaded from checkout/thankyou.php.
 *
 * @var WC_Order $order
 */

defined( 'ABSPATH' ) || exit;

$email    = $order->get_billing_email();
$txn      = $order->get_transaction_id();
$address  = NVX_Payment_Result::address( $order );
$delivery = $order->get_shipping_method();
$eta      = NVX_Payment_Result::eta( $order );
$note     = NVX_Settings::get( 'eta_note' );
$text     = strtr( (string) NVX_Settings::get( 'success_text' ), [
	'{store}' => get_bloginfo( 'name' ),
	'{email}' => $email ? ' and a confirmation has been sent to your email' : '',
] );
$continue_url   = NVX_Settings::get( 'continue_url' ) ?: wc_get_page_permalink( 'shop' );
$continue_label = NVX_Settings::get( 'continue_label' ) ?: 'Continue Shopping';
$orders_url     = is_user_logged_in() ? wc_get_account_endpoint_url( 'orders' ) : '';
?>
<div class="nvx-result nvx-result--success">
	<div class="nvx-result__card">

		<span class="nvx-result__icon" aria-hidden="true">
			<span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7"/></svg></span>
		</span>
		<h1 class="nvx-result__title"><?php echo esc_html( NVX_Settings::get( 'success_title' ) ); ?></h1>
		<?php if ( $text ) : ?><p class="nvx-result__text"><?php echo esc_html( $text ); ?></p><?php endif; ?>
		<p class="nvx-result__ref"><span>Order ID:</span> <strong>#<?php echo esc_html( $order->get_order_number() ); ?></strong></p>

		<div class="nvx-result__details">
			<h2>Order Details</h2>
			<dl>
				<div><dt>Order Date</dt><dd><?php echo esc_html( NVX_Payment_Result::date( $order ) ); ?></dd></div>
				<div><dt>Payment Method</dt><dd><?php echo esc_html( $order->get_payment_method_title() ); ?></dd></div>
				<?php if ( $txn ) : ?><div><dt>Transaction ID</dt><dd><?php echo esc_html( $txn ); ?></dd></div><?php endif; ?>
				<?php if ( $address ) : ?><div><dt>Delivery Address</dt><dd><?php echo esc_html( $address ); ?></dd></div><?php endif; ?>
				<?php if ( $delivery ) : ?><div><dt>Delivery</dt><dd><?php echo esc_html( $delivery ); ?></dd></div><?php endif; ?>
				<div class="is-total"><dt>Amount Paid</dt><dd><?php echo wp_kses_post( wc_price( $order->get_total(), [ 'currency' => $order->get_currency() ] ) ); ?></dd></div>
			</dl>
		</div>

		<?php if ( $eta ) : ?>
			<div class="nvx-result__note">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 6h11v10H2zM13 10h5l3 3v3h-8z"/><circle cx="6" cy="18" r="2"/><circle cx="17" cy="18" r="2"/></svg>
				<div>
					<strong>Estimated delivery: <?php echo esc_html( $eta ); ?></strong>
					<?php if ( $note ) : ?><span><?php echo esc_html( $note ); ?></span><?php endif; ?>
				</div>
			</div>
		<?php endif; ?>

		<div class="nvx-result__actions">
			<?php if ( $orders_url ) : ?>
				<a class="nvx-result__btn" href="<?php echo esc_url( $orders_url ); ?>">View My Orders</a>
			<?php endif; ?>
			<a class="nvx-result__btn <?php echo $orders_url ? 'is-outline' : ''; ?>" href="<?php echo esc_url( $continue_url ); ?>"><?php echo esc_html( $continue_label ); ?></a>
			<?php NVX_Invoice::button( $order, 'nvx-result__btn is-outline is-invoice' ); ?>
		</div>

		<div class="nvx-result__hooks">
			<?php
			// Keep gateway / tracking hooks (purchase pixels, gateway notices, etc.).
			do_action( 'woocommerce_thankyou_' . $order->get_payment_method(), $order->get_id() );
			do_action( 'woocommerce_thankyou', $order->get_id() );
			?>
		</div>
	</div>
</div>
