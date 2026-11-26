<?php
defined( 'ABSPATH' ) || exit;

/**
 * Printable A4 invoice.
 *
 * URL: /?nvx_invoice={order_id}&key={order_key}  (add &download=1 to open the print / "Save as PDF" dialog)
 * Access: the order key (same rule as WooCommerce's order-received page), the customer who owns
 * the order, or a shop manager.
 */
class NVX_Invoice {

	public static function init() {
		add_action( 'template_redirect', [ __CLASS__, 'maybe_render' ], 5 );
		add_filter( 'woocommerce_my_account_my_orders_actions', [ __CLASS__, 'account_action' ], 10, 2 );
		add_action( 'woocommerce_admin_order_data_after_order_details', [ __CLASS__, 'admin_link' ] );
	}

	public static function enabled() {
		return 'yes' === NVX_Settings::get( 'invoice_enable' );
	}

	/** Invoice available for this order? (not for failed / cancelled / unpaid-online orders) */
	public static function available( $order ) {
		return self::enabled() && $order && ! $order->has_status( [ 'failed', 'cancelled', 'pending', 'checkout-draft' ] );
	}

	public static function url( $order, $download = false ) {
		$args = [ 'nvx_invoice' => $order->get_id(), 'key' => $order->get_order_key() ];
		if ( $download ) {
			$args['download'] = 1;
		}
		return add_query_arg( $args, home_url( '/' ) );
	}

	public static function number( $order ) {
		$created = $order->get_date_created();
		$year    = $created ? $created->date( 'Y' ) : gmdate( 'Y' );
		$number  = NVX_Settings::get( 'invoice_prefix' ) . $year . '-' . str_pad( (string) $order->get_id(), 6, '0', STR_PAD_LEFT );
		return apply_filters( 'nvx_invoice_number', $number, $order );
	}

	/** Money actually received: online + paid, or any order marked Completed (COD delivered). */
	public static function is_paid( $order ) {
		if ( $order->has_status( 'completed' ) ) {
			return true;
		}
		return $order->is_paid() && NVX_Payment_Result::is_online( $order );
	}

	/** @return array{0:string,1:string} [ badge text, badge class ] */
	public static function badge( $order ) {
		if ( $order->has_status( 'refunded' ) ) {
			return [ 'Refunded', 'is-muted' ];
		}
		if ( $order->has_status( [ 'failed', 'cancelled' ] ) ) {
			return [ wc_get_order_status_name( $order->get_status() ), 'is-muted' ];
		}
		return self::is_paid( $order ) ? [ 'Paid', 'is-paid' ] : [ 'Unpaid', 'is-due' ];
	}

	public static function payment_status( $order ) {
		if ( $order->has_status( 'refunded' ) ) {
			return 'Refunded';
		}
		if ( self::is_paid( $order ) ) {
			return 'Paid in full';
		}
		return 'cod' === $order->get_payment_method() ? 'Pay on delivery' : 'Awaiting payment';
	}

	/** Store logo shipped with the plugin (assets/img/invoice-logo.svg). */
	const BUNDLED_LOGO = 'assets/img/invoice-logo.svg';

	/** Invoice logo: the Logo URL setting, else the bundled store logo, else the site logo. */
	public static function logo_url() {
		$logo = NVX_Settings::get( 'invoice_logo' );
		if ( ! $logo && file_exists( NVX_CO_PATH . self::BUNDLED_LOGO ) ) {
			$logo = NVX_CO_URL . self::BUNDLED_LOGO;
		}
		if ( ! $logo && ( $id = get_theme_mod( 'custom_logo' ) ) ) {
			$logo = wp_get_attachment_image_url( $id, 'medium' );
		}
		return (string) $logo;
	}

	/** Logo for emails: as logo_url(), but never an SVG (Gmail and Outlook don't show SVG images). */
	public static function email_logo_url() {
		$logo = self::logo_url();
		if ( 'svg' === strtolower( pathinfo( (string) wp_parse_url( $logo, PHP_URL_PATH ), PATHINFO_EXTENSION ) ) ) {
			$id   = get_theme_mod( 'custom_logo' );
			$logo = $id ? (string) wp_get_attachment_image_url( $id, 'medium' ) : ''; // '' → the store name is shown
		}
		return $logo;
	}

	/** Replace {store} in a settings string. */
	public static function text( $key ) {
		return str_replace( '{store}', get_bloginfo( 'name' ), (string) NVX_Settings::get( $key ) );
	}

	public static function can_view( $order ) {
		$key = isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : ''; // phpcs:ignore
		if ( $key && hash_equals( $order->get_order_key(), $key ) ) {
			return true;
		}
		if ( current_user_can( 'edit_shop_orders' ) ) {
			return true;
		}
		return is_user_logged_in() && (int) $order->get_customer_id() === get_current_user_id();
	}

