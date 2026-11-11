<?php
/**
 * Lightweight behavior tests that run without loading WordPress.
 */

define( 'ABSPATH', __DIR__ . '/' );
define( 'KO_LSEO_VERSION', '0.7.0' );
define( 'KO_LSEO_FILE', dirname( __DIR__ ) . '/ko-lightweight-seo.php' );
define( 'KO_LSEO_PATH', dirname( __DIR__ ) . '/' );
define( 'KO_LSEO_URL', 'https://example.test/wp-content/plugins/ko-lightweight-seo/' );

$GLOBALS['ko_lseo_test_actions'] = array();
$GLOBALS['ko_lseo_test_filters'] = array();
$GLOBALS['ko_lseo_test_menu_pages'] = array();
$GLOBALS['ko_lseo_test_submenu_pages'] = array();
$GLOBALS['ko_lseo_test_legacy_pages'] = array();
$GLOBALS['ko_lseo_test_removed_submenus'] = array();
$GLOBALS['ko_lseo_test_styles'] = array();
$GLOBALS['ko_lseo_test_scripts'] = array();
$GLOBALS['ko_lseo_test_script_data'] = array();
$GLOBALS['ko_lseo_test_inline_scripts'] = array();
$GLOBALS['ko_lseo_test_settings_errors'] = array();
$GLOBALS['ko_lseo_test_options'] = array();
$GLOBALS['ko_lseo_test_rewrite_rules'] = array();
$GLOBALS['ko_lseo_test_rewrite_flushes'] = 0;
$GLOBALS['ko_lseo_test_status_headers'] = array();
$GLOBALS['ko_lseo_test_attachments'] = array(
	101 => (object) array(
		'ID'           => 101,
		'post_type'    => 'attachment',
		'post_title'   => 'IMG_4832',
		'post_excerpt' => 'Red dump truck unloading gravel',
	),
	102 => (object) array(
		'ID'           => 102,
		'post_type'    => 'attachment',
		'post_title'   => 'Blue excavator beside a trench',
		'post_excerpt' => '',
	),
	103 => (object) array(
		'ID'           => 103,
		'post_type'    => 'attachment',
		'post_title'   => 'yellow-loader-at-work',
		'post_excerpt' => '',
	),
	104 => (object) array(
		'ID'           => 104,
		'post_type'    => 'attachment',
		'post_title'   => 'IMG_4832',
		'post_excerpt' => '',
	),
);
$GLOBALS['ko_lseo_test_attachment_files'] = array(
	101 => '/tmp/IMG_4832.jpg',
	102 => '/tmp/IMG_9921.jpg',
	103 => '/tmp/yellow-loader-at-work.jpg',
	104 => '/tmp/IMG_4832.jpg',
);

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['ko_lseo_test_actions'][ $hook ][] = array( $callback, $priority, $accepted_args );
}

function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['ko_lseo_test_filters'][ $hook ][] = array( $callback, $priority, $accepted_args );
}

function add_menu_page( $page_title, $menu_title, $capability, $menu_slug, $callback = '', $icon_url = '', $position = null ) {
	$GLOBALS['ko_lseo_test_menu_pages'][] = compact( 'page_title', 'menu_title', 'capability', 'menu_slug', 'callback', 'icon_url', 'position' );

	return 'toplevel_page_' . $menu_slug;
}

function add_submenu_page( $parent_slug, $page_title, $menu_title, $capability, $menu_slug, $callback = '', $position = null ) {
	$GLOBALS['ko_lseo_test_submenu_pages'][] = compact( 'parent_slug', 'page_title', 'menu_title', 'capability', 'menu_slug', 'callback', 'position' );

	return 'seo-basics_page_' . $menu_slug;
}

function add_options_page( $page_title, $menu_title, $capability, $menu_slug, $callback = '', $position = null ) {
	$GLOBALS['ko_lseo_test_legacy_pages'][] = array( 'parent_slug' => 'options-general.php', 'menu_slug' => $menu_slug, 'callback' => $callback );

	return 'settings_page_' . $menu_slug;
}

function add_media_page( $page_title, $menu_title, $capability, $menu_slug, $callback = '', $position = null ) {
	$GLOBALS['ko_lseo_test_legacy_pages'][] = array( 'parent_slug' => 'upload.php', 'menu_slug' => $menu_slug, 'callback' => $callback );

	return 'media_page_' . $menu_slug;
}

