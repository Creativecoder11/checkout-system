<?php
defined( 'ABSPATH' ) || exit;

/**
 * Blocks third-party scripts on the checkout page (e.g. Microsoft Clarity, which times out on
 * some networks and delays the page). Keyword list is editable in settings; one keyword per line.
 */
class NVX_Performance {

	public static function init() {
		add_action( 'wp_enqueue_scripts', [ __CLASS__, 'dequeue' ], 9999 );
		add_action( 'template_redirect', [ __CLASS__, 'buffer' ], 0 );
	}

	public static function keywords() {
		$list = preg_split( '/\r\n|\r|\n/', (string) NVX_Settings::get( 'block_scripts' ) );
		return array_values( array_filter( array_map( 'trim', (array) $list ) ) );
	}

	protected static function active() {
		return function_exists( 'is_checkout' ) && is_checkout() && ! is_wc_endpoint_url( 'order-received' ) && ! empty( self::keywords() );
	}

	protected static function matches( $haystack ) {
		foreach ( self::keywords() as $kw ) {
			if ( false !== stripos( $haystack, $kw ) ) {
				return true;
			}
		}
		return false;
	}

	/** Remove enqueued scripts whose src (or inline data) matches a keyword. */
	public static function dequeue() {
		if ( ! self::active() ) {
			return;
		}
		$scripts = wp_scripts();
		foreach ( (array) $scripts->queue as $handle ) {
			$dep = $scripts->registered[ $handle ] ?? null;
			if ( ! $dep ) {
				continue;
			}
			$blob = (string) $dep->src . ' ' . wp_json_encode( $dep->extra );
			if ( self::matches( $handle . ' ' . $blob ) ) {
				wp_dequeue_script( $handle );
				wp_deregister_script( $handle );
			}
		}
	}

	/** Strip inline snippets / raw <script> tags that were printed directly into the page. */
	public static function buffer() {
		if ( ! self::active() ) {
			return;
		}
		ob_start( [ __CLASS__, 'strip' ] );
	}

	public static function strip( $html ) {
		if ( '' === $html ) {
			return $html;
		}
		$out = preg_replace_callback(
			'#<script\b[^>]*>.*?</script>#is',
			function ( $m ) {
				return self::matches( $m[0] ) ? '<!-- script blocked on checkout by Neuvix Checkout -->' : $m[0];
			},
			$html
		);
		return null === $out ? $html : $out;
	}
}
