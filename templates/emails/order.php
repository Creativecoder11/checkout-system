<?php
/**
 * Order email (admin + customer). Email-client safe: tables + inline styles only.
 *
 * @var WC_Order $order
 * @var bool     $sent_to_admin
 */

defined( 'ABSPATH' ) || exit;

$p        = '#b02a3a';
$soft     = '#f8e9ec';
$text     = '#1f2430';
$muted    = '#6b7080';
$line     = '#e3e5e8';
$panel    = '#f5f6f8';
$font     = "-apple-system,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";
$store    = get_bloginfo( 'name' );
$currency = [ 'currency' => $order->get_currency() ];
$logo     = class_exists( 'NVX_Invoice' ) ? NVX_Invoice::email_logo_url() : '';
$invoice  = class_exists( 'NVX_Invoice' ) && NVX_Invoice::available( $order ) ? NVX_Invoice::url( $order, true ) : '';
$created  = $order->get_date_created();
$district = (string) $order->get_meta( '_nvx_district' );
$area     = (string) ( $order->get_meta( '_nvx_area' ) ?: $order->get_billing_city() );
$address  = implode( ', ', array_unique( array_filter( [ $order->get_billing_address_1(), $area, $district ] ) ) );
$money    = function ( $amount ) use ( $currency ) {
	return wc_price( $amount, $currency );
};

if ( $sent_to_admin ) {
	$heading = 'New order received';
	$intro   = sprintf( 'You have a new order from %s.', $order->get_formatted_billing_full_name() );
} else {
	$heading = NVX_Emails::text( 'email_customer_heading', $order );
	$intro   = NVX_Emails::text( 'email_customer_text', $order );
}