function add_management_page( $page_title, $menu_title, $capability, $menu_slug, $callback = '', $position = null ) {
	$GLOBALS['ko_lseo_test_legacy_pages'][] = array( 'parent_slug' => 'tools.php', 'menu_slug' => $menu_slug, 'callback' => $callback );

	return 'tools_page_' . $menu_slug;
}

function remove_submenu_page( $menu_slug, $submenu_slug ) {
	$GLOBALS['ko_lseo_test_removed_submenus'][] = array( $menu_slug, $submenu_slug );

	return array();
}

function apply_filters( $hook, $value ) {
	return $value;
}

function current_user_can( $capability ) {
	return true;
}

function wp_unslash( $value ) {
	return $value;
}

function absint( $value ) {
	return abs( (int) $value );
}

function add_settings_error( $setting, $code, $message, $type = 'error' ) {
	$GLOBALS['ko_lseo_test_settings_errors'][] = array( $setting, $code, $message, $type );
}

function plugin_basename( $file ) {
	return basename( dirname( $file ) ) . '/' . basename( $file );
}

function get_post( $post_id ) {
	return $GLOBALS['ko_lseo_test_attachments'][ $post_id ] ?? null;
}

function wp_attachment_is_image( $attachment_id ) {
	return isset( $GLOBALS['ko_lseo_test_attachments'][ $attachment_id ] );
}

function get_attached_file( $attachment_id ) {
	return $GLOBALS['ko_lseo_test_attachment_files'][ $attachment_id ] ?? false;
}

function wp_basename( $path ) {
	return basename( $path );
}

function get_option( $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['ko_lseo_test_options'] ) ? $GLOBALS['ko_lseo_test_options'][ $key ] : $default;
}

function update_option( $key, $value, $autoload = null ) {
	$GLOBALS['ko_lseo_test_options'][ $key ] = $value;

	return true;
}

function delete_option( $key ) {
	unset( $GLOBALS['ko_lseo_test_options'][ $key ] );

	return true;
}

function add_rewrite_rule( $regex, $query, $after = 'bottom' ) {
	$GLOBALS['ko_lseo_test_rewrite_rules'][ $regex ] = array( $query, $after );
}

function flush_rewrite_rules( $hard = true ) {
	$GLOBALS['ko_lseo_test_rewrite_flushes']++;
}

function status_header( $code, $description = '' ) {
	$GLOBALS['ko_lseo_test_status_headers'][] = array( (int) $code, (string) $description );
}

function home_url( $path = '' ) {
	return 'https://example.test' . $path;
}

function untrailingslashit( $value ) {
	return rtrim( (string) $value, '/\\' );
}

function get_bloginfo( $show = '' ) {
	if ( 'charset' === $show ) {
		return 'UTF-8';
	}

	return 'Example Site';
}

function is_admin() {
	return false;
}

function is_feed() {
	return false;
}

function is_robots() {
	return false;
}

function is_trackback() {
	return false;
}

function sanitize_text_field( $value ) {
	return trim( preg_replace( '/\s+/', ' ', strip_tags( (string) $value ) ) );
}

function sanitize_textarea_field( $value ) {
	return trim( strip_tags( (string) $value ) );
}

function sanitize_key( $value ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
}

function esc_url_raw( $url, $protocols = null ) {
	$url    = trim( (string) $url );
	$scheme = parse_url( $url, PHP_URL_SCHEME );

	if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
		return '';
	}

	if ( is_array( $protocols ) && ! in_array( $scheme, $protocols, true ) ) {
		return '';
	}

	return $url;
}

function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component );
}

function add_query_arg( $key, $value, $url ) {
	return $url . '?' . rawurlencode( $key ) . '=' . rawurlencode( $value );
}

function wp_enqueue_script( $handle, $src = '', $dependencies = array(), $version = false, $in_footer = false ) {
	$GLOBALS['ko_lseo_test_scripts'][ $handle ] = array( $src, $dependencies, $version, $in_footer );
}

function wp_enqueue_style( $handle, $src = '', $dependencies = array(), $version = false, $media = 'all' ) {
	$GLOBALS['ko_lseo_test_styles'][ $handle ] = array( $src, $dependencies, $version, $media );
}

function wp_script_add_data( $handle, $key, $value ) {
	$GLOBALS['ko_lseo_test_script_data'][ $handle ][ $key ] = $value;

	return true;
}

function wp_add_inline_script( $handle, $data, $position = 'after' ) {
	$GLOBALS['ko_lseo_test_inline_scripts'][ $handle ][] = array( $data, $position );

	return true;
}

