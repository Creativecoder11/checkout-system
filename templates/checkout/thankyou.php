<?php
/**
 * Order confirmation page — overrides WooCommerce checkout/thankyou.php
 * Shipped by the Neuvix Checkout plugin. To customise, copy to your-child-theme/woocommerce/checkout/thankyou.php
 *
 * @var WC_Order $order
 */

defined( 'ABSPATH' ) || exit;

if ( ! $order ) :
	?>
	<div class="nvx-thankyou"><div class="nvx-thankyou__hero"><h1><?php esc_html_e( 'Thank you. Your order has been received.', 'woocommerce' ); ?></h1></div></div>
	<?php
	return;
endif;

// Online payments: "Payment Successful" / "Payment Cancelled" screens.
$nvx_view = class_exists( 'NVX_Payment_Result' ) ? NVX_Payment_Result::view( $order ) : 'default';
if ( in_array( $nvx_view, [ 'success', 'cancelled' ], true ) ) {
	include NVX_CO_PATH . 'templates/checkout/payment-' . $nvx_view . '.php';
	return;
}

$continue_url   = NVX_Settings::get( 'continue_url' ) ?: wc_get_page_permalink( 'shop' );
$continue_label = NVX_Settings::get( 'continue_label' ) ?: 'Continue Shopping';
$email          = $order->get_billing_email();
$packaging      = $order->get_meta( '_nvx_packaging' );
$district       = $order->get_meta( '_nvx_district' );
$area           = $order->get_meta( '_nvx_area' );
?>
<div class="nvx-thankyou">

	<?php if ( $order->has_status( 'failed' ) ) : ?>

		<div class="nvx-thankyou__hero is-failed">
			<span class="nvx-thankyou__icon is-failed" aria-hidden="true">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"><path d="M6 6l12 12M18 6L6 18"/></svg>
			</span>
			<h1><?php esc_html_e( 'Payment failed', 'neuvix-checkout' ); ?></h1>
			<p><?php esc_html_e( 'Unfortunately your order cannot be processed as the originating bank/merchant has declined your transaction. Please attempt your purchase again.', 'woocommerce' ); ?></p>
			<p class="nvx-thankyou__actions">
				<a href="<?php echo esc_url( $order->get_checkout_payment_url() ); ?>" class="nvx-btn"><?php esc_html_e( 'Pay', 'woocommerce' ); ?></a>
				<?php if ( is_user_logged_in() ) : ?>
					<a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>" class="nvx-btn nvx-btn--ghost"><?php esc_html_e( 'My account', 'woocommerce' ); ?></a>
				<?php endif; ?>
			</p>
		</div>

	<?php else : ?>

		<div class="nvx-thankyou__hero">
			<span class="nvx-thankyou__icon" aria-hidden="true">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12.5l4.5 4.5L19 7"/></svg>
			</span>
			<h1><?php echo esc_html( NVX_Settings::get( 'thankyou_title' ) ?: 'Thank you for your order!' ); ?></h1>
			<p class="nvx-thankyou__sub"><?php echo esc_html( NVX_Settings::get( 'thankyou_subtitle' ) ?: 'Your order has been received and is being processed' ); ?></p>

			<div class="nvx-thankyou__number">
				<span>
					<small><?php esc_html_e( 'Order number', 'woocommerce' ); ?></small>
					<strong>#<?php echo esc_html( $order->get_order_number() ); ?></strong>
				</span>
				<button type="button" class="nvx-copy" data-copy="<?php echo esc_attr( $order->get_order_number() ); ?>" aria-label="Copy order number" title="Copy">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V6a2 2 0 0 1 2-2h9"/></svg>
				</button>
			</div>

			<?php if ( $email ) : ?>
				<p class="nvx-thankyou__email">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg>
					Order confirmation sent to <strong><?php echo esc_html( $email ); ?></strong>
				</p>
			<?php endif; ?>
		</div>

		<details class="nvx-card" open>
			<summary class="nvx-card__head"><span>Order Summary</span><i class="nvx-card__chevron" aria-hidden="true"></i></summary>
			<div class="nvx-card__body">
				<?php foreach ( $order->get_items() as $item_id => $item ) :
					$product = $item->get_product();
					$meta    = [];
					foreach ( $item->get_formatted_meta_data( '_', true ) as $m ) {
						$meta[] = wp_strip_all_tags( $m->display_value );
					}
					?>
					<div class="nvx-ty-item">
						<div class="nvx-ty-item__thumb"><?php echo $product ? $product->get_image( 'woocommerce_gallery_thumbnail' ) : ''; // phpcs:ignore ?></div>
						<div class="nvx-ty-item__info">
							<span class="nvx-ty-item__name"><?php echo esc_html( $item->get_name() ); ?></span>
							<?php if ( $meta ) : ?><span class="nvx-ty-item__meta"><?php echo esc_html( implode( ', ', $meta ) ); ?></span><?php endif; ?>
							<span class="nvx-ty-item__meta">Qty: <?php echo esc_html( $item->get_quantity() ); ?></span>
						</div>
						<div class="nvx-ty-item__price"><?php echo wp_kses_post( $order->get_formatted_line_subtotal( $item ) ); ?></div>
					</div>
				<?php endforeach; ?>

				<div class="nvx-ty-totals">
					<div class="nvx-ty-row"><span>Subtotal</span><span><?php echo wp_kses_post( $order->get_subtotal_to_display() ); ?></span></div>
					<?php if ( $order->get_total_discount() > 0 ) : ?>
						<div class="nvx-ty-row"><span>Discount</span><span class="is-discount">-<?php echo wp_kses_post( wc_price( $order->get_total_discount(), [ 'currency' => $order->get_currency() ] ) ); ?></span></div>
					<?php endif; ?>
					<div class="nvx-ty-row">
						<span>Shipping<?php echo $order->get_shipping_method() ? ' <em>(' . esc_html( $order->get_shipping_method() ) . ')</em>' : ''; ?></span>
						<span><?php echo (float) $order->get_shipping_total() > 0 ? wp_kses_post( wc_price( $order->get_shipping_total(), [ 'currency' => $order->get_currency() ] ) ) : '<span class="is-free">Free</span>'; ?></span>
					</div>
					<?php foreach ( $order->get_items( 'fee' ) as $fee ) : ?>
						<div class="nvx-ty-row"><span><?php echo esc_html( $fee->get_name() ); ?></span><span><?php echo (float) $fee->get_total() > 0 ? wp_kses_post( wc_price( $fee->get_total(), [ 'currency' => $order->get_currency() ] ) ) : '<span class="is-free">Free</span>'; ?></span></div>
					<?php endforeach; ?>
					<?php if ( (float) $order->get_total_tax() > 0 ) : ?>
						<div class="nvx-ty-row"><span>Tax</span><span><?php echo wp_kses_post( wc_price( $order->get_total_tax(), [ 'currency' => $order->get_currency() ] ) ); ?></span></div>
					<?php endif; ?>
					<div class="nvx-ty-row nvx-ty-row--total"><span>Total</span><span><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></span></div>
				</div>
			</div>
		</details>

		<div class="nvx-card">
			<div class="nvx-card__head"><span>Delivery Details</span></div>
			<div class="nvx-card__body nvx-ty-details">
				<div class="nvx-ty-detail">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s7-7 7-12a7 7 0 0 0-14 0c0 5 7 12 7 12z"/><circle cx="12" cy="10" r="2.5"/></svg>
					<div>
						<strong>Shipping to</strong>
						<address><?php echo wp_kses_post( $order->get_formatted_billing_address() ); ?><?php if ( $order->get_billing_phone() ) : ?><br><?php echo esc_html( $order->get_billing_phone() ); ?><?php endif; ?></address>
					</div>
				</div>
				<div class="nvx-ty-detail">
					<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
					<div><strong>Payment method</strong><span><?php echo esc_html( $order->get_payment_method_title() ); ?></span></div>
				</div>
				<?php if ( $order->get_shipping_method() ) : ?>
					<div class="nvx-ty-detail">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2 6h11v10H2zM13 10h5l3 3v3h-8z"/><circle cx="6" cy="18" r="2"/><circle cx="17" cy="18" r="2"/></svg>
						<div><strong>Delivery</strong><span><?php echo esc_html( $order->get_shipping_method() ); ?><?php echo $district ? ' — ' . esc_html( trim( $area . ', ' . $district, ', ' ) ) : ''; ?></span></div>
					</div>
				<?php endif; ?>
				<?php if ( $packaging ) : ?>
					<div class="nvx-ty-detail">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 8l9-4 9 4v8l-9 4-9-4zM3 8l9 4 9-4M12 12v8"/></svg>
						<div><strong>Packaging</strong><span><?php echo esc_html( $packaging ); ?></span></div>
					</div>
				<?php endif; ?>
				<?php if ( $order->get_customer_note() ) : ?>
					<div class="nvx-ty-detail">
						<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 4h16v12H8l-4 4z"/></svg>
						<div><strong>Order note</strong><span><?php echo esc_html( $order->get_customer_note() ); ?></span></div>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<p class="nvx-thankyou__actions">
			<a href="<?php echo esc_url( $continue_url ); ?>" class="nvx-btn"><?php echo esc_html( $continue_label ); ?></a>
			<?php if ( class_exists( 'NVX_Invoice' ) ) { NVX_Invoice::button( $order, 'nvx-btn nvx-btn--ghost is-invoice' ); } ?>
		</p>

	<?php endif; ?>

	<div class="nvx-thankyou__hooks">
		<?php
		// Keep gateway hooks (COD instructions, bKash/SSLCommerz notices, etc.).
		do_action( 'woocommerce_thankyou_' . $order->get_payment_method(), $order->get_id() );
		do_action( 'woocommerce_thankyou', $order->get_id() );
		?>
	</div>
</div>
<script>
(function(){
	var b=document.querySelector('.nvx-copy'); if(!b) return;
	b.addEventListener('click',function(){
		var v=b.getAttribute('data-copy')||'';
		var done=function(){ b.classList.add('is-done'); setTimeout(function(){ b.classList.remove('is-done'); },1500); };
		if(navigator.clipboard&&navigator.clipboard.writeText){ navigator.clipboard.writeText(v).then(done); }
		else { var t=document.createElement('textarea'); t.value=v; document.body.appendChild(t); t.select(); try{ document.execCommand('copy'); done(); }catch(e){} document.body.removeChild(t); }
	});
})();
</script>
