<?php
defined( 'ABSPATH' ) || exit;

/**
 * Delivery address fields: Full Name | Mobile, Email, Street, District | Area, Order note.
 */
class NVX_Fields {

	public static function init() {
		add_filter( 'default_checkout_billing_country', [ __CLASS__, 'default_country' ] );
		add_filter( 'woocommerce_checkout_fields', [ __CLASS__, 'fields' ], 100 );
		add_filter( 'woocommerce_cart_needs_shipping_address', '__return_false' );
		add_filter( 'woocommerce_enable_order_notes_field', '__return_false' );
		add_action( 'woocommerce_checkout_process', [ __CLASS__, 'validate' ] );
		add_filter( 'woocommerce_checkout_posted_data', [ __CLASS__, 'map_to_address' ] );
		add_action( 'woocommerce_checkout_create_order', [ __CLASS__, 'save_meta' ] );
		add_action( 'woocommerce_admin_order_data_after_billing_address', [ __CLASS__, 'admin_display' ] );
	}

	public static function default_country() {
		return NVX_Settings::get( 'country' ) ?: 'BD';
	}

	protected static function posted( $key ) {
		return isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : ''; // phpcs:ignore
	}

	public static function fields( $fields ) {
		$country = self::default_country();

		$district = self::posted( 'nvx_district' );
		if ( ! $district && WC()->session ) {
			$district = (string) WC()->session->get( NVX_Shipping::SESSION_KEY, '' );
		}
		$districts = NVX_Locations::districts();
		$areas     = $district ? NVX_Locations::areas( $district ) : [];

		$fields['billing'] = [
			'billing_country'    => [
				'type'     => 'hidden',
				'default'  => $country,
				'required' => true,
				'priority' => 1,
			],
			'billing_first_name' => [
				'label'       => 'Full Name',
				'placeholder' => 'Enter Your Full Name',
				'required'    => true,
				'class'       => [ 'form-row-first', 'nvx-icon-user' ],
				'autocomplete' => 'name',
				'priority'    => 10,
			],
			'billing_phone'      => [
				'label'       => 'Mobile Number',
				'placeholder' => 'BD' === $country ? '01712-345678' : 'Enter Your Mobile Number',
				'type'        => 'tel',
				'required'    => true,
				'class'       => [ 'form-row-last', 'nvx-phone' ],
				'autocomplete' => 'tel',
				'custom_attributes' => [ 'inputmode' => 'tel', 'maxlength' => '20' ],
				'priority'    => 20,
			],
			'billing_email'      => [
				'label'       => 'Email',
				'placeholder' => 'Enter Your Email',
				'type'        => 'email',
				'required'    => false,
				'validate'    => [ 'email' ],
				'class'       => [ 'form-row-wide', 'nvx-icon-mail' ],
				'autocomplete' => 'email',
				'priority'    => 25,
			],
			'billing_address_1'  => [
				'label'       => 'Street address',
				'placeholder' => 'Enter Your Street Address',
				'required'    => true,
				'class'       => [ 'form-row-wide', 'nvx-icon-pin' ],
				'autocomplete' => 'street-address',
				'priority'    => 30,
			],
			'nvx_district'       => [
				'type'     => 'select',
				'label'    => 'District',
				'required' => true,
				'class'    => [ 'form-row-first', 'nvx-icon-map', 'update_totals_on_change' ],
				'options'  => [ '' => 'Select District' ] + array_combine( $districts, $districts ),
				'priority' => 40,
			],
			'nvx_area'           => [
				'type'     => 'select',
				'label'    => 'Area',
				'required' => true,
				'class'    => [ 'form-row-last', 'nvx-icon-city' ],
				'options'  => [ '' => 'Select Area' ] + array_combine( $areas, $areas ),
				'priority' => 50,
			],
			'order_comments'     => [
				'type'        => 'textarea',
				'label'       => 'Order note',
				'placeholder' => 'Notes about your order, e.g. special delivery instructions',
				'required'    => false,
				'class'       => [ 'form-row-wide', 'nvx-note' ],
				'priority'    => 60,
			],
		];

		$fields['shipping'] = [];
		$fields['order']    = [];

		return $fields;
	}

