<?php
/**
 * Plugin Name: KO: SEO Basics
 * Description: Lightweight technical SEO for titles, descriptions, canonicals, robots, sitemaps, schema, social tags, heading checks, image alt text, and live audits.
 * Version: 0.7.0
 * Author: KO
 * License: GPL-2.0-or-later
 * Text Domain: ko-lightweight-seo
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KO_LSEO_VERSION', '0.7.0' );
define( 'KO_LSEO_FILE', __FILE__ );
define( 'KO_LSEO_PATH', plugin_dir_path( __FILE__ ) );
define( 'KO_LSEO_URL', plugin_dir_url( __FILE__ ) );

require_once KO_LSEO_PATH . 'includes/class-ko-lightweight-seo-settings.php';
require_once KO_LSEO_PATH . 'includes/class-ko-lightweight-seo-sitemap.php';
require_once KO_LSEO_PATH . 'includes/class-ko-lightweight-seo-meta.php';
require_once KO_LSEO_PATH . 'includes/class-ko-lightweight-seo-images.php';
require_once KO_LSEO_PATH . 'includes/class-ko-lightweight-seo-audit.php';
require_once KO_LSEO_PATH . 'includes/class-ko-lightweight-seo-headings.php';
require_once KO_LSEO_PATH . 'includes/class-ko-lightweight-seo-google.php';
require_once KO_LSEO_PATH . 'includes/class-ko-lightweight-seo.php';

register_activation_hook( __FILE__, array( 'KO_Lightweight_SEO', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'KO_Lightweight_SEO', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'KO_Lightweight_SEO', 'init' ), 20 );