	public static function maybe_render() {
		if ( empty( $_GET['nvx_invoice'] ) || ! self::enabled() ) { // phpcs:ignore
			return;
		}
		$order = wc_get_order( absint( $_GET['nvx_invoice'] ) ); // phpcs:ignore
		if ( ! $order || ! self::can_view( $order ) ) {
			wp_die( esc_html__( 'Invoice not found.', 'neuvix-checkout' ), '', [ 'response' => 404 ] );
		}
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow', true );
		$autoprint = ! empty( $_GET['download'] ); // phpcs:ignore
		$child = trailingslashit( get_stylesheet_directory() ) . 'neuvix-checkout/invoice.php';
		include file_exists( $child ) ? $child : NVX_CO_PATH . 'templates/invoice/invoice.php';
		exit;
	}

	public static function account_action( $actions, $order ) {
		if ( self::available( $order ) ) {
			$actions['nvx-invoice'] = [ 'url' => self::url( $order, true ), 'name' => 'Invoice' ];
		}
		return $actions;
	}

	public static function admin_link( $order ) {
		if ( ! self::enabled() ) {
			return;
		}
		echo '<p class="form-field form-field-wide"><a class="button" target="_blank" href="' . esc_url( self::url( $order ) ) . '">View invoice</a></p>';
	}

	/* ---------- PDF (dompdf, bundled in vendor/) ---------- */

	/** Loads dompdf — from another plugin if it already did, else from this plugin's vendor/. */
	protected static function load_dompdf() {
		if ( ! class_exists( '\Dompdf\Dompdf' ) && file_exists( NVX_CO_PATH . 'vendor/autoload.php' ) ) {
			require_once NVX_CO_PATH . 'vendor/autoload.php';
		}
		return class_exists( '\Dompdf\Dompdf' );
	}

	/**
	 * A site URL → its file on disk, so dompdf reads images locally (no HTTP fetch, works
	 * behind basic-auth / firewalls). Returns '' for remote or unsupported images.
	 */
	public static function local_image( $url ) {
		$url = strtok( (string) $url, '?#' );
		if ( ! $url || ! self::pdf_image_type_ok( $url ) ) {
			return '';
		}
		$uploads = wp_get_upload_dir();
		$roots   = [
			$uploads['baseurl'] => $uploads['basedir'],
			content_url()       => WP_CONTENT_DIR,
			site_url()          => ABSPATH,
		];
		$strip = function ( $u ) {
			return preg_replace( '#^https?://#i', '', untrailingslashit( $u ) );
		};
		foreach ( $roots as $base_url => $dir ) {
			$base = $strip( $base_url );
			$rel  = $strip( $url );
			if ( 0 === strpos( $rel, $base . '/' ) ) {
				$path = wp_normalize_path( untrailingslashit( $dir ) . '/' . rawurldecode( substr( $rel, strlen( $base ) + 1 ) ) );
				if ( false === strpos( $path, '../' ) && self::pdf_image_file_ok( $path ) ) {
					return $path;
				}
			}
		}
		return '';
	}

	/**
	 * A media-library image's file on disk at the given size — read from the attachment
	 * itself, so it works behind CDN rewrites / www mismatches. '' if unusable.
	 */
	public static function attachment_image( $attachment_id, $size = 'thumbnail' ) {
		$attachment_id = (int) $attachment_id;
		if ( ! $attachment_id ) {
			return '';
		}
		$file = get_attached_file( $attachment_id );
		if ( ! $file ) {
			return '';
		}
		$sized = image_get_intermediate_size( $attachment_id, $size );
		if ( $sized && ! empty( $sized['path'] ) ) {
			$candidate = trailingslashit( wp_get_upload_dir()['basedir'] ) . $sized['path'];
		} elseif ( $sized && ! empty( $sized['file'] ) ) {
			$candidate = trailingslashit( dirname( $file ) ) . $sized['file'];
		} else {
			$candidate = $file; // no generated size — the original, if it is small enough
		}
		$candidate = wp_normalize_path( $candidate );
		return self::pdf_image_type_ok( $candidate ) && self::pdf_image_file_ok( $candidate ) ? $candidate : '';
	}

