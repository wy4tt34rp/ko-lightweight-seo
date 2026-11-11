<?php
/**
 * Provides the branded sitemap URL without generating a second sitemap.
 *
 * @package KO_Lightweight_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KO_Lightweight_SEO_Sitemap {
	const ALIAS                  = 'sitemap.xml';
	const LEGACY_ALIAS           = 'ko-sitemap.xml';
	const REWRITE_VERSION_OPTION = 'ko_lseo_sitemap_rewrite_version';

	/**
	 * Registers the sitemap alias and its one-time upgrade refresh.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_rewrite_rule' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_refresh_rewrite_rules' ) );
		add_filter( 'pre_handle_404', array( __CLASS__, 'normalize_sitemap_status' ), 10, 2 );
		add_filter( 'redirect_canonical', array( __CLASS__, 'preserve_alias_url' ), 10, 2 );
	}

	/**
	 * Maps the primary and legacy URLs to WordPress's native sitemap index.
	 *
	 * @return void
	 */
	public static function register_rewrite_rule() {
		foreach ( self::get_aliases() as $alias ) {
			add_rewrite_rule(
				self::get_rewrite_regex( $alias ),
				'index.php?sitemap=index',
				'top'
			);
		}
	}

	/**
	 * Refreshes rewrite rules once when an active installation is upgraded.
	 *
	 * @return void
	 */
	public static function maybe_refresh_rewrite_rules() {
		if ( KO_LSEO_VERSION === get_option( self::REWRITE_VERSION_OPTION ) ) {
			return;
		}

		self::register_rewrite_rule();
		flush_rewrite_rules( false );
		update_option( self::REWRITE_VERSION_OPTION, KO_LSEO_VERSION, false );
	}

	/**
	 * Adds the alias immediately on first activation.
	 *
	 * @return void
	 */
	public static function activate() {
		self::register_rewrite_rule();
		flush_rewrite_rules( false );
		update_option( self::REWRITE_VERSION_OPTION, KO_LSEO_VERSION, false );
	}

	/**
	 * Removes the stored aliases when the plugin is deactivated.
	 *
	 * @return void
	 */
	public static function deactivate() {
		global $wp_rewrite;

		foreach ( self::get_aliases() as $alias ) {
			$regex = self::get_rewrite_regex( $alias );

			if ( isset( $wp_rewrite->extra_rules_top[ $regex ] ) ) {
				unset( $wp_rewrite->extra_rules_top[ $regex ] );
			}

			if ( isset( $wp_rewrite->extra_rules[ $regex ] ) ) {
				unset( $wp_rewrite->extra_rules[ $regex ] );
			}
		}

		flush_rewrite_rules( false );
		delete_option( self::REWRITE_VERSION_OPTION );
	}

	/**
	 * Prevents valid core sitemap requests from retaining a false 404 status.
	 *
	 * WordPress's sitemap renderer still owns the XML and may restore a 404 for
	 * an unavailable provider or empty page. This filter only short-circuits the
	 * earlier generic post-query 404 decision for recognized sitemap routes.
	 *
	 * @param bool     $preempt Existing preemption value.
	 * @param WP_Query $query   Main WordPress query.
	 * @return bool
	 */
	public static function normalize_sitemap_status( $preempt, $query ) {
		if ( false !== $preempt || ! is_object( $query ) || ! method_exists( $query, 'get' ) ) {
			return $preempt;
		}

		$sitemap    = sanitize_key( (string) $query->get( 'sitemap' ) );
		$stylesheet = sanitize_key( (string) $query->get( 'sitemap-stylesheet' ) );
		$recognized = 'index' === $sitemap || in_array( $stylesheet, array( 'index', 'sitemap' ), true );

		if ( ! $recognized && '' !== $sitemap && function_exists( 'wp_sitemaps_get_server' ) ) {
			$server     = wp_sitemaps_get_server();
			$recognized = isset( $server->registry ) && (bool) $server->registry->get_provider( $sitemap );
		}

		if ( ! $recognized ) {
			return $preempt;
		}

		if ( function_exists( 'wp_sitemaps_get_server' ) ) {
			$server = isset( $server ) ? $server : wp_sitemaps_get_server();

			if ( method_exists( $server, 'sitemaps_enabled' ) && ! $server->sitemaps_enabled() ) {
				return $preempt;
			}
		}

		status_header( 200 );

		return true;
	}

	/**
	 * Prevents WordPress from redirecting the branded alias to its default name.
	 *
	 * @param string|false $redirect_url  Proposed canonical redirect.
	 * @param string       $requested_url Requested public URL.
	 * @return string|false
	 */
	public static function preserve_alias_url( $redirect_url, $requested_url ) {
		$requested_path = wp_parse_url( (string) $requested_url, PHP_URL_PATH );

		if ( is_string( $requested_path ) ) {
			foreach ( self::get_aliases() as $alias ) {
				$alias_path = wp_parse_url( home_url( '/' . $alias ), PHP_URL_PATH );

				if ( is_string( $alias_path ) && untrailingslashit( $requested_path ) === untrailingslashit( $alias_path ) ) {
					return false;
				}
			}
		}

		return $redirect_url;
	}

	/**
	 * Returns the public branded sitemap URL.
	 *
	 * @return string
	 */
	public static function get_url() {
		return home_url( '/' . self::ALIAS );
	}

	/**
	 * Returns the previous public URL retained for existing submissions.
	 *
	 * @return string
	 */
	public static function get_legacy_url() {
		return home_url( '/' . self::LEGACY_ALIAS );
	}

	/**
	 * Returns all public aliases owned by the plugin.
	 *
	 * @return string[]
	 */
	private static function get_aliases() {
		return array( self::ALIAS, self::LEGACY_ALIAS );
	}

	/**
	 * Returns the rewrite expression used for one sitemap route.
	 *
	 * @param string $alias Public filename.
	 * @return string
	 */
	private static function get_rewrite_regex( $alias ) {
		return '^' . preg_quote( $alias, '/' ) . '$';
	}
}
