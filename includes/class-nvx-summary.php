<?php
defined( 'ABSPATH' ) || exit;

/**
 * Order summary template + payment section tweaks.
 */
class NVX_Summary {

	public static function init() {
		add_filter( 'woocommerce_locate_template', [ __CLASS__, 'locate_template' ], 20, 3 );
		add_action( 'woocommerce_review_order_before_payment', [ __CLASS__, 'payment_title' ] );
		add_filter( 'woocommerce_order_button_text', [ __CLASS__, 'button_text' ] );
		add_filter( 'woocommerce_gateway_icon', [ __CLASS__, 'gateway_icon' ], 20, 2 );
		add_filter( 'gettext', [ __CLASS__, 'rename_steps' ], 10, 3 );
		add_action( 'init', [ __CLASS__, 'thankyou_hooks' ], 20 );
		add_action( 'init', [ __CLASS__, 'coupon_hooks' ], 20 );
		add_filter( 'body_class', [ __CLASS__, 'coupon_body_class' ] );
	}

	/** Our thank-you template renders the order itself, so drop WooCommerce's default tables. */
	public static function thankyou_hooks() {
		if ( 'yes' === NVX_Settings::get( 'thankyou_enable' ) ) {
			remove_action( 'woocommerce_thankyou', 'woocommerce_order_details_table', 10 );
		}
	}

	/* ---------- Coupon field in the Order Summary ---------- */

	public static function coupon_enabled() {
		return 'yes' === NVX_Settings::get( 'coupon_enable' ) && function_exists( 'wc_coupons_enabled' ) && wc_coupons_enabled();
	}

	/**
	 * True only when the plugin's own review-order.php renders the summary (so our field is shown).
	 * A theme copy made before v1.6 has no coupon field — keep WooCommerce's bar for it.
	 */
	protected static function replaces_default_coupon() {
		if ( ! self::coupon_enabled() ) {
			return false;
		}
		$path = function_exists( 'WC' ) ? WC()->template_path() : 'woocommerce/';
		return ! locate_template( [ trailingslashit( $path ) . 'checkout/review-order.php', 'checkout/review-order.php' ] );
	}

	/** One place to enter a code: drop WooCommerce's "Have a coupon? Click here" bar above the form. */
	public static function coupon_hooks() {
		if ( self::replaces_default_coupon() ) {
			remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10 );
		}
	}

	/** Lets the CSS also hide a coupon bar a theme (e.g. WoodMart) re-adds on its own hook. */
	public static function coupon_body_class( $classes ) {
		if ( self::replaces_default_coupon() ) {
			$classes[] = 'nvx-has-coupon';
		}
		return $classes;
	}

	/**
	 * Code field + APPLY button + applied codes. Rendered inside review-order.php so the
	 * checkout AJAX refresh redraws it. No <form> (it sits inside form.checkout) and no input
	 * name, so it is never posted with the order — checkout.js applies codes via wc-ajax.
	 */
	public static function render_coupon() {
		if ( ! self::coupon_enabled() || ! WC()->cart ) {
			return;
		}
		$applied = WC()->cart->get_applied_coupons();
		?>
		<div class="nvx-coupon">
			<label class="nvx-coupon__label" for="nvx_coupon_code"><?php echo esc_html( NVX_Settings::get( 'coupon_label' ) ?: 'Have a coupon code?' ); ?></label>
			<div class="nvx-coupon__field">
				<input type="text" id="nvx_coupon_code" class="nvx-coupon__input" placeholder="Enter coupon code" autocomplete="off" autocapitalize="characters" spellcheck="false" aria-describedby="nvx_coupon_msg">
				<button type="button" class="nvx-coupon__btn">Apply</button>
			</div>
			<p id="nvx_coupon_msg" class="nvx-coupon__msg"></p>
			<?php if ( $applied ) : ?>
				<ul class="nvx-coupon__applied" aria-label="Applied coupons">
					<?php foreach ( $applied as $code ) : ?>
						<li class="nvx-coupon__chip">
							<span><?php echo esc_html( wc_format_coupon_code( $code ) ); ?></span>
							<button type="button" class="nvx-coupon__remove" data-coupon="<?php echo esc_attr( $code ); ?>" aria-label="<?php echo esc_attr( sprintf( 'Remove coupon %s', wc_format_coupon_code( $code ) ) ); ?>">&times;</button>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Use the plugin's review-order.php unless the theme overrides it.
	 */
	public static function locate_template( $template, $template_name, $template_path ) {
		if ( 'checkout/review-order.php' === $template_name ) {
			$theme_file = locate_template( [ trailingslashit( $template_path ) . $template_name, $template_name ] );
			return $theme_file ? $theme_file : NVX_CO_PATH . 'templates/checkout/review-order.php'; // Theme override wins.
		}
		if ( 'checkout/thankyou.php' === $template_name && 'yes' === NVX_Settings::get( 'thankyou_enable' ) ) {
			$child = trailingslashit( get_stylesheet_directory() ) . trailingslashit( $template_path ) . $template_name;
			return file_exists( $child ) ? $child : NVX_CO_PATH . 'templates/checkout/thankyou.php'; // Only a child-theme override wins.
		}
		return $template;
	}

	public static function payment_title() {
		$t = NVX_Settings::get( 'payment_title' );
		if ( $t ) {
			echo '<h3 class="nvx-payment__title">' . esc_html( $t ) . '</h3>';
		}
	}

	public static function button_text( $text ) {
		return NVX_Settings::get( 'button_text' ) ?: $text;
	}

	public static function gateway_icon( $icon, $id ) {
		$map = [];
		if ( $u = NVX_Settings::get( 'icon_cod' ) ) {
			$map['cod'] = $u;
		}
		if ( $u = NVX_Settings::get( 'icon_card' ) ) {
			foreach ( array_map( 'trim', explode( ',', NVX_Settings::get( 'icon_card_ids' ) ) ) as $gid ) {
				$map[ $gid ] = $u;
			}
		}
		if ( $u = NVX_Settings::get( 'icon_bkash' ) ) {
			foreach ( array_map( 'trim', explode( ',', NVX_Settings::get( 'icon_bkash_ids' ) ) ) as $gid ) {
				$map[ $gid ] = $u;
			}
		}
		return isset( $map[ $id ] ) ? '<img class="nvx-gateway-icon" src="' . esc_url( $map[ $id ] ) . '" alt="">' : $icon;
	}

	/**
	 * WoodMart checkout steps → Cart › Delivery › Payment.
	 * Adjust source strings if your WoodMart version uses different labels.
	 */
	public static function rename_steps( $translated, $text, $domain ) {
		if ( 'woodmart' !== $domain ) {
			return $translated;
		}
		$map = apply_filters( 'nvx_checkout_step_labels', [
			'Shopping Cart'  => 'Cart',
			'Checkout'       => 'Delivery',
			'Order Complete' => 'Payment',
		] );
		return $map[ $text ] ?? $translated;
	}
}
