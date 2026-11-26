<?php
/**
 * A4 invoice for PDF (rendered by dompdf, attached to the admin order email).
 * Same content and look as templates/invoice/invoice.php, written for dompdf:
 * tables instead of flex/grid, colours as PHP values instead of CSS variables,
 * local image files only, and the DejaVu Sans font that ships with dompdf.
 * Copy to your-child-theme/neuvix-checkout/invoice-pdf.php to customise.
 *
 * @var WC_Order $order
 */

defined( 'ABSPATH' ) || exit;

// Invoice colours — the same values as the :root tokens in invoice.php.
$c = [
	'p'          => '#b02a3a',
	'p_soft'     => '#f8e9ec',
	'p_line'     => '#ecc9cf',
	'green'      => '#1a9e4b',
	'green_soft' => '#e3f4e8',
	'amber'      => '#b7791f',
	'amber_soft' => '#fdf3e1',
	'text'       => '#1f2430',
	'muted'      => '#6b7080',
	'line'       => '#e3e5e8',
	'panel'      => '#f5f6f8',
	'divider'    => '#d7d9de',
];

$store     = get_bloginfo( 'name' );
$currency  = [ 'currency' => $order->get_currency() ];
$number    = NVX_Invoice::number( $order );
$logo      = NVX_Invoice::pdf_logo();
$paid      = NVX_Invoice::is_paid( $order );
[ $badge, $badge_class ] = NVX_Invoice::badge( $order );
$badge_colors = [
	'is-paid'  => [ $c['green_soft'], $c['green'] ],
	'is-due'   => [ $c['amber_soft'], $c['amber'] ],
	'is-muted' => [ $c['panel'], $c['muted'] ],
];
[ $badge_bg, $badge_fg ] = $badge_colors[ $badge_class ] ?? $badge_colors['is-muted'];

$created   = $order->get_date_created();
$date_paid = $order->get_date_paid();
$district  = (string) $order->get_meta( '_nvx_district' );
$area      = (string) ( $order->get_meta( '_nvx_area' ) ?: $order->get_billing_city() );
$packaging = (string) $order->get_meta( '_nvx_packaging' );
$name      = trim( $order->get_formatted_billing_full_name() );
$countries = WC()->countries->get_countries();
$country   = $countries[ $order->get_billing_country() ] ?? '';
$eta       = $order->has_status( [ 'completed', 'refunded' ] ) ? '' : NVX_Payment_Result::eta( $order );
$coupons   = $order->get_coupon_codes();
$refunded  = (float) $order->get_total_refunded();
$total     = (float) $order->get_total();
$amount_paid = $paid ? max( 0, $total - $refunded ) : 0;
$balance   = $paid || $order->has_status( 'refunded' ) ? 0 : $total;

$ship_lines = array_filter( [
	trim( $order->get_billing_address_1() . ( $order->get_billing_address_2() ? ', ' . $order->get_billing_address_2() : '' ) ),
	implode( ', ', array_unique( array_filter( [ $area, $district ] ) ) ) . ( $country ? ', ' . $country : '' ),
] );

$contact = array_filter( [
	NVX_Settings::get( 'invoice_address' ),
	NVX_Settings::get( 'support_phone' ),
	NVX_Settings::get( 'support_email' ),
	NVX_Settings::get( 'invoice_website' ),
] );

