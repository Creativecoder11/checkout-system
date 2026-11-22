<?php
defined( 'ABSPATH' ) || exit;

/**
 * Full-width wrapper + step bar around the checkout form.
 */
class NVX_Layout {

	public static function init() {
		add_action( 'woocommerce_before_checkout_form', [ __CLASS__, 'open' ], 1 );
		add_action( 'woocommerce_after_checkout_form', [ __CLASS__, 'close' ], 99 );
		add_filter( 'body_class', [ __CLASS__, 'body_class' ] );
	}

	public static function body_class( $classes ) {
		if ( function_exists( 'is_checkout' ) && is_checkout() && ! is_wc_endpoint_url( 'order-received' ) ) {
			$classes[] = 'nvx-checkout-page';
		}
		return $classes;
	}

	protected static function enabled() {
		return 'yes' === NVX_Settings::get( 'layout_wrap' );
	}

	public static function open() {
		if ( ! self::enabled() ) {
			return;
		}
		$steps = apply_filters( 'nvx_checkout_steps', [
			[ 'label' => 'Cart', 'url' => wc_get_cart_url(), 'state' => 'done' ],
			[ 'label' => 'Delivery', 'url' => '', 'state' => 'done' ],
			[ 'label' => 'Payment', 'url' => '', 'state' => 'active' ],
		] );
		?>
		<div class="nvx-checkout-wrap">
			<div class="nvx-checkout-inner">
				<?php if ( 'yes' === NVX_Settings::get( 'show_steps' ) ) : ?>
					<nav class="nvx-steps" aria-label="Checkout steps">
						<?php foreach ( $steps as $i => $s ) : ?>
							<?php if ( $i > 0 ) : ?><span class="nvx-steps__sep" aria-hidden="true">›</span><?php endif; ?>
							<?php if ( $s['url'] ) : ?>
								<a class="nvx-steps__item is-<?php echo esc_attr( $s['state'] ); ?>" href="<?php echo esc_url( $s['url'] ); ?>"><?php echo esc_html( $s['label'] ); ?></a>
							<?php else : ?>
								<span class="nvx-steps__item is-<?php echo esc_attr( $s['state'] ); ?>"><?php echo esc_html( $s['label'] ); ?></span>
							<?php endif; ?>
						<?php endforeach; ?>
					</nav>
				<?php endif; ?>
		<?php
	}

	public static function close() {
		if ( self::enabled() ) {
			echo '</div></div>';
		}
	}
}