	/** Formats dompdf can decode on this server (PNG/GIF/WebP need GD; JPEG does not). */
	protected static function pdf_image_type_ok( $path ) {
		$ext = strtolower( pathinfo( (string) $path, PATHINFO_EXTENSION ) );
		$ok  = [ 'jpg', 'jpeg', 'svg' ]; // SVG is drawn as vectors (no GD needed)
		if ( function_exists( 'imagecreatefrompng' ) ) {
			$ok[] = 'png';
		}
		if ( function_exists( 'imagecreatefromgif' ) ) {
			$ok[] = 'gif';
		}
		if ( function_exists( 'imagecreatefromwebp' ) ) {
			$ok[] = 'webp';
		}
		return in_array( $ext, $ok, true );
	}

	/** Readable and small enough to decode safely inside the checkout request. */
	protected static function pdf_image_file_ok( $path ) {
		$max = (int) apply_filters( 'nvx_invoice_pdf_max_image_bytes', 1536 * 1024 );
		if ( ! is_readable( $path ) || filesize( $path ) > $max ) {
			return false;
		}
		if ( 'svg' === strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) ) {
			return (bool) self::svg_size( $path );
		}
		// GD decodes to width × height × 4 bytes — a small file can still be a huge bitmap.
		$size = @getimagesize( $path ); // phpcs:ignore
		$px   = (int) apply_filters( 'nvx_invoice_pdf_max_image_pixels', 2000 * 2000 );
		return $size && $size[0] * $size[1] <= $px;
	}

	/** [width, height] of an SVG from its width/height attributes or viewBox; null if unreadable. */
	protected static function svg_size( $path ) {
		$head = (string) @file_get_contents( $path, false, null, 0, 4096 ); // phpcs:ignore
		if ( ! preg_match( '/<svg\b[^>]*>/i', $head, $tag ) ) {
			return null;
		}
		$attr = function ( $name ) use ( $tag ) {
			return preg_match( '/\s' . $name . '\s*=\s*["\']([^"\']+)["\']/i', $tag[0], $m ) ? $m[1] : '';
		};
		$w = (float) $attr( 'width' );
		$h = (float) $attr( 'height' );
		if ( ( ! $w || ! $h ) && ( $vb = preg_split( '/[\s,]+/', trim( $attr( 'viewBox' ) ) ) ) && 4 === count( $vb ) ) {
			$w = (float) $vb[2];
			$h = (float) $vb[3];
		}
		return $w > 0 && $h > 0 ? [ $w, $h ] : null;
	}

	/**
	 * Logo for the PDF, with the size to draw it at (fits the reference's 200 × 40 px box).
	 *
	 * @return array{path:string,width:float,height:float}|null
	 */
	public static function pdf_logo() {
		$path    = '';
		$setting = NVX_Settings::get( 'invoice_logo' );
		if ( $setting ) {
			$id   = attachment_url_to_postid( $setting );
			$path = $id ? self::attachment_image( $id, 'medium' ) : self::local_image( $setting );
		}
		if ( ! $path && ! $setting && file_exists( NVX_CO_PATH . self::BUNDLED_LOGO ) ) {
			$path = NVX_CO_PATH . self::BUNDLED_LOGO;
		}
		if ( ! $path && ! $setting && ( $id = get_theme_mod( 'custom_logo' ) ) ) {
			$path = self::attachment_image( $id, 'medium' );
		}
		if ( ! $path ) {
			return null;
		}
		$size = 'svg' === strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) ? self::svg_size( $path ) : @getimagesize( $path ); // phpcs:ignore
		if ( ! $size || ! $size[0] || ! $size[1] ) {
			return null;
		}
		$scale = min( 200 / $size[0], 40 / $size[1] );
		if ( 'svg' !== strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) ) {
			$scale = min( 1, $scale ); // like max-width/max-height: never enlarge a bitmap
		}
		return [ 'path' => $path, 'width' => round( $size[0] * $scale, 2 ), 'height' => round( $size[1] * $scale, 2 ) ];
	}

	/** Product thumbnail for the PDF, falling back to WooCommerce's placeholder image. */
	public static function pdf_product_image( $product ) {
		$id = $product ? (int) $product->get_image_id() : 0;
		if ( ! $id ) {
			$id = (int) get_option( 'woocommerce_placeholder_image', 0 );
		}
		$path = self::attachment_image( $id, 'thumbnail' );
		return $path ?: self::local_image( wc_placeholder_img_src( 'thumbnail' ) );
	}

	/** A writable folder for dompdf's font cache: uploads/nvx-invoice-fonts, else the temp dir. */
	protected static function pdf_font_cache_dir() {
		$dir = trailingslashit( wp_get_upload_dir()['basedir'] ) . 'nvx-invoice-fonts';
		if ( wp_mkdir_p( $dir ) && wp_is_writable( $dir ) ) {
			if ( ! file_exists( $dir . '/index.php' ) ) {
				@file_put_contents( $dir . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore
			}
			return $dir;
		}
		return untrailingslashit( get_temp_dir() );
	}

	/** True when the bundled PDF library is present (vendor/ uploaded with the plugin). */
	public static function pdf_library_present() {
		return class_exists( '\Dompdf\Dompdf' ) || file_exists( NVX_CO_PATH . 'vendor/autoload.php' );
	}

	/** dompdf's fonts have no ৳ glyph — print "Tk" in the PDF instead. */
	public static function pdf_currency_symbol( $symbol ) {
		return str_ireplace( [ '&#2547;', '&#x9f3;', '&#x09f3;', '৳' ], 'Tk', $symbol );
	}

	/**
	 * Renders the invoice as PDF.
	 *
	 * @return string|WP_Error PDF bytes.
	 */
	public static function pdf( $order ) {
		if ( ! self::load_dompdf() ) {
			return new WP_Error( 'nvx_no_dompdf', 'PDF library (dompdf) is missing from the plugin\'s vendor folder.' );
		}
		add_filter( 'woocommerce_currency_symbol', [ __CLASS__, 'pdf_currency_symbol' ], 999 );
		wp_raise_memory_limit( 'image' );
		$level = ob_get_level();
		try {
			ob_start();
			$child = trailingslashit( get_stylesheet_directory() ) . 'neuvix-checkout/invoice-pdf.php';
			include file_exists( $child ) ? $child : NVX_CO_PATH . 'templates/invoice/invoice-pdf.php';
			$html = ob_get_clean();

			$options = new \Dompdf\Options();
			$options->set( 'isRemoteEnabled', false ); // images come from disk (local_image)
			$options->set( 'isPhpEnabled', false );
			$options->set( 'defaultFont', 'DejaVu Sans' );
			$options->set( 'tempDir', get_temp_dir() );
			// dompdf writes font-metric caches while rendering; the plugin folder is often read-only on hosts.
			$options->set( 'fontCache', self::pdf_font_cache_dir() );
			$options->setChroot( array_unique( [ ABSPATH, WP_CONTENT_DIR, wp_get_upload_dir()['basedir'], NVX_CO_PATH ] ) );

			$dompdf = new \Dompdf\Dompdf( $options );
			$dompdf->loadHtml( $html, 'UTF-8' );
			$dompdf->setPaper( 'A4', 'portrait' );
			$dompdf->render();
			return $dompdf->output();
		} catch ( \Throwable $e ) {
			while ( ob_get_level() > $level ) {
				ob_end_clean();
			}
			return new WP_Error( 'nvx_pdf_failed', $e->getMessage() );
		} finally {
			remove_filter( 'woocommerce_currency_symbol', [ __CLASS__, 'pdf_currency_symbol' ], 999 );
		}
	}

	/**
	 * Writes the PDF to a temporary file named like "Invoice-INV-2026-001284.pdf" (the
	 * attachment name the admin sees). Delete it with delete_pdf_file() after sending.
	 *
	 * @return string|WP_Error File path.
	 */
	public static function pdf_file( $order ) {
		$pdf = self::pdf( $order );
		if ( is_wp_error( $pdf ) ) {
			return $pdf;
		}
		$dir = trailingslashit( get_temp_dir() ) . 'nvx-invoice-' . wp_generate_password( 16, false, false );
		if ( ! wp_mkdir_p( $dir ) ) {
			return new WP_Error( 'nvx_pdf_tmp', 'Could not create a temporary folder for the invoice PDF.' );
		}
		$file = $dir . '/' . sanitize_file_name( 'Invoice-' . self::number( $order ) . '.pdf' );
		if ( false === file_put_contents( $file, $pdf ) ) { // phpcs:ignore
			@rmdir( $dir ); // phpcs:ignore
			return new WP_Error( 'nvx_pdf_tmp', 'Could not write the invoice PDF to the temporary folder.' );
		}
		return $file;
	}

	public static function delete_pdf_file( $file ) {
		if ( $file && 0 === strpos( basename( dirname( $file ) ), 'nvx-invoice-' ) ) {
			@unlink( $file ); // phpcs:ignore
			@rmdir( dirname( $file ) ); // phpcs:ignore
		}
	}

	/** "Download Invoice" button for the confirmation screens. */
	public static function button( $order, $class ) {
		if ( ! self::available( $order ) ) {
			return;
		}
		?>
		<a class="<?php echo esc_attr( $class ); ?>" href="<?php echo esc_url( self::url( $order, true ) ); ?>" target="_blank" rel="noopener">
			<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 4v11M7 10l5 5 5-5M5 20h14"/></svg>
			Download Invoice
		</a>
		<?php
	}
}
