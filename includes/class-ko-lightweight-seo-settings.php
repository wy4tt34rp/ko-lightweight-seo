<?php
/**
 * Registers the compact settings screen and per-content SEO fields.
 *
 * @package KO_Lightweight_SEO
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class KO_Lightweight_SEO_Settings {
	const OPTION_KEY      = 'ko_lightweight_seo_settings';
	const MENU_SLUG       = 'ko-lightweight-seo';
	const IMAGE_PAGE_SLUG = 'ko-lightweight-seo-images';
	const AUDIT_PAGE_SLUG = 'ko-lightweight-seo-audit';

	/**
	 * Detected SEO suite that suppresses this plugin's metadata module.
	 *
	 * @var string
	 */
	private static $conflicting_plugin = '';

	/**
	 * Request-local settings cache.
	 *
	 * @var array<string, int|string>|null
	 */
	private static $settings_cache = null;

	/**
	 * Registers settings and editor hooks.
	 *
	 * @param string $conflicting_plugin Active full-service SEO plugin name.
	 * @return void
	 */
	public static function init( $conflicting_plugin = '' ) {
		self::$conflicting_plugin = (string) $conflicting_plugin;

		add_action( 'admin_menu', array( __CLASS__, 'register_settings_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( KO_LSEO_FILE ), array( __CLASS__, 'add_plugin_action_links' ) );

		$settings = self::get_settings();

		if ( '' === self::$conflicting_plugin && ! empty( $settings['enable_metadata'] ) ) {
			add_action( 'add_meta_boxes', array( __CLASS__, 'register_meta_boxes' ) );
			add_action( 'save_post', array( __CLASS__, 'save_post_metadata' ) );
		}
	}

	/**
	 * Returns the default plugin settings.
	 *
	 * Automatic alt assignment is opt-in because an image's correct text
	 * alternative depends on its meaning in context.
	 *
	 * @return array<string, int|string>
	 */
	public static function get_defaults() {
		return array(
			'enable_metadata'      => 1,
			'enable_social'        => 1,
			'enable_schema'        => 1,
			'enable_heading_guard' => 0,
			'organization_name'    => '',
			'image_alt_autofill'   => 0,
			'google_analytics_id'   => '',
			'google_tag_snippet'    => '',
			'google_verification'   => '',
		);
	}

	/**
	 * Returns validated settings merged with defaults.
	 *
	 * @return array<string, int|string>
	 */
	public static function get_settings() {
		if ( is_array( self::$settings_cache ) ) {
			return self::$settings_cache;
		}

		$stored = get_option( self::OPTION_KEY, array() );

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		self::$settings_cache = array_merge( self::get_defaults(), $stored );

		return self::$settings_cache;
	}

	/**
	 * Registers the Settings API fields.
	 *
	 * @return void
	 */
	public static function register_settings() {
		register_setting(
			'ko_lightweight_seo',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
				'default'           => self::get_defaults(),
			)
		);

		add_settings_section(
			'ko_lseo_search_output',
			'Search output',
			array( __CLASS__, 'render_search_section' ),
			'ko-lightweight-seo'
		);

		add_settings_field(
			'enable_metadata',
			'Essential metadata',
			array( __CLASS__, 'render_checkbox_field' ),
			'ko-lightweight-seo',
			'ko_lseo_search_output',
			array(
				'key'         => 'enable_metadata',
				'label'       => 'Enable titles, descriptions, canonicals, robots controls, and sitemap exclusions.',
				'description' => 'Disable this only when another system owns all technical SEO output.',
			)
		);

		add_settings_field(
			'enable_social',
			'Social metadata',
			array( __CLASS__, 'render_checkbox_field' ),
			'ko-lightweight-seo',
			'ko_lseo_search_output',
			array(
				'key'         => 'enable_social',
				'label'       => 'Output Open Graph and Twitter card metadata.',
				'description' => 'Featured images are preferred; the site logo is the fallback image.',
			)
		);

		add_settings_field(
			'enable_schema',
			'Structured data',
			array( __CLASS__, 'render_checkbox_field' ),
			'ko-lightweight-seo',
			'ko_lseo_search_output',
			array(
				'key'         => 'enable_schema',
				'label'       => 'Output a compact WebSite, Organization, WebPage, and Article graph where applicable.',
				'description' => 'The graph is generated from published WordPress data and does not call a remote service.',
			)
		);

		add_settings_field(
			'organization_name',
			'Organization name',
			array( __CLASS__, 'render_text_field' ),
			'ko-lightweight-seo',
			'ko_lseo_search_output',
			array(
				'key'         => 'organization_name',
				'placeholder' => get_bloginfo( 'name' ),
				'description' => 'Leave blank to use the WordPress site title.',
			)
		);

		add_settings_field(
			'enable_heading_guard',
			'Heading hierarchy guard',
			array( __CLASS__, 'render_checkbox_field' ),
			'ko-lightweight-seo',
			'ko_lseo_search_output',
			array(
				'key'         => 'enable_heading_guard',
				'label'       => 'Keep the WordPress page or post title as the primary H1 inside rendered content.',
				'description' => 'Enable only when separate theme or builder title output is disabled and the visible title lives in main content. The first exact title heading becomes H1; other content H1 elements become H2. Headers, footers, and unrelated queries are never rewritten.',
			)
		);

		add_settings_section(
			'ko_lseo_google_services',
			'Google services',
			array( __CLASS__, 'render_google_section' ),
			'ko-lightweight-seo'
		);

		add_settings_field(
			'google_analytics_id',
			'GA4 Measurement ID',
			array( __CLASS__, 'render_google_analytics_field' ),
			'ko-lightweight-seo',
			'ko_lseo_google_services',
			array(
				'key'         => 'google_analytics_id',
				'placeholder' => 'G-XXXXXXXXXX',
				'description' => 'Enter the GA4 Measurement ID. Saving a valid ID automatically loads the Google tag on every frontend page; there is no separate enable switch.',
			)
		);

		add_settings_field(
			'google_tag_snippet',
			'Complete Google tag',
			array( __CLASS__, 'render_google_tag_field' ),
			'ko-lightweight-seo',
			'ko_lseo_google_services',
			array(
				'key'         => 'google_tag_snippet',
				'description' => 'Paste the complete Google Analytics or Google tag script when custom gtag() configuration is required. A saved complete tag takes precedence over the Measurement ID field above.',
			)
		);

		add_settings_field(
			'google_verification',
			'Search Console HTML tag',
			array( __CLASS__, 'render_google_verification_field' ),
			'ko-lightweight-seo',
			'ko_lseo_google_services',
			array(
				'key'         => 'google_verification',
				'placeholder' => 'Paste the verification token or complete meta tag',
				'description' => 'The verification meta tag is added to the site head. Complete verification in Google Search Console after saving.',
			)
		);

		add_settings_field(
			'google_sitemap',
			'Sitemap for Google',
			array( __CLASS__, 'render_google_sitemap_field' ),
			'ko-lightweight-seo',
			'ko_lseo_google_services'
		);

		add_settings_section(
			'ko_lseo_image_tools',
			'Image alt text',
			array( __CLASS__, 'render_image_section' ),
			'ko-lightweight-seo'
		);

		add_settings_field(
			'image_alt_autofill',
			'New uploads',
			array( __CLASS__, 'render_checkbox_field' ),
			'ko-lightweight-seo',
			'ko_lseo_image_tools',
			array(
				'key'         => 'image_alt_autofill',
				'label'       => 'Assign missing alt text on upload only when a human-authored caption or title is available.',
				'description' => 'Generic filenames, stock-image IDs, and camera filenames are never assigned automatically.',
			)
		);
	}

	/**
	 * Sanitizes the complete settings record.
	 *
	 * @param mixed $input Submitted settings.
	 * @return array<string, int|string>
	 */
	public static function sanitize_settings( $input ) {
		$input     = is_array( $input ) ? $input : array();
		$previous  = get_option( self::OPTION_KEY, array() );
		$previous  = is_array( $previous ) ? $previous : array();
		$sanitized = array(
			'enable_metadata'      => empty( $input['enable_metadata'] ) ? 0 : 1,
			'enable_social'        => empty( $input['enable_social'] ) ? 0 : 1,
			'enable_schema'        => empty( $input['enable_schema'] ) ? 0 : 1,
			'enable_heading_guard' => empty( $input['enable_heading_guard'] ) ? 0 : 1,
			'organization_name'    => isset( $input['organization_name'] ) ? sanitize_text_field( $input['organization_name'] ) : '',
			'image_alt_autofill'   => empty( $input['image_alt_autofill'] ) ? 0 : 1,
			'google_analytics_id'   => self::sanitize_google_analytics_id( isset( $input['google_analytics_id'] ) ? $input['google_analytics_id'] : '' ),
			'google_tag_snippet'    => self::sanitize_google_tag_snippet(
				isset( $input['google_tag_snippet'] ) ? $input['google_tag_snippet'] : '',
				isset( $previous['google_tag_snippet'] ) ? $previous['google_tag_snippet'] : ''
			),
			'google_verification'   => self::sanitize_google_verification( isset( $input['google_verification'] ) ? $input['google_verification'] : '' ),
		);

		self::$settings_cache = $sanitized;

		return $sanitized;
	}

	/**
	 * Accepts a GA4 Measurement ID and rejects legacy or malformed IDs.
	 *
	 * @param mixed $value Submitted Analytics value.
	 * @return string
	 */
	public static function sanitize_google_analytics_id( $value ) {
		$value = strtoupper( trim( sanitize_text_field( (string) $value ) ) );

		if ( '' === $value || preg_match( '/^G-[A-Z0-9]{4,32}$/', $value ) ) {
			return $value;
		}

		if ( function_exists( 'add_settings_error' ) ) {
			add_settings_error(
				self::OPTION_KEY,
				'ko_lseo_invalid_google_analytics_id',
				'Google Analytics was not saved. Enter a GA4 Measurement ID beginning with G-.'
			);
		}

		return '';
	}

	/**
	 * Accepts a complete, administrator-supplied Google tag script.
	 *
	 * The field intentionally supports inline gtag() customization. It remains
	 * limited to script-only Google Analytics markup and users who may publish
	 * unfiltered HTML, so it does not become a general-purpose code injector.
	 *
	 * @param mixed  $value    Submitted Google tag markup.
	 * @param string $previous Previously saved valid markup.
	 * @return string
	 */
	public static function sanitize_google_tag_snippet( $value, $previous = '' ) {
		$value    = function_exists( 'wp_unslash' ) ? wp_unslash( (string) $value ) : (string) $value;
		$value    = trim( str_replace( array( "\r\n", "\r" ), "\n", $value ) );
		$previous = (string) $previous;

		if ( '' === $value ) {
			return '';
		}

		if ( function_exists( 'current_user_can' ) && ! current_user_can( 'unfiltered_html' ) ) {
			self::add_google_tag_error( 'Only an administrator allowed to publish unfiltered HTML can change the complete Google tag.' );
			return $previous;
		}

		if ( strlen( $value ) > 30000 ) {
			self::add_google_tag_error( 'The complete Google tag was not saved because it exceeds the 30,000-character limit.' );
			return $previous;
		}

		$without_comments = preg_replace( '/<!--.*?-->/s', '', $value );
		$script_pattern   = '/<script\b([^>]*)>.*?<\/script\s*>/is';

		if ( ! preg_match_all( $script_pattern, $without_comments, $scripts, PREG_SET_ORDER ) ) {
			self::add_google_tag_error( 'The complete Google tag was not saved. Paste the full script snippet supplied by Google.' );
			return $previous;
		}

		$remaining = preg_replace( $script_pattern, '', $without_comments );

		if ( '' !== trim( (string) $remaining ) ) {
			self::add_google_tag_error( 'The complete Google tag was not saved. This field accepts Google script tags only.' );
			return $previous;
		}

		foreach ( $scripts as $script ) {
			if ( ! preg_match_all( '/\bsrc\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', $script[1], $sources, PREG_SET_ORDER ) ) {
				continue;
			}

			foreach ( $sources as $source ) {
				$source_url = '' !== $source[1] ? $source[1] : ( '' !== $source[2] ? $source[2] : $source[3] );
				$scheme     = strtolower( (string) wp_parse_url( $source_url, PHP_URL_SCHEME ) );
				$host       = strtolower( (string) wp_parse_url( $source_url, PHP_URL_HOST ) );

				if ( 'https' !== $scheme || ! in_array( $host, array( 'www.googletagmanager.com', 'www.google-analytics.com', 'ssl.google-analytics.com' ), true ) ) {
					self::add_google_tag_error( 'The complete Google tag was not saved because it contains a non-Google or non-HTTPS script source.' );
					return $previous;
				}
			}
		}

		if ( ! preg_match( '/(?:googletagmanager\.com\/gtag\/js|google-analytics\.com|\bgtag\s*\(|\bdataLayer\b)/i', $value ) ) {
			self::add_google_tag_error( 'The complete Google tag was not saved because Google Analytics tag code could not be identified.' );
			return $previous;
		}

		return $value;
	}

	/**
	 * Adds one settings error for a rejected complete Google tag.
	 *
	 * @param string $message Human-readable validation message.
	 * @return void
	 */
	private static function add_google_tag_error( $message ) {
		if ( function_exists( 'add_settings_error' ) ) {
			add_settings_error(
				self::OPTION_KEY,
				'ko_lseo_invalid_google_tag_snippet',
				$message
			);
		}
	}

	/**
	 * Accepts either a Search Console token or its complete HTML meta tag.
	 *
	 * @param mixed $value Submitted verification value.
	 * @return string
	 */
	public static function sanitize_google_verification( $value ) {
		$value = html_entity_decode( trim( (string) $value ), ENT_QUOTES, 'UTF-8' );

		if ( preg_match( '/\bcontent\s*=\s*(["\'])(.*?)\1/is', $value, $content_match ) ) {
			$value = $content_match[2];
		} elseif ( preg_match( '/google-site-verification\s*[:=]\s*([A-Za-z0-9._:=\-]+)/i', $value, $token_match ) ) {
			$value = $token_match[1];
		}

		$value = trim( sanitize_text_field( $value ) );

		if ( '' === $value || preg_match( '/^[A-Za-z0-9._:=\-]{6,255}$/', $value ) ) {
			return $value;
		}

		if ( function_exists( 'add_settings_error' ) ) {
			add_settings_error(
				self::OPTION_KEY,
				'ko_lseo_invalid_google_verification',
				'Search Console verification was not saved. Paste the token or the complete verification meta tag supplied by Google.'
			);
		}

		return '';
	}

	/**
	 * Adds the primary SEO Basics menu and its Settings screen.
	 *
	 * The hidden Settings registration keeps the previous admin URL working for
	 * saved bookmarks and links from older plugin releases.
	 *
	 * @return void
	 */
	public static function register_settings_page() {
		add_options_page(
			'KO: SEO Basics',
			'KO SEO',
			'manage_options',
			self::MENU_SLUG,
			array( __CLASS__, 'render_settings_page' )
		);

		remove_submenu_page( 'options-general.php', self::MENU_SLUG );

		add_menu_page(
			'KO: SEO Basics',
			'SEO Basics',
			'manage_options',
			self::MENU_SLUG,
			array( __CLASS__, 'render_settings_page' ),
			'dashicons-search',
			58
		);

		add_submenu_page(
			self::MENU_SLUG,
			'KO: SEO Basics',
			'Settings',
			'manage_options',
			self::MENU_SLUG,
			array( __CLASS__, 'render_settings_page' )
		);
	}

	/**
	 * Loads the small stylesheet only on this plugin's admin screens.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public static function enqueue_admin_assets( $hook_suffix ) {
		$legacy_screens = array(
			'settings_page_ko-lightweight-seo',
			'media_page_ko-lightweight-seo-images',
			'tools_page_ko-lightweight-seo-audit',
		);
		$plugin_pages   = array(
			self::MENU_SLUG,
			self::IMAGE_PAGE_SLUG,
			self::AUDIT_PAGE_SLUG,
		);
		$current_page   = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';

		if ( ! in_array( $hook_suffix, $legacy_screens, true ) && ! in_array( $current_page, $plugin_pages, true ) ) {
			return;
		}

		wp_enqueue_style(
			'ko-lightweight-seo-admin',
			KO_LSEO_URL . 'assets/admin.css',
			array(),
			KO_LSEO_VERSION
		);

		if ( 'media_page_ko-lightweight-seo-images' === $hook_suffix || self::IMAGE_PAGE_SLUG === $current_page ) {
			wp_enqueue_script(
				'ko-lightweight-seo-image-audit',
				KO_LSEO_URL . 'assets/admin.js',
				array(),
				KO_LSEO_VERSION,
				true
			);
		}
	}

	/**
	 * Adds quick links from the Plugins screen.
	 *
	 * @param string[] $links Existing plugin action links.
	 * @return string[]
	 */
	public static function add_plugin_action_links( $links ) {
		array_unshift(
			$links,
			'<a href="' . esc_url( admin_url( 'admin.php?page=' . self::MENU_SLUG ) ) . '">Settings</a>',
			'<a href="' . esc_url( admin_url( 'admin.php?page=' . self::IMAGE_PAGE_SLUG ) ) . '">Image Alt Text</a>'
		);

		return $links;
	}

	/**
	 * Explains the metadata module's scope.
	 *
	 * @return void
	 */
	public static function render_search_section() {
		echo '<p>KO SEO uses WordPress core hooks and sitemaps. Its SEO modules add no frontend stylesheet, tracker, or remote request. A configured Analytics ID or complete Google tag intentionally adds Google\'s tracking script.</p>';
	}

	/**
	 * Explains the intentionally simple Google setup.
	 *
	 * @return void
	 */
	public static function render_google_section() {
		echo '<p>Connect Google services without OAuth or stored account credentials. Use either the Measurement ID or the complete-tag field; the complete tag wins when both contain a value. Remove any other Analytics or Tag Manager installation first, or pageviews may be counted twice. Ensure the site\'s privacy and consent setup permits Analytics to load.</p>';
	}

	/**
	 * Displays the core sitemap that should be submitted once in Search Console.
	 *
	 * @return void
	 */
	public static function render_google_sitemap_field() {
		$sitemap_url      = KO_Lightweight_SEO_Sitemap::get_url();
		$core_sitemap_url = get_sitemap_url( 'index' );
		?>
		<code class="ko-lseo-break-word"><?php echo esc_html( $sitemap_url ); ?></code>
		<p class="description">This lightweight URL serves WordPress's native sitemap index. New and revised public content is reflected automatically; the previous <code>ko-sitemap.xml</code> and standard WordPress sitemap URLs remain available for compatibility.</p>
		<?php if ( '' === get_option( 'permalink_structure' ) ) : ?>
			<div class="notice notice-warning inline"><p><strong>Pretty permalinks are required for the branded filename.</strong> Until they are enabled under Settings &gt; Permalinks, submit <a href="<?php echo esc_url( $core_sitemap_url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $core_sitemap_url ); ?></a> instead.</p></div>
		<?php endif; ?>
		<div class="ko-lseo-field-help">
			<p><strong>Submit it to Google:</strong></p>
			<ol>
				<li>Open Search Console and select the correct property.</li>
				<li>Open <strong>Sitemaps</strong>, enter <code>sitemap.xml</code> under <strong>Add a new sitemap</strong>, and click <strong>Submit</strong>.</li>
				<li>Confirm that Google reports a successful fetch. You only need to submit the sitemap once.</li>
			</ol>
			<p><a href="<?php echo esc_url( $sitemap_url ); ?>" target="_blank" rel="noopener noreferrer">Open KO sitemap</a> &nbsp; <a href="https://search.google.com/search-console/sitemaps" target="_blank" rel="noopener noreferrer">Open Search Console Sitemaps</a> &nbsp; <a href="https://support.google.com/webmasters/answer/7451001" target="_blank" rel="noopener noreferrer">Google's sitemap instructions</a></p>
		</div>
		<?php
	}

	/**
	 * Links the settings screen to the reviewed image workflow.
	 *
	 * @return void
	 */
	public static function render_image_section() {
		printf(
			'<p>Review existing media under <a href="%s">SEO Basics &gt; Image Alt Text</a>. Empty alt text can be intentionally marked decorative so it is not repeatedly flagged.</p>',
			esc_url( admin_url( 'admin.php?page=' . self::IMAGE_PAGE_SLUG ) )
		);
	}

	/**
	 * Renders a Settings API checkbox.
	 *
	 * @param array<string, string> $args Field configuration.
	 * @return void
	 */
	public static function render_checkbox_field( $args ) {
		$settings = self::get_settings();
		$key      = $args['key'];
		?>
		<label>
			<input
				type="checkbox"
				name="<?php echo esc_attr( self::OPTION_KEY . '[' . $key . ']' ); ?>"
				value="1"
				<?php checked( ! empty( $settings[ $key ] ) ); ?>
			/>
			<?php echo esc_html( $args['label'] ); ?>
		</label>
		<?php if ( ! empty( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Renders a Settings API text field.
	 *
	 * @param array<string, string> $args Field configuration.
	 * @return void
	 */
	public static function render_text_field( $args ) {
		$settings = self::get_settings();
		$key      = $args['key'];
		?>
		<input
			type="text"
			class="regular-text"
			name="<?php echo esc_attr( self::OPTION_KEY . '[' . $key . ']' ); ?>"
			value="<?php echo esc_attr( $settings[ $key ] ); ?>"
			placeholder="<?php echo esc_attr( $args['placeholder'] ); ?>"
		/>
		<?php if ( ! empty( $args['description'] ) ) : ?>
			<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Renders the Analytics field with direct setup instructions.
	 *
	 * @param array<string, string> $args Field configuration.
	 * @return void
	 */
	public static function render_google_analytics_field( $args ) {
		self::render_text_field( $args );
		?>
		<div class="ko-lseo-field-help">
			<p><strong>Find the Measurement ID:</strong></p>
			<ol>
				<li>Open Google Analytics and select the correct account and GA4 property.</li>
				<li>Open <strong>Admin &gt; Data streams &gt; Web</strong>, then select or create the site's web stream.</li>
				<li>Copy the <strong>Measurement ID</strong> beginning with <code>G-</code>, paste it above, and save. Tracking starts automatically.</li>
			</ol>
			<p><a href="https://analytics.google.com/analytics/web/#/admin" target="_blank" rel="noopener noreferrer">Open Google Analytics Admin</a> &nbsp; <a href="https://support.google.com/analytics/answer/12270356" target="_blank" rel="noopener noreferrer">Google's Measurement ID instructions</a></p>
		</div>
		<?php
	}

	/**
	 * Renders the complete Google tag field for customized installations.
	 *
	 * @param array<string, string> $args Field configuration.
	 * @return void
	 */
	public static function render_google_tag_field( $args ) {
		$settings = self::get_settings();
		$key      = $args['key'];
		?>
		<textarea
			class="large-text code ko-lseo-code-field"
			name="<?php echo esc_attr( self::OPTION_KEY . '[' . $key . ']' ); ?>"
			rows="9"
			spellcheck="false"
			placeholder="&lt;!-- Google tag (gtag.js) --&gt;&#10;&lt;script async src=&quot;https://www.googletagmanager.com/gtag/js?id=G-XXXXXXXXXX&quot;&gt;&lt;/script&gt;&#10;&lt;script&gt;...&lt;/script&gt;"
		><?php echo esc_textarea( $settings[ $key ] ); ?></textarea>
		<p class="description"><?php echo esc_html( $args['description'] ); ?></p>
		<div class="ko-lseo-field-help">
			<p><strong>Use this for a customized Google tag:</strong></p>
			<ol>
				<li>Copy the complete Google tag, including its opening and closing <code>&lt;script&gt;</code> tags.</li>
				<li>Add any required <code>gtag()</code> configuration inside that snippet, then paste the complete result above.</li>
				<li>Save changes and confirm the customized tag appears once in the frontend page source.</li>
			</ol>
			<p>Only Google Analytics script markup is accepted. Clearing this field returns control to the Measurement ID field above.</p>
			<p><a href="https://developers.google.com/tag-platform/devguides/gtag-integration" target="_blank" rel="noopener noreferrer">Google's complete-tag instructions</a></p>
		</div>
		<?php
	}

	/**
	 * Renders the Search Console field with verification guidance.
	 *
	 * @param array<string, string> $args Field configuration.
	 * @return void
	 */
	public static function render_google_verification_field( $args ) {
		self::render_text_field( $args );
		?>
		<div class="ko-lseo-field-help">
			<p><strong>Verify with this field:</strong></p>
			<ol>
				<li>In Search Console, add or select the site's <strong>URL-prefix property</strong>.</li>
				<li>Choose <strong>HTML tag</strong> under ownership verification and copy the complete meta tag.</li>
				<li>Paste it above, save, clear any page cache, then return to Search Console and click <strong>Verify</strong>.</li>
			</ol>
			<p>Do not paste an HTML verification file here. DNS is the more durable primary verification method for a Domain property and does not require this field; the HTML tag can be kept as an additional method.</p>
			<p><a href="https://search.google.com/search-console/" target="_blank" rel="noopener noreferrer">Open Google Search Console</a> &nbsp; <a href="https://support.google.com/webmasters/answer/9008080" target="_blank" rel="noopener noreferrer">Google's verification instructions</a></p>
		</div>
		<?php
	}

	/**
	 * Renders the plugin settings screen.
	 *
	 * @return void
	 */
	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap ko-lseo-wrap">
			<?php self::render_admin_header( 'Settings', 'KO: SEO Basics', 'Essential technical SEO with a deliberately small runtime footprint.' ); ?>

			<?php if ( '' !== self::$conflicting_plugin ) : ?>
				<div class="notice notice-warning inline">
					<p>
						<strong>Duplicate metadata protection is active.</strong>
						<?php echo esc_html( self::$conflicting_plugin ); ?> is currently providing SEO output. KO metadata, social tags, and schema are suppressed; image and audit tools remain available.
					</p>
				</div>
			<?php endif; ?>

			<form method="post" action="options.php" class="ko-lseo-card ko-lseo-panel">
				<?php
				settings_fields( 'ko_lightweight_seo' );
				do_settings_sections( 'ko-lightweight-seo' );
				submit_button();
				?>
			</form>

			<div class="ko-lseo-links">
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::IMAGE_PAGE_SLUG ) ); ?>">Open Image Alt Text</a>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::AUDIT_PAGE_SLUG ) ); ?>">Run Live HTML Audit</a>
			</div>
		</div>
		<?php
	}

	/**
	 * Renders the shared black-and-white header used by KO SEO admin screens.
	 *
	 * @param string $section Short screen label.
	 * @param string $title   Screen title.
	 * @param string $lede    Short screen description.
	 * @return void
	 */
	public static function render_admin_header( $section, $title, $lede ) {
		?>
		<header class="ko-lseo-hero">
			<div class="ko-lseo-brand-mark" aria-hidden="true">KO</div>
			<div>
				<span>KO / SEO BASICS / <?php echo esc_html( $section ); ?></span>
				<h1><?php echo esc_html( $title ); ?></h1>
				<p><?php echo esc_html( $lede ); ?></p>
			</div>
		</header>
		<?php
	}

	/**
	 * Registers the SEO panel on public, editable content types.
	 *
	 * @return void
	 */
	public static function register_meta_boxes() {
		$post_types = get_post_types( array( 'show_ui' => true ), 'objects' );

		foreach ( $post_types as $post_type ) {
			if ( 'attachment' === $post_type->name || ! is_post_type_viewable( $post_type ) ) {
				continue;
			}

			add_meta_box(
				'ko-lightweight-seo',
				'KO: SEO Basics',
				array( __CLASS__, 'render_meta_box' ),
				$post_type->name,
				'normal',
				'default'
			);
		}
	}

	/**
	 * Renders per-content metadata overrides.
	 *
	 * @param WP_Post $post Current post.
	 * @return void
	 */
	public static function render_meta_box( $post ) {
		$title       = get_post_meta( $post->ID, KO_Lightweight_SEO_Meta::META_TITLE, true );
		$description = get_post_meta( $post->ID, KO_Lightweight_SEO_Meta::META_DESCRIPTION, true );
		$canonical   = get_post_meta( $post->ID, KO_Lightweight_SEO_Meta::META_CANONICAL, true );
		$noindex     = get_post_meta( $post->ID, KO_Lightweight_SEO_Meta::META_NOINDEX, true );
		$nofollow    = get_post_meta( $post->ID, KO_Lightweight_SEO_Meta::META_NOFOLLOW, true );

		wp_nonce_field( 'ko_lseo_save_post_metadata', 'ko_lseo_meta_nonce' );
		?>
		<div class="ko-lseo-meta-fields">
			<p>
				<label for="ko-lseo-title"><strong>Search title</strong></label><br />
				<input type="text" class="widefat" id="ko-lseo-title" name="ko_lseo_title" value="<?php echo esc_attr( $title ); ?>" maxlength="300" />
				<span class="description">Leave blank to use WordPress's generated document title.</span>
			</p>
			<p>
				<label for="ko-lseo-description"><strong>Meta description</strong></label><br />
				<textarea class="widefat" id="ko-lseo-description" name="ko_lseo_description" rows="3" maxlength="500"><?php echo esc_textarea( $description ); ?></textarea>
				<span class="description">Leave blank to use the excerpt or a short plain-text content summary.</span>
			</p>
			<p>
				<label for="ko-lseo-canonical"><strong>Canonical URL</strong></label><br />
				<input type="url" class="widefat" id="ko-lseo-canonical" name="ko_lseo_canonical" value="<?php echo esc_attr( $canonical ); ?>" placeholder="<?php echo esc_attr( get_permalink( $post ) ); ?>" />
				<span class="description">Use an absolute HTTP or HTTPS URL only when this content has a different preferred location.</span>
			</p>
			<p class="ko-lseo-index-controls">
				<label><input type="checkbox" name="ko_lseo_noindex" value="1" <?php checked( '1', $noindex ); ?> /> Prevent search engines from indexing this content and exclude it from the core sitemap.</label><br />
				<label><input type="checkbox" name="ko_lseo_nofollow" value="1" <?php checked( '1', $nofollow ); ?> /> Ask search engines not to follow links from this content.</label>
			</p>
		</div>
		<?php
	}

	/**
	 * Saves per-content metadata without overwriting unrelated post data.
	 *
	 * @param int $post_id Saved post ID.
	 * @return void
	 */
	public static function save_post_metadata( $post_id ) {
		if ( ! isset( $_POST['ko_lseo_meta_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['ko_lseo_meta_nonce'] ) );

		if ( ! wp_verify_nonce( $nonce, 'ko_lseo_save_post_metadata' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$title       = isset( $_POST['ko_lseo_title'] ) ? sanitize_text_field( wp_unslash( $_POST['ko_lseo_title'] ) ) : '';
		$description = isset( $_POST['ko_lseo_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['ko_lseo_description'] ) ) : '';
		$canonical   = isset( $_POST['ko_lseo_canonical'] ) ? self::sanitize_canonical_url( wp_unslash( $_POST['ko_lseo_canonical'] ) ) : '';

		self::update_or_delete_post_meta( $post_id, KO_Lightweight_SEO_Meta::META_TITLE, $title );
		self::update_or_delete_post_meta( $post_id, KO_Lightweight_SEO_Meta::META_DESCRIPTION, $description );
		self::update_or_delete_post_meta( $post_id, KO_Lightweight_SEO_Meta::META_CANONICAL, $canonical );
		self::update_or_delete_post_meta( $post_id, KO_Lightweight_SEO_Meta::META_NOINDEX, empty( $_POST['ko_lseo_noindex'] ) ? '' : '1' );
		self::update_or_delete_post_meta( $post_id, KO_Lightweight_SEO_Meta::META_NOFOLLOW, empty( $_POST['ko_lseo_nofollow'] ) ? '' : '1' );
	}

	/**
	 * Accepts absolute HTTP and HTTPS canonical URLs only.
	 *
	 * @param string $url Submitted URL.
	 * @return string
	 */
	public static function sanitize_canonical_url( $url ) {
		$url    = esc_url_raw( trim( (string) $url ), array( 'http', 'https' ) );
		$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
		$host   = wp_parse_url( $url, PHP_URL_HOST );

		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) || empty( $host ) ) {
			return '';
		}

		return $url;
	}

	/**
	 * Stores a non-empty value or removes the now-unused metadata row.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Metadata key.
	 * @param string $value   Sanitized value.
	 * @return void
	 */
	private static function update_or_delete_post_meta( $post_id, $key, $value ) {
		if ( '' === $value ) {
			delete_post_meta( $post_id, $key );
			return;
		}

		update_post_meta( $post_id, $key, $value );
	}
}
