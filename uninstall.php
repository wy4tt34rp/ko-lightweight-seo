<?php
/**
 * Removes plugin-owned settings and control metadata during uninstall.
 *
 * Reviewed alt text is retained because it is user-approved attachment content,
 * not disposable plugin configuration.
 *
 * @package KO_Lightweight_SEO
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Removes KO SEO data from the current site in the active blog context.
 *
 * @return void
 */
function ko_lseo_uninstall_site_data() {
	delete_option( 'ko_lightweight_seo_settings' );
	delete_option( 'ko_lseo_sitemap_rewrite_version' );
	delete_post_meta_by_key( '_ko_lseo_title' );
	delete_post_meta_by_key( '_ko_lseo_description' );
	delete_post_meta_by_key( '_ko_lseo_canonical' );
	delete_post_meta_by_key( '_ko_lseo_noindex' );
	delete_post_meta_by_key( '_ko_lseo_nofollow' );
	delete_post_meta_by_key( '_ko_lseo_alt_state' );
}

if ( is_multisite() ) {
	$site_ids = get_sites( array( 'fields' => 'ids' ) );

	foreach ( $site_ids as $site_id ) {
		switch_to_blog( $site_id );
		ko_lseo_uninstall_site_data();
		restore_current_blog();
	}
} else {
	ko_lseo_uninstall_site_data();
}
