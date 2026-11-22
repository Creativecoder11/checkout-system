<?php
defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WC_Shipping_Method' ) ) {
	return;
}

/**
 * Global (non-zone) shipping method that returns a single rate based on the district.
 */
class NVX_Shipping_Method extends WC_Shipping_Method {

	public function __construct( $instance_id = 0 ) {
		$this->id                 = NVX_Shipping::METHOD_ID;
		$this->instance_id        = absint( $instance_id );
		$this->method_title       = 'Neuvix Delivery (district based)';
		$this->method_description = 'Automatic delivery charge — inside vs outside Dhaka. Configure it in WooCommerce → Settings → Neuvix Checkout.';
		$this->title              = 'Delivery';
		$this->enabled            = 'yes';
		$this->supports           = [];
	}

	public function is_enabled() {
		return true;
	}

	public function is_available( $package ) {
		return true;
	}

	public function calculate_shipping( $package = [] ) {
		$district = isset( $package['nvx_district'] ) ? $package['nvx_district'] : NVX_Shipping::current_district();
		$area     = isset( $package['nvx_area'] ) ? $package['nvx_area'] : NVX_Shipping::current_area();
		$inside   = NVX_Shipping::is_inside( $district, $area );

		$this->add_rate( [
			'id'      => $this->id,
			'label'   => NVX_Shipping::label( $inside ),
			'cost'    => NVX_Shipping::cost( $inside ),
			'package' => $package,
		] );
	}
}
