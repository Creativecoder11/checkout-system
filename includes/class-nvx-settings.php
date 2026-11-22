<?php
defined( 'ABSPATH' ) || exit;

/**
 * Adds a "Neuvix Checkout" tab to WooCommerce → Settings.
 */
class NVX_Settings {

	const OPT = 'nvx_checkout_settings';

	public static function init() {
		add_filter( 'woocommerce_settings_tabs_array', [ __CLASS__, 'add_tab' ], 50 );
		add_action( 'woocommerce_settings_tabs_nvx_checkout', [ __CLASS__, 'render' ] );
		add_action( 'woocommerce_update_options_nvx_checkout', [ __CLASS__, 'save' ] );
	}

	public static function add_tab( $tabs ) {
		$tabs['nvx_checkout'] = 'Neuvix Checkout';
		return $tabs;
	}

	public static function defaults() {
		return [
			'country'          => 'BD',
			// District / Area data (empty = use data/bd-locations.json).
			'locations'             => '',
			// Delivery charge.
			'ship_title'            => 'Delivery Charge',
			'ship_inside_districts' => 'Dhaka',
			'ship_inside_label'     => 'Inside Dhaka',
			'ship_inside_cost'      => '60',
			'ship_outside_label'    => 'Outside Dhaka',
			'ship_outside_cost'     => '120',
			'phone_prefix'     => '+880',
			'show_steps'       => 'yes',
			'layout_wrap'      => 'yes',
			// Terms checkbox.
			'terms_text'       => 'I agree to the {terms}, {privacy}, and {return}.',
			'terms_label'      => 'Terms',
			'terms_page'       => '',
			'privacy_label'    => 'Privacy Policy',
			'privacy_page'     => '',
			'return_label'     => 'Return Policy',
			'return_page'      => '',
			// Order confirmation page.
			'thankyou_enable'   => 'yes',
			'thankyou_title'    => 'Thank you for your order!',
			'thankyou_subtitle' => 'Your order has been received and is being processed',
			'continue_label'    => 'Continue Shopping',
			'continue_url'      => '',
			// Scripts blocked on checkout (one keyword per line).
			'block_scripts'    => 'clarity.ms',
			'load_css'         => 'yes',
			'button_text'      => 'PLACE ORDER',
			'payment_title'    => 'Select Payment Method',
			// Packaging.
			'pack_poly_on'     => 'yes',
			'pack_poly_label'  => 'Standard Poly',
			'pack_poly_price'  => '0',
			'pack_poly_free'   => '0',
			'pack_poly_img'    => '',
			'pack_poly_feat'   => "Durable shipping poly\nkeeps your items safe\nEco Friendly",
			'pack_bag_on'      => 'yes',
			'pack_bag_label'   => 'Regular Shopping Bag',
			'pack_bag_price'   => '20',
			'pack_bag_free'    => '1000',
			'pack_bag_img'     => '',
			'pack_bag_feat'    => "Branded premium bag\nExtra protection\nGreat for daily orders",
			'pack_box_on'      => 'yes',
			'pack_box_label'   => 'Gift-Ready Box',
			'pack_box_price'   => '100',
			'pack_box_free'    => '5000',
			'pack_box_img'     => '',
			'pack_box_feat'    => "Elegant gift box\nGreat for Occasions\nMakes it extra special",
			// Gateway icons (gateway_id => image URL).
			'icon_cod'         => '',
			'icon_card'        => '',
			'icon_card_ids'    => 'sslcommerz, stripe, sslcz',
			'icon_bkash'       => '',
			'icon_bkash_ids'   => 'bkash, woo_bkash, bkash_pgw',
		];
	}

	public static function get( $key ) {
		$opts = wp_parse_args( (array) get_option( self::OPT, [] ), self::defaults() );
		return $opts[ $key ] ?? '';
	}

