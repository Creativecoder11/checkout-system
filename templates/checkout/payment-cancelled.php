<?php
/**
 * "Payment Cancelled" screen — online payment failed or was cancelled.
 * Loaded from checkout/thankyou.php.
 *
 * @var WC_Order $order
 */

defined( 'ABSPATH' ) || exit;

$failed  = $order->has_status( 'failed' );
$tips    = array_filter( array_map( 'trim', explode( "\n", (string) NVX_Settings::get( 'cancel_tips' ) ) ) );
$phone   = trim( (string) NVX_Settings::get( 'support_phone' ) );
$s_email = trim( (string) NVX_Settings::get( 'support_email' ) );
?>
<div class="nvx-result nvx-result--cancelled">
	<div class="nvx-result__card">

		<span class="nvx-result__icon" aria-hidden="true">
			<span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><path d="M7 7l10 10M17 7L7 17"/></svg></span>
		</span>
		<h1 class="nvx-result__title"><?php echo esc_html( NVX_Settings::get( $failed ? 'failed_title' : 'cancel_title' ) ); ?></h1>
		<p class="nvx-result__text"><?php echo esc_html( NVX_Settings::get( 'cancel_text' ) ); ?></p>
		<p class="nvx-result__ref"><span>Reference:</span> <strong>#<?php echo esc_html( $order->get_order_number() ); ?></strong></p>

		<div class="nvx-result__details">
			<h2>Payment Details</h2>
			<dl>
				<div><dt>Attempted On</dt><dd><?php echo esc_html( NVX_Payment_Result::date( $order ) ); ?></dd></div>
				<div><dt>Payment Method</dt><dd><?php echo esc_html( $order->get_payment_method_title() ); ?></dd></div>
				<div><dt>Amount</dt><dd><strong><?php echo wp_kses_post( wc_price( $order->get_total(), [ 'currency' => $order->get_currency() ] ) ); ?></strong></dd></div>
				<div class="is-status"><dt>Status</dt><dd><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></dd></div>
			</dl>
		</div>

		<?php if ( $tips ) : ?>
			<div class="nvx-result__tips">
				<h2>What you can do</h2>
				<ul>
					<?php foreach ( $tips as $tip ) : ?><li><?php echo esc_html( $tip ); ?></li><?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<?php if ( NVX_Settings::get( 'cancel_note_title' ) || NVX_Settings::get( 'cancel_note_text' ) ) : ?>
			<div class="nvx-result__note">
				<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><path d="M8.5 12l2.5 2.5 4.5-4.5"/></svg>
				<div>
					<strong><?php echo esc_html( NVX_Settings::get( 'cancel_note_title' ) ); ?></strong>
					<span><?php echo esc_html( NVX_Settings::get( 'cancel_note_text' ) ); ?></span>
				</div>
			</div>
		<?php endif; ?>

		<div class="nvx-result__actions">
			<a class="nvx-result__btn" href="<?php echo esc_url( NVX_Payment_Result::retry_url( $order ) ); ?>">Retry Payment</a>
			<a class="nvx-result__btn is-outline" href="<?php echo esc_url( wc_get_cart_url() ); ?>">Back to Cart</a>
		</div>

		<?php if ( $phone || $s_email ) : ?>
			<p class="nvx-result__help">
				Need help?
				<?php if ( $phone ) : ?>Call <a href="tel:<?php echo esc_attr( preg_replace( '/[^\d+]/', '', $phone ) ); ?>"><?php echo esc_html( $phone ); ?></a><?php endif; ?>
				<?php if ( $phone && $s_email ) : ?> or <?php endif; ?>
				<?php if ( $s_email ) : ?>email <a href="mailto:<?php echo esc_attr( antispambot( $s_email ) ); ?>"><?php echo esc_html( antispambot( $s_email ) ); ?></a><?php endif; ?>
			</p>
		<?php endif; ?>
	</div>
</div>
