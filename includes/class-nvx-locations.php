<?php
defined( 'ABSPATH' ) || exit;

/**
 * District / Area data loaded from data/bd-locations.json.
 *
 * Accepted JSON shapes:
 *   [ { "district": "Dhaka", "areas": [ "Banani", "Gulshan" ] }, ... ]
 *   { "Dhaka": [ "Banani", "Gulshan" ], ... }
 *
 * Override the file by placing bd-locations.json in your-theme/neuvix-checkout/
 * or via the `nvx_locations_file` filter.
 */
class NVX_Locations {

	/** @var array<string,string[]>|null */
	protected static $map = null;

	public static function file() {
		$theme = trailingslashit( get_stylesheet_directory() ) . 'neuvix-checkout/bd-locations.json';
		$file  = file_exists( $theme ) ? $theme : NVX_CO_PATH . 'data/bd-locations.json';
		return apply_filters( 'nvx_locations_file', $file );
	}

	/**
	 * Parse the dashboard textarea. Accepts raw JSON (either shape above) or one district per line:
	 *   Dhaka: Banani, Gulshan, Uttara
	 *   Dhaka | Banani | Gulshan
	 */
	public static function parse_text( $text ) {
		$text = trim( (string) $text );
		if ( '' === $text ) {
			return [];
		}
		if ( '[' === $text[0] || '{' === $text[0] ) {
			$json = json_decode( $text, true );
			return is_array( $json ) ? $json : [];
		}
		$out = [];
		foreach ( preg_split( '/\r\n|\r|\n/', $text ) as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}
			$parts = preg_split( '/\s*[:|]\s*/', $line, 2 );
			$district = trim( $parts[0] );
			$areas    = isset( $parts[1] ) ? preg_split( '/\s*[,|]\s*/', $parts[1] ) : [];
			if ( '' !== $district ) {
				$out[ $district ] = array_merge( $out[ $district ] ?? [], $areas );
			}
		}
		return $out;
	}

	/** @return array<string,string[]>  district => areas */
	public static function map() {
		if ( null !== self::$map ) {
			return self::$map;
		}
		self::$map = [];

		// 1) Dashboard data (WooCommerce → Settings → Neuvix Checkout) wins.
		$json = self::parse_text( NVX_Settings::get( 'locations' ) );

		// 2) Fallback: JSON file.
		if ( ! $json ) {
			$file = self::file();
			if ( is_readable( $file ) ) {
				$json = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore
			}
		}
		if ( ! is_array( $json ) ) {
			return self::$map;
		}
		foreach ( $json as $key => $row ) {
			if ( is_array( $row ) && isset( $row['district'] ) ) {
				$district = $row['district'];
				$areas    = $row['areas'] ?? [];
			} else {
				$district = $key;
				$areas    = $row;
			}
			$district = trim( (string) $district );
			if ( '' === $district ) {
				continue;
			}
			$clean = [];
			foreach ( (array) $areas as $a ) {
				$a = trim( preg_replace( '/\s+/', ' ', (string) $a ) );
				if ( '' !== $a && ! in_array( $a, $clean, true ) ) {
					$clean[] = $a;
				}
			}
			if ( ! $clean ) {
				$clean = [ $district ]; // Fallback so the Area field is never empty.
			}
			self::$map[ $district ] = $clean;
		}
		ksort( self::$map, SORT_NATURAL | SORT_FLAG_CASE );
		self::$map = apply_filters( 'nvx_locations_map', self::$map );
		return self::$map;
	}

	public static function districts() {
		return array_keys( self::map() );
	}

	public static function areas( $district ) {
		$map = self::map();
		return $map[ $district ] ?? [];
	}

	public static function is_district( $district ) {
		return '' !== $district && array_key_exists( $district, self::map() );
	}

	public static function is_area( $district, $area ) {
		return in_array( $area, self::areas( $district ), true );
	}

	/**
	 * WooCommerce state code for a district name (e.g. "Dhaka" → "BD-13"); falls back to the name.
	 */
	public static function state_code( $district, $country = 'BD' ) {
		$states = WC()->countries->get_states( $country );
		if ( is_array( $states ) ) {
			foreach ( $states as $code => $name ) {
				if ( 0 === strcasecmp( html_entity_decode( $name ), $district ) ) {
					return $code;
				}
			}
		}
		return $district;
	}
}
