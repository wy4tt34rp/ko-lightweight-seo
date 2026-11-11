<?php
/**
 * Runs an administrator-requested audit against rendered server HTML.
 *
 * @package KO_Lightweight_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KO_Lightweight_SEO_Audit {
	/**
	 * Registers the Live HTML Audit submenu.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_audit_page' ) );
	}

	/**
	 * Adds SEO Basics > Live HTML Audit and preserves the previous Tools URL.
	 *
	 * @return void
	 */
	public static function register_audit_page() {
		add_management_page(
			'KO SEO Live HTML Audit',
			'KO SEO Audit',
			'manage_options',
			KO_Lightweight_SEO_Settings::AUDIT_PAGE_SLUG,
			array( __CLASS__, 'render_audit_page' )
		);

		remove_submenu_page( 'tools.php', KO_Lightweight_SEO_Settings::AUDIT_PAGE_SLUG );

		add_submenu_page(
			KO_Lightweight_SEO_Settings::MENU_SLUG,
			'KO SEO Live HTML Audit',
			'Live HTML Audit',
			'manage_options',
			KO_Lightweight_SEO_Settings::AUDIT_PAGE_SLUG,
			array( __CLASS__, 'render_audit_page' )
		);
	}

	/**
	 * Renders the same-site audit form and results.
	 *
	 * @return void
	 */
	public static function render_audit_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$url    = home_url( '/' );
		$audit  = null;
		$error  = '';
		$status = 0;

		if ( isset( $_POST['ko_lseo_audit_submit'] ) ) {
			check_admin_referer( 'ko_lseo_run_audit', 'ko_lseo_audit_nonce' );
			$url      = isset( $_POST['ko_lseo_audit_url'] ) ? esc_url_raw( wp_unslash( $_POST['ko_lseo_audit_url'] ) ) : '';
			$response = self::fetch_same_site_url( $url );

			if ( is_wp_error( $response ) ) {
				$error = $response->get_error_message();
			} else {
				$audit  = self::audit_html( $response['body'], self::resolve_content_title( $response['url'] ) );
				$status = $response['status'];
				$url    = $response['url'];
			}
		}
		?>
		<div class="wrap ko-lseo-wrap">
			<?php KO_Lightweight_SEO_Settings::render_admin_header( 'Live HTML Audit', 'Live HTML Audit', 'Fetch and inspect the server-rendered HTML that search engines receive.' ); ?>

			<form method="post" class="ko-lseo-card ko-lseo-panel ko-lseo-audit-form">
				<?php wp_nonce_field( 'ko_lseo_run_audit', 'ko_lseo_audit_nonce' ); ?>
				<label for="ko-lseo-audit-url"><strong>Same-site URL</strong></label>
				<div class="ko-lseo-audit-input-row">
					<input type="url" class="regular-text code" id="ko-lseo-audit-url" name="ko_lseo_audit_url" value="<?php echo esc_attr( $url ); ?>" required />
					<button type="submit" class="button button-primary" name="ko_lseo_audit_submit" value="1">Run audit</button>
				</div>
				<p class="description">The URL must use this WordPress site's host. Nothing is stored, and no browser-side script is added.</p>
			</form>

			<?php if ( '' !== $error ) : ?>
				<div class="notice notice-error inline"><p><?php echo esc_html( $error ); ?></p></div>
			<?php endif; ?>

			<?php if ( is_array( $audit ) ) : ?>
				<?php self::render_audit_results( $audit, $url, $status ); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Fetches an HTTP(S) URL only when it belongs to the current site host.
	 *
	 * @param string $url Requested URL.
	 * @return array{url:string,status:int,body:string}|WP_Error
	 */
	public static function fetch_same_site_url( $url ) {
		$url       = esc_url_raw( trim( (string) $url ), array( 'http', 'https' ) );
		$home      = home_url( '/' );
		$url_host  = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		$home_host = strtolower( (string) wp_parse_url( $home, PHP_URL_HOST ) );
		$url_port  = wp_parse_url( $url, PHP_URL_PORT );
		$home_port = wp_parse_url( $home, PHP_URL_PORT );
		$scheme    = wp_parse_url( $url, PHP_URL_SCHEME );

		if ( '' === $url || ! in_array( $scheme, array( 'http', 'https' ), true ) || '' === $url_host ) {
			return new WP_Error( 'ko_lseo_invalid_audit_url', 'Enter a complete HTTP or HTTPS URL.' );
		}

		if ( $url_host !== $home_host || (string) $url_port !== (string) $home_port ) {
			return new WP_Error( 'ko_lseo_external_audit_url', 'For safety, the live audit accepts URLs on this WordPress site only.' );
		}

		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'             => 12,
				'redirection'         => 3,
				'limit_response_size' => 5 * MB_IN_BYTES,
				'user-agent'          => 'KO-SEO-Basics/' . KO_LSEO_VERSION . '; ' . home_url( '/' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$body   = (string) wp_remote_retrieve_body( $response );

		if ( $status < 200 || $status >= 400 ) {
			return new WP_Error( 'ko_lseo_audit_http_status', sprintf( 'The URL returned HTTP %d.', $status ) );
		}

		if ( '' === $body ) {
			return new WP_Error( 'ko_lseo_empty_audit_response', 'The URL returned an empty response.' );
		}

		return array(
			'url'    => $url,
			'status' => $status,
			'body'   => $body,
		);
	}

	/**
	 * Analyzes server-rendered HTML without changing the audited page.
	 *
	 * @param string $html          Complete HTML document.
	 * @param string $content_title WordPress title resolved from the audited URL.
	 * @return array<string, mixed>
	 */
	public static function audit_html( $html, $content_title = '' ) {
		if ( class_exists( 'DOMDocument' ) && class_exists( 'DOMXPath' ) ) {
			return self::audit_html_with_dom( $html, $content_title );
		}

		return self::audit_html_with_regex( $html, $content_title );
	}

	/**
	 * Uses PHP DOM to count and inspect search-relevant elements.
	 *
	 * @param string $html          Complete HTML document.
	 * @param string $content_title WordPress title resolved from the audited URL.
	 * @return array<string, mixed>
	 */
	private static function audit_html_with_dom( $html, $content_title = '' ) {
		$document = new DOMDocument();
		$previous = libxml_use_internal_errors( true );
		$flags    = defined( 'LIBXML_NONET' ) ? LIBXML_NONET : 0;
		$loaded   = $document->loadHTML( '<?xml encoding="utf-8" ?>' . $html, $flags );

		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		if ( ! $loaded ) {
			return self::audit_html_with_regex( $html, $content_title );
		}

		$xpath = new DOMXPath( $document );
		$upper = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$lower = 'abcdefghijklmnopqrstuvwxyz';
		$title_nodes       = $xpath->query( '//title' );
		$description_nodes = $xpath->query( "//meta[translate(@name, '$upper', '$lower') = 'description']" );
		$canonical_nodes   = $xpath->query( "//link[contains(concat(' ', normalize-space(translate(@rel, '$upper', '$lower')), ' '), ' canonical ')]" );
		$robots_nodes      = $xpath->query( "//meta[translate(@name, '$upper', '$lower') = 'robots']" );
		$og_title_nodes    = $xpath->query( "//meta[translate(@property, '$upper', '$lower') = 'og:title']" );
		$og_image_nodes    = $xpath->query( "//meta[translate(@property, '$upper', '$lower') = 'og:image']" );
		$json_nodes        = $xpath->query( "//script[translate(@type, '$upper', '$lower') = 'application/ld+json']" );
		$heading_nodes     = $xpath->query( '//h1 | //h2 | //h3 | //h4 | //h5 | //h6' );
		$image_nodes       = $xpath->query( '//img' );
		$html_nodes        = $xpath->query( '/html' );
		$missing_alt       = 0;
		$empty_alt         = 0;
		$headings          = array();

		foreach ( $heading_nodes as $heading_node ) {
			$headings[] = array(
				'tag'  => strtolower( $heading_node->nodeName ),
				'text' => self::clean_heading_text( $heading_node->textContent ),
			);
		}

		$heading_report = self::build_heading_report( $headings, $content_title );

		foreach ( $image_nodes as $image_node ) {
			if ( ! $image_node->hasAttribute( 'alt' ) ) {
				++$missing_alt;
			} elseif ( '' === trim( $image_node->getAttribute( 'alt' ) ) ) {
				++$empty_alt;
			}
		}

		return array(
			'parser'            => 'DOM',
			'title_count'       => $title_nodes->length,
			'title'             => self::get_dom_text( $title_nodes ),
			'description_count' => $description_nodes->length,
			'description'       => self::get_dom_attribute( $description_nodes, 'content' ),
			'canonical_count'   => $canonical_nodes->length,
			'canonical'         => self::get_dom_attribute( $canonical_nodes, 'href' ),
			'robots_count'      => $robots_nodes->length,
			'robots'            => self::get_dom_attribute( $robots_nodes, 'content' ),
			'og_title_count'    => $og_title_nodes->length,
			'og_image_count'    => $og_image_nodes->length,
			'json_ld_count'     => $json_nodes->length,
			'h1_count'          => $heading_report['h1_count'],
			'image_count'       => $image_nodes->length,
			'missing_alt_count' => $missing_alt,
			'empty_alt_count'   => $empty_alt,
			'language'          => $html_nodes->length ? trim( $html_nodes->item( 0 )->getAttribute( 'lang' ) ) : '',
		) + $heading_report;
	}

	/**
	 * Provides a dependency-free fallback when the DOM extension is unavailable.
	 *
	 * @param string $html          Complete HTML document.
	 * @param string $content_title WordPress title resolved from the audited URL.
	 * @return array<string, mixed>
	 */
	private static function audit_html_with_regex( $html, $content_title = '' ) {
		$title       = self::match_element_text( $html, 'title' );
		$description = self::match_meta_values( $html, 'name', 'description', 'content' );
		$robots      = self::match_meta_values( $html, 'name', 'robots', 'content' );
		$og_title    = self::match_meta_values( $html, 'property', 'og:title', 'content' );
		$og_image    = self::match_meta_values( $html, 'property', 'og:image', 'content' );
		$canonical   = self::match_link_values( $html, 'canonical', 'href' );
		preg_match_all( '/<script\b[^>]*\btype\s*=\s*(["\'])application\/ld\+json\1[^>]*>/i', $html, $json_matches );
		preg_match_all( '/<h([1-6])\b[^>]*>(.*?)<\/h\1\s*>/is', $html, $heading_matches, PREG_SET_ORDER );
		preg_match_all( '/<img\b[^>]*>/i', $html, $image_matches );
		$missing_alt = 0;
		$empty_alt   = 0;
		$headings    = array();

		foreach ( $heading_matches as $heading_match ) {
			$headings[] = array(
				'tag'  => 'h' . $heading_match[1],
				'text' => self::clean_heading_text( $heading_match[2] ),
			);
		}

		$heading_report = self::build_heading_report( $headings, $content_title );

		foreach ( $image_matches[0] as $image_tag ) {
			if ( ! preg_match( '/\salt\s*=/i', $image_tag ) ) {
				++$missing_alt;
			} elseif ( preg_match( '/\salt\s*=\s*(["\'])\s*\1/i', $image_tag ) ) {
				++$empty_alt;
			}
		}

		preg_match( '/<html\b[^>]*\blang\s*=\s*(["\'])(.*?)\1/i', $html, $language_match );

		return array(
			'parser'            => 'Regex fallback',
			'title_count'       => count( $title ),
			'title'             => $title[0] ?? '',
			'description_count' => count( $description ),
			'description'       => $description[0] ?? '',
			'canonical_count'   => count( $canonical ),
			'canonical'         => $canonical[0] ?? '',
			'robots_count'      => count( $robots ),
			'robots'            => $robots[0] ?? '',
			'og_title_count'    => count( $og_title ),
			'og_image_count'    => count( $og_image ),
			'json_ld_count'     => count( $json_matches[0] ),
			'h1_count'          => $heading_report['h1_count'],
			'image_count'       => count( $image_matches[0] ),
			'missing_alt_count' => $missing_alt,
			'empty_alt_count'   => $empty_alt,
			'language'          => isset( $language_match[2] ) ? trim( $language_match[2] ) : '',
		) + $heading_report;
	}

	/**
	 * Renders outcome-oriented audit checks and raw counts.
	 *
	 * @param array<string, mixed> $audit  Audit data.
	 * @param string               $url    Audited URL.
	 * @param int                  $status HTTP status.
	 * @return void
	 */
	private static function render_audit_results( $audit, $url, $status ) {
		$title_heading_note = '';

		if ( '' === $audit['content_title'] ) {
			$title_heading_note = 'The URL did not resolve to a WordPress page or post, so the first rendered H1 is used for this check.';
		} elseif ( ! $audit['title_heading_found'] ) {
			$title_heading_note = 'The WordPress title “' . $audit['content_title'] . '” was not found in a rendered H1–H6 element.';
		} else {
			$title_heading_note = '“' . $audit['title_heading_text'] . '” is rendered as ' . strtoupper( $audit['title_heading_tag'] ) . '.';
		}

		$secondary_h1_note = '';

		if ( ! empty( $audit['secondary_h1_headings'] ) ) {
			$recommended = array_map(
				static function ( $heading ) {
					return 'H2 — ' . ( '' !== $heading ? $heading : '[empty heading]' );
				},
				$audit['secondary_h1_headings']
			);
			$secondary_h1_note = implode( '; ', $recommended );
		}

		$checks = array(
			array( 'Document title', 1 === $audit['title_count'] && '' !== $audit['title'], $audit['title_count'] . ' found', $audit['title'] ),
			array( 'Meta description', 1 === $audit['description_count'] && '' !== $audit['description'], $audit['description_count'] . ' found', $audit['description'] ),
			array( 'Canonical link', 1 === $audit['canonical_count'] && '' !== $audit['canonical'], $audit['canonical_count'] . ' found', $audit['canonical'] ),
			array( 'Open Graph title', 1 === $audit['og_title_count'], $audit['og_title_count'] . ' found', '' ),
			array( 'Open Graph image', $audit['og_image_count'] <= 1, $audit['og_image_count'] . ' found', 'An image is optional, but duplicates should be reviewed.' ),
			array( 'JSON-LD graph', $audit['json_ld_count'] >= 1, $audit['json_ld_count'] . ' block(s)', '' ),
			array( 'Page/post title heading', $audit['title_heading_is_h1'], $audit['title_heading_found'] ? strtoupper( $audit['title_heading_tag'] ) . ' found' : 'Not found', $title_heading_note ),
			array( 'Other H1 headings', 0 === $audit['secondary_h1_count'], $audit['secondary_h1_count'] . ' should be H2', $secondary_h1_note ),
			array( 'Images missing alt attribute', 0 === $audit['missing_alt_count'], $audit['missing_alt_count'] . ' of ' . $audit['image_count'], '' ),
			array( 'HTML language', '' !== $audit['language'], '' !== $audit['language'] ? $audit['language'] : 'Missing', '' ),
		);
		?>
		<section class="ko-lseo-audit-results ko-lseo-panel">
			<h2>Audit results</h2>
			<p><strong>URL:</strong> <a href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $url ); ?></a> &nbsp; <strong>HTTP:</strong> <?php echo esc_html( $status ); ?> &nbsp; <strong>Parser:</strong> <?php echo esc_html( $audit['parser'] ); ?></p>

			<table class="widefat striped ko-lseo-check-table">
				<thead><tr><th>Check</th><th>Status</th><th>Result</th><th>Resolved value or note</th></tr></thead>
				<tbody>
					<?php foreach ( $checks as $check ) : ?>
						<tr>
							<th scope="row"><?php echo esc_html( $check[0] ); ?></th>
							<td><span class="ko-lseo-status <?php echo $check[1] ? 'ko-lseo-status-pass' : 'ko-lseo-status-warn'; ?>"><?php echo $check[1] ? 'Pass' : 'Review'; ?></span></td>
							<td><?php echo esc_html( $check[2] ); ?></td>
							<td class="ko-lseo-break-word"><?php echo esc_html( $check[3] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<div class="notice notice-info inline ko-lseo-audit-note">
				<p><strong>Empty alt attributes:</strong> <?php echo esc_html( $audit['empty_alt_count'] ); ?>. These may be correct for decorative images and require contextual review. This server audit cannot see images injected only after JavaScript runs.</p>
				<p><strong>Robots:</strong> <?php echo esc_html( $audit['robots_count'] ? $audit['robots'] : 'No explicit meta robots tag; normal index/follow defaults apply.' ); ?></p>
			</div>
		</section>
		<?php
	}

	/**
	 * Resolves the stored WordPress title represented by a same-site URL.
	 *
	 * @param string $url Audited same-site URL.
	 * @return string
	 */
	private static function resolve_content_title( $url ) {
		$post_id = url_to_postid( $url );

		if ( ! $post_id && 'page' === get_option( 'show_on_front' ) && untrailingslashit( $url ) === untrailingslashit( home_url( '/' ) ) ) {
			$post_id = (int) get_option( 'page_on_front' );
		}

		if ( ! $post_id || ! get_post( $post_id ) ) {
			return '';
		}

		return self::clean_heading_text( get_post_field( 'post_title', $post_id ) );
	}

	/**
	 * Maps the WordPress title to its rendered heading and isolates extra H1s.
	 *
	 * @param array<int, array{tag:string,text:string}> $headings      Rendered headings in document order.
	 * @param string                                   $content_title Stored WordPress title.
	 * @return array<string, mixed>
	 */
	private static function build_heading_report( $headings, $content_title ) {
		$content_title  = self::clean_heading_text( $content_title );
		$target         = self::normalize_heading_text( $content_title );
		$primary_index  = null;
		$first_h1_index = null;
		$h1_count       = 0;

		foreach ( $headings as $index => $heading ) {
			if ( 'h1' === $heading['tag'] ) {
				++$h1_count;

				if ( null === $first_h1_index ) {
					$first_h1_index = $index;
				}
			}

			if ( '' !== $target && null === $primary_index && $target === self::normalize_heading_text( $heading['text'] ) ) {
				$primary_index = $index;
			}
		}

		if ( '' === $target ) {
			$primary_index = $first_h1_index;
		}

		$primary       = null === $primary_index ? null : $headings[ $primary_index ];
		$secondary_h1s = array();

		foreach ( $headings as $index => $heading ) {
			if ( 'h1' === $heading['tag'] && $index !== $primary_index ) {
				$secondary_h1s[] = $heading['text'];
			}
		}

		return array(
			'content_title'         => $content_title,
			'title_heading_found'   => null !== $primary,
			'title_heading_is_h1'   => null !== $primary && 'h1' === $primary['tag'],
			'title_heading_tag'     => null !== $primary ? $primary['tag'] : '',
			'title_heading_text'    => null !== $primary ? $primary['text'] : '',
			'h1_count'              => $h1_count,
			'secondary_h1_count'    => count( $secondary_h1s ),
			'secondary_h1_headings' => $secondary_h1s,
		);
	}

	/**
	 * Returns readable plain text from a heading or WordPress title.
	 *
	 * @param string $text Heading markup or title text.
	 * @return string
	 */
	private static function clean_heading_text( $text ) {
		$text = html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES, 'UTF-8' );
		$text = preg_replace( '/\s+/u', ' ', $text );

		return trim( (string) $text );
	}

	/**
	 * Produces a case-insensitive comparison key for heading text.
	 *
	 * @param string $text Clean heading text.
	 * @return string
	 */
	private static function normalize_heading_text( $text ) {
		$text = self::clean_heading_text( $text );

		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
	}

	/**
	 * Returns the first node's trimmed text.
	 *
	 * @param DOMNodeList $nodes Matched nodes.
	 * @return string
	 */
	private static function get_dom_text( $nodes ) {
		return $nodes->length ? trim( $nodes->item( 0 )->textContent ) : '';
	}

	/**
	 * Returns an attribute from the first matched node.
	 *
	 * @param DOMNodeList $nodes     Matched nodes.
	 * @param string      $attribute Attribute name.
	 * @return string
	 */
	private static function get_dom_attribute( $nodes, $attribute ) {
		return $nodes->length ? trim( $nodes->item( 0 )->getAttribute( $attribute ) ) : '';
	}

	/**
	 * Matches text from simple paired elements for the regex fallback.
	 *
	 * @param string $html HTML document.
	 * @param string $tag  Element name.
	 * @return string[]
	 */
	private static function match_element_text( $html, $tag ) {
		preg_match_all( '/<' . preg_quote( $tag, '/' ) . '\b[^>]*>(.*?)<\/' . preg_quote( $tag, '/' ) . '>/is', $html, $matches );

		return array_map(
			static function ( $value ) {
				return trim( wp_strip_all_tags( html_entity_decode( $value, ENT_QUOTES, 'UTF-8' ) ) );
			},
			$matches[1]
		);
	}

	/**
	 * Matches metadata attribute values regardless of attribute order.
	 *
	 * @param string $html           HTML document.
	 * @param string $key_attribute  Attribute containing the metadata key.
	 * @param string $key_value      Required metadata key.
	 * @param string $value_attribute Attribute containing the returned value.
	 * @return string[]
	 */
	private static function match_meta_values( $html, $key_attribute, $key_value, $value_attribute ) {
		preg_match_all( '/<meta\b[^>]*>/i', $html, $tags );

		return self::extract_attribute_values( $tags[0], $key_attribute, $key_value, $value_attribute );
	}

	/**
	 * Matches link relationship values for the regex fallback.
	 *
	 * @param string $html            HTML document.
	 * @param string $relationship    Required rel token.
	 * @param string $value_attribute Attribute containing the returned value.
	 * @return string[]
	 */
	private static function match_link_values( $html, $relationship, $value_attribute ) {
		preg_match_all( '/<link\b[^>]*>/i', $html, $tags );

		return self::extract_attribute_values( $tags[0], 'rel', $relationship, $value_attribute, true );
	}

	/**
	 * Extracts values from matched single tags without depending on DOM.
	 *
	 * @param string[] $tags            HTML tags.
	 * @param string   $key_attribute   Attribute containing the comparison key.
	 * @param string   $key_value       Required comparison value.
	 * @param string   $value_attribute Attribute to return.
	 * @param bool     $token_match     Whether the key is a space-separated token list.
	 * @return string[]
	 */
	private static function extract_attribute_values( $tags, $key_attribute, $key_value, $value_attribute, $token_match = false ) {
		$values = array();

		foreach ( $tags as $tag ) {
			$key_pattern = '/\b' . preg_quote( $key_attribute, '/' ) . '\s*=\s*(["\'])(.*?)\1/i';
			$value_pattern = '/\b' . preg_quote( $value_attribute, '/' ) . '\s*=\s*(["\'])(.*?)\1/i';

			if ( ! preg_match( $key_pattern, $tag, $key_match ) || ! preg_match( $value_pattern, $tag, $value_match ) ) {
				continue;
			}

			$key = strtolower( trim( $key_match[2] ) );
			$is_match = $token_match ? in_array( strtolower( $key_value ), preg_split( '/\s+/', $key ), true ) : strtolower( $key_value ) === $key;

			if ( $is_match ) {
				$values[] = html_entity_decode( trim( $value_match[2] ), ENT_QUOTES, 'UTF-8' );
			}
		}

		return $values;
	}
}
