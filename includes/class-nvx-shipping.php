<?php
defined( 'ABSPATH' ) || exit;

/**
 * Automatic district-based delivery charge (inside Dhaka vs outside).
 * The customer cannot pick a method — the rate is forced from the selected district.
 */
class NVX_Shipping {

	const SESSION_KEY  = 'nvx_district';
	const SESSION_AREA = 'nvx_area';
	const METHOD_ID   = 'nvx_delivery';

	public static function init() {
		add_action( 'woocommerce_shipping_init', [ __CLASS__, 'load_method_class' ] );
		add_filter( 'woocommerce_shipping_methods', [ __CLASS__, 'register_method' ] );
		add_action( 'woocommerce_checkout_update_order_review', [ __CLASS__, 'store_from_ajax' ] );
		add_action( 'woocommerce_before_checkout_process', [ __CLASS__, 'store_from_post' ] );
		add_filter( 'woocommerce_cart_shipping_packages', [ __CLASS__, 'tag_packages' ] );
		add_filter( 'woocommerce_package_rates', [ __CLASS__, 'force_rate' ], 100, 2 );
		add_filter( 'woocommerce_shipping_chosen_method', [ __CLASS__, 'chosen_method' ], 100, 2 );
		// Always calculate shipping — we have no postcode, and "hide costs until address" would otherwise block it.
		add_filter( 'pre_option_woocommerce_shipping_cost_requires_address', [ __CLASS__, 'no_address_needed' ] );
		add_filter( 'woocommerce_cart_ready_to_calc_shipping', '__return_true' );
		add_filter( 'woocommerce_shipping_cost_requires_address', '__return_false' );
	}

	public static function no_address_needed() {
		return 'no';
	}

	public static function load_method_class() {
		require_once NVX_CO_PATH . 'includes/class-nvx-shipping-method.php';
	}

	public static function register_method( $methods ) {
		$methods[ self::METHOD_ID ] = 'NVX_Shipping_Method';
		return $methods;
	}

	/* ---------- Config ---------- */

	public static function inside_districts() {
		$list = array_filter( array_map( 'trim', explode( "\n", (string) NVX_Settings::get( 'ship_inside_districts' ) ) ) );
		return $list ?: [ 'Dhaka' ];
	}

	/** True when the district OR the area matches an "inside" name. */
	public static function is_inside( $district, $area = null ) {
		if ( null === $area ) {
			$area = self::current_area();
		}
		foreach ( self::inside_districts() as $d ) {
			if ( ( '' !== (string) $district && 0 === strcasecmp( $d, $district ) ) || ( '' !== (string) $area && 0 === strcasecmp( $d, $area ) ) ) {
				return true;
			}
		}
		return false;
	}

	public static function cost( $inside ) {
		return (float) NVX_Settings::get( $inside ? 'ship_inside_cost' : 'ship_outside_cost' );
	}

	public static function label( $inside ) {
		return (string) NVX_Settings::get( $inside ? 'ship_inside_label' : 'ship_outside_label' );
	}

	/* ---------- Current district ---------- */

	public static function current_district() {
		if ( ! empty( $_POST['nvx_district'] ) ) { // phpcs:ignore
			return sanitize_text_field( wp_unslash( $_POST['nvx_district'] ) ); // phpcs:ignore
		}
		return WC()->session ? (string) WC()->session->get( self::SESSION_KEY, '' ) : '';
	}

	public static function current_area() {
		if ( ! empty( $_POST['nvx_area'] ) ) { // phpcs:ignore
			return sanitize_text_field( wp_unslash( $_POST['nvx_area'] ) ); // phpcs:ignore
		}
		return WC()->session ? (string) WC()->session->get( self::SESSION_AREA, '' ) : '';
	}

	protected static function set_district( $district, $area = '' ) {
		$district = sanitize_text_field( $district );
		$area     = sanitize_text_field( $area );
		if ( ! NVX_Locations::is_district( $district ) ) {
			$district = '';
		}
		if ( WC()->session ) {
			WC()->session->set( self::SESSION_KEY, $district );
			WC()->session->set( self::SESSION_AREA, $area );
		}
		if ( WC()->customer && $district ) {
			WC()->customer->set_billing_state( NVX_Locations::state_code( $district ) );
		}
	}

	public static function store_from_ajax( $post_data ) {
		parse_str( (string) $post_data, $data );
		self::set_district(
			isset( $data['nvx_district'] ) ? wp_unslash( $data['nvx_district'] ) : '',
			isset( $data['nvx_area'] ) ? wp_unslash( $data['nvx_area'] ) : ''
		);
	}

	public static function store_from_post() {
		self::set_district(
			isset( $_POST['nvx_district'] ) ? wp_unslash( $_POST['nvx_district'] ) : '', // phpcs:ignore
			isset( $_POST['nvx_area'] ) ? wp_unslash( $_POST['nvx_area'] ) : '' // phpcs:ignore
		);
	}

	/* ---------- Rates ---------- */

	/** Adds the district to each package so WooCommerce's rate cache is keyed on it. */
	public static function tag_packages( $packages ) {
		$district = self::current_district();
		$area     = self::current_area();
		foreach ( $packages as &$p ) {
			$p['nvx_district'] = $district;
			$p['nvx_area']     = $area;
			if ( $district && isset( $p['destination'] ) ) {
				$p['destination']['state'] = NVX_Locations::state_code( $district );
			}
		}
		return $packages;
	}

	/** Drop every other rate whenever ours is present. */
	public static function force_rate( $rates, $package ) {
		if ( isset( $rates[ self::METHOD_ID ] ) ) {
			return [ self::METHOD_ID => $rates[ self::METHOD_ID ] ];
		}
		return $rates;
	}

	public static function chosen_method( $default, $rates ) {
		return isset( $rates[ self::METHOD_ID ] ) ? self::METHOD_ID : $default;
	}

	/* ---------- UI above payment methods (rendered inside review-order.php so AJAX refreshes it) ---------- */

	public static function render_ui() {
		if ( ! WC()->cart || ! WC()->cart->needs_shipping() ) {
			return;
		}
		$district = self::current_district();
		$area     = self::current_area();
		$inside   = self::is_inside( $district, $area );
		$title    = NVX_Settings::get( 'ship_title' );
		?>
		<div class="nvx-delivery">
			<?php if ( $title ) : ?><h3 class="nvx-delivery__title"><?php echo esc_html( $title ); ?></h3><?php endif; ?>
			<?php foreach ( [ true, false ] as $zone ) : $on = ( $zone === $inside ); ?>
				<label class="nvx-delivery__opt <?php echo $on ? 'is-selected' : ''; ?>">
					<input type="radio" name="nvx_delivery_zone" value="<?php echo $zone ? 'inside' : 'outside'; ?>" <?php checked( $on ); ?> disabled>
					<span class="nvx-delivery__radio" aria-hidden="true"></span>
					<span class="nvx-delivery__label"><?php echo esc_html( self::label( $zone ) ); ?></span>
					<span class="nvx-delivery__price"><?php echo wp_kses_post( wc_price( self::cost( $zone ) ) ); ?></span>
				</label>
			<?php endforeach; ?>
			<p class="nvx-delivery__note">
				<?php echo $district ? esc_html( sprintf( 'Delivery charge is set automatically for %s.', trim( $area . ', ' . $district, ', ' ) ) ) : 'Delivery charge is set automatically once you select your district and area.'; ?>
			</p>
		</div>
		<?php
	}
}
