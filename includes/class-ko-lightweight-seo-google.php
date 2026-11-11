<?php
/**
 * Adds lightweight Google Analytics and Search Console connections.
 *
 * @package KO_Lightweight_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KO_Lightweight_SEO_Google {
	const SCRIPT_HANDLE = 'ko-seo-basics-google-tag';

	/**
	 * Sanitized GA4 Measurement ID for the current request.
	 *
	 * @var string
	 */
	private static $measurement_id = '';

	/**
	 * Validated complete Google tag for the current request.
	 *
	 * @var string
	 */
	private static $tag_snippet = '';

	/**
	 * Sanitized Search Console verification token for the current request.
	 *
	 * @var string
	 */
	private static $verification_token = '';

	/**
	 * Registers only the output required by configured Google values.
	 *
	 * @param array<string, int|string> $settings Validated plugin settings.
	 * @return void
	 */
	public static function init( $settings ) {
		self::$measurement_id     = isset( $settings['google_analytics_id'] ) ? (string) $settings['google_analytics_id'] : '';
		self::$tag_snippet        = isset( $settings['google_tag_snippet'] ) ? (string) $settings['google_tag_snippet'] : '';
		self::$verification_token = isset( $settings['google_verification'] ) ? (string) $settings['google_verification'] : '';

		if ( '' !== self::$tag_snippet ) {
			add_action( 'wp_head', array( __CLASS__, 'output_complete_tag' ), 20 );
		} elseif ( '' !== self::$measurement_id ) {
			add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_analytics' ), 1 );
			add_filter( 'script_loader_tag', array( __CLASS__, 'add_async_attribute' ), 10, 3 );
		}

		if ( '' !== self::$verification_token ) {
			add_action( 'wp_head', array( __CLASS__, 'output_site_verification' ), 1 );
		}
	}

	/**
	 * Loads the official Google tag automatically after an ID is configured.
	 *
	 * @return void
	 */
	public static function enqueue_analytics() {
		if ( is_admin() || is_feed() || is_robots() || is_trackback() ) {
			return;
		}

		$should_output = self::should_output_analytics();

		if ( ! $should_output ) {
			return;
		}

		$source = add_query_arg( 'id', self::$measurement_id, 'https://www.googletagmanager.com/gtag/js' );

		wp_enqueue_script( self::SCRIPT_HANDLE, $source, array(), null, false );
		wp_script_add_data( self::SCRIPT_HANDLE, 'strategy', 'async' );
		wp_add_inline_script( self::SCRIPT_HANDLE, self::get_inline_script(), 'after' );
	}

	/**
	 * Prints the validated complete Google tag when advanced setup is used.
	 *
	 * @return void
	 */
	public static function output_complete_tag() {
		if ( is_admin() || is_feed() || is_robots() || is_trackback() || ! self::should_output_analytics() ) {
			return;
		}

		echo "\n<!-- KO: SEO Basics complete Google tag -->\n";
		echo self::$tag_snippet . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Saved only after capability and Google-script validation.
	}

	/**
	 * Applies the shared consent/output filter to either Analytics setup mode.
	 *
	 * @return bool
	 */
	private static function should_output_analytics() {
		/**
		 * Filters whether the configured Google tag should load for this request.
		 *
		 * Consent integrations may return false until tracking is permitted. The
		 * third argument is true when the complete-tag field owns the output.
		 *
		 * @param bool   $should_output Whether the tag should be output.
		 * @param string $measurement_id Sanitized GA4 Measurement ID, when set.
		 * @param bool   $uses_complete_tag Whether complete saved markup is active.
		 */
		return (bool) apply_filters(
			'ko_lseo_google_analytics_should_output',
			true,
			self::$measurement_id,
			'' !== self::$tag_snippet
		);
	}

	/**
	 * Builds the small queue bootstrap used by Google's asynchronous library.
	 *
	 * @return string
	 */
	public static function get_inline_script() {
		$measurement_id = wp_json_encode( self::$measurement_id );

		return 'window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag("js",new Date());gtag("config",' . $measurement_id . ');';
	}

	/**
	 * Ensures the external Google library remains asynchronous on WordPress 6.0+.
	 *
	 * @param string $tag    Complete script element.
	 * @param string $handle Registered script handle.
	 * @param string $src    Script source URL.
	 * @return string
	 */
	public static function add_async_attribute( $tag, $handle, $src = '' ) {
		if ( self::SCRIPT_HANDLE !== $handle || false !== strpos( $tag, ' async' ) ) {
			return $tag;
		}

		return str_replace( '<script ', '<script async ', $tag );
	}

	/**
	 * Outputs the Search Console ownership token in the document head.
	 *
	 * @return void
	 */
	public static function output_site_verification() {
		/**
		 * Filters whether the Search Console verification token should render.
		 *
		 * @param bool   $should_output Whether verification should render.
		 * @param string $token         Sanitized verification token.
		 */
		$should_output = apply_filters( 'ko_lseo_google_verification_should_output', true, self::$verification_token );

		if ( ! $should_output ) {
			return;
		}

		printf( "\n<meta name=\"google-site-verification\" content=\"%s\" />\n", esc_attr( self::$verification_token ) );
	}
}
