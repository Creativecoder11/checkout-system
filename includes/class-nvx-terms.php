<?php
defined( 'ABSPATH' ) || exit;

/**
 * "I agree to the Terms, Privacy Policy, and Return Policy." checkbox above Place Order.
 * Uses WooCommerce's native `terms` field so the checked state survives AJAX refreshes and
 * WooCommerce validates it on submit.
 */
class NVX_Terms {

	public static function init() {
		add_filter( 'woocommerce_checkout_show_terms', '__return_false' );
		add_action( 'init', [ __CLASS__, 'remove_wc_terms_output' ], 20 );
		add_action( 'woocommerce_review_order_before_submit', [ __CLASS__, 'render' ], 10 );
	}

	public static function remove_wc_terms_output() {
		remove_action( 'woocommerce_checkout_terms_and_conditions', 'wc_checkout_privacy_policy_text', 20 );
		remove_action( 'woocommerce_checkout_terms_and_conditions', 'wc_terms_and_conditions_page_content', 30 );
	}

	/** Page URLs: plugin settings first, then WooCommerce / WordPress defaults. */
	public static function links() {
		$terms_id   = (int) NVX_Settings::get( 'terms_page' ) ?: (int) wc_get_page_id( 'terms' );
		$privacy_id = (int) NVX_Settings::get( 'privacy_page' ) ?: (int) get_option( 'wp_page_for_privacy_policy' );
		$return_id  = (int) NVX_Settings::get( 'return_page' );

		return apply_filters( 'nvx_terms_links', [
			'terms'   => [ 'label' => NVX_Settings::get( 'terms_label' ), 'url' => $terms_id > 0 ? get_permalink( $terms_id ) : '' ],
			'privacy' => [ 'label' => NVX_Settings::get( 'privacy_label' ), 'url' => $privacy_id > 0 ? get_permalink( $privacy_id ) : '' ],
			'return'  => [ 'label' => NVX_Settings::get( 'return_label' ), 'url' => $return_id > 0 ? get_permalink( $return_id ) : '' ],
		] );
	}

	public static function text_html() {
		$text = (string) NVX_Settings::get( 'terms_text' );
		foreach ( self::links() as $key => $l ) {
			$label = esc_html( $l['label'] );
			$html  = $l['url'] ? '<a href="' . esc_url( $l['url'] ) . '" target="_blank" rel="noopener">' . $label . '</a>' : $label;
			$text  = str_replace( '{' . $key . '}', $html, $text );
		}
		return wp_kses_post( $text );
	}

	public static function render() {
		$checked = apply_filters( 'woocommerce_terms_is_checked_default', isset( $_POST['terms'] ) ); // phpcs:ignore
		?>
		<div class="woocommerce-terms-and-conditions-wrapper nvx-terms">
			<p class="form-row validate-required">
				<label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox">
					<input type="checkbox" class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" name="terms" id="terms" <?php checked( $checked ); ?>>
					<span class="woocommerce-terms-and-conditions-checkbox-text"><?php echo self::text_html(); // phpcs:ignore ?></span>&nbsp;<abbr class="required" title="required">*</abbr>
				</label>
				<input type="hidden" name="terms-field" value="1">
			</p>
		</div>
		<?php
	}
}
