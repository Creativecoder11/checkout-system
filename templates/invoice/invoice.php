<?php
/**
 * Printable A4 invoice (standalone HTML document).
 * Copy to your-child-theme/neuvix-checkout/invoice.php to customise.
 *
 * @var WC_Order $order
 * @var bool     $autoprint
 */

defined( 'ABSPATH' ) || exit;

$store     = get_bloginfo( 'name' );
$currency  = [ 'currency' => $order->get_currency() ];
$number    = NVX_Invoice::number( $order );
$logo      = NVX_Invoice::logo_url();
$paid      = NVX_Invoice::is_paid( $order );
[ $badge, $badge_class ] = NVX_Invoice::badge( $order );

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
	'pin'   => NVX_Settings::get( 'invoice_address' ),
	'phone' => NVX_Settings::get( 'support_phone' ),
	'mail'  => NVX_Settings::get( 'support_email' ),
	'web'   => NVX_Settings::get( 'invoice_website' ),
] );

$icons = [
	'pin'   => '<path d="M12 22s7-7 7-12a7 7 0 0 0-14 0c0 5 7 12 7 12z"/><circle cx="12" cy="10" r="2.5"/>',
	'phone' => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/>',
	'mail'  => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
	'web'   => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
];

$fmt = function ( $date, $format ) {
	return $date ? wc_format_datetime( $date, $format ) : '';
};
$money = function ( $amount ) use ( $currency ) {
	return wp_kses_post( wc_price( $amount, $currency ) );
};
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?php echo esc_html( 'Invoice-' . $number ); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Belleza&family=Urbanist:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
	:root {
		--p: #b02a3a; --p-dark: #97202f; --p-soft: #f8e9ec;
		--green: #1a9e4b; --green-soft: #e3f4e8; --amber: #b7791f; --amber-soft: #fdf3e1;
		--text: #1f2430; --muted: #6b7080; --line: #e3e5e8; --panel: #f5f6f8;
	}
	* { box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
	html, body { margin: 0; padding: 0; }
	body { background: #e9eaee; font-family: "Urbanist", -apple-system, "Segoe UI", Arial, sans-serif; color: var(--text); font-size: 12px; line-height: 1.45; }

	/* Toolbar (screen only) */
	.bar { position: sticky; top: 0; z-index: 5; display: flex; justify-content: center; gap: 10px; padding: 12px 16px; background: rgba(255,255,255,.92); backdrop-filter: blur(6px); border-bottom: 1px solid var(--line); }
	.bar a, .bar button { display: inline-flex; align-items: center; gap: 8px; height: 40px; padding: 0 18px; border: 1.5px solid var(--p); background: #fff; color: var(--p); font: 600 13px/1 "Urbanist", sans-serif; letter-spacing: .4px; text-transform: uppercase; text-decoration: none; cursor: pointer; }
	.bar button { background: var(--p); color: #fff; }
	.bar svg { width: 16px; height: 16px; }

	/* A4 sheet */
	.sheet { position: relative; width: 210mm; min-height: 297mm; margin: 24px auto; background: #fff; box-shadow: 0 12px 40px rgba(16,24,40,.12); display: flex; flex-direction: column; overflow: hidden; }
	.sheet::before { content: ""; display: block; height: 6px; background: var(--p); flex: none; }
	.content { padding: 8mm 12mm 0; }

	.head { display: flex; justify-content: space-between; align-items: flex-start; gap: 24px; margin-bottom: 6mm; }
	.brand img { display: block; max-height: 40px; max-width: 200px; width: auto; }
	.brand .name { font: 700 26px/1 "Urbanist", sans-serif; color: var(--p); }
	.brand p { margin: 10px 0 0; max-width: 250px; color: var(--muted); font-size: 11px; }
	.title { text-align: right; }
	.title h1 { margin: 0 0 8px; font: 400 36px/1 "Belleza", "Urbanist", sans-serif; letter-spacing: .3px; }
	.title .no { margin: 0 0 10px; color: var(--muted); font-size: 11px; }
	.title .no strong { margin-left: 6px; color: var(--p); font-size: 13px; font-weight: 700; }
	.badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 999px; font-size: 9.5px; font-weight: 700; letter-spacing: .8px; text-transform: uppercase; }
	.badge::before { content: ""; width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
	.badge.is-paid { background: var(--green-soft); color: var(--green); }
	.badge.is-due { background: var(--amber-soft); color: var(--amber); }
	.badge.is-muted { background: var(--panel); color: var(--muted); }

	.meta { display: grid; grid-template-columns: repeat(4, 1fr); background: var(--panel); padding: 4mm 0; margin-bottom: 6mm; }
	.meta div { padding: 0 5mm; border-left: 1px solid #d7d9de; }
	.meta div:first-child { border-left: 0; }
	.meta small { display: block; color: var(--muted); font-size: 9.5px; margin-bottom: 5px; }
	.meta strong { font-size: 12.5px; font-weight: 500; }

	.parties { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8mm; margin-bottom: 6mm; }
	.parties > div { border-left: 2px solid var(--p); padding: 1px 0 1px 4mm; }
	.parties h3 { margin: 0 0 9px; color: var(--p); font-size: 9.5px; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; }
	.parties strong { display: block; margin-bottom: 5px; font-size: 13px; font-weight: 500; }
	.parties span { display: block; color: var(--muted); font-size: 11px; line-height: 1.7; word-break: break-word; }

	table.items { width: 100%; border-collapse: collapse; margin-bottom: 6mm; }
	.items thead th { background: var(--p); color: #fff; padding: 9px 12px; font-size: 9.5px; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; text-align: left; }
	.items td { padding: 7px 12px; border-bottom: 1px solid var(--line); vertical-align: middle; font-size: 11.5px; }
	.items tr { break-inside: avoid; page-break-inside: avoid; }
	.items .c-no { width: 44px; }
	.items td.c-no { color: var(--muted); }
	.items .c-qty { width: 60px; text-align: center; }
	.items .c-unit, .items .c-amt { width: 100px; text-align: right; white-space: nowrap; }
	.items td.c-amt { font-weight: 500; }
	.prod { display: flex; align-items: center; gap: 12px; }
	.prod img { width: 36px; height: 36px; object-fit: contain; border: 1px solid var(--line); padding: 2px; flex: none; background: #fff; }
	.prod b { display: block; font-weight: 500; font-size: 11.5px; }
	.prod small { display: block; color: var(--muted); font-size: 9.5px; margin-top: 3px; }

	.end { flex: 1 0 auto; display: flex; flex-direction: column; break-inside: avoid; page-break-inside: avoid; }
	.bottom { display: grid; grid-template-columns: 1fr 310px; gap: 10mm; align-items: start; padding: 0 12mm; margin-bottom: 6mm; }
	.panel { background: var(--panel); padding: 4mm 5mm; margin-bottom: 5mm; }
	.panel h3 { margin: 0 0 8px; font-size: 13px; font-weight: 500; }
	.kv { display: flex; justify-content: space-between; gap: 12px; padding: 3px 0; font-size: 11px; }
	.kv span:first-child { color: var(--muted); }
	.kv span:last-child { text-align: right; word-break: break-word; }
	.notes h4 { margin: 0 0 6px; color: var(--p); font-size: 9.5px; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; }
	.notes p { margin: 0 0 8px; color: var(--muted); font-size: 10.5px; line-height: 1.6; }
	.totals .kv { padding: 4px 0; }
	.totals .kv span:last-child { color: var(--text); }
	.totals .is-minus span:last-child { color: var(--p); }
	.totals .is-free span:last-child { color: var(--green); }
	.grand { display: flex; justify-content: space-between; align-items: baseline; border-top: 1px solid var(--line); margin-top: 6px; padding: 10px 0; }
	.grand span:first-child { font-size: 16px; font-weight: 600; }
	.grand span:last-child { font-size: 20px; font-weight: 700; color: var(--p); }
	.paidbar { display: flex; justify-content: space-between; align-items: center; background: var(--p); color: #fff; padding: 10px 16px; margin-bottom: 6px; }
	.paidbar span:first-child { font-size: 10.5px; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; }
	.paidbar span:last-child { font-size: 15px; font-weight: 700; }
	.totals .woocommerce-Price-amount, .paidbar .woocommerce-Price-amount { color: inherit; }

	/* Footer */
	.foot { margin-top: auto; break-inside: avoid; page-break-inside: avoid; }
	.thanks { position: relative; overflow: hidden; background: var(--p); color: #fff; text-align: center; padding: 6mm 12mm; }
	.thanks h2 { margin: 0 0 5px; font: 400 24px/1.2 "Belleza", "Urbanist", sans-serif; }
	.thanks p { margin: 0; font-size: 11.5px; opacity: .92; }
	.thanks svg { position: absolute; right: -10px; top: -8px; width: 190px; height: 130px; opacity: .22; }
	.contact { background: var(--p-soft); padding: 4mm 12mm 3.5mm; }
	.contact ul { display: flex; justify-content: space-between; flex-wrap: wrap; gap: 6px 18px; margin: 0; padding: 0 0 3mm; list-style: none; border-bottom: 1px solid #ecc9cf; }
	.contact li { display: flex; align-items: center; gap: 7px; font-size: 10.5px; }
	.contact li svg { width: 13px; height: 13px; color: var(--p); flex: none; }
	.legal { display: flex; justify-content: space-between; gap: 12px; padding-top: 3mm; color: var(--muted); font-size: 9px; }

	@page { size: A4; margin: 12mm 0; }
	@page :first { margin: 0; }
	@media print {
		body { background: #fff; }
		.bar { display: none !important; }
		.sheet { margin: 0; box-shadow: none; width: 210mm; min-height: 296mm; overflow: visible; }
	}
	/* Small screens: scale the A4 sheet down instead of reflowing it */
	@media screen and (max-width: 820px) {
		.sheet { margin: 12px auto; zoom: calc(100vw / 840px); }
	}
</style>
</head>
<body>

<div class="bar">
	<a href="javascript:history.length > 1 ? history.back() : window.close()">← Back</a>
	<button type="button" onclick="window.print()">
		<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 4v11M7 10l5 5 5-5M5 20h14"/></svg>
		Download PDF
	</button>
</div>

<main class="sheet">
	<div class="content">

		<header class="head">
			<div class="brand">
				<?php if ( $logo ) : ?>
					<img src="<?php echo esc_url( $logo ); ?>" alt="<?php echo esc_attr( $store ); ?>">
				<?php else : ?>
					<div class="name"><?php echo esc_html( $store ); ?></div>
				<?php endif; ?>
				<?php if ( $tagline = NVX_Invoice::text( 'invoice_tagline' ) ) : ?><p><?php echo esc_html( $tagline ); ?></p><?php endif; ?>
			</div>
			<div class="title">
				<h1>Invoice</h1>
				<p class="no">Invoice No.<strong>#<?php echo esc_html( $number ); ?></strong></p>
				<span class="badge <?php echo esc_attr( $badge_class ); ?>"><?php echo esc_html( $badge ); ?></span>
			</div>
		</header>

		<section class="meta">
			<div><small>Invoice Date</small><strong><?php echo esc_html( $fmt( $created, 'j M Y' ) ); ?></strong></div>
			<div><small>Order ID</small><strong>#<?php echo esc_html( $order->get_order_number() ); ?></strong></div>
			<div><small>Order Date</small><strong><?php echo esc_html( $fmt( $created, 'j M Y, h:i A' ) ); ?></strong></div>
			<div><small>Payment Status</small><strong><?php echo esc_html( NVX_Invoice::payment_status( $order ) ); ?></strong></div>
		</section>

		<section class="parties">
			<div>
				<h3>Shipped To</h3>
				<strong><?php echo esc_html( $name ); ?></strong>
				<?php foreach ( $ship_lines as $line ) : ?><span><?php echo esc_html( $line ); ?></span><?php endforeach; ?>
				<?php if ( $order->get_billing_phone() ) : ?><span><?php echo esc_html( $order->get_billing_phone() ); ?></span><?php endif; ?>
			</div>
			<div>
				<h3>Delivery</h3>
				<strong><?php echo esc_html( $order->get_shipping_method() ?: '—' ); ?></strong>
				<?php if ( $packaging ) : ?><span>Packaging: <?php echo esc_html( $packaging ); ?></span><?php endif; ?>
				<?php if ( $eta ) : ?><span>Est. delivery: <?php echo esc_html( $eta ); ?></span><?php endif; ?>
			</div>
		</section>

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
					$img     = $product && $product->get_image_id() ? wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ) : wc_placeholder_img_src( 'thumbnail' );
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
							<div class="prod">
								<img src="<?php echo esc_url( $img ); ?>" alt="">
								<div>
									<b><?php echo esc_html( $item->get_name() ); ?></b>
									<?php if ( $meta ) : ?><small><?php echo esc_html( implode( ' · ', $meta ) ); ?></small><?php endif; ?>
								</div>
							</div>
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
		<section class="bottom">
			<div>
				<div class="panel">
					<h3>Payment Information</h3>
					<div class="kv"><span>Payment Method</span><span><?php echo esc_html( $order->get_payment_method_title() ?: '—' ); ?></span></div>
					<?php if ( $order->get_transaction_id() ) : ?>
						<div class="kv"><span>Transaction ID</span><span><?php echo esc_html( $order->get_transaction_id() ); ?></span></div>
					<?php endif; ?>
					<?php if ( $paid && ( $date_paid || $order->get_date_completed() ) ) : ?>
						<div class="kv"><span>Paid On</span><span><?php echo esc_html( $fmt( $date_paid ?: $order->get_date_completed(), 'j M Y, h:i A' ) ); ?></span></div>
					<?php endif; ?>
					<?php if ( $coupons ) : ?>
						<div class="kv"><span>Promo Code</span><span><?php echo esc_html( strtoupper( implode( ', ', $coupons ) ) ); ?></span></div>
					<?php endif; ?>
				</div>
				<?php if ( ( $notes = NVX_Invoice::text( 'invoice_notes' ) ) || $order->get_customer_note() ) : ?>
					<div class="notes">
						<h4>Notes</h4>
						<?php if ( $order->get_customer_note() ) : ?><p><strong>Order note:</strong> <?php echo esc_html( $order->get_customer_note() ); ?></p><?php endif; ?>
						<?php if ( $notes ) : ?><p><?php echo esc_html( $notes ); ?></p><?php endif; ?>
					</div>
				<?php endif; ?>
			</div>

			<div class="totals">
				<div class="kv"><span>Subtotal (<?php echo esc_html( $order->get_item_count() ); ?> <?php echo 1 === (int) $order->get_item_count() ? 'item' : 'items'; ?>)</span><span><?php echo $money( $order->get_subtotal() ); // phpcs:ignore ?></span></div>
				<?php if ( (float) $order->get_total_discount() > 0 ) : ?>
					<div class="kv is-minus"><span>Discount<?php echo $coupons ? ' (' . esc_html( strtoupper( implode( ', ', $coupons ) ) ) . ')' : ''; ?></span><span>−<?php echo $money( $order->get_total_discount() ); // phpcs:ignore ?></span></div>
				<?php endif; ?>
				<?php foreach ( $order->get_fees() as $fee ) : $fee_total = (float) $fee->get_total(); ?>
					<div class="kv <?php echo $fee_total > 0 ? '' : 'is-free'; ?>"><span><?php echo esc_html( $fee->get_name() ); ?></span><span><?php echo $fee_total > 0 ? $money( $fee_total ) : 'Free'; // phpcs:ignore ?></span></div>
				<?php endforeach; ?>
				<?php if ( $order->get_shipping_method() ) : ?>
					<div class="kv <?php echo (float) $order->get_shipping_total() > 0 ? '' : 'is-free'; ?>"><span>Shipping (<?php echo esc_html( $order->get_shipping_method() ); ?>)</span><span><?php echo (float) $order->get_shipping_total() > 0 ? $money( $order->get_shipping_total() ) : 'Free'; // phpcs:ignore ?></span></div>
				<?php endif; ?>
				<?php foreach ( $order->get_tax_totals() as $tax ) : ?>
					<div class="kv"><span><?php echo esc_html( $tax->label ); ?></span><span><?php echo wp_kses_post( $tax->formatted_amount ); ?></span></div>
				<?php endforeach; ?>

				<div class="grand"><span>Total</span><span><?php echo $money( $total ); // phpcs:ignore ?></span></div>

				<?php if ( $refunded > 0 ) : ?>
					<div class="kv is-minus"><span>Refunded</span><span>−<?php echo $money( $refunded ); // phpcs:ignore ?></span></div>
				<?php endif; ?>
				<div class="paidbar"><span>Amount Paid</span><span><?php echo $money( $amount_paid ); // phpcs:ignore ?></span></div>
				<div class="kv"><span>Balance Due</span><span><?php echo $money( $balance ); // phpcs:ignore ?></span></div>
			</div>
		</section>

	<footer class="foot">
		<div class="thanks">
			<svg viewBox="0 0 190 130" fill="none" stroke="#fff" stroke-width="1.2" aria-hidden="true">
				<path d="M40 120C40 70 80 30 140 20c-5 55-45 95-100 100z"/><path d="M40 120C70 90 100 60 140 20"/>
				<path d="M120 135c0-40 25-70 70-80-3 40-30 70-70 80z"/><path d="M120 135c20-25 40-50 70-80"/>
				<path d="M95 15c15-10 35-12 50-5-12 12-32 16-50 5z"/>
			</svg>
			<h2><?php echo esc_html( NVX_Invoice::text( 'invoice_thanks_title' ) ); ?></h2>
			<?php if ( $sub = NVX_Invoice::text( 'invoice_thanks_text' ) ) : ?><p><?php echo esc_html( $sub ); ?></p><?php endif; ?>
		</div>
		<div class="contact">
			<?php if ( $contact ) : ?>
				<ul>
					<?php foreach ( $contact as $k => $v ) : ?>
						<li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><?php echo $icons[ $k ]; // phpcs:ignore ?></svg><?php echo esc_html( $v ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
			<div class="legal">
				<span>This is a computer-generated invoice and does not require a signature.</span>
				<span>© <?php echo esc_html( gmdate( 'Y' ) . ' ' . $store ); ?>. All rights reserved.</span>
			</div>
		</div>
	</footer>
	</div>
</main>

<?php if ( $autoprint ) : ?>
<script>
(function () {
	var go = function () { setTimeout(function () { window.print(); }, 300); };
	var imgs = Array.prototype.slice.call(document.images).map(function (img) {
		return img.complete ? Promise.resolve() : new Promise(function (r) { img.onload = img.onerror = r; });
	});
	Promise.all([document.fonts ? document.fonts.ready : Promise.resolve()].concat(imgs)).then(go, go);
})();
</script>
<?php endif; ?>
</body>
</html>
