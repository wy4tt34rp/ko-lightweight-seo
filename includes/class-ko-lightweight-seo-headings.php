<?php
/**
 * Protects the primary heading hierarchy inside rendered post content.
 *
 * @package KO_Lightweight_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KO_Lightweight_SEO_Headings {
	/**
	 * Registers the content filter only when the optional guard is enabled.
	 *
	 * @param array<string, int|string> $settings Validated plugin settings.
	 * @return void
	 */
	public static function init( $settings ) {
		if ( empty( $settings['enable_heading_guard'] ) ) {
			return;
		}

		add_filter( 'the_content', array( __CLASS__, 'filter_main_content' ), 99 );
	}

	/**
	 * Normalizes headings only in the main page or post content.
	 *
	 * The filter does not buffer the full response. It also leaves content alone
	 * when the WordPress title cannot be matched exactly, avoiding a guess about
	 * which visually similar heading should own the document's H1 role.
	 *
	 * @param string $content Rendered post content.
	 * @return string
	 */
	public static function filter_main_content( $content ) {
		if ( is_admin() || is_feed() || ! is_singular( array( 'post', 'page' ) ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$post_id = get_the_ID();

		if ( ! $post_id || (int) get_queried_object_id() !== (int) $post_id ) {
			return $content;
		}

		$title = (string) get_post_field( 'post_title', $post_id );

		return self::normalize_content_headings( $content, $title );
	}

	/**
	 * Promotes the first exact title heading and demotes other H1 elements.
	 *
	 * DOM parsing is deferred until the feature is enabled, the current request
	 * passes the main-content guards, and rendered content contains a heading.
	 * The original string is returned byte-for-byte when no exact title heading
	 * is found or when PHP's DOM extension is unavailable.
	 *
	 * @param string $content    Rendered post content.
	 * @param string $post_title Stored WordPress page or post title.
	 * @return string
	 */
	public static function normalize_content_headings( $content, $post_title ) {
		if ( '' === trim( (string) $post_title ) || ! preg_match( '/<h[1-6]\b/i', (string) $content ) ) {
			return $content;
		}

		if ( ! class_exists( 'DOMDocument' ) || ! class_exists( 'DOMXPath' ) ) {
			return $content;
		}

		$document = new DOMDocument( '1.0', 'UTF-8' );
		$previous = libxml_use_internal_errors( true );
		$flags    = defined( 'LIBXML_NONET' ) ? LIBXML_NONET : 0;

		if ( defined( 'LIBXML_HTML_NOIMPLIED' ) ) {
			$flags |= LIBXML_HTML_NOIMPLIED;
		}

		if ( defined( 'LIBXML_HTML_NODEFDTD' ) ) {
			$flags |= LIBXML_HTML_NODEFDTD;
		}

		$loaded = $document->loadHTML(
			'<?xml encoding="utf-8" ?><div id="ko-lseo-heading-root">' . $content . '</div>',
			$flags
		);

		libxml_clear_errors();
		libxml_use_internal_errors( $previous );

		if ( ! $loaded ) {
			return $content;
		}

		$xpath = new DOMXPath( $document );
		$roots = $xpath->query( '//*[@id="ko-lseo-heading-root"]' );

		if ( ! $roots || 0 === $roots->length ) {
			return $content;
		}

		$root          = $roots->item( 0 );
		$heading_nodes = $xpath->query( './/*[self::h1 or self::h2 or self::h3 or self::h4 or self::h5 or self::h6]', $root );
		$headings      = array();
		$primary       = null;
		$target_title  = self::normalize_heading_text( $post_title );

		foreach ( $heading_nodes as $heading_node ) {
			$headings[] = $heading_node;

			if ( null === $primary && $target_title === self::normalize_heading_text( $heading_node->textContent ) ) {
				$primary = $heading_node;
			}
		}

		if ( null === $primary ) {
			return $content;
		}

		$changed = false;

		foreach ( $headings as $heading ) {
			$is_primary = $heading->isSameNode( $primary );
			$tag_name   = strtolower( $heading->nodeName );

			if ( $is_primary && 'h1' !== $tag_name ) {
				self::replace_element_name( $heading, 'h1' );
				$changed = true;
			} elseif ( ! $is_primary && 'h1' === $tag_name ) {
				self::replace_element_name( $heading, 'h2' );
				$changed = true;
			}
		}

		if ( ! $changed ) {
			return $content;
		}

		$updated = '';

		foreach ( $root->childNodes as $child ) {
			$fragment = $document->saveHTML( $child );

			if ( false === $fragment ) {
				return $content;
			}

			$updated .= $fragment;
		}

		return $updated;
	}

	/**
	 * Replaces an element name while retaining its attributes and child nodes.
	 *
	 * @param DOMElement $element Existing heading element.
	 * @param string     $new_name Required replacement element name.
	 * @return DOMElement
	 */
	private static function replace_element_name( $element, $new_name ) {
		$document    = $element->ownerDocument;
		$replacement = $document->createElement( $new_name );

		foreach ( $element->attributes as $attribute ) {
			$replacement->setAttribute( $attribute->nodeName, $attribute->nodeValue );
		}

		while ( $element->firstChild ) {
			$replacement->appendChild( $element->firstChild );
		}

		$element->parentNode->replaceChild( $replacement, $element );

		return $replacement;
	}

	/**
	 * Produces a conservative comparison key for visible heading text.
	 *
	 * @param string $text Heading or post-title text.
	 * @return string
	 */
	private static function normalize_heading_text( $text ) {
		$text = html_entity_decode( wp_strip_all_tags( (string) $text ), ENT_QUOTES, 'UTF-8' );
		$text = trim( preg_replace( '/\s+/u', ' ', $text ) );

		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
	}
}