$fmt = function ( $date, $format ) {
	return $date ? wc_format_datetime( $date, $format ) : '';
};
$money = function ( $amount ) use ( $currency ) {
	return wp_kses_post( wc_price( $amount, $currency ) );
};
?><!doctype html>
<html>
<head>
<meta charset="utf-8">
<title><?php echo esc_html( 'Invoice-' . $number ); ?></title>
<style>
	@page { size: A4; margin: 12mm 0; }
	@page :first { margin: 0; }
	/* No margin on html: dompdf applies the @page margins there (pages 2+ keep 12mm). */
	body { margin: 0; padding: 0; }
	body { font-family: "DejaVu Sans", sans-serif; color: <?php echo $c['text']; ?>; font-size: 11px; line-height: 1.45; }
	table { border-collapse: collapse; width: 100%; }
	td, th { vertical-align: top; padding: 0; }

	.topbar { height: 6px; background: <?php echo $c['p']; ?>; }
	.content { padding: 8mm 12mm 0; }

	.brand img { display: block; } /* drawn at the size pdf_logo() fits into 200 × 40 px */
	.brand .name { font-size: 24px; font-weight: bold; line-height: 1; color: <?php echo $c['p']; ?>; }
	.brand p { margin: 10px 0 0; width: 250px; color: <?php echo $c['muted']; ?>; font-size: 10px; }
	.title { text-align: right; }
	.title h1 { margin: 0 0 8px; font-size: 32px; font-weight: normal; line-height: 1; letter-spacing: .3px; }
	.title .no { margin: 0 0 10px; color: <?php echo $c['muted']; ?>; font-size: 10px; }
	.title .no strong { margin-left: 6px; color: <?php echo $c['p']; ?>; font-size: 12px; font-weight: bold; }
	.badge { display: inline-block; padding: 4px 12px; border-radius: 10px; font-size: 8.5px; font-weight: bold; letter-spacing: .8px; text-transform: uppercase; background: <?php echo $badge_bg; ?>; color: <?php echo $badge_fg; ?>; }
	.badge .dot { display: inline-block; width: 6px; height: 6px; margin: 0 6px 1px 0; border-radius: 3px; background: <?php echo $badge_fg; ?>; }

	.meta { background: <?php echo $c['panel']; ?>; margin: 6mm 0; }
	.meta td { width: 25%; padding: 4mm 5mm; border-left: 1px solid <?php echo $c['divider']; ?>; }
	.meta td.first { border-left: 0; }
	.meta small { display: block; color: <?php echo $c['muted']; ?>; font-size: 8.5px; margin-bottom: 5px; }
	.meta strong { font-size: 11.5px; font-weight: normal; }

	/* Columns are fixed-width floats: dompdf only wraps long words (links, IDs) inside a fixed width.
	   A4 at 96dpi is 794px; minus 2 × 12mm side padding leaves 703px of content. */
	.clear { clear: both; height: 0; }
	.parties { margin-bottom: 6mm; }
	.parties .col { width: 336px; } /* (703px − 8mm gap) / 2 */
	.parties .left { float: left; }
	.parties .right { float: right; }
	.party { border-left: 2px solid <?php echo $c['p']; ?>; padding: 1px 0 1px 4mm; }
	.party h3 { margin: 0 0 9px; color: <?php echo $c['p']; ?>; font-size: 8.5px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; }
	.party strong { display: block; margin-bottom: 5px; font-size: 12px; font-weight: normal; }
	.party span { display: block; color: <?php echo $c['muted']; ?>; font-size: 10px; line-height: 1.7; word-wrap: break-word; }

	table.items { margin-bottom: 6mm; }
	.items th { background: <?php echo $c['p']; ?>; color: #fff; padding: 9px 12px; font-size: 8.5px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; text-align: left; }
	.items td { padding: 7px 12px; border-bottom: 1px solid <?php echo $c['line']; ?>; vertical-align: middle; font-size: 10.5px; }
	.items tr { page-break-inside: avoid; }
	.items .c-no { width: 44px; }
	.items td.c-no { color: <?php echo $c['muted']; ?>; }
	.items .c-qty { width: 50px; text-align: center; }
	.items .c-unit, .items .c-amt { width: 95px; text-align: right; white-space: nowrap; }
	.items td.c-amt { font-weight: normal; }
	.prod td { border: 0; padding: 0; vertical-align: middle; }
	.prod td.img { width: 48px; }
	.prod img { width: 36px; height: 36px; border: 1px solid <?php echo $c['line']; ?>; padding: 2px; background: #fff; }
	.prod .noimg { width: 40px; height: 40px; border: 1px solid <?php echo $c['line']; ?>; background: <?php echo $c['panel']; ?>; }
	/* Fixed width so a long name or meta value without spaces wraps instead of pushing the price columns off the page. */
	.prod .txt { width: 240px; word-wrap: break-word; }
	.prod b { display: block; font-weight: normal; font-size: 10.5px; }
	.prod small { display: block; color: <?php echo $c['muted']; ?>; font-size: 8.5px; margin-top: 3px; }

	.end { page-break-inside: avoid; }
	.bottom { margin: 0 12mm 6mm; }
	.bottom .left { float: left; width: 355px; } /* 703px − 310px − 10mm gap */
	.bottom .right { float: right; width: 310px; } /* the reference's totals column */
	.panel { background: <?php echo $c['panel']; ?>; padding: 4mm 5mm; margin-bottom: 5mm; }
	.panel h3 { margin: 0 0 8px; font-size: 12px; font-weight: normal; }
	.kv td { padding: 3px 0; font-size: 10px; word-wrap: break-word; }
	.kv td.k { color: <?php echo $c['muted']; ?>; white-space: nowrap; padding-right: 12px; }
	.totals td.k { white-space: normal; }
	.kv td.v { text-align: right; }
	.panel .kv { table-layout: fixed; } /* fixed so a long Transaction ID wraps */
	.panel .kv td.k { width: 110px; }
	.totals { table-layout: fixed; }
	.totals td.v { width: 95px; }
	.notes h4 { margin: 0 0 6px; color: <?php echo $c['p']; ?>; font-size: 8.5px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; }
	.notes p { margin: 0 0 8px; color: <?php echo $c['muted']; ?>; font-size: 9.5px; line-height: 1.6; word-wrap: break-word; }
	.totals td { padding: 4px 0; }
	.totals td.v { color: <?php echo $c['text']; ?>; }
	.totals tr.is-minus td.v { color: <?php echo $c['p']; ?>; }
	.totals tr.is-free td.v { color: <?php echo $c['green']; ?>; }
	.grand td { border-top: 1px solid <?php echo $c['line']; ?>; padding: 10px 0; }
	.grand td.k { font-size: 14px; font-weight: bold; color: <?php echo $c['text']; ?>; }
	.grand td.v { font-size: 18px; font-weight: bold; color: <?php echo $c['p']; ?>; text-align: right; }
	.paidbar td { background: <?php echo $c['p']; ?>; color: #fff; padding: 10px 16px; }
	.paidbar td.k { font-size: 9.5px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; }
	.paidbar td.v { font-size: 13px; font-weight: bold; text-align: right; }
	.paidbar-gap { height: 6px; }
	.woocommerce-Price-amount { color: inherit; }

	.thanks { background: <?php echo $c['p']; ?>; color: #fff; text-align: center; padding: 6mm 12mm; }
	.thanks h2 { margin: 0 0 5px; font-size: 21px; font-weight: normal; line-height: 1.2; }
	.thanks p { margin: 0; font-size: 10.5px; opacity: .92; }
	.contact { background: <?php echo $c['p_soft']; ?>; padding: 4mm 12mm 3.5mm; }
	.contact .list td { padding: 0 0 3mm; font-size: 9.5px; border-bottom: 1px solid <?php echo $c['p_line']; ?>; }
	.contact .legal td { padding-top: 3mm; color: <?php echo $c['muted']; ?>; font-size: 8px; }
</style>
</head>
<body>

<div class="topbar"></div>
<div class="content">

	<table class="head">
		<tr>
			<td class="brand">
				<?php if ( $logo ) : ?>
					<img src="<?php echo esc_attr( $logo['path'] ); ?>" alt="<?php echo esc_attr( $store ); ?>" style="width:<?php echo esc_attr( $logo['width'] ); ?>px;height:<?php echo esc_attr( $logo['height'] ); ?>px">
				<?php else : ?>
					<div class="name"><?php echo esc_html( $store ); ?></div>
				<?php endif; ?>
				<?php if ( $tagline = NVX_Invoice::text( 'invoice_tagline' ) ) : ?><p><?php echo esc_html( $tagline ); ?></p><?php endif; ?>
			</td>
			<td class="title">
				<h1>Invoice</h1>
				<p class="no">Invoice No.<strong>#<?php echo esc_html( $number ); ?></strong></p>
				<span class="badge"><span class="dot"></span><?php echo esc_html( $badge ); ?></span>
			</td>
		</tr>
	</table>

	<table class="meta">
		<tr>
			<td class="first"><small>Invoice Date</small><strong><?php echo esc_html( $fmt( $created, 'j M Y' ) ); ?></strong></td>
			<td><small>Order ID</small><strong>#<?php echo esc_html( $order->get_order_number() ); ?></strong></td>
			<td><small>Order Date</small><strong><?php echo esc_html( $fmt( $created, 'j M Y, h:i A' ) ); ?></strong></td>
			<td><small>Payment Status</small><strong><?php echo esc_html( NVX_Invoice::payment_status( $order ) ); ?></strong></td>
		</tr>
	</table>

	<div class="parties">
		<div class="col left">
				<div class="party">
					<h3>Shipped To</h3>
					<strong><?php echo esc_html( $name ); ?></strong>
					<?php foreach ( $ship_lines as $line ) : ?><span><?php echo esc_html( $line ); ?></span><?php endforeach; ?>
					<?php if ( $order->get_billing_phone() ) : ?><span><?php echo esc_html( $order->get_billing_phone() ); ?></span><?php endif; ?>
				</div>
		</div>
		<div class="col right">
				<div class="party">
					<h3>Delivery</h3>
					<strong><?php echo esc_html( $order->get_shipping_method() ?: '—' ); ?></strong>
					<?php if ( $packaging ) : ?><span>Packaging: <?php echo esc_html( $packaging ); ?></span><?php endif; ?>
					<?php if ( $eta ) : ?><span>Est. delivery: <?php echo esc_html( $eta ); ?></span><?php endif; ?>
				</div>
		</div>
		<div class="clear"></div>
	</div>

	<table class="items">
		<thead>
			<tr><th class="c-no">#</th><th>Product</th><th class="c-qty">Qty</th><th class="c-unit">Unit Price</th><th class="c-amt">Amount</th></tr>
		</thead>
		<tbody>
			<?php
			$i = 0;
			foreach ( $order->get_items() as $item ) :
				$i++;
				$product = $item->get_product();
				$img     = NVX_Invoice::pdf_product_image( $product );
				$meta    = [];
				foreach ( $item->get_formatted_meta_data( '_', true ) as $m ) {
					$meta[] = wp_strip_all_tags( $m->display_value );
				}
				$qty  = max( 1, (int) $item->get_quantity() );
				$line = (float) $item->get_subtotal();
				?>
				<tr>
					<td class="c-no"><?php echo esc_html( str_pad( (string) $i, 2, '0', STR_PAD_LEFT ) ); ?></td>
					<td>
						<table class="prod"><tr>
							<td class="img"><?php if ( $img ) : ?><img src="<?php echo esc_attr( $img ); ?>" alt=""><?php else : ?><div class="noimg"></div><?php endif; ?></td>
							<td><div class="txt">
								<b><?php echo esc_html( $item->get_name() ); ?></b>
								<?php if ( $meta ) : ?><small><?php echo esc_html( implode( ' · ', $meta ) ); ?></small><?php endif; ?>
							</div></td>
						</tr></table>
					</td>
					<td class="c-qty"><?php echo esc_html( $qty ); ?></td>
					<td class="c-unit"><?php echo $money( $line / $qty ); // phpcs:ignore ?></td>
					<td class="c-amt"><?php echo $money( $line ); // phpcs:ignore ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>

<!-- Totals + footer stay together: if they don't fit, both move to the next page. -->
<div class="end">
	<div class="bottom">
		<div class="left">
				<div class="panel">
					<h3>Payment Information</h3>
					<table class="kv">
						<tr><td class="k">Payment Method</td><td class="v"><?php echo esc_html( $order->get_payment_method_title() ?: '—' ); ?></td></tr>
						<?php if ( $order->get_transaction_id() ) : ?>
							<tr><td class="k">Transaction ID</td><td class="v"><?php echo esc_html( $order->get_transaction_id() ); ?></td></tr>
						<?php endif; ?>
						<?php if ( $paid && ( $date_paid || $order->get_date_completed() ) ) : ?>
							<tr><td class="k">Paid On</td><td class="v"><?php echo esc_html( $fmt( $date_paid ?: $order->get_date_completed(), 'j M Y, h:i A' ) ); ?></td></tr>
						<?php endif; ?>
						<?php if ( $coupons ) : ?>
							<tr><td class="k">Promo Code</td><td class="v"><?php echo esc_html( strtoupper( implode( ', ', $coupons ) ) ); ?></td></tr>
						<?php endif; ?>
					</table>
				</div>
				<?php if ( ( $notes = NVX_Invoice::text( 'invoice_notes' ) ) || $order->get_customer_note() ) : ?>
					<div class="notes">
						<h4>Notes</h4>
						<?php if ( $order->get_customer_note() ) : ?><p><strong>Order note:</strong> <?php echo esc_html( $order->get_customer_note() ); ?></p><?php endif; ?>
						<?php if ( $notes ) : ?><p><?php echo esc_html( $notes ); ?></p><?php endif; ?>
					</div>
				<?php endif; ?>
		</div>
		<div class="right">
				<table class="kv totals">
					<tr><td class="k">Subtotal (<?php echo esc_html( $order->get_item_count() ); ?> <?php echo 1 === (int) $order->get_item_count() ? 'item' : 'items'; ?>)</td><td class="v"><?php echo $money( $order->get_subtotal() ); // phpcs:ignore ?></td></tr>
					<?php if ( (float) $order->get_total_discount() > 0 ) : ?>
						<tr class="is-minus"><td class="k">Discount<?php echo $coupons ? ' (' . esc_html( strtoupper( implode( ', ', $coupons ) ) ) . ')' : ''; ?></td><td class="v">−<?php echo $money( $order->get_total_discount() ); // phpcs:ignore ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $order->get_fees() as $fee ) : $fee_total = (float) $fee->get_total(); ?>
						<tr class="<?php echo $fee_total > 0 ? '' : 'is-free'; ?>"><td class="k"><?php echo esc_html( $fee->get_name() ); ?></td><td class="v"><?php echo $fee_total > 0 ? $money( $fee_total ) : 'Free'; // phpcs:ignore ?></td></tr>
					<?php endforeach; ?>
					<?php if ( $order->get_shipping_method() ) : ?>
						<tr class="<?php echo (float) $order->get_shipping_total() > 0 ? '' : 'is-free'; ?>"><td class="k">Shipping (<?php echo esc_html( $order->get_shipping_method() ); ?>)</td><td class="v"><?php echo (float) $order->get_shipping_total() > 0 ? $money( $order->get_shipping_total() ) : 'Free'; // phpcs:ignore ?></td></tr>
					<?php endif; ?>
					<?php foreach ( $order->get_tax_totals() as $tax ) : ?>
						<tr><td class="k"><?php echo esc_html( $tax->label ); ?></td><td class="v"><?php echo wp_kses_post( $tax->formatted_amount ); ?></td></tr>
					<?php endforeach; ?>
				</table>

				<table class="grand"><tr><td class="k">Total</td><td class="v"><?php echo $money( $total ); // phpcs:ignore ?></td></tr></table>

				<?php if ( $refunded > 0 ) : ?>
					<table class="kv totals"><tr class="is-minus"><td class="k">Refunded</td><td class="v">−<?php echo $money( $refunded ); // phpcs:ignore ?></td></tr></table>
				<?php endif; ?>
				<table class="paidbar"><tr><td class="k">Amount Paid</td><td class="v"><?php echo $money( $amount_paid ); // phpcs:ignore ?></td></tr></table>
				<div class="paidbar-gap"></div>
				<table class="kv totals"><tr><td class="k">Balance Due</td><td class="v"><?php echo $money( $balance ); // phpcs:ignore ?></td></tr></table>
		</div>
		<div class="clear"></div>
	</div>

	<div class="thanks">
		<h2><?php echo esc_html( NVX_Invoice::text( 'invoice_thanks_title' ) ); ?></h2>
		<?php if ( $sub = NVX_Invoice::text( 'invoice_thanks_text' ) ) : ?><p><?php echo esc_html( $sub ); ?></p><?php endif; ?>
	</div>
	<div class="contact">
		<?php if ( $contact ) : ?>
			<table class="list"><tr>
				<?php
				// Spread across the width like the reference (first left, last right, the rest centred).
				$n = count( $contact );
				foreach ( array_values( $contact ) as $k => $v ) :
					$align = 0 === $k ? 'left' : ( $k === $n - 1 ? 'right' : 'center' );
					?><td style="text-align:<?php echo esc_attr( $align ); ?>"><?php echo esc_html( $v ); ?></td><?php
				endforeach;
				?>
			</tr></table>
		<?php endif; ?>
		<table class="legal"><tr>
			<td>This is a computer-generated invoice and does not require a signature.</td>
			<td style="text-align:right">© <?php echo esc_html( gmdate( 'Y' ) . ' ' . $store ); ?>. All rights reserved.</td>
		</tr></table>
	</div>
</div>

</body>
</html>
