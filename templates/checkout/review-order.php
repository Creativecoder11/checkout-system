<?php
/**
 * Order Summary panel — overrides WooCommerce checkout/review-order.php
 * Shipped by the Neuvix Checkout plugin. To customise, copy to your-theme/woocommerce/checkout/review-order.php
 *
 * @package Neuvix
 */

defined( 'ABSPATH' ) || exit;

$cart  = WC()->cart;
$count = $cart->get_cart_contents_count();
?>
<div class="nvx-summary shop_table woocommerce-checkout-review-order-table">

	<div class="nvx-summary__head">
		<h3>Order Summary</h3>
		<span class="nvx-summary__count"><?php echo esc_html( $count . ' ' . _n( 'Item', 'Items', $count, 'woocommerce' ) ); ?></span>
	</div>

	<div class="nvx-summary__items">
		<?php
		do_action( 'woocommerce_review_order_before_cart_contents' );

		foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
			$_product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );

			if ( ! $_product || ! $_product->exists() || $cart_item['quantity'] <= 0 || ! apply_filters( 'woocommerce_checkout_cart_item_visible', true, $cart_item, $cart_item_key ) ) {
				continue;
			}
			?>
			<div class="nvx-summary__item <?php echo esc_attr( apply_filters( 'woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key ) ); ?>">
				<div class="nvx-summary__thumb"><?php echo $_product->get_image( 'woocommerce_gallery_thumbnail' ); // phpcs:ignore ?></div>
				<div class="nvx-summary__info">
					<span class="nvx-summary__name"><?php echo wp_kses_post( apply_filters( 'nvx_summary_item_name', $_product->get_name(), $cart_item, $cart_item_key ) ); ?></span>
					<span class="nvx-summary__meta">
						<?php echo wc_get_formatted_cart_item_data( $cart_item ); // variation attributes, e.g. 100ml | 1 month ?>
						<span class="nvx-summary__qty">Qty: <?php echo esc_html( $cart_item['quantity'] ); ?></span>
					</span>
				</div>
				<div class="nvx-summary__price"><?php echo apply_filters( 'woocommerce_cart_item_subtotal', $cart->get_product_subtotal( $_product, $cart_item['quantity'] ), $cart_item, $cart_item_key ); // phpcs:ignore ?></div>
			</div>
			<?php
		}

		do_action( 'woocommerce_review_order_after_cart_contents' );
		?>
	</div>

	<?php if ( class_exists( 'NVX_Summary' ) ) { NVX_Summary::render_coupon(); } ?>

	<div class="nvx-summary__totals">
		<div class="nvx-row">
			<span>Subtotal (<?php echo esc_html( str_pad( $count, 2, '0', STR_PAD_LEFT ) ); ?> Products)</span>
			<span><?php wc_cart_totals_subtotal_html(); ?></span>
		</div>

		<?php if ( $cart->get_discount_total() > 0 ) : ?>
			<div class="nvx-row nvx-row--discount">
				<span>Discount Applied</span>
				<span>-<?php echo wp_kses_post( wc_price( $cart->get_discount_total() ) ); ?></span>
			</div>
		<?php endif; ?>

		<?php if ( $cart->needs_shipping() && $cart->show_shipping() ) : ?>
			<div class="nvx-row">
				<span>Shipping</span>
				<span><?php echo wp_kses_post( wc_price( $cart->get_shipping_total() ) ); ?></span>
			</div>
		<?php endif; ?>

		<?php foreach ( $cart->get_fees() as $fee ) : ?>
			<div class="nvx-row nvx-row--fee">
				<span><?php echo esc_html( $fee->name ); ?></span>
				<span><?php if ( (float) $fee->total > 0 ) { wc_cart_totals_fee_html( $fee ); } else { echo '<span class="nvx-free">FREE</span>'; } ?></span>
			</div>
		<?php endforeach; ?>

		<?php if ( wc_tax_enabled() && ! $cart->display_prices_including_tax() ) : ?>
			<div class="nvx-row">
				<span>Vat and Tax</span>
				<span><?php echo wp_kses_post( wc_price( $cart->get_total_tax() ) ); ?></span>
			</div>
		<?php endif; ?>

		<?php do_action( 'woocommerce_review_order_before_order_total' ); ?>
		<div class="nvx-row nvx-row--total">
			<span>Total</span>
			<span><?php wc_cart_totals_order_total_html(); ?></span>
		</div>
		<?php do_action( 'woocommerce_review_order_after_order_total' ); ?>
	</div>

	<?php if ( class_exists( 'NVX_Shipping' ) ) { NVX_Shipping::render_ui(); } ?>

	<?php
	// WooCommerce needs the shipping-method inputs in the form; the district-based rate is forced
	// server-side, so the list stays hidden and the charge is shown above the payment methods.
	if ( $cart->needs_shipping() && $cart->show_shipping() ) :
		?>
		<table class="nvx-shipping-src" style="display:none" aria-hidden="true"><tbody>
			<?php
			do_action( 'woocommerce_review_order_before_shipping' );
			wc_cart_totals_shipping_html();
			do_action( 'woocommerce_review_order_after_shipping' );
			?>
		</tbody></table>
	<?php endif; ?>
</div>
