<?php
defined( 'ABSPATH' ) || exit;

/**
 * Option storage, defaults and sanitization.
 */
class EZMAILS_Settings {

	const OPTION = 'ezmails_settings';

	private static $cache = null;

	public static function fonts() {
		return array(
			'helvetica' => array( 'Helvetica / Arial', "'Helvetica Neue',Helvetica,Roboto,Arial,sans-serif" ),
			'system'    => array( 'System UI', "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif" ),
			'georgia'   => array( 'Georgia (serif)', "Georgia,'Times New Roman',serif" ),
			'verdana'   => array( 'Verdana', 'Verdana,Geneva,sans-serif' ),
			'trebuchet' => array( 'Trebuchet MS', "'Trebuchet MS',Helvetica,sans-serif" ),
		);
	}

	/**
	 * Schema for the global (header/footer/design) settings: key => [type, default, min, max].
	 */
	public static function general_schema() {
		return array(
			'enabled'       => array( 'bool', 1 ),
			'page_bg'       => array( 'color', '#f4f4f4' ),
			'body_bg'       => array( 'color', '#ffffff' ),
			'text_color'    => array( 'color', '#444444' ),
			'accent'        => array( 'color', '#7f54b3' ),
			'font'          => array( 'font', 'helvetica' ),
			'font_custom'   => array( 'fontstack', '' ),
			'heading_font'  => array( 'headfont', 'inherit' ),
			'font_size'     => array( 'int', 15, 12, 22 ),
			'line_height'   => array( 'int', 160, 110, 220 ),
			'heading_size'  => array( 'int', 26, 16, 48 ),
			'heading_weight'    => array( 'enum', '600', array( '400', '600', '700' ) ),
			'heading_transform' => array( 'enum', 'none', array( 'none', 'uppercase', 'capitalize' ) ),
			'heading_spacing'   => array( 'int', 0, 0, 6 ),
			'heading_style'     => array( 'enum', 'bar', array( 'bar', 'inline' ) ),
			'h2_size'       => array( 'int', 18, 12, 30 ),
			'link_underline' => array( 'bool', 0 ),
			'border_color'  => array( 'color', '#e5e5e5' ),
			'radius'        => array( 'int', 6, 0, 30 ),
			'page_padding'  => array( 'int', 40, 0, 80 ),
			'content_padding' => array( 'int', 36, 12, 60 ),
			'button_bg'     => array( 'color', '#7f54b3' ),
			'button_color'  => array( 'color', '#ffffff' ),
			'button_radius' => array( 'int', 4, 0, 30 ),
			'footer_size'   => array( 'int', 13, 10, 18 ),
			'width'         => array( 'int', 600, 360, 800 ),
			'logo_url'      => array( 'url', '' ),
			'logo_width'    => array( 'int', 180, 20, 600 ),
			'header_text'   => array( 'html', '' ),
			'header_bg'     => array( 'color', '#ffffff' ),
			'header_color'  => array( 'color', '#333333' ),
			'header_align'  => array( 'align', 'center' ),
			'heading_bg'    => array( 'color', '#7f54b3' ),
			'heading_color' => array( 'color', '#ffffff' ),
			'footer_text'   => array( 'html', '<p>&copy; [current_year] [site_title]<br>[store_address]</p>' ),
			'footer_bg'     => array( 'color', '#2b2b2b' ),
			'footer_color'  => array( 'color', '#cccccc' ),
			'social_facebook'  => array( 'url', '' ),
			'social_instagram' => array( 'url', '' ),
			'social_x'         => array( 'url', '' ),
			'social_linkedin'  => array( 'url', '' ),
			'social_youtube'   => array( 'url', '' ),
		);
	}

	/**
	 * Schema for per-email settings.
	 */
	public static function email_schema() {
		return array(
			'subject'     => array( 'text', '' ),
			'heading'     => array( 'text', '' ),
			'intro'       => array( 'html', '' ),
			'body_text'   => array( 'html', '' ),
			'additional'  => array( 'html', '' ),
			'body_mode'   => array( 'mode', 'default' ),
			'custom_body' => array( 'html', '' ),
		);
	}

	private static function defaults_from( $schema ) {
		$out = array();
		foreach ( $schema as $key => $def ) {
			$out[ $key ] = $def[1];
		}
		return $out;
	}

	public static function all() {
		if ( null === self::$cache ) {
			$saved = get_option( self::OPTION, array() );
			$saved = is_array( $saved ) ? $saved : array();
			self::$cache = array(
				'general' => wp_parse_args( isset( $saved['general'] ) ? (array) $saved['general'] : array(), self::defaults_from( self::general_schema() ) ),
				'emails'  => isset( $saved['emails'] ) ? (array) $saved['emails'] : array(),
			);
		}
		return self::$cache;
	}

	public static function general( $key = null ) {
		$all = self::all();
		if ( null === $key ) {
			return $all['general'];
		}
		return isset( $all['general'][ $key ] ) ? $all['general'][ $key ] : '';
	}

	/**
	 * Normalise an email ID (third-party emails may use uppercase, dashes or dots) into a storage key.
	 */
	public static function key( $id ) {
		return preg_replace( '/[^a-z0-9_]/', '_', strtolower( (string) $id ) );
	}

	public static function email( $id ) {
		$id    = self::key( $id );
		$all   = self::all();
		$saved = isset( $all['emails'][ $id ] ) && is_array( $all['emails'][ $id ] ) ? $all['emails'][ $id ] : array();
		return wp_parse_args( $saved, self::defaults_from( self::email_schema() ) );
	}