	/* ---------- Phone ---------- */

	/** Strip spaces/dashes/brackets and convert Bangla digits (০-৯) to 0-9. */
	protected static function clean_phone( $phone ) {
		$phone = str_replace( [ '০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯' ], [ '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' ], (string) $phone );
		return preg_replace( '/[\s\-\.\(\)]/', '', $phone );
	}

	/**
	 * Normalised phone number, or '' when invalid.
	 * BD: accepts 01712345678, 1712345678, +8801712345678, 8801712345678 → returns 01712345678.
	 * Other countries: 7–15 digits, optional leading +.
	 */
	public static function normalize_phone( $phone, $country = null ) {
		$country = $country ?: self::default_country();
		$phone   = self::clean_phone( $phone );

		if ( 'BD' === $country ) {
			$digits = ltrim( $phone, '+' );
			if ( 0 === strpos( $digits, '880' ) ) {
				$digits = substr( $digits, 3 );
			}
			if ( preg_match( '/^1\d{9}$/', $digits ) ) {
				$digits = '0' . $digits;
			}
			return preg_match( '/^01[3-9]\d{8}$/', $digits ) ? $digits : '';
		}

		return preg_match( '/^\+?\d{7,15}$/', $phone ) ? $phone : '';
	}

	public static function phone_error() {
		return 'BD' === self::default_country()
			? 'Please enter a valid Bangladeshi mobile number (e.g. 01712345678).'
			: 'Please enter a valid mobile number.';
	}

	public static function validate() {
		$phone = self::posted( 'billing_phone' );
		if ( '' !== $phone && '' === self::normalize_phone( $phone ) ) {
			wc_add_notice( self::phone_error(), 'error', [ 'id' => 'billing_phone' ] );
		}
		// Email format is validated by WooCommerce itself ('validate' => [ 'email' ]).

		$district = self::posted( 'nvx_district' );
		$area     = self::posted( 'nvx_area' );

		if ( $district && ! NVX_Locations::is_district( $district ) ) {
			wc_add_notice( 'Please select a valid district.', 'error' );
		}
		if ( $district && $area && ! NVX_Locations::is_area( $district, $area ) ) {
			wc_add_notice( 'Please select a valid area for your district.', 'error' );
		}
	}

	/** Copy district/area into the standard WooCommerce address so orders, emails and admin show them. */
	public static function map_to_address( $data ) {
		$district = isset( $data['nvx_district'] ) ? sanitize_text_field( $data['nvx_district'] ) : '';
		$area     = isset( $data['nvx_area'] ) ? sanitize_text_field( $data['nvx_area'] ) : '';

		if ( $district ) {
			$data['billing_state'] = NVX_Locations::state_code( $district, $data['billing_country'] ?? 'BD' );
		}
		if ( $area ) {
			$data['billing_city'] = $area;
		}
		// Store the phone in one consistent format (BD: 01XXXXXXXXX).
		if ( ! empty( $data['billing_phone'] ) ) {
			$phone = self::normalize_phone( $data['billing_phone'], $data['billing_country'] ?? null );
			if ( '' !== $phone ) {
				$data['billing_phone'] = $phone;
			}
		}
		$data['billing_postcode'] = '';
		return $data;
	}

	public static function save_meta( $order ) {
		$district = self::posted( 'nvx_district' );
		$area     = self::posted( 'nvx_area' );
		if ( $district ) {
			$order->update_meta_data( '_nvx_district', $district );
		}
		if ( $area ) {
			$order->update_meta_data( '_nvx_area', $area );
		}
	}

	public static function admin_display( $order ) {
		$d = $order->get_meta( '_nvx_district' );
		$a = $order->get_meta( '_nvx_area' );
		if ( $d || $a ) {
			echo '<p><strong>District / Area:</strong> ' . esc_html( trim( $d . ' / ' . $a, ' /' ) ) . '</p>';
		}
	}
}
