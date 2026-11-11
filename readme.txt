=== KO: SEO Basics ===
Contributors: KO
Tags: seo, google analytics, search console, schema, image alt text
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.7.0
License: GPL-2.0-or-later

Lightweight technical SEO for titles, descriptions, canonicals, robots, sitemaps, schema, social tags, heading checks, image alt text, and live audits.

== Description ==

KO: SEO Basics provides a deliberately focused technical SEO layer through documented WordPress APIs.

Core features:

* Per-content search title, meta description, canonical, noindex, and nofollow controls
* WordPress-native document titles, robots directives, canonicals, and sitemap exclusions
* Compact Open Graph and Twitter card metadata
* WebSite, Organization, WebPage, and Article JSON-LD where applicable
* Duplicate-output protection when Yoast SEO, Rank Math SEO, All in One SEO, or SEOPress is active
* Title-aware heading audits that report the intended H1 and identify every other H1 as an H2 recommendation
* An optional main-content guard that promotes an exact page/post title heading and demotes other content H1 elements
* Automatic Google Analytics loading after a valid GA4 Measurement ID or validated complete Google tag is saved
* Google Search Console ownership verification with direct setup links and directions
* A conventional `sitemap.xml` URL backed by the WordPress core sitemap, with the previous `ko-sitemap.xml` alias retained
* A narrow status safeguard for valid WordPress sitemap requests that would otherwise retain a false 404 response
* A reviewed Media Library workflow with adjustable page size and cross-page browser drafts
* Explicit handling for intentionally decorative images
* Conservative opt-in alt assignment for new uploads with human-authored captions or titles
* A same-site, administrator-requested audit of rendered server HTML
* One primary SEO Basics dashboard menu containing Settings, Image Alt Text, and Live HTML Audit
* A 98.5%-wide KO admin interface with full-width panels

The plugin adds no frontend CSS, full-response output buffer, or custom sitemap generator. Without a Google Analytics ID or complete Google tag, it adds no frontend JavaScript, tracking, or visitor-time external request. Saving either valid setup automatically loads that tag; there is no separate enable switch.

= Heading hierarchy =

The live audit resolves the stored WordPress page/post title and finds its first exact match among the rendered H1–H6 headings. It reports that title as the intended H1 and lists every other rendered H1 by text as an H2 recommendation.

The automatic heading guard is off by default. It should be enabled only when separate theme title output is disabled and the visible title lives inside main post content. It changes only that content: the first exact title heading becomes H1 and other content H1 elements become H2. If no exact title match is found, nothing is changed. Theme headers, footers, sidebars, and JavaScript-injected markup are not rewritten.

= Image alt text =

Existing alt text is never overwritten automatically. Generic camera names, stock-image IDs, hashes, numeric filenames, and default attachment titles are never assigned automatically.

Descriptive filename candidates appear only for human review. When no safer candidate exists, the audit shows a humanized filename fallback and labels it for review. It is not saved until edited or explicitly selected. Drafts follow the user across pagination pages in the same browser tab, and the number of displayed images can be set from 5 to 100. Images that add no meaning can be marked decorative and retain an empty `alt=""` value without remaining in the missing-alt queue.

The correct alt text depends on the image's purpose in context. Attachment metadata is global, and proprietary builders or JavaScript galleries may render different markup. The included live audit checks server-rendered HTML; browser-only injected images still require browser QA.

= Google services =

Enter a GA4 Measurement ID beginning with `G-` to load the official Google tag automatically on frontend pages. For customized Analytics setups, paste the complete Google tag instead; it takes precedence over the Measurement ID and preserves custom `gtag()` calls after validation. Clearing both fields removes the tag. Remove any existing Analytics plugin, hard-coded Google tag, or Tag Manager setup that already installs Analytics to avoid duplicate pageviews.

The Search Console field accepts either Google's verification token or the complete verification meta tag. Complete verification in Search Console after saving. Direct links and click-by-click directions beneath both Google fields explain where each value comes from.

Submit the displayed `/sitemap.xml` URL once. It is a lightweight alias for WordPress's native sitemap index, not a second sitemap generator. The previous `/ko-sitemap.xml` route remains available for existing submissions. New and revised public content is reflected through WordPress. Google retired its general sitemap-ping endpoint, so the plugin does not send a misleading or unsupported indexing ping.

Analytics can involve cookies, identifiers, and visitor data. Site owners are responsible for applicable privacy disclosures and consent behavior.

= Conflict safety =

When a supported full-service SEO plugin is active, KO metadata and schema output are suppressed. Google, image, and diagnostic tools remain available because they do not generate duplicate SEO metadata.

== Installation ==

1. Upload the plugin ZIP through Plugins > Add New > Upload Plugin.
2. Activate KO: SEO Basics.
3. Open SEO Basics > Settings and review the enabled modules.
4. Follow the links and directions beneath the Google fields to enter a GA4 Measurement ID or complete customized Google tag and the optional Search Console verification token.
5. Submit the displayed `/sitemap.xml` URL once in Search Console.
6. Open SEO Basics > Image Alt Text to review missing alternatives.
7. Open SEO Basics > Live HTML Audit and test representative pages.
8. Clear site/CDN caches and verify staging output before production use.

