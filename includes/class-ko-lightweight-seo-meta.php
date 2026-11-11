<?php
/**
 * Produces essential metadata through documented WordPress extension points.
 *
 * @package KO_Lightweight_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KO_Lightweight_SEO_Meta {
	const META_TITLE       = '_ko_lseo_title';
	const META_DESCRIPTION = '_ko_lseo_description';
	const META_CANONICAL   = '_ko_lseo_canonical';
	const META_NOINDEX     = '_ko_lseo_noindex';
	const META_NOFOLLOW    = '_ko_lseo_nofollow';

	/**
	 * Validated plugin settings for the current request.
	 *
	 * @var array<string, int|string>
	 */
	private static $settings = array();

	/**
	 * Registers the metadata filters and head output.
	 *
	 * @param array<string, int|string> $settings Validated plugin settings.
	 * @return void
	 */
	public static function init( $settings ) {
		self::$settings = is_array( $settings ) ? $settings : array();

		add_filter( 'pre_get_document_title', array( __CLASS__, 'filter_document_title' ), 99 );
		add_filter( 'get_canonical_url', array( __CLASS__, 'filter_canonical_url' ), 99, 2 );
		add_filter( 'wp_robots', array( __CLASS__, 'filter_robots' ), 99 );
		add_filter( 'wp_sitemaps_posts_query_args', array( __CLASS__, 'filter_sitemap_query_args' ), 10, 2 );
		add_action( 'wp_head', array( __CLASS__, 'output_meta_description' ), 1 );
		add_action( 'wp_head', array( __CLASS__, 'output_non_singular_canonical' ), 2 );

		if ( ! empty( self::$settings['enable_social'] ) ) {
			add_action( 'wp_head', array( __CLASS__, 'output_social_metadata' ), 5 );
		}

		if ( ! empty( self::$settings['enable_schema'] ) ) {
			add_action( 'wp_head', array( __CLASS__, 'output_schema' ), 30 );
		}
	}

	/**
	 * Uses the explicit per-content title when one has been saved.
	 *
	 * @param string $title WordPress document title.
	 * @return string
	 */
	public static function filter_document_title( $title ) {
		if ( ! is_singular() ) {
			return $title;
		}

		$custom_title = get_post_meta( get_queried_object_id(), self::META_TITLE, true );

		return '' !== trim( (string) $custom_title ) ? $custom_title : $title;
	}

	/**
	 * Replaces WordPress's singular canonical only when an override is valid.
	 *
	 * @param string  $canonical_url WordPress canonical URL.
	 * @param WP_Post $post          Current post.
	 * @return string
	 */
	public static function filter_canonical_url( $canonical_url, $post ) {
		$custom_url = get_post_meta( $post->ID, self::META_CANONICAL, true );

		if ( '' === $custom_url ) {
			return $canonical_url;
		}

		$custom_url = KO_Lightweight_SEO_Settings::sanitize_canonical_url( $custom_url );

		return '' !== $custom_url ? $custom_url : $canonical_url;
	}

	/**
	 * Adds per-content robots directives through WordPress's native robots API.
	 *
	 * @param array<string, bool|string> $robots Existing directives.
	 * @return array<string, bool|string>
	 */
	public static function filter_robots( $robots ) {
		if ( ! is_singular() ) {
			return $robots;
		}

		$post_id = get_queried_object_id();

		if ( '1' === get_post_meta( $post_id, self::META_NOINDEX, true ) ) {
			$robots['noindex'] = true;
		}

		if ( '1' === get_post_meta( $post_id, self::META_NOFOLLOW, true ) ) {
			$robots['nofollow'] = true;
		}

		if ( ! isset( $robots['noindex'] ) && ! isset( $robots['max-image-preview'] ) ) {
			$robots['max-image-preview'] = 'large';
		}

		return $robots;
	}

	/**
	 * Excludes explicitly noindexed content from WordPress core post sitemaps.
	 *
	 * @param array<string, mixed> $args      WP_Query arguments.
	 * @param string               $post_type Sitemap post type.
	 * @return array<string, mixed>
	 */
	public static function filter_sitemap_query_args( $args, $post_type ) {
		$indexable_clause = array(
			'relation' => 'OR',
			array(
				'key'     => self::META_NOINDEX,
				'compare' => 'NOT EXISTS',
			),
			array(
				'key'     => self::META_NOINDEX,
				'value'   => '1',
				'compare' => '!=',
			),
		);

		if ( empty( $args['meta_query'] ) ) {
			$args['meta_query'] = $indexable_clause;
			return $args;
		}

		$args['meta_query'] = array(
			'relation' => 'AND',
			$args['meta_query'],
			$indexable_clause,
		);

		return $args;
	}

	/**
	 * Prints a single useful meta description when one can be derived.
	 *
	 * @return void
	 */
	public static function output_meta_description() {
		if ( is_404() || is_search() || is_embed() ) {
			return;
		}

		$description = self::get_meta_description();

		if ( '' === $description ) {
			return;
		}

		echo "\n<!-- KO: SEO Basics " . esc_html( KO_LSEO_VERSION ) . " -->\n";
		printf( '<meta name="description" content="%s" />' . "\n", esc_attr( $description ) );
	}

	/**
	 * Completes WordPress's singular-only canonical output for archive views.
	 *
	 * WordPress core continues to own singular canonicals, including pagination.
	 * This method handles only views where rel_canonical() does not print one.
	 *
	 * @return void
	 */
	public static function output_non_singular_canonical() {
		if ( is_singular() || is_404() || is_search() ) {
			return;
		}

		$url = self::get_current_url();

		if ( '' !== $url ) {
			printf( '<link rel="canonical" href="%s" />' . "\n", esc_url( $url ) );
		}
	}

	/**
	 * Prints Open Graph and Twitter card metadata without frontend assets.
	 *
	 * @return void
	 */
	public static function output_social_metadata() {
		if ( is_404() || is_search() || is_embed() ) {
			return;
		}

		$title       = wp_get_document_title();
		$description = self::get_meta_description();
		$url         = self::get_current_url();
		$image       = self::get_primary_image();
		$type        = is_singular( 'post' ) ? 'article' : 'website';

		self::print_meta_tag( 'property', 'og:type', $type );
		self::print_meta_tag( 'property', 'og:title', $title );
		self::print_meta_tag( 'property', 'og:url', $url );
		self::print_meta_tag( 'property', 'og:site_name', get_bloginfo( 'name' ) );
		self::print_meta_tag( 'property', 'og:locale', str_replace( '-', '_', get_locale() ) );

		if ( '' !== $description ) {
			self::print_meta_tag( 'property', 'og:description', $description );
		}

		if ( ! empty( $image['url'] ) ) {
			self::print_meta_tag( 'property', 'og:image', $image['url'] );
			self::print_meta_tag( 'property', 'og:image:width', (string) $image['width'] );
			self::print_meta_tag( 'property', 'og:image:height', (string) $image['height'] );

			if ( '' !== $image['alt'] ) {
				self::print_meta_tag( 'property', 'og:image:alt', $image['alt'] );
			}
		}

		self::print_meta_tag( 'name', 'twitter:card', empty( $image['url'] ) ? 'summary' : 'summary_large_image' );
		self::print_meta_tag( 'name', 'twitter:title', $title );

		if ( '' !== $description ) {
			self::print_meta_tag( 'name', 'twitter:description', $description );
		}

		if ( ! empty( $image['url'] ) ) {
			self::print_meta_tag( 'name', 'twitter:image', $image['url'] );

			if ( '' !== $image['alt'] ) {
				self::print_meta_tag( 'name', 'twitter:image:alt', $image['alt'] );
			}
		}
	}

	/**
	 * Prints the compact JSON-LD graph.
	 *
	 * @return void
	 */
	public static function output_schema() {
		if ( is_404() || is_search() || is_embed() ) {
			return;
		}

		$graph = self::get_schema_graph();

		/**
		 * Filters the complete KO JSON-LD graph before output.
		 *
		 * @param array<int, array<string, mixed>> $graph Schema graph entities.
		 */
		$graph = apply_filters( 'ko_lseo_schema_graph', $graph );

		if ( empty( $graph ) || ! is_array( $graph ) ) {
			return;
		}

		$schema = array(
			'@context' => 'https://schema.org',
			'@graph'   => array_values( $graph ),
		);

		echo '<script type="application/ld+json">';
		echo wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		echo "</script>\n";
	}

	/**
	 * Returns the best available plain-text description for the current view.
	 *
	 * @return string
	 */
	public static function get_meta_description() {
		$description = '';

		if ( is_singular() ) {
			$post_id     = get_queried_object_id();
			$description = (string) get_post_meta( $post_id, self::META_DESCRIPTION, true );

			if ( '' === trim( $description ) ) {
				$post = get_post( $post_id );

				if ( $post ) {
					$description = '' !== trim( (string) $post->post_excerpt ) ? $post->post_excerpt : $post->post_content;
				}
			}
		} elseif ( is_front_page() || is_home() ) {
			$description = get_bloginfo( 'description' );
		} elseif ( is_category() || is_tag() || is_tax() ) {
			$description = term_description();
		} elseif ( is_post_type_archive() ) {
			$description = get_the_archive_description();
		}

		$description = strip_shortcodes( (string) $description );
		$description = wp_strip_all_tags( $description, true );
		$description = self::trim_description( $description, 160 );

		/**
		 * Filters the resolved description for the current request.
		 *
		 * @param string $description Plain-text description.
		 */
		return (string) apply_filters( 'ko_lseo_meta_description', $description );
	}

	/**
	 * Builds the schema entities used by output_schema().
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_schema_graph() {
		$url               = self::get_current_url();

		if ( '' === $url ) {
			return array();
		}

		$site_url          = home_url( '/' );
		$organization_name = empty( self::$settings['organization_name'] ) ? get_bloginfo( 'name' ) : self::$settings['organization_name'];
		$organization_id   = trailingslashit( $site_url ) . '#organization';
		$website_id        = trailingslashit( $site_url ) . '#website';
		$webpage_id        = strtok( $url, '#' ) . '#webpage';
		$image             = self::get_primary_image();
		$language          = str_replace( '_', '-', get_locale() );
		$description       = self::get_meta_description();

		$organization = array(
			'@type' => 'Organization',
			'@id'   => $organization_id,
			'name'  => $organization_name,
			'url'   => $site_url,
		);

		$logo = self::get_attachment_image_data( (int) get_theme_mod( 'custom_logo', 0 ) );

		if ( ! empty( $logo['url'] ) ) {
			$organization['logo'] = array(
				'@type'  => 'ImageObject',
				'url'    => $logo['url'],
				'width'  => $logo['width'],
				'height' => $logo['height'],
			);
		}

		$website = array(
			'@type'     => 'WebSite',
			'@id'       => $website_id,
			'url'       => $site_url,
			'name'      => get_bloginfo( 'name' ),
			'inLanguage' => $language,
			'publisher' => array( '@id' => $organization_id ),
		);

		$webpage = array(
			'@type'      => 'WebPage',
			'@id'        => $webpage_id,
			'url'        => $url,
			'name'       => wp_get_document_title(),
			'isPartOf'   => array( '@id' => $website_id ),
			'inLanguage' => $language,
		);

		if ( '' !== $description ) {
			$webpage['description'] = $description;
		}

		if ( ! empty( $image['url'] ) ) {
			$webpage['primaryImageOfPage'] = array(
				'@type'  => 'ImageObject',
				'url'    => $image['url'],
				'width'  => $image['width'],
				'height' => $image['height'],
			);
		}

		$graph = array( $organization, $website, $webpage );

		if ( is_singular( 'post' ) ) {
			$post = get_post( get_queried_object_id() );

			if ( $post ) {
				$article = array(
					'@type'            => 'Article',
					'@id'              => strtok( $url, '#' ) . '#article',
					'headline'         => get_the_title( $post ),
					'datePublished'    => get_post_time( DATE_W3C, true, $post ),
					'dateModified'     => get_post_modified_time( DATE_W3C, true, $post ),
					'mainEntityOfPage' => array( '@id' => $webpage_id ),
					'isPartOf'         => array( '@id' => $website_id ),
					'author'           => array( '@id' => $organization_id ),
					'publisher'        => array( '@id' => $organization_id ),
					'inLanguage'       => $language,
				);

				if ( ! empty( $image['url'] ) ) {
					$article['image'] = array( $image['url'] );
				}

				$graph[] = $article;
			}
		}

		return $graph;
	}

	/**
	 * Returns the canonical-like URL for the current public view.
	 *
	 * @return string
	 */
	public static function get_current_url() {
		if ( is_singular() ) {
			$canonical = wp_get_canonical_url( get_queried_object_id() );
			return $canonical ? $canonical : get_permalink( get_queried_object_id() );
		}

		if ( is_paged() ) {
			$paged_url = get_pagenum_link( max( 1, (int) get_query_var( 'paged' ) ) );
			return $paged_url ? $paged_url : home_url( '/' );
		}

		if ( is_front_page() ) {
			return home_url( '/' );
		}

		if ( is_home() ) {
			$page_for_posts = (int) get_option( 'page_for_posts' );
			return $page_for_posts ? get_permalink( $page_for_posts ) : home_url( '/' );
		}

		if ( is_category() || is_tag() || is_tax() ) {
			$term_url = get_term_link( get_queried_object() );
			return is_wp_error( $term_url ) ? home_url( '/' ) : $term_url;
		}

		if ( is_post_type_archive() ) {
			$post_type = get_query_var( 'post_type' );
			$post_type = is_array( $post_type ) ? reset( $post_type ) : $post_type;
			$post_type_url = get_post_type_archive_link( $post_type );
			return $post_type_url ? $post_type_url : home_url( '/' );
		}

		if ( is_author() ) {
			$author = get_queried_object();
			return ! empty( $author->ID ) ? get_author_posts_url( $author->ID ) : '';
		}

		if ( is_day() ) {
			return get_day_link( get_query_var( 'year' ), get_query_var( 'monthnum' ), get_query_var( 'day' ) );
		}

		if ( is_month() ) {
			return get_month_link( get_query_var( 'year' ), get_query_var( 'monthnum' ) );
		}

		if ( is_year() ) {
			return get_year_link( get_query_var( 'year' ) );
		}

		return '';
	}

	/**
	 * Resolves the featured image or site logo used by metadata and schema.
	 *
	 * @return array{url:string,width:int,height:int,alt:string}
	 */
	private static function get_primary_image() {
		$attachment_id = is_singular() ? (int) get_post_thumbnail_id( get_queried_object_id() ) : 0;

		if ( ! $attachment_id ) {
			$attachment_id = (int) get_theme_mod( 'custom_logo', 0 );
		}

		return self::get_attachment_image_data( $attachment_id );
	}

	/**
	 * Returns normalized metadata for an image attachment.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array{url:string,width:int,height:int,alt:string}
	 */
	private static function get_attachment_image_data( $attachment_id ) {
		$empty = array(
			'url'    => '',
			'width'  => 0,
			'height' => 0,
			'alt'    => '',
		);

		if ( ! $attachment_id ) {
			return $empty;
		}

		$image = wp_get_attachment_image_src( $attachment_id, 'full' );

		if ( ! is_array( $image ) ) {
			return $empty;
		}

		return array(
			'url'    => $image[0],
			'width'  => (int) $image[1],
			'height' => (int) $image[2],
			'alt'    => trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ),
		);
	}

	/**
	 * Prints one escaped social metadata element when its value is non-empty.
	 *
	 * @param string $attribute_name  Either name or property.
	 * @param string $attribute_value Metadata key.
	 * @param string $content         Metadata value.
	 * @return void
	 */
	private static function print_meta_tag( $attribute_name, $attribute_value, $content ) {
		if ( '' === trim( (string) $content ) ) {
			return;
		}

		printf(
			'<meta %1$s="%2$s" content="%3$s" />' . "\n",
			esc_attr( $attribute_name ),
			esc_attr( $attribute_value ),
			esc_attr( $content )
		);
	}

	/**
	 * Normalizes whitespace and trims a description without splitting a word.
	 *
	 * @param string $text   Source text.
	 * @param int    $length Maximum character length.
	 * @return string
	 */
	public static function trim_description( $text, $length = 160 ) {
		$text = trim( preg_replace( '/\s+/u', ' ', (string) $text ) );

		if ( '' === $text ) {
			return '';
		}

		$strlen = function_exists( 'mb_strlen' ) ? 'mb_strlen' : 'strlen';
		$substr = function_exists( 'mb_substr' ) ? 'mb_substr' : 'substr';
		$strrpos = function_exists( 'mb_strrpos' ) ? 'mb_strrpos' : 'strrpos';

		if ( $strlen( $text ) <= $length ) {
			return $text;
		}

		$shortened  = $substr( $text, 0, $length - 1 );
		$last_space = $strrpos( $shortened, ' ' );

		if ( false !== $last_space && $last_space > (int) ( $length * 0.6 ) ) {
			$shortened = $substr( $shortened, 0, $last_space );
		}

		return rtrim( $shortened, " \t\n\r\0\x0B,.;:-" ) . '…';
	}
}