$rows = array_filter( [
	'Order Date'     => $created ? wc_format_datetime( $created, 'j M Y, h:i A' ) : '',
	'Payment Method' => $order->get_payment_method_title(),
	'Payment Status' => class_exists( 'NVX_Invoice' ) ? NVX_Invoice::payment_status( $order ) : '',
	'Transaction ID' => $order->get_transaction_id(),
	'Delivery'       => $order->get_shipping_method(),
	'Packaging'      => $order->get_meta( '_nvx_packaging' ),
] );
?><!doctype html>
<html>
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?php echo esc_html( $heading ); ?></title></head>
<body style="margin:0;padding:0;background:<?php echo $panel; ?>;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:<?php echo $panel; ?>;">
<tr><td align="center" style="padding:28px 12px;">

	<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;background:#ffffff;font-family:<?php echo $font; ?>;color:<?php echo $text; ?>;">
		<tr><td style="height:5px;line-height:5px;font-size:0;background:<?php echo $p; ?>;">&nbsp;</td></tr>

		<!-- Logo -->
		<tr><td align="center" style="padding:28px 32px 8px;">
			<?php if ( $logo ) : ?>
				<img src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr( $store ); ?>" style="display:block;max-height:40px;max-width:200px;height:auto;border:0;">
			<?php else : ?>
				<span style="font-size:24px;font-weight:700;color:<?php echo $p; ?>;"><?php echo esc_html( $store ); ?></span>
			<?php endif; ?>
		</td></tr>

		<!-- Heading -->
		<tr><td align="center" style="padding:18px 32px 0;">
			<h1 style="margin:0 0 10px;font-size:26px;line-height:1.25;font-weight:600;color:<?php echo $text; ?>;"><?php echo esc_html( $heading ); ?></h1>
			<?php if ( $intro ) : ?><p style="margin:0 auto 18px;max-width:440px;font-size:14px;line-height:1.6;color:<?php echo $muted; ?>;"><?php echo esc_html( $intro ); ?></p><?php endif; ?>
			<span style="display:inline-block;padding:7px 16px;border-radius:999px;background:<?php echo $soft; ?>;font-size:13px;color:<?php echo $muted; ?>;">Order ID: <strong style="color:<?php echo $p; ?>;">#<?php echo esc_html( $order->get_order_number() ); ?></strong></span>
		</td></tr>

		<!-- Details -->
		<tr><td style="padding:26px 32px 0;">
			<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:<?php echo $panel; ?>;">
				<tr><td colspan="2" style="padding:18px 20px 6px;font-size:15px;font-weight:600;">Order Details</td></tr>
				<?php foreach ( $rows as $label => $value ) : ?>
					<tr>
						<td style="padding:6px 20px;font-size:13px;color:<?php echo $muted; ?>;"><?php echo esc_html( $label ); ?></td>
						<td align="right" style="padding:6px 20px;font-size:13px;"><?php echo esc_html( $value ); ?></td>
					</tr>
				<?php endforeach; ?>
				<tr><td colspan="2" style="height:12px;line-height:12px;font-size:0;">&nbsp;</td></tr>
			</table>
		</td></tr>

		<!-- Items -->
		<tr><td style="padding:24px 32px 0;">
			<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
				<tr>
					<td style="background:<?php echo $p; ?>;color:#fff;padding:10px 12px;font-size:11px;font-weight:600;letter-spacing:1px;text-transform:uppercase;">Product</td>
					<td align="center" style="background:<?php echo $p; ?>;color:#fff;padding:10px 8px;font-size:11px;font-weight:600;letter-spacing:1px;text-transform:uppercase;">Qty</td>
					<td align="right" style="background:<?php echo $p; ?>;color:#fff;padding:10px 12px;font-size:11px;font-weight:600;letter-spacing:1px;text-transform:uppercase;">Amount</td>
				</tr>
				<?php foreach ( $order->get_items() as $item ) :
					$meta = [];
					foreach ( $item->get_formatted_meta_data( '_', true ) as $m ) {
						$meta[] = wp_strip_all_tags( $m->display_value );
					}
					?>
					<tr>
						<td style="padding:12px;border-bottom:1px solid <?php echo $line; ?>;font-size:13px;">
							<?php echo esc_html( $item->get_name() ); ?>
							<?php if ( $meta ) : ?><br><span style="font-size:11px;color:<?php echo $muted; ?>;"><?php echo esc_html( implode( ' · ', $meta ) ); ?></span><?php endif; ?>
						</td>
						<td align="center" style="padding:12px 8px;border-bottom:1px solid <?php echo $line; ?>;font-size:13px;"><?php echo esc_html( $item->get_quantity() ); ?></td>
						<td align="right" style="padding:12px;border-bottom:1px solid <?php echo $line; ?>;font-size:13px;white-space:nowrap;"><?php echo wp_kses_post( $money( $item->get_subtotal() ) ); ?></td>
					</tr>
				<?php endforeach; ?>
			</table>
		</td></tr>

		<!-- Totals -->
		<tr><td style="padding:14px 32px 0;">
			<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:13px;">
				<tr><td style="padding:5px 0;color:<?php echo $muted; ?>;">Subtotal</td><td align="right" style="padding:5px 0;"><?php echo wp_kses_post( $money( $order->get_subtotal() ) ); ?></td></tr>
				<?php if ( (float) $order->get_total_discount() > 0 ) : ?>
					<tr><td style="padding:5px 0;color:<?php echo $muted; ?>;">Discount<?php echo $order->get_coupon_codes() ? ' (' . esc_html( strtoupper( implode( ', ', $order->get_coupon_codes() ) ) ) . ')' : ''; ?></td><td align="right" style="padding:5px 0;color:<?php echo $p; ?>;">−<?php echo wp_kses_post( $money( $order->get_total_discount() ) ); ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $order->get_fees() as $fee ) : ?>
					<tr><td style="padding:5px 0;color:<?php echo $muted; ?>;"><?php echo esc_html( $fee->get_name() ); ?></td><td align="right" style="padding:5px 0;"><?php echo (float) $fee->get_total() > 0 ? wp_kses_post( $money( $fee->get_total() ) ) : '<span style="color:#1a9e4b;">Free</span>'; ?></td></tr>
				<?php endforeach; ?>
				<?php if ( $order->get_shipping_method() ) : ?>
					<tr><td style="padding:5px 0;color:<?php echo $muted; ?>;">Shipping (<?php echo esc_html( $order->get_shipping_method() ); ?>)</td><td align="right" style="padding:5px 0;"><?php echo (float) $order->get_shipping_total() > 0 ? wp_kses_post( $money( $order->get_shipping_total() ) ) : '<span style="color:#1a9e4b;">Free</span>'; ?></td></tr>
				<?php endif; ?>
				<?php foreach ( $order->get_tax_totals() as $tax ) : ?>
					<tr><td style="padding:5px 0;color:<?php echo $muted; ?>;"><?php echo esc_html( $tax->label ); ?></td><td align="right" style="padding:5px 0;"><?php echo wp_kses_post( $tax->formatted_amount ); ?></td></tr>
				<?php endforeach; ?>
				<tr>
					<td style="padding:12px 0 0;border-top:1px solid <?php echo $line; ?>;font-size:16px;font-weight:600;">Total</td>
					<td align="right" style="padding:12px 0 0;border-top:1px solid <?php echo $line; ?>;font-size:18px;font-weight:700;color:<?php echo $p; ?>;"><?php echo wp_kses_post( $money( $order->get_total() ) ); ?></td>
				</tr>
			</table>
		</td></tr>

		<!-- Customer / delivery -->
		<tr><td style="padding:24px 32px 0;">
			<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border:1px solid <?php echo $line; ?>;">
				<tr><td style="padding:16px 20px;font-size:13px;line-height:1.7;">
					<span style="display:block;margin-bottom:4px;font-size:11px;font-weight:600;letter-spacing:1px;text-transform:uppercase;color:<?php echo $p; ?>;">Delivery Address</span>
					<strong style="font-weight:600;"><?php echo esc_html( $order->get_formatted_billing_full_name() ); ?></strong><br>
					<?php if ( $address ) : ?><span style="color:<?php echo $muted; ?>;"><?php echo esc_html( $address ); ?></span><br><?php endif; ?>
					<?php if ( $order->get_billing_phone() ) : ?><span style="color:<?php echo $muted; ?>;"><?php echo esc_html( $order->get_billing_phone() ); ?></span><br><?php endif; ?>
					<?php if ( $sent_to_admin && $order->get_billing_email() ) : ?><span style="color:<?php echo $muted; ?>;"><?php echo esc_html( $order->get_billing_email() ); ?></span><br><?php endif; ?>
					<?php if ( $order->get_customer_note() ) : ?><span style="display:block;margin-top:8px;color:<?php echo $text; ?>;"><strong>Order note:</strong> <?php echo esc_html( $order->get_customer_note() ); ?></span><?php endif; ?>
				</td></tr>
			</table>
		</td></tr>

		<!-- Buttons -->
		<tr><td align="center" style="padding:26px 32px 8px;">
			<?php if ( $sent_to_admin ) : ?>
				<a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>" style="display:inline-block;padding:14px 28px;margin:0 4px 8px;background:<?php echo $p; ?>;color:#ffffff;text-decoration:none;font-size:13px;font-weight:600;letter-spacing:.5px;text-transform:uppercase;">View Order</a>
			<?php endif; ?>
			<?php if ( $invoice ) : ?>
				<a href="<?php echo esc_url( $invoice ); ?>" style="display:inline-block;padding:13px 28px;margin:0 4px 8px;<?php echo $sent_to_admin ? 'background:#ffffff;color:' . $p . ';border:1.5px solid ' . $p : 'background:' . $p . ';color:#ffffff;border:1.5px solid ' . $p; ?>;text-decoration:none;font-size:13px;font-weight:600;letter-spacing:.5px;text-transform:uppercase;">Download Invoice</a>
			<?php endif; ?>
		</td></tr>

		<!-- Footer -->
		<tr><td align="center" style="padding:18px 32px 26px;font-size:12px;line-height:1.7;color:<?php echo $muted; ?>;">
			<?php
			$bits = array_filter( [ NVX_Settings::get( 'support_phone' ), NVX_Settings::get( 'support_email' ), NVX_Settings::get( 'invoice_website' ) ] );
			if ( $bits ) {
				echo esc_html( implode( '  ·  ', $bits ) ) . '<br>';
			}
			?>
			© <?php echo esc_html( gmdate( 'Y' ) . ' ' . $store ); ?>
		</td></tr>
	</table>

</td></tr>
</table>
</body>
</html>