== Frequently Asked Questions ==

= Will this slow down the frontend? =

The SEO modules add no frontend asset, full-response output buffer, custom crawler, or visitor-time HTTP request. The optional heading guard runs only on main singular post content. A configured Analytics ID or complete Google tag intentionally adds Google's script and measurement requests.

= Does Analytics begin automatically? =

Yes. Saving a valid GA4 Measurement ID or complete Google tag immediately installs it on frontend pages. A complete tag takes precedence, and there is no additional enable checkbox.

= Does the plugin notify Google after every update? =

It updates the WordPress core sitemap data that Google supports. Google retired the general sitemap-ping endpoint, and its Indexing API is restricted to specific job-posting and livestream content, so the plugin does not make unsupported submission calls for ordinary posts and pages.

= Is `sitemap.xml` a separate sitemap generator? =

No. It is a lightweight conventional route to WordPress's native sitemap index. The previous `ko-sitemap.xml` route and standard WordPress sitemap endpoint remain available for compatibility, and the plugin does not crawl the site or store a second XML copy. The friendly filename requires WordPress's normal pretty-permalink routing; sites using plain permalinks receive an admin warning with the compatible core sitemap URL.

= Does it automatically turn filenames into alt text? =

No. Filenames can appear as review-only candidates or fallbacks, but they are not saved until edited or explicitly selected. Automatic assignment is opt-in and accepts only a human-authored attachment caption or a title that differs meaningfully from the filename.

= What happens to decorative images? =

Mark them decorative in the audit. Their alt text remains intentionally empty, and the plugin stores a private state marker so they are not repeatedly flagged as missing.

= Can it run beside another SEO plugin? =

The Google, image, and live-audit tools can. Metadata output is suppressed when Yoast SEO, Rank Math SEO, All in One SEO, or SEOPress is detected.

= Does uninstall delete alt text I approved? =

No. Uninstall removes KO settings, per-content SEO overrides, and decorative-state markers. Reviewed attachment alt text remains part of the site's content.

== Changelog ==

= 0.7.0 =
* Makes `/sitemap.xml` the primary sitemap URL shown and recommended by the plugin.
* Retains `/ko-sitemap.xml` as a compatibility alias for existing installs and Search Console submissions.
* Prevents recognized native WordPress sitemap routes from retaining an incorrect generic 404 status before their XML is rendered.
* Leaves WordPress's native sitemap renderer responsible for valid XML, unavailable providers, and genuinely empty sitemap pages.

= 0.6.0 =
* Adds a primary SEO Basics dashboard menu with Settings, Image Alt Text, and Live HTML Audit beneath it.
* Moves every generated plugin link, image view, pagination link, and workflow redirect to the unified menu URLs.
* Keeps the previous Settings, Media, and Tools page URLs available for bookmarks without displaying duplicate menu entries.
* Expands every KO admin screen to 98.5% of the available content width and lets its main panels fill that space.

= 0.5.0 =
* Adds a complete Google tag field for customized Analytics scripts, with explicit precedence over the Measurement ID field.
* Restyles all plugin admin screens in the black-and-white KO interface pattern.
* Adds an adjustable 5–100 image page size to the missing-alt and decorative views.
* Retains selected alt drafts across pagination pages in the current browser tab and saves edited fields even when their checkbox was missed.
* Uses a clearly labeled, humanized filename fallback when no safer alt suggestion exists; it remains manual-review only.
* Replaces the ambiguous bulk dropdown with explicit save, decorative, and return action buttons and clearer no-selection feedback.

= 0.4.0 =
* Adds direct Google setup links and click-by-click directions beneath the Analytics, Search Console, and sitemap fields.
* Adds `/ko-sitemap.xml` as a lightweight alias for the WordPress core sitemap index.
* Refreshes the new rewrite rule once on activation or upgrade without adding a per-request flush.

= 0.3.0 =
* Renames the plugin to KO: SEO Basics with a clearer feature description.
* Automatically loads the official Google tag after a valid GA4 Measurement ID is saved.
* Adds Search Console ownership verification and core sitemap submission guidance.
* Rejects legacy Analytics IDs, unsafe verification markup, and unsupported generic Google indexing pings.

= 0.2.0 =
* Maps the WordPress page/post title to its rendered heading in the live audit.
* Reports each secondary H1 as a named H2 recommendation.
* Adds an opt-in, main-content-only heading hierarchy guard.

= 0.1.0 =
* Adds native title, description, canonical, robots, and sitemap behavior.
* Adds Open Graph, Twitter card, and compact JSON-LD output.
* Adds conflict protection for major full-service SEO plugins.
* Adds reviewed missing-alt and decorative-image workflows.
* Adds conservative opt-in alt assignment for human-authored attachment data.
* Adds a same-site live HTML audit with no stored crawl data.