	private static function clean( $value, $def ) {
		$type    = $def[0];
		$default = $def[1];
		$value   = is_scalar( $value ) ? (string) $value : '';

		switch ( $type ) {
			case 'bool':
				return empty( $value ) ? 0 : 1;
			case 'color':
				$c = sanitize_hex_color( $value );
				return $c ? $c : $default;
			case 'int':
				if ( '' === trim( $value ) || ! is_numeric( $value ) ) {
					return $default;
				}
				$n = (int) $value;
				return max( $def[2], min( $def[3], $n ) );
			case 'url':
				return esc_url_raw( $value );
			case 'font':
				return isset( self::fonts()[ $value ] ) ? $value : $default;
			case 'align':
				return in_array( $value, array( 'left', 'center', 'right' ), true ) ? $value : $default;
			case 'mode':
				return in_array( $value, array( 'default', 'text', 'custom' ), true ) ? $value : $default;
			case 'enum':
				return in_array( $value, $def[2], true ) ? $value : $default;
			case 'headfont':
				return ( 'inherit' === $value || isset( self::fonts()[ $value ] ) ) ? $value : $default;
			case 'fontstack':
				return trim( preg_replace( '/[^A-Za-z0-9 ,\'"_\-]/', '', $value ) );
			case 'html':
				return wp_kses_post( $value );
			case 'text':
			default:
				return sanitize_text_field( $value );
		}
	}

	public static function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();
		$out   = array(
			'general' => array(),
			'emails'  => array(),
		);

		$general = isset( $input['general'] ) && is_array( $input['general'] ) ? $input['general'] : array();
		foreach ( self::general_schema() as $key => $def ) {
			$out['general'][ $key ] = self::clean( isset( $general[ $key ] ) ? $general[ $key ] : '', $def );
		}

		$emails = isset( $input['emails'] ) && is_array( $input['emails'] ) ? $input['emails'] : array();
		foreach ( $emails as $id => $fields ) {
			$id = self::key( $id );
			if ( '' === $id || ! is_array( $fields ) ) {
				continue;
			}
			foreach ( self::email_schema() as $key => $def ) {
				$out['emails'][ $id ][ $key ] = self::clean( isset( $fields[ $key ] ) ? $fields[ $key ] : '', $def );
			}
		}

		return $out;
	}

	public static function update( $input ) {
		update_option( self::OPTION, self::sanitize( $input ) );
		self::$cache = null;
	}

	/* ------------------------------------------------------------------
	 * Import / export
	 * ---------------------------------------------------------------- */

	const EXPORT_ID = 'ezmails-simple-email-customizer-for-woocommerce';

	/** Export IDs written by earlier builds of this plugin; still accepted on import. */
	const LEGACY_EXPORT_IDS = array( 'minimal-email-customizer-for-woocommerce' );

	/**
	 * Build the export payload.
	 *
	 * @param array $parts Any of 'general' (header, footer, design) and 'emails' (email content).
	 */
	public static function export_payload( $parts ) {
		$all  = self::all();
		$data = array(
			'plugin'      => self::EXPORT_ID,
			'format'      => 1,
			'version'     => EZMAILS_VERSION,
			'exported_at' => gmdate( 'c' ),
			'source'      => home_url(),
			'settings'    => array(),
		);
		if ( in_array( 'general', $parts, true ) ) {
			$data['settings']['general'] = $all['general'];
		}
		if ( in_array( 'emails', $parts, true ) ) {
			$data['settings']['emails'] = (object) $all['emails'];
		}
		return $data;
	}

	/**
	 * Validate and apply an imported payload. Everything is passed through the normal sanitizer.
	 *
	 * @param mixed $data  Decoded JSON.
	 * @param array $parts Parts to import ('general', 'emails').
	 * @return array|WP_Error Counts of what was imported.
	 */
	public static function import_payload( $data, $parts ) {
		if ( ! is_array( $data ) || ! isset( $data['plugin'], $data['settings'] ) || ( self::EXPORT_ID !== $data['plugin'] && ! in_array( $data['plugin'], self::LEGACY_EXPORT_IDS, true ) ) || ! is_array( $data['settings'] ) ) {
			return new WP_Error( 'ezmails_format', __( 'This file is not an export from EZmails - Simple Email Customizer for WooCommerce.', 'ezmails-simple-email-customizer-for-woocommerce' ) );
		}

		$in      = $data['settings'];
		$clean   = self::sanitize( $in );
		$current = self::all();
		$result  = array(
			'general' => 0,
			'emails'  => 0,
		);

		$general = $current['general'];
		$emails  = $current['emails'];

		if ( in_array( 'general', $parts, true ) && isset( $in['general'] ) && is_array( $in['general'] ) ) {
			$general           = $clean['general'];
			$result['general'] = 1;
		}
		if ( in_array( 'emails', $parts, true ) && isset( $in['emails'] ) && is_array( $in['emails'] ) ) {
			$emails           = array_merge( $emails, $clean['emails'] );
			$result['emails'] = count( $clean['emails'] );
		}

		if ( ! $result['general'] && ! $result['emails'] ) {
			return new WP_Error( 'ezmails_empty', __( 'The file contains nothing to import for the selected options.', 'ezmails-simple-email-customizer-for-woocommerce' ) );
		}

		update_option(
			self::OPTION,
			array(
				'general' => $general,
				'emails'  => $emails,
			)
		);
		self::$cache = null;

		return $result;
	}
}