function wp_json_encode( $value ) {
	return json_encode( $value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
}

function esc_attr( $value ) {
	return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}

function strip_shortcodes( $value ) {
	return preg_replace( '/\[[^\]]+\]/', '', (string) $value );
}

function wp_strip_all_tags( $value ) {
	return strip_tags( (string) $value );
}

final class KO_LSEO_Test_Query {
	private $values;

	public function __construct( $values ) {
		$this->values = $values;
	}

	public function get( $key ) {
		return isset( $this->values[ $key ] ) ? $this->values[ $key ] : '';
	}
}

require dirname( __DIR__ ) . '/includes/class-ko-lightweight-seo-settings.php';
require dirname( __DIR__ ) . '/includes/class-ko-lightweight-seo-sitemap.php';
require dirname( __DIR__ ) . '/includes/class-ko-lightweight-seo-meta.php';
require dirname( __DIR__ ) . '/includes/class-ko-lightweight-seo-images.php';
require dirname( __DIR__ ) . '/includes/class-ko-lightweight-seo-audit.php';
require dirname( __DIR__ ) . '/includes/class-ko-lightweight-seo-headings.php';
require dirname( __DIR__ ) . '/includes/class-ko-lightweight-seo-google.php';
require dirname( __DIR__ ) . '/includes/class-ko-lightweight-seo.php';

$failures = array();

/**
 * Records a strict comparison failure without stopping the full test run.
 *
 * @param mixed  $expected Expected value.
 * @param mixed  $actual   Actual value.
 * @param string $message  Behavior under test.
 * @return void
 */
function ko_lseo_assert_same( $expected, $actual, $message ) {
	global $failures;

	if ( $expected !== $actual ) {
		$failures[] = $message . "\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true );
	}
}

/**
 * Records a boolean assertion failure.
 *
 * @param bool   $condition Required condition.
 * @param string $message   Behavior under test.
 * @return void
 */
function ko_lseo_assert_true( $condition, $message ) {
	ko_lseo_assert_same( true, (bool) $condition, $message );
}

$settings = KO_Lightweight_SEO_Settings::sanitize_settings(
	array(
		'enable_metadata'      => '1',
		'enable_schema'        => '1',
		'enable_heading_guard' => '1',
		'organization_name'    => '  Example <b>Company</b>  ',
		'image_alt_autofill'   => '1',
		'google_analytics_id'   => ' g-test12345 ',
		'google_verification'   => '<meta name="google-site-verification" content="verify_token-123" />',
	)
);

ko_lseo_assert_same( 1, $settings['enable_metadata'], 'Metadata setting is normalized to an integer flag.' );
ko_lseo_assert_same( 0, $settings['enable_social'], 'Missing checkbox values are disabled.' );
ko_lseo_assert_same( 1, $settings['enable_heading_guard'], 'The optional heading guard is normalized to an integer flag.' );
ko_lseo_assert_same( 'Example Company', $settings['organization_name'], 'Organization names are sanitized.' );
ko_lseo_assert_same( 'G-TEST12345', $settings['google_analytics_id'], 'A GA4 Measurement ID is normalized and saved without a second enable flag.' );
ko_lseo_assert_same( 'verify_token-123', $settings['google_verification'], 'The Search Console token is extracted from a complete verification meta tag.' );
ko_lseo_assert_same( '', KO_Lightweight_SEO_Settings::sanitize_google_analytics_id( 'UA-123456-1' ), 'Legacy Analytics IDs are rejected.' );
ko_lseo_assert_same( '', KO_Lightweight_SEO_Settings::sanitize_google_verification( '<script>alert(1)</script>' ), 'Executable markup is rejected as a verification token.' );

$complete_google_tag = '<!-- Google tag (gtag.js) -->' . "\n"
	. '<script async src="https://www.googletagmanager.com/gtag/js?id=G-CUSTOM123"></script>' . "\n"
	. '<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag("config","G-CUSTOM123",{"send_page_view":false});</script>';

ko_lseo_assert_same( $complete_google_tag, KO_Lightweight_SEO_Settings::sanitize_google_tag_snippet( $complete_google_tag ), 'A complete customized Google tag is preserved exactly.' );
ko_lseo_assert_same( $complete_google_tag, KO_Lightweight_SEO_Settings::sanitize_google_tag_snippet( '<script src="https://evil.example/tag.js"></script><script>dataLayer=[];</script>', $complete_google_tag ), 'A non-Google script source is rejected without erasing the previous valid tag.' );
ko_lseo_assert_same( $complete_google_tag, KO_Lightweight_SEO_Settings::sanitize_google_tag_snippet( '<script>alert(1)</script>', $complete_google_tag ), 'Unrelated executable markup is rejected without erasing the previous valid tag.' );

ko_lseo_assert_same( 'https://example.test/sitemap.xml', KO_Lightweight_SEO_Sitemap::get_url(), 'The primary sitemap URL uses the conventional filename.' );
ko_lseo_assert_same( 'https://example.test/ko-sitemap.xml', KO_Lightweight_SEO_Sitemap::get_legacy_url(), 'The previous branded sitemap URL remains available for compatibility.' );

KO_Lightweight_SEO_Sitemap::maybe_refresh_rewrite_rules();

ko_lseo_assert_same(
	array( 'index.php?sitemap=index', 'top' ),
	$GLOBALS['ko_lseo_test_rewrite_rules']['^sitemap\.xml$'],
	'The primary sitemap path maps to the WordPress core sitemap index.'
);
ko_lseo_assert_same(
	array( 'index.php?sitemap=index', 'top' ),
	$GLOBALS['ko_lseo_test_rewrite_rules']['^ko\-sitemap\.xml$'],
	'The previous branded sitemap path still maps to the WordPress core sitemap index.'
);
ko_lseo_assert_same( 1, $GLOBALS['ko_lseo_test_rewrite_flushes'], 'The sitemap rewrite rules are refreshed once for a new plugin version.' );
ko_lseo_assert_same( '0.7.0', get_option( KO_Lightweight_SEO_Sitemap::REWRITE_VERSION_OPTION ), 'The refreshed sitemap rewrite version is stored.' );

KO_Lightweight_SEO_Sitemap::maybe_refresh_rewrite_rules();

ko_lseo_assert_same( 1, $GLOBALS['ko_lseo_test_rewrite_flushes'], 'Current sitemap rewrite rules are not flushed again.' );
ko_lseo_assert_same(
	false,
	KO_Lightweight_SEO_Sitemap::preserve_alias_url( 'https://example.test/wp-sitemap.xml', 'https://example.test/sitemap.xml' ),
	'The primary sitemap request is not redirected to WordPress\'s default sitemap name.'
);
ko_lseo_assert_same(
	false,
	KO_Lightweight_SEO_Sitemap::preserve_alias_url( 'https://example.test/sitemap.xml', 'https://example.test/ko-sitemap.xml' ),
	'The previous branded sitemap request remains protected from a canonical redirect.'
);
ko_lseo_assert_same(
	'https://example.test/preferred/',
	KO_Lightweight_SEO_Sitemap::preserve_alias_url( 'https://example.test/preferred/', 'https://example.test/old/' ),
	'Unrelated canonical redirects are preserved.'
);

$GLOBALS['wp_rewrite'] = (object) array(
	'extra_rules_top' => array(
		'^sitemap\.xml$'     => 'index.php?sitemap=index',
		'^ko\-sitemap\.xml$' => 'index.php?sitemap=index',
	),
	'extra_rules'     => array(),
);

KO_Lightweight_SEO_Sitemap::deactivate();

ko_lseo_assert_same( array(), $GLOBALS['wp_rewrite']->extra_rules_top, 'Deactivation removes the branded rule before flushing stored rewrites.' );
ko_lseo_assert_same( false, get_option( KO_Lightweight_SEO_Sitemap::REWRITE_VERSION_OPTION, false ), 'Deactivation removes the stored sitemap rewrite version.' );
ko_lseo_assert_same( 2, $GLOBALS['ko_lseo_test_rewrite_flushes'], 'Deactivation performs one rewrite refresh after removing the alias.' );

$sitemap_status = KO_Lightweight_SEO_Sitemap::normalize_sitemap_status(
	false,
	new KO_LSEO_Test_Query( array( 'sitemap' => 'index' ) )
);

ko_lseo_assert_same( true, $sitemap_status, 'A recognized sitemap index bypasses WordPress\'s generic post-query 404 decision.' );
ko_lseo_assert_same( array( 200, '' ), end( $GLOBALS['ko_lseo_test_status_headers'] ), 'A recognized sitemap index receives an explicit successful HTTP status.' );

$status_count = count( $GLOBALS['ko_lseo_test_status_headers'] );

ko_lseo_assert_same(
	false,
	KO_Lightweight_SEO_Sitemap::normalize_sitemap_status( false, new KO_LSEO_Test_Query( array() ) ),
	'An unrelated request retains normal WordPress 404 handling.'
);
ko_lseo_assert_same( $status_count, count( $GLOBALS['ko_lseo_test_status_headers'] ), 'An unrelated request does not receive a forced successful status.' );
ko_lseo_assert_same(
	true,
	KO_Lightweight_SEO_Sitemap::normalize_sitemap_status( true, new KO_LSEO_Test_Query( array( 'sitemap' => 'index' ) ) ),
	'An earlier 404 preemption value is preserved without interference.'
);

ko_lseo_assert_same(
	'https://example.test/preferred-page/',
	KO_Lightweight_SEO_Settings::sanitize_canonical_url( 'https://example.test/preferred-page/' ),
	'Absolute HTTPS canonical URLs are accepted.'
);
ko_lseo_assert_same( '', KO_Lightweight_SEO_Settings::sanitize_canonical_url( 'javascript:alert(1)' ), 'Unsafe canonical schemes are rejected.' );
ko_lseo_assert_same( '', KO_Lightweight_SEO_Settings::sanitize_canonical_url( '/relative-page/' ), 'Relative canonical URLs are rejected.' );

ko_lseo_assert_same( '', KO_Lightweight_SEO_Images::get_filename_suggestion_from_name( 'IMG_4832.jpg' ), 'Camera filenames never become alt suggestions.' );
ko_lseo_assert_same( 'IMG 4832', KO_Lightweight_SEO_Images::get_filename_fallback_from_name( 'IMG_4832.jpg' ), 'A generic filename remains available as a manual-review fallback.' );
ko_lseo_assert_same( '', KO_Lightweight_SEO_Images::get_filename_suggestion_from_name( 'AdobeStock_593004281.jpeg' ), 'Stock-library IDs never become alt suggestions.' );
ko_lseo_assert_same(
	'Red dump truck at construction site',
	KO_Lightweight_SEO_Images::get_filename_suggestion_from_name( 'red-dump-truck-at-construction-site.jpg' ),
	'Descriptive filenames become readable review-only candidates.'
);
ko_lseo_assert_same( false, KO_Lightweight_SEO_Images::is_trustworthy_title( 'red-dump-truck', 'red-dump-truck' ), 'Default filename titles are not treated as human-authored.' );
ko_lseo_assert_same( true, KO_Lightweight_SEO_Images::is_trustworthy_title( 'Red dump truck at a construction site', 'IMG_4832' ), 'Meaningful edited titles are trusted.' );

$caption_suggestion = KO_Lightweight_SEO_Images::get_suggestion( 101, false );
$title_suggestion   = KO_Lightweight_SEO_Images::get_suggestion( 102, false );
$review_suggestion  = KO_Lightweight_SEO_Images::get_suggestion( 103, true );
$fallback_suggestion = KO_Lightweight_SEO_Images::get_suggestion( 104, true );

ko_lseo_assert_same( 'Red dump truck unloading gravel', $caption_suggestion['text'], 'Human-authored captions are high-confidence candidates.' );
ko_lseo_assert_same( 'high', $caption_suggestion['confidence'], 'Caption candidates may be used by the opt-in upload workflow.' );
ko_lseo_assert_same( 'Blue excavator beside a trench', $title_suggestion['text'], 'Edited attachment titles are high-confidence candidates.' );
ko_lseo_assert_same( 'Yellow loader at work', $review_suggestion['text'], 'Descriptive default filenames remain available for review.' );
ko_lseo_assert_same( 'review', $review_suggestion['confidence'], 'Filename candidates are never marked safe for automatic assignment.' );
ko_lseo_assert_same( 'IMG 4832', $fallback_suggestion['text'], 'The audit uses the filename when no safer suggestion exists.' );
ko_lseo_assert_same( 'Filename fallback — review required', $fallback_suggestion['source'], 'Generic filename fallbacks remain clearly labeled for review.' );
ko_lseo_assert_same( 25, KO_Lightweight_SEO_Images::sanitize_per_page( 0 ), 'The image audit has a lightweight default page size.' );
ko_lseo_assert_same( 5, KO_Lightweight_SEO_Images::sanitize_per_page( 2 ), 'Very small image-audit page sizes are clamped.' );
ko_lseo_assert_same( 40, KO_Lightweight_SEO_Images::sanitize_per_page( 40 ), 'A requested image-audit page size inside the allowed range is preserved.' );
ko_lseo_assert_same( 100, KO_Lightweight_SEO_Images::sanitize_per_page( 500 ), 'Large image-audit page sizes are capped at 100.' );
ko_lseo_assert_same(
	array( 104 ),
	KO_Lightweight_SEO_Images::resolve_save_ids(
		array(),
		array( 104 => 'A yoga student in tree pose' ),
		array( 104 => 'IMG 4832' )
	),
	'Typing a changed non-empty alt value saves the row even when its checkbox was missed.'
);
ko_lseo_assert_same(
	array(),
	KO_Lightweight_SEO_Images::resolve_save_ids(
		array(),
		array( 104 => 'IMG 4832' ),
		array( 104 => 'IMG 4832' )
	),
	'An unchanged filename fallback is never treated as reviewed merely because the form included it.'
);
ko_lseo_assert_same(
	array( 104 ),
	KO_Lightweight_SEO_Images::resolve_save_ids(
		array( 104 ),
		array( 104 => 'IMG 4832' ),
		array( 104 => 'IMG 4832' )
	),
	'Checking a row explicitly accepts its unchanged review candidate.'
);
ko_lseo_assert_same(
	array(),
	KO_Lightweight_SEO_Images::resolve_save_ids(
		array(),
		array( 104 => 'A yoga student in tree pose' ),
		array( 104 => 'IMG 4832' ),
		array( 104 => 'excluded' )
	),
	'Explicitly unchecking an edited row excludes it from the server-side changed-field fallback.'
);

$query_args = KO_Lightweight_SEO_Meta::filter_sitemap_query_args(
	array(
		'post_status' => 'publish',
		'meta_query'  => array(
			array(
				'key'   => '_existing_rule',
				'value' => 'yes',
			),
		),
	),
	'post'
);

ko_lseo_assert_same( 'AND', $query_args['meta_query']['relation'], 'Sitemap exclusion combines with existing metadata constraints.' );
ko_lseo_assert_same( '_existing_rule', $query_args['meta_query'][0][0]['key'], 'Existing sitemap metadata constraints are preserved.' );
ko_lseo_assert_same( 'OR', $query_args['meta_query'][1]['relation'], 'Noindex exclusion allows posts without the private metadata key.' );

$long_description = str_repeat( 'Useful descriptive content ', 20 );
$trimmed          = KO_Lightweight_SEO_Meta::trim_description( $long_description, 160 );
ko_lseo_assert_true( strlen( $trimmed ) <= 163, 'Descriptions are trimmed to the requested length plus a multibyte ellipsis.' );
ko_lseo_assert_true( '…' === substr( $trimmed, -3 ), 'Trimmed descriptions end with an ellipsis.' );

$html = '<!doctype html><html lang="en-US"><head>'
	. '<title>Example page</title>'
	. '<meta name="description" content="Useful example description">'
	. '<link rel="canonical" href="https://example.test/page/">'
	. '<meta property="og:title" content="Example page">'
	. '<meta property="og:image" content="https://example.test/image.jpg">'
	. '<script type="application/ld+json">{"@context":"https://schema.org"}</script>'
	. '</head><body><h1>Example page</h1>'
	. '<img src="described.jpg" alt="A described image">'
	. '<img src="decorative.jpg" alt="">'
	. '<img src="missing.jpg">'
	. '</body></html>';
$audit = KO_Lightweight_SEO_Audit::audit_html( $html, 'Example page' );

ko_lseo_assert_same( 1, $audit['title_count'], 'The HTML audit counts document titles.' );
ko_lseo_assert_same( 'Example page', $audit['title'], 'The HTML audit resolves the document title.' );
ko_lseo_assert_same( 1, $audit['canonical_count'], 'The HTML audit counts canonical links.' );
ko_lseo_assert_same( 3, $audit['image_count'], 'The HTML audit counts rendered images.' );
ko_lseo_assert_same( 1, $audit['missing_alt_count'], 'The HTML audit separates missing alt attributes.' );
ko_lseo_assert_same( 1, $audit['empty_alt_count'], 'The HTML audit separates intentionally empty alt attributes for review.' );
ko_lseo_assert_same( 'en-US', $audit['language'], 'The HTML audit reads the document language.' );
ko_lseo_assert_same( true, $audit['title_heading_is_h1'], 'The HTML audit maps the WordPress title to its rendered H1.' );
ko_lseo_assert_same( 0, $audit['secondary_h1_count'], 'The HTML audit reports no secondary H1 when the title is the only H1.' );

$multiple_h1_html  = '<main><h2 class="hero-title"><span>Example &amp; Company</span></h2><h1>Services</h1><h1>Contact</h1></main>';
$multiple_h1_audit = KO_Lightweight_SEO_Audit::audit_html( $multiple_h1_html, 'Example & Company' );

ko_lseo_assert_same( 'h2', $multiple_h1_audit['title_heading_tag'], 'The audit reports when the page title is rendered at the wrong heading level.' );
ko_lseo_assert_same( array( 'Services', 'Contact' ), $multiple_h1_audit['secondary_h1_headings'], 'Every non-title H1 is reported as an H2 recommendation.' );

$normalized_headings = KO_Lightweight_SEO_Headings::normalize_content_headings( $multiple_h1_html, 'Example & Company' );

ko_lseo_assert_true( false !== strpos( $normalized_headings, '<h1 class="hero-title"><span>Example &amp; Company</span></h1>' ), 'The first exact page-title heading is promoted to H1 without losing its markup.' );
ko_lseo_assert_true( false !== strpos( $normalized_headings, '<h2>Services</h2>' ), 'A secondary content H1 is demoted to H2.' );
ko_lseo_assert_true( false !== strpos( $normalized_headings, '<h2>Contact</h2>' ), 'Every additional content H1 is demoted to H2.' );

$unmatched_headings = '<section><h1>Unrelated hero</h1><p>Body copy.</p></section>';
ko_lseo_assert_same( $unmatched_headings, KO_Lightweight_SEO_Headings::normalize_content_headings( $unmatched_headings, 'Stored post title' ), 'Content remains byte-for-byte unchanged when no exact title heading can be identified.' );

KO_Lightweight_SEO::init();

ko_lseo_assert_true( isset( $GLOBALS['ko_lseo_test_filters']['pre_get_document_title'] ), 'The metadata title filter is registered.' );
ko_lseo_assert_true( isset( $GLOBALS['ko_lseo_test_filters']['wp_sitemaps_posts_query_args'] ), 'The core sitemap filter is registered.' );
ko_lseo_assert_true( isset( $GLOBALS['ko_lseo_test_actions']['init'] ), 'The branded core-sitemap alias is registered.' );
ko_lseo_assert_true( isset( $GLOBALS['ko_lseo_test_filters']['pre_handle_404'] ), 'Valid sitemap requests register the narrow false-404 safeguard.' );
ko_lseo_assert_true( isset( $GLOBALS['ko_lseo_test_filters']['redirect_canonical'] ), 'The branded sitemap name is protected from a core canonical redirect.' );
ko_lseo_assert_true( isset( $GLOBALS['ko_lseo_test_filters']['the_content'] ), 'The opt-in heading guard registers its late main-content filter.' );
ko_lseo_assert_true( isset( $GLOBALS['ko_lseo_test_actions']['wp_enqueue_scripts'] ), 'A configured Analytics ID automatically registers the Google tag loader.' );
ko_lseo_assert_true( isset( $GLOBALS['ko_lseo_test_filters']['script_loader_tag'] ), 'The Google tag registers its asynchronous compatibility filter.' );
ko_lseo_assert_true( isset( $GLOBALS['ko_lseo_test_actions']['add_attachment'] ), 'The conservative upload-time alt check is registered.' );
ko_lseo_assert_true( isset( $GLOBALS['ko_lseo_test_actions']['admin_post_ko_lseo_image_alt_bulk'] ), 'The reviewed alt-text handler is registered.' );
ko_lseo_assert_true( isset( $GLOBALS['ko_lseo_test_actions']['admin_menu'] ), 'Admin audit and settings screens are registered.' );

KO_Lightweight_SEO_Settings::register_settings_page();
KO_Lightweight_SEO_Images::register_audit_page();
KO_Lightweight_SEO_Audit::register_audit_page();

ko_lseo_assert_same( 'SEO Basics', $GLOBALS['ko_lseo_test_menu_pages'][0]['menu_title'], 'The plugin owns a primary SEO Basics dashboard menu.' );
ko_lseo_assert_same( 'ko-lightweight-seo', $GLOBALS['ko_lseo_test_menu_pages'][0]['menu_slug'], 'The primary menu retains the established plugin page slug.' );
ko_lseo_assert_same( 'dashicons-search', $GLOBALS['ko_lseo_test_menu_pages'][0]['icon_url'], 'The primary menu uses the lightweight core search icon.' );
ko_lseo_assert_same(
	array( 'Settings', 'Image Alt Text', 'Live HTML Audit' ),
	array_column( $GLOBALS['ko_lseo_test_submenu_pages'], 'menu_title' ),
	'All three interfaces sit together beneath SEO Basics.'
);
ko_lseo_assert_same(
	array( 'ko-lightweight-seo', 'ko-lightweight-seo-images', 'ko-lightweight-seo-audit' ),
	array_column( $GLOBALS['ko_lseo_test_submenu_pages'], 'menu_slug' ),
	'Unified navigation keeps the established admin page slugs.'
);
ko_lseo_assert_same(
	array(
		array( 'options-general.php', 'ko-lightweight-seo' ),
		array( 'upload.php', 'ko-lightweight-seo-images' ),
		array( 'tools.php', 'ko-lightweight-seo-audit' ),
	),
	$GLOBALS['ko_lseo_test_removed_submenus'],
	'Legacy Settings, Media, and Tools registrations remain routable without duplicate menu items.'
);

$_GET['page'] = 'ko-lightweight-seo-images';
KO_Lightweight_SEO_Settings::enqueue_admin_assets( 'seo-basics_page_ko-lightweight-seo-images' );
unset( $_GET['page'] );

ko_lseo_assert_same( '0.7.0', $GLOBALS['ko_lseo_test_styles']['ko-lightweight-seo-admin'][2], 'The unified admin screens load the current KO stylesheet.' );
ko_lseo_assert_same( '0.7.0', $GLOBALS['ko_lseo_test_scripts']['ko-lightweight-seo-image-audit'][2], 'The image workflow script loads on its new submenu screen.' );

$admin_css = file_get_contents( dirname( __DIR__ ) . '/assets/admin.css' );
ko_lseo_assert_true( false !== strpos( $admin_css, 'width: 98.5%;' ), 'The KO admin wrapper uses the requested 98.5 percent width.' );
ko_lseo_assert_true( false !== strpos( $admin_css, '.ko-lseo-panel,' ) && false !== strpos( $admin_css, 'width: 100%;' ), 'The interface panels fill the widened KO wrapper.' );

KO_Lightweight_SEO_Google::enqueue_analytics();

ko_lseo_assert_same( 'https://www.googletagmanager.com/gtag/js?id=G-TEST12345', $GLOBALS['ko_lseo_test_scripts'][ KO_Lightweight_SEO_Google::SCRIPT_HANDLE ][0], 'The configured Measurement ID is added to the official Google tag URL.' );
ko_lseo_assert_same( 'async', $GLOBALS['ko_lseo_test_script_data'][ KO_Lightweight_SEO_Google::SCRIPT_HANDLE ]['strategy'], 'The Google tag uses WordPress\'s asynchronous loading strategy.' );
ko_lseo_assert_true( false !== strpos( $GLOBALS['ko_lseo_test_inline_scripts'][ KO_Lightweight_SEO_Google::SCRIPT_HANDLE ][0][0], 'gtag("config","G-TEST12345")' ), 'The inline queue automatically configures the saved Analytics stream.' );
ko_lseo_assert_true( false !== strpos( KO_Lightweight_SEO_Google::add_async_attribute( '<script src="tag.js"></script>', KO_Lightweight_SEO_Google::SCRIPT_HANDLE ), '<script async ' ), 'WordPress versions without native loading strategies receive the async attribute.' );

$analytics_loader_count = count( $GLOBALS['ko_lseo_test_actions']['wp_enqueue_scripts'] );
KO_Lightweight_SEO_Google::init(
	array(
		'google_analytics_id' => 'G-IGNORED123',
		'google_tag_snippet'  => $complete_google_tag,
		'google_verification' => 'verify_token-123',
	)
);

ko_lseo_assert_same( $analytics_loader_count, count( $GLOBALS['ko_lseo_test_actions']['wp_enqueue_scripts'] ), 'A complete Google tag takes precedence and does not register a second generated loader.' );

ob_start();
KO_Lightweight_SEO_Google::output_complete_tag();
$complete_tag_output = ob_get_clean();

ko_lseo_assert_true( false !== strpos( $complete_tag_output, 'send_page_view' ), 'The complete customized Google tag renders without removing its configuration.' );

ob_start();
KO_Lightweight_SEO_Google::output_site_verification();
$verification_output = ob_get_clean();

ko_lseo_assert_true( false !== strpos( $verification_output, 'name="google-site-verification" content="verify_token-123"' ), 'The sanitized Search Console verification token renders in the document head.' );

if ( $failures ) {
	fwrite( STDERR, implode( "\n\n", $failures ) . "\n" );
	exit( 1 );
}

echo "All KO: SEO Basics tests passed.\n";
