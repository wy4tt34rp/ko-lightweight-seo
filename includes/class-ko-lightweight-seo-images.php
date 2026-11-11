<?php
/**
 * Provides reviewed, non-destructive image alt-text tools.
 *
 * @package KO_Lightweight_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KO_Lightweight_SEO_Images {
	const ALT_STATE_META   = '_ko_lseo_alt_state';
	const DECORATIVE       = 'decorative';
	const DEFAULT_PER_PAGE = 25;
	const MIN_PER_PAGE     = 5;
	const MAX_PER_PAGE     = 100;

	/**
	 * Registers upload-time checks and the image alt-text audit screen.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'add_attachment', array( __CLASS__, 'maybe_autofill_attachment_alt' ) );
		add_action( 'edit_attachment', array( __CLASS__, 'maybe_autofill_attachment_alt' ) );
		add_action( 'admin_menu', array( __CLASS__, 'register_audit_page' ) );
		add_action( 'admin_post_ko_lseo_image_alt_bulk', array( __CLASS__, 'handle_bulk_action' ) );
	}

	/**
	 * Adds SEO Basics > Image Alt Text and preserves the previous Media URL.
	 *
	 * @return void
	 */
	public static function register_audit_page() {
		add_media_page(
			'KO Alt Text Audit',
			'Alt Text Audit',
			'upload_files',
			KO_Lightweight_SEO_Settings::IMAGE_PAGE_SLUG,
			array( __CLASS__, 'render_audit_page' )
		);

		remove_submenu_page( 'upload.php', KO_Lightweight_SEO_Settings::IMAGE_PAGE_SLUG );

		add_submenu_page(
			KO_Lightweight_SEO_Settings::MENU_SLUG,
			'KO Image Alt Text',
			'Image Alt Text',
			'upload_files',
			KO_Lightweight_SEO_Settings::IMAGE_PAGE_SLUG,
			array( __CLASS__, 'render_audit_page' )
		);
	}

	/**
	 * Assigns alt text only from a trustworthy human-authored field.
	 *
	 * Filename-derived suggestions are deliberately excluded from automatic
	 * assignment. They remain available for review on the audit screen.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	public static function maybe_autofill_attachment_alt( $attachment_id ) {
		$settings = KO_Lightweight_SEO_Settings::get_settings();

		if ( empty( $settings['image_alt_autofill'] ) || ! wp_attachment_is_image( $attachment_id ) ) {
			return;
		}

		$current_alt = trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );
		$alt_state   = get_post_meta( $attachment_id, self::ALT_STATE_META, true );

		if ( '' !== $current_alt || self::DECORATIVE === $alt_state ) {
			return;
		}

		$suggestion = self::get_suggestion( $attachment_id, false );

		if ( '' === $suggestion['text'] || 'high' !== $suggestion['confidence'] ) {
			return;
		}

		update_post_meta( $attachment_id, '_wp_attachment_image_alt', $suggestion['text'] );
		delete_post_meta( $attachment_id, self::ALT_STATE_META );
	}

	/**
	 * Resolves the strongest available candidate and records its source.
	 *
	 * @param int  $attachment_id Attachment ID.
	 * @param bool $allow_filename Whether to return a review-only filename candidate.
	 * @return array{text:string,source:string,confidence:string}
	 */
	public static function get_suggestion( $attachment_id, $allow_filename = true ) {
		$empty = array(
			'text'       => '',
			'source'     => 'No safe suggestion',
			'confidence' => 'none',
		);
		$post  = get_post( $attachment_id );

		if ( ! $post || 'attachment' !== $post->post_type || ! wp_attachment_is_image( $attachment_id ) ) {
			return $empty;
		}

		$caption = self::clean_candidate( $post->post_excerpt );

		if ( '' !== $caption ) {
			return array(
				'text'       => $caption,
				'source'     => 'Attachment caption',
				'confidence' => 'high',
			);
		}

		$file          = get_attached_file( $attachment_id );
		$filename      = $file ? wp_basename( $file ) : '';
		$filename_stem = pathinfo( $filename, PATHINFO_FILENAME );
		$title         = self::clean_candidate( $post->post_title );

		if ( self::is_trustworthy_title( $title, $filename_stem ) ) {
			return array(
				'text'       => $title,
				'source'     => 'Human-authored attachment title',
				'confidence' => 'high',
			);
		}

		if ( $allow_filename ) {
			$filename_suggestion = self::get_filename_suggestion_from_name( $filename );

			if ( '' !== $filename_suggestion ) {
				return array(
					'text'       => $filename_suggestion,
					'source'     => 'Descriptive filename — review required',
					'confidence' => 'review',
				);
			}

			$filename_fallback = self::get_filename_fallback_from_name( $filename );

			if ( '' !== $filename_fallback ) {
				return array(
					'text'       => $filename_fallback,
					'source'     => 'Filename fallback — review required',
					'confidence' => 'review',
				);
			}
		}

		return $empty;
	}

	/**
	 * Determines whether an attachment title differs meaningfully from its file.
	 *
	 * @param string $title         Attachment title.
	 * @param string $filename_stem Filename without extension.
	 * @return bool
	 */
	public static function is_trustworthy_title( $title, $filename_stem ) {
		$title = self::clean_candidate( $title );

		if ( '' === $title || self::is_generic_media_name( $title ) ) {
			return false;
		}

		$normalize = static function ( $value ) {
			$value = strtolower( (string) $value );
			return preg_replace( '/[^a-z0-9]+/', '', $value );
		};

		return $normalize( $title ) !== $normalize( $filename_stem );
	}

	/**
	 * Converts a descriptive filename into a review-only text candidate.
	 *
	 * @param string $filename Filename with or without an extension.
	 * @return string
	 */
	public static function get_filename_suggestion_from_name( $filename ) {
		$stem = pathinfo( (string) $filename, PATHINFO_FILENAME );
		$stem = preg_replace( '/-(?:\d+x\d+|scaled|edited)$/i', '', $stem );

		if ( self::is_generic_media_name( $stem ) ) {
			return '';
		}

		$candidate = preg_replace( '/[-_]+/', ' ', $stem );
		$candidate = self::clean_candidate( $candidate );

		if ( '' === $candidate || ! preg_match( '/[a-z]{3}/i', $candidate ) ) {
			return '';
		}

		return ucfirst( $candidate );
	}

	/**
	 * Converts any available filename into a manual-review starting point.
	 *
	 * This fallback never participates in automatic upload assignment. It exists
	 * only on the audit screen when no safer caption, title, or descriptive
	 * filename candidate is available.
	 *
	 * @param string $filename Filename with or without an extension.
	 * @return string
	 */
	public static function get_filename_fallback_from_name( $filename ) {
		$stem = pathinfo( (string) $filename, PATHINFO_FILENAME );
		$stem = preg_replace( '/-(?:\d+x\d+|scaled|edited)$/i', '', $stem );
		$stem = preg_replace( '/[-_]+/', ' ', $stem );

		return self::clean_candidate( $stem );
	}

	/**
	 * Clamps the audit page size to a useful, lightweight range.
	 *
	 * @param mixed $value Requested number of images.
	 * @return int
	 */
	public static function sanitize_per_page( $value ) {
		$value = absint( $value );

		if ( 0 === $value ) {
			return self::DEFAULT_PER_PAGE;
		}

		return min( self::MAX_PER_PAGE, max( self::MIN_PER_PAGE, $value ) );
	}

	/**
	 * Identifies filenames and titles that should never become alt text.
	 *
	 * @param string $value Candidate media name.
	 * @return bool
	 */
	public static function is_generic_media_name( $value ) {
		$value = trim( (string) $value );

		if ( '' === $value ) {
			return true;
		}

		$generic_prefixes = '/^(?:img|image|photo|dsc|dscn|pxl|mvimg|screenshot|screen[\s_-]*shot|untitled|adobestock|shutterstock|istock|depositphotos|pexels|unsplash|canva)[\s_-]*\d*(?:[\s_-].*)?$/i';

		if ( preg_match( $generic_prefixes, $value ) ) {
			return true;
		}

		$compact = preg_replace( '/[^a-z0-9]/i', '', $value );

		return (bool) preg_match( '/^(?:\d+|[a-f0-9]{12,})$/i', $compact );
	}

	/**
	 * Renders the missing-alt or intentionally-decorative attachment list.
	 *
	 * @return void
	 */
	public static function render_audit_page() {
		if ( ! current_user_can( 'upload_files' ) ) {
			return;
		}

		$view           = isset( $_GET['view'] ) && 'decorative' === sanitize_key( wp_unslash( $_GET['view'] ) ) ? 'decorative' : 'missing';
		$paged          = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$per_page       = self::sanitize_per_page( isset( $_GET['per_page'] ) ? wp_unslash( $_GET['per_page'] ) : self::DEFAULT_PER_PAGE );
		$query          = new WP_Query( self::get_audit_query_args( $view, $paged, $per_page ) );
		$missing_url    = add_query_arg(
			array(
				'page'     => 'ko-lightweight-seo-images',
				'per_page' => $per_page,
			),
			admin_url( 'admin.php' )
		);
		$decorative_url = add_query_arg(
			array(
				'page'     => 'ko-lightweight-seo-images',
				'view'     => 'decorative',
				'per_page' => $per_page,
			),
			admin_url( 'admin.php' )
		);
		?>
		<div class="wrap ko-lseo-wrap">
			<?php KO_Lightweight_SEO_Settings::render_admin_header( 'Image Alt Text', 'Image Alt Text Audit', 'Review missing alternatives without keyword stuffing or frontend processing.' ); ?>

			<?php self::render_update_notice(); ?>

			<section class="ko-lseo-panel ko-lseo-image-audit-panel">
				<div class="notice notice-info inline">
					<p><strong>Context still matters.</strong> Informative images need a concise description of their purpose on the page. Images that add no meaning should be marked decorative and keep an empty <code>alt=""</code>.</p>
				</div>

				<nav class="nav-tab-wrapper ko-lseo-tabs" aria-label="Alt text audit views">
					<a class="nav-tab <?php echo 'missing' === $view ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( $missing_url ); ?>">Missing alt text</a>
					<a class="nav-tab <?php echo 'decorative' === $view ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( $decorative_url ); ?>">Marked decorative</a>
				</nav>

				<div class="ko-lseo-audit-toolbar">
					<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="ko-lseo-per-page-form">
						<input type="hidden" name="page" value="ko-lightweight-seo-images" />
						<?php if ( 'decorative' === $view ) : ?>
							<input type="hidden" name="view" value="decorative" />
						<?php endif; ?>
						<label for="ko-lseo-per-page"><strong>Images per page</strong></label>
						<input type="number" id="ko-lseo-per-page" name="per_page" value="<?php echo esc_attr( $per_page ); ?>" min="<?php echo esc_attr( self::MIN_PER_PAGE ); ?>" max="<?php echo esc_attr( self::MAX_PER_PAGE ); ?>" step="1" />
						<button type="submit" class="button">Update</button>
					</form>
					<span class="ko-lseo-item-count"><?php echo esc_html( number_format_i18n( $query->found_posts ) ); ?> images</span>
				</div>

				<p class="ko-lseo-workflow-note"><strong>How saving works:</strong> typing or editing alt text selects that row automatically. Your selected drafts stay available while you move between these pagination pages in the same browser tab. Click <strong>Save reviewed alt text</strong> from any page to save them together. A prefilled filename is not saved until you edit it or check its row.</p>

				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="ko-lseo-alt-form" data-ko-lseo-alt-form data-ko-lseo-view="<?php echo esc_attr( $view ); ?>" data-ko-lseo-draft-root="ko-lseo-alt-drafts-v1">
					<input type="hidden" name="action" value="ko_lseo_image_alt_bulk" />
					<input type="hidden" name="ko_lseo_view" value="<?php echo esc_attr( $view ); ?>" />
					<input type="hidden" name="ko_lseo_paged" value="<?php echo esc_attr( $paged ); ?>" />
					<input type="hidden" name="ko_lseo_per_page" value="<?php echo esc_attr( $per_page ); ?>" />
					<?php wp_nonce_field( 'ko_lseo_image_alt_bulk', 'ko_lseo_image_nonce' ); ?>

					<div class="ko-lseo-action-bar">
						<div>
							<button type="submit" name="ko_lseo_bulk_action" value="save_alt" class="button button-primary">Save reviewed alt text</button>
							<?php if ( 'missing' === $view ) : ?>
								<button type="submit" name="ko_lseo_bulk_action" value="mark_decorative" class="button">Mark selected as decorative</button>
							<?php else : ?>
								<button type="submit" name="ko_lseo_bulk_action" value="return_to_audit" class="button">Return selected to missing audit</button>
							<?php endif; ?>
						</div>
						<span id="ko-lseo-draft-status" class="ko-lseo-draft-status" aria-live="polite">No unsaved selections.</span>
					</div>

					<table class="wp-list-table widefat fixed striped table-view-list ko-lseo-alt-table">
					<thead>
						<tr>
							<td class="manage-column column-cb check-column"><input type="checkbox" aria-label="Select all images" /></td>
							<th scope="col" class="ko-lseo-image-column">Image</th>
							<th scope="col">Reviewed alt text</th>
							<th scope="col" class="ko-lseo-source-column">Candidate source</th>
						</tr>
					</thead>
					<tbody>
						<?php if ( ! $query->have_posts() ) : ?>
							<tr><td colspan="4">No images found in this view.</td></tr>
						<?php else : ?>
							<?php foreach ( $query->posts as $attachment ) : ?>
								<?php
								$suggestion = self::get_suggestion( $attachment->ID, true );
								$current_alt = trim( (string) get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true ) );
								$field_value = '' !== $current_alt ? $current_alt : $suggestion['text'];
								?>
								<tr data-ko-lseo-row="<?php echo esc_attr( $attachment->ID ); ?>">
									<th scope="row" class="check-column"><input type="checkbox" name="ko_lseo_attachment_ids[]" value="<?php echo esc_attr( $attachment->ID ); ?>" data-ko-lseo-select="<?php echo esc_attr( $attachment->ID ); ?>" aria-label="Select <?php echo esc_attr( get_the_title( $attachment ) ); ?>" /></th>
									<td class="ko-lseo-image-cell">
										<?php echo wp_kses_post( wp_get_attachment_image( $attachment->ID, array( 80, 80 ), true, array( 'alt' => '' ) ) ); ?>
										<div>
											<strong><?php echo esc_html( get_the_title( $attachment ) ? get_the_title( $attachment ) : '(untitled image)' ); ?></strong><br />
											<a href="<?php echo esc_url( get_edit_post_link( $attachment->ID ) ); ?>">Edit attachment</a><br />
											<span class="description">ID <?php echo esc_html( $attachment->ID ); ?></span>
										</div>
									</td>
									<td>
										<input type="hidden" name="ko_lseo_original_alt[<?php echo esc_attr( $attachment->ID ); ?>]" value="<?php echo esc_attr( $field_value ); ?>" data-ko-lseo-original="<?php echo esc_attr( $attachment->ID ); ?>" />
										<input type="hidden" name="ko_lseo_review_state[<?php echo esc_attr( $attachment->ID ); ?>]" value="" data-ko-lseo-review-state="<?php echo esc_attr( $attachment->ID ); ?>" />
										<input type="text" class="widefat" name="ko_lseo_alt[<?php echo esc_attr( $attachment->ID ); ?>]" value="<?php echo esc_attr( $field_value ); ?>" maxlength="500" data-ko-lseo-alt="<?php echo esc_attr( $attachment->ID ); ?>" placeholder="Write a concise, contextual alternative" />
									</td>
									<td>
										<?php echo esc_html( '' !== $current_alt ? 'Existing alt text' : $suggestion['source'] ); ?>
										<?php if ( 'review' === $suggestion['confidence'] && '' === $current_alt ) : ?>
											<br /><span class="ko-lseo-status ko-lseo-status-warn">Review</span>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						<?php endif; ?>
					</tbody>
					</table>
				</form>

				<?php self::render_pagination( $query, $view, $per_page ); ?>
			</section>
		</div>
		<?php

		wp_reset_postdata();
	}

	/**
	 * Returns the attachment query for the selected audit view.
	 *
	 * @param string $view     Missing or decorative view.
	 * @param int    $paged    Page number.
	 * @param int    $per_page Items per page.
	 * @return array<string, mixed>
	 */
	public static function get_audit_query_args( $view, $paged = 1, $per_page = self::DEFAULT_PER_PAGE ) {
		$meta_query = array();

		if ( 'decorative' === $view ) {
			$meta_query[] = array(
				'key'   => self::ALT_STATE_META,
				'value' => self::DECORATIVE,
			);
		} else {
			$meta_query = array(
				'relation' => 'AND',
				array(
					'relation' => 'OR',
					array(
						'key'     => '_wp_attachment_image_alt',
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => '_wp_attachment_image_alt',
						'value'   => '',
						'compare' => '=',
					),
				),
				array(
					'relation' => 'OR',
					array(
						'key'     => self::ALT_STATE_META,
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => self::ALT_STATE_META,
						'value'   => self::DECORATIVE,
						'compare' => '!=',
					),
				),
			);
		}

		return array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'post_mime_type' => 'image',
			'posts_per_page' => self::sanitize_per_page( $per_page ),
			'paged'          => max( 1, (int) $paged ),
			'orderby'        => 'date',
			'order'          => 'DESC',
			'meta_query'     => $meta_query,
		);
	}

	/**
	 * Applies the explicitly selected reviewed action to editable attachments.
	 *
	 * @return void
	 */
	public static function handle_bulk_action() {
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_die( 'You are not allowed to edit image alt text.' );
		}

		check_admin_referer( 'ko_lseo_image_alt_bulk', 'ko_lseo_image_nonce' );

		$action    = isset( $_POST['ko_lseo_bulk_action'] ) ? sanitize_key( wp_unslash( $_POST['ko_lseo_bulk_action'] ) ) : '';
		$view      = isset( $_POST['ko_lseo_view'] ) && 'decorative' === sanitize_key( wp_unslash( $_POST['ko_lseo_view'] ) ) ? 'decorative' : 'missing';
		$paged     = isset( $_POST['ko_lseo_paged'] ) ? max( 1, absint( $_POST['ko_lseo_paged'] ) ) : 1;
		$per_page  = self::sanitize_per_page( isset( $_POST['ko_lseo_per_page'] ) ? wp_unslash( $_POST['ko_lseo_per_page'] ) : self::DEFAULT_PER_PAGE );
		$ids       = isset( $_POST['ko_lseo_attachment_ids'] ) && is_array( $_POST['ko_lseo_attachment_ids'] ) ? array_map( 'absint', wp_unslash( $_POST['ko_lseo_attachment_ids'] ) ) : array();
		$alts      = isset( $_POST['ko_lseo_alt'] ) && is_array( $_POST['ko_lseo_alt'] ) ? wp_unslash( $_POST['ko_lseo_alt'] ) : array();
		$originals = isset( $_POST['ko_lseo_original_alt'] ) && is_array( $_POST['ko_lseo_original_alt'] ) ? wp_unslash( $_POST['ko_lseo_original_alt'] ) : array();
		$states    = isset( $_POST['ko_lseo_review_state'] ) && is_array( $_POST['ko_lseo_review_state'] ) ? wp_unslash( $_POST['ko_lseo_review_state'] ) : array();
		$updated = 0;
		$skipped = 0;

		if ( ! in_array( $action, array( 'save_alt', 'mark_decorative', 'return_to_audit' ), true ) ) {
			self::redirect_after_bulk_action( $view, $updated, $skipped, $paged, $per_page, 'choose_action', false );
		}

		$ids = 'save_alt' === $action
			? self::resolve_save_ids( $ids, $alts, $originals, $states )
			: array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );

		if ( empty( $ids ) ) {
			self::redirect_after_bulk_action( $view, $updated, $skipped, $paged, $per_page, 'no_selection', false );
		}

		foreach ( $ids as $attachment_id ) {
			if ( 'attachment' !== get_post_type( $attachment_id ) || ! wp_attachment_is_image( $attachment_id ) || ! current_user_can( 'edit_post', $attachment_id ) ) {
				++$skipped;
				continue;
			}

			if ( 'mark_decorative' === $action ) {
				if ( '' !== trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ) ) {
					++$skipped;
					continue;
				}

				update_post_meta( $attachment_id, '_wp_attachment_image_alt', '' );
				update_post_meta( $attachment_id, self::ALT_STATE_META, self::DECORATIVE );
				++$updated;
				continue;
			}

			if ( 'return_to_audit' === $action ) {
				delete_post_meta( $attachment_id, self::ALT_STATE_META );
				++$updated;
				continue;
			}

			$alt_text = isset( $alts[ $attachment_id ] ) ? sanitize_text_field( $alts[ $attachment_id ] ) : '';

			if ( '' === trim( $alt_text ) ) {
				++$skipped;
				continue;
			}

			if ( 'missing' === $view && '' !== trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ) ) {
				++$skipped;
				continue;
			}

			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt_text );
			delete_post_meta( $attachment_id, self::ALT_STATE_META );
			++$updated;
		}

		self::redirect_after_bulk_action( $view, $updated, $skipped, $paged, $per_page, '', true );
	}

	/**
	 * Includes non-empty fields that were actually changed, even if unchecked.
	 *
	 * Suggested values remain review-only until the user edits their field or
	 * explicitly checks the row. This also provides a safe non-JavaScript path
	 * for the text-entry workflow.
	 *
	 * @param int[]                   $selected_ids Explicitly selected attachment IDs.
	 * @param array<int|string,mixed> $alts         Submitted alt values keyed by attachment ID.
	 * @param array<int|string,mixed> $originals    Initial field values keyed by attachment ID.
	 * @param array<int|string,mixed> $states       Browser review states keyed by attachment ID.
	 * @return int[]
	 */
	public static function resolve_save_ids( $selected_ids, $alts, $originals, $states = array() ) {
		$ids       = is_array( $selected_ids ) ? array_map( 'absint', $selected_ids ) : array();
		$alts      = is_array( $alts ) ? $alts : array();
		$originals = is_array( $originals ) ? $originals : array();
		$states    = is_array( $states ) ? $states : array();

		foreach ( $alts as $attachment_id => $submitted_alt ) {
			$attachment_id = absint( $attachment_id );
			$submitted_alt = sanitize_text_field( $submitted_alt );
			$original_alt  = isset( $originals[ $attachment_id ] ) ? sanitize_text_field( $originals[ $attachment_id ] ) : '';
			$review_state  = isset( $states[ $attachment_id ] ) ? sanitize_key( $states[ $attachment_id ] ) : '';

			if ( $attachment_id && 'excluded' !== $review_state && '' !== trim( $submitted_alt ) && $submitted_alt !== $original_alt ) {
				$ids[] = $attachment_id;
			}
		}

		return array_values( array_unique( array_filter( $ids ) ) );
	}

	/**
	 * Redirects back to the audit after a POST request.
	 *
	 * @param string $view         Audit view.
	 * @param int    $updated      Number of updated attachments.
	 * @param int    $skipped      Number of skipped attachments.
	 * @param int    $paged        Submitted audit page.
	 * @param int    $per_page     Requested page size.
	 * @param string $message      Optional workflow message key.
	 * @param bool   $clear_drafts Whether submitted browser drafts may be cleared.
	 * @return void
	 */
	private static function redirect_after_bulk_action( $view, $updated, $skipped, $paged, $per_page, $message = '', $clear_drafts = false ) {
		$url = add_query_arg(
			array(
				'page'            => 'ko-lightweight-seo-images',
				'view'            => 'decorative' === $view ? 'decorative' : false,
				'paged'           => $updated > 0 ? false : max( 1, (int) $paged ),
				'per_page'        => self::sanitize_per_page( $per_page ),
				'ko_lseo_updated' => (int) $updated,
				'ko_lseo_skipped' => (int) $skipped,
				'ko_lseo_message' => '' !== $message ? sanitize_key( $message ) : false,
				'ko_lseo_drafts'  => $clear_drafts ? 'clear' : false,
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Displays the result of the previous reviewed bulk action.
	 *
	 * @return void
	 */
	private static function render_update_notice() {
		$message = isset( $_GET['ko_lseo_message'] ) ? sanitize_key( wp_unslash( $_GET['ko_lseo_message'] ) ) : '';

		if ( 'no_selection' === $message ) {
			echo '<div class="notice notice-warning is-dismissible"><p><strong>Nothing was saved.</strong> Type or edit an alt value, or check at least one image before choosing an action.</p></div>';
			return;
		}

		if ( 'choose_action' === $message ) {
			echo '<div class="notice notice-warning is-dismissible"><p><strong>Nothing was saved.</strong> Choose one of the clearly labeled action buttons.</p></div>';
			return;
		}

		if ( ! isset( $_GET['ko_lseo_updated'] ) ) {
			return;
		}

		$updated = absint( $_GET['ko_lseo_updated'] );
		$skipped = isset( $_GET['ko_lseo_skipped'] ) ? absint( $_GET['ko_lseo_skipped'] ) : 0;
		?>
		<div class="notice <?php echo $updated > 0 ? 'notice-success' : 'notice-warning'; ?> is-dismissible">
			<p><?php echo esc_html( sprintf( '%1$d image(s) updated. %2$d skipped.', $updated, $skipped ) ); ?></p>
		</div>
		<?php
	}

	/**
	 * Prints pagination beneath the audit table.
	 *
	 * @param WP_Query $query    Attachment query.
	 * @param string   $view     Audit view.
	 * @param int      $per_page Requested page size.
	 * @return void
	 */
	private static function render_pagination( $query, $view, $per_page ) {
		if ( $query->max_num_pages < 2 ) {
			return;
		}

		$base_url = add_query_arg(
			array(
				'page'     => 'ko-lightweight-seo-images',
				'view'     => 'decorative' === $view ? 'decorative' : false,
				'paged'    => '%#%',
				'per_page' => self::sanitize_per_page( $per_page ),
			),
			admin_url( 'admin.php' )
		);

		echo '<div class="tablenav bottom"><div class="tablenav-pages">';
		echo wp_kses_post(
			paginate_links(
				array(
					'base'    => $base_url,
					'format'  => '',
					'current' => max( 1, absint( $_GET['paged'] ?? 1 ) ),
					'total'   => $query->max_num_pages,
				)
			)
		);
		echo '</div></div>';
	}

	/**
	 * Converts attachment text to a short single-line candidate.
	 *
	 * @param string $value Source caption, title, or filename text.
	 * @return string
	 */
	private static function clean_candidate( $value ) {
		$value = strip_shortcodes( (string) $value );
		$value = html_entity_decode( wp_strip_all_tags( $value, true ), ENT_QUOTES, get_bloginfo( 'charset' ) );

		return KO_Lightweight_SEO_Meta::trim_description( $value, 180 );
	}
}