	public static function fields() {
		$f = [
			[ 'title' => 'General', 'type' => 'title', 'id' => 'nvx_general' ],
			[ 'title' => 'Country', 'id' => 'country', 'type' => 'select', 'options' => WC()->countries->get_allowed_countries(), 'desc' => 'Hidden on checkout; drives the District list.' ],
			[ 'title' => 'Phone prefix', 'id' => 'phone_prefix', 'type' => 'text' ],
			[ 'title' => 'Payment section title', 'id' => 'payment_title', 'type' => 'text' ],
			[ 'title' => 'Place order button', 'id' => 'button_text', 'type' => 'text' ],
			[ 'title' => 'Full-width wrapper', 'id' => 'layout_wrap', 'type' => 'checkbox', 'desc' => 'Wrap the checkout in the plugin\'s full-width container (turn off when using a WoodMart/Elementor checkout layout)' ],
			[ 'title' => 'Show step bar', 'id' => 'show_steps', 'type' => 'checkbox', 'desc' => 'Cart › Delivery › Payment above the form' ],
			[ 'title' => 'Load plugin CSS', 'id' => 'load_css', 'type' => 'checkbox', 'desc' => 'Untick if you style everything in your theme' ],
			[ 'type' => 'sectionend', 'id' => 'nvx_general' ],

			[ 'title' => 'District & Area data', 'type' => 'title', 'id' => 'nvx_loc', 'desc' => 'One district per line: <code>Dhaka: Banani, Gulshan, Uttara</code>. You can also paste your JSON here. Leave empty to use the plugin\'s data/bd-locations.json file.' ],
			[ 'title' => 'Districts & areas', 'id' => 'locations', 'type' => 'textarea', 'css' => 'height:320px;width:100%;max-width:700px;font-family:monospace;font-size:12px', 'placeholder' => "Dhaka: Banani, Gulshan, Uttara, Mirpur\nChattogram: Agrabad, Halishahar\nSylhet: Zindabazar, Ambarkhana" ],
			[ 'type' => 'sectionend', 'id' => 'nvx_loc' ],

			[ 'title' => 'Terms checkbox', 'type' => 'title', 'id' => 'nvx_terms', 'desc' => 'Shown above Place Order. Use {terms}, {privacy} and {return} in the text — each becomes a link to the selected page. Leave a page empty to fall back to the WooCommerce Terms page / WordPress Privacy page.' ],
			[ 'title' => 'Text', 'id' => 'terms_text', 'type' => 'text', 'css' => 'width:100%;max-width:520px' ],
			[ 'title' => 'Terms label', 'id' => 'terms_label', 'type' => 'text' ],
			[ 'title' => 'Terms page', 'id' => 'terms_page', 'type' => 'single_select_page', 'class' => 'wc-enhanced-select-nostd', 'css' => 'min-width:300px' ],
			[ 'title' => 'Privacy label', 'id' => 'privacy_label', 'type' => 'text' ],
			[ 'title' => 'Privacy page', 'id' => 'privacy_page', 'type' => 'single_select_page', 'class' => 'wc-enhanced-select-nostd', 'css' => 'min-width:300px' ],
			[ 'title' => 'Return label', 'id' => 'return_label', 'type' => 'text' ],
			[ 'title' => 'Return policy page', 'id' => 'return_page', 'type' => 'single_select_page', 'class' => 'wc-enhanced-select-nostd', 'css' => 'min-width:300px' ],
			[ 'type' => 'sectionend', 'id' => 'nvx_terms' ],

			[ 'title' => 'Order confirmation page', 'type' => 'title', 'id' => 'nvx_ty', 'desc' => 'Replaces the WooCommerce "order received" page with the confirmation design (check icon, order number, summary, delivery details, continue button).' ],
			[ 'title' => 'Enable', 'id' => 'thankyou_enable', 'type' => 'checkbox' ],
			[ 'title' => 'Title', 'id' => 'thankyou_title', 'type' => 'text' ],
			[ 'title' => 'Subtitle', 'id' => 'thankyou_subtitle', 'type' => 'text', 'css' => 'width:100%;max-width:520px' ],
			[ 'title' => 'Button label', 'id' => 'continue_label', 'type' => 'text' ],
			[ 'title' => 'Button URL', 'id' => 'continue_url', 'type' => 'text', 'desc' => 'Leave empty to use the WooCommerce Shop page.', 'placeholder' => 'https://…' ],
			[ 'type' => 'sectionend', 'id' => 'nvx_ty' ],

			[ 'title' => 'Checkout performance', 'type' => 'title', 'id' => 'nvx_perf', 'desc' => 'Third-party scripts matching these keywords are removed from the checkout page only (one keyword per line). Default blocks Microsoft Clarity, which times out on some networks and slows the page. Leave empty to disable.' ],
			[ 'title' => 'Block scripts on checkout', 'id' => 'block_scripts', 'type' => 'textarea', 'css' => 'height:80px', 'placeholder' => "clarity.ms\nhotjar" ],
			[ 'type' => 'sectionend', 'id' => 'nvx_perf' ],

			[ 'title' => 'Delivery charge', 'type' => 'title', 'id' => 'nvx_ship', 'desc' => 'Applied automatically. A location counts as "inside" when the selected District OR Area matches a name in the list below.' ],
			[ 'title' => 'Section title', 'id' => 'ship_title', 'type' => 'text' ],
			[ 'title' => '"Inside" districts', 'id' => 'ship_inside_districts', 'type' => 'textarea', 'desc' => 'One name per line (district or area), e.g. Dhaka.', 'css' => 'height:70px' ],
			[ 'title' => 'Inside label', 'id' => 'ship_inside_label', 'type' => 'text' ],
			[ 'title' => 'Inside charge', 'id' => 'ship_inside_cost', 'type' => 'number', 'custom_attributes' => [ 'min' => 0, 'step' => '0.01' ] ],
			[ 'title' => 'Outside label', 'id' => 'ship_outside_label', 'type' => 'text' ],
			[ 'title' => 'Outside charge', 'id' => 'ship_outside_cost', 'type' => 'number', 'custom_attributes' => [ 'min' => 0, 'step' => '0.01' ] ],
			[ 'type' => 'sectionend', 'id' => 'nvx_ship' ],
		];

		foreach ( [ 'poly' => 'Packaging option 1', 'bag' => 'Packaging option 2', 'box' => 'Packaging option 3' ] as $k => $title ) {
			$f[] = [ 'title' => $title, 'type' => 'title', 'id' => "nvx_pack_$k" ];
			$f[] = [ 'title' => 'Enabled', 'id' => "pack_{$k}_on", 'type' => 'checkbox' ];
			$f[] = [ 'title' => 'Label', 'id' => "pack_{$k}_label", 'type' => 'text' ];
			$f[] = [ 'title' => 'Price', 'id' => "pack_{$k}_price", 'type' => 'number', 'custom_attributes' => [ 'min' => 0, 'step' => '0.01' ] ];
			$f[] = [ 'title' => 'Free when subtotal ≥', 'id' => "pack_{$k}_free", 'type' => 'number', 'desc' => '0 = never free (or always free if price is 0)', 'custom_attributes' => [ 'min' => 0 ] ];
			$f[] = [ 'title' => 'Image URL', 'id' => "pack_{$k}_img", 'type' => 'text', 'desc' => 'Leave empty to use the bundled placeholder' ];
			$f[] = [ 'title' => 'Features', 'id' => "pack_{$k}_feat", 'type' => 'textarea', 'desc' => 'One per line', 'css' => 'height:90px' ];
			$f[] = [ 'type' => 'sectionend', 'id' => "nvx_pack_$k" ];
		}

		$f[] = [ 'title' => 'Payment method icons', 'type' => 'title', 'id' => 'nvx_icons', 'desc' => 'Paste image URLs (upload via Media Library first). Leave empty to keep the gateway\'s own icon.' ];
		$f[] = [ 'title' => 'Cash on Delivery icon', 'id' => 'icon_cod', 'type' => 'text' ];
		$f[] = [ 'title' => 'Card gateway icon', 'id' => 'icon_card', 'type' => 'text' ];
		$f[] = [ 'title' => 'Card gateway IDs', 'id' => 'icon_card_ids', 'type' => 'text', 'desc' => 'Comma-separated gateway IDs' ];
		$f[] = [ 'title' => 'bKash icon', 'id' => 'icon_bkash', 'type' => 'text' ];
		$f[] = [ 'title' => 'bKash gateway IDs', 'id' => 'icon_bkash_ids', 'type' => 'text', 'desc' => 'Comma-separated gateway IDs' ];
		$f[] = [ 'type' => 'sectionend', 'id' => 'nvx_icons' ];

		// Prefix ids so they're stored under one option array.
		foreach ( $f as &$row ) {
			if ( isset( $row['id'] ) && ! in_array( $row['type'], [ 'title', 'sectionend' ], true ) ) {
				$row['default'] = self::defaults()[ $row['id'] ] ?? '';
				$row['id']      = self::OPT . '[' . $row['id'] . ']';
			}
		}
		return $f;
	}

	public static function render() {
		woocommerce_admin_fields( self::fields() );
	}

	public static function save() {
		woocommerce_update_options( self::fields() );
	}
}
