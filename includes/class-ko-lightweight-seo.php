<?php
/**
 * Coordinates the plugin modules and protects sites from duplicate SEO output.
 *
 * @package KO_Lightweight_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KO_Lightweight_SEO {
	/**
	 * Detected full-service SEO plugin name, when one is active.
	 *
	 * @var string
	 */
	private static $conflicting_plugin = '';

	/**
	 * Registers admin tools and conditionally enables frontend metadata.
	 *
	 * The image and audit modules remain available during an SEO-plugin conflict
	 * because neither module emits competing metadata on normal page requests.
	 *
	 * @return void
	 */
	public static function init() {
		self::$conflicting_plugin = self::detect_conflicting_plugin();

		KO_Lightweight_SEO_Settings::init( self::$conflicting_plugin );
		KO_Lightweight_SEO_Sitemap::init();
		KO_Lightweight_SEO_Images::init();
		KO_Lightweight_SEO_Audit::init();

		$settings = KO_Lightweight_SEO_Settings::get_settings();
		KO_Lightweight_SEO_Headings::init( $settings );
		KO_Lightweight_SEO_Google::init( $settings );

		if ( '' === self::$conflicting_plugin && ! empty( $settings['enable_metadata'] ) ) {
			KO_Lightweight_SEO_Meta::init( $settings );
		}
	}

	/**
	 * Adds the compact settings record to WordPress's normal autoloaded options.
	 *
	 * The complete record is intentionally tiny. Autoloading it avoids a separate
	 * database query on frontend requests where metadata output is enabled.
	 *
	 * @return void
	 */
	public static function activate() {
		add_option(
			KO_Lightweight_SEO_Settings::OPTION_KEY,
			KO_Lightweight_SEO_Settings::get_defaults(),
			'',
			true
		);

		KO_Lightweight_SEO_Sitemap::activate();
	}

	/**
	 * Removes the branded sitemap rewrite when the plugin is deactivated.
	 *
	 * @return void
	 */
	public static function deactivate() {
		KO_Lightweight_SEO_Sitemap::deactivate();
	}

	/**
	 * Returns the active SEO suite that would produce duplicate metadata.
	 *
	 * This intentionally checks public bootstrap markers instead of reading or
	 * depending on another plugin's private implementation.
	 *
	 * @return string Conflicting plugin name, or an empty string.
	 */
	public static function detect_conflicting_plugin() {
		$conflict = '';

		if ( defined( 'WPSEO_VERSION' ) || class_exists( 'WPSEO_Options', false ) ) {
			$conflict = 'Yoast SEO';
		} elseif ( defined( 'RANK_MATH_VERSION' ) || class_exists( 'RankMath', false ) ) {
			$conflict = 'Rank Math SEO';
		} elseif ( defined( 'AIOSEO_VERSION' ) || function_exists( 'aioseo' ) ) {
			$conflict = 'All in One SEO';
		} elseif ( defined( 'SEOPRESS_VERSION' ) || defined( 'SEOPRESS_PRO_VERSION' ) ) {
			$conflict = 'SEOPress';
		}

		/**
		 * Filters the detected SEO suite name.
		 *
		 * Return a non-empty name to suppress KO metadata while preserving the
		 * image-alt and diagnostic tools.
		 *
		 * @param string $conflict Detected plugin name.
		 */
		return (string) apply_filters( 'ko_lseo_conflicting_plugin', $conflict );
	}

	/**
	 * Returns the detected SEO-suite conflict for admin messaging.
	 *
	 * @return string
	 */
	public static function get_conflicting_plugin() {
		return self::$conflicting_plugin;
	}
}
