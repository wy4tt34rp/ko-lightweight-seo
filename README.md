# KO: SEO Basics

KO: SEO Basics is a focused WordPress technical SEO plugin for titles, descriptions, canonicals, robots controls, core sitemaps, structured data, social metadata, heading checks, image alt text, Google setup, and live HTML audits. It uses native WordPress APIs and keeps its admin tools out of normal page rendering. The only optional frontend dependency is the Google tag, loaded automatically from either a GA4 Measurement ID or a validated complete tag supplied by an administrator.

Version 0.7.0 is a measurable, deliberately focused release. It is intentionally smaller than a full marketing suite.

## Code provenance

This is an original KO implementation with no copied SEO-suite code and no bundled third-party PHP, JavaScript, or CSS dependency. It is built against public extension points and published standards:

- [WordPress document-title filter](https://developer.wordpress.org/reference/hooks/pre_get_document_title/)
- [WordPress robots API](https://developer.wordpress.org/reference/functions/wp_robots/)
- [WordPress canonical API](https://developer.wordpress.org/reference/functions/wp_get_canonical_url/)
- [WordPress sitemap classes and filters](https://developer.wordpress.org/reference/classes/wp_sitemaps/)
- [Google tag CMS integration](https://developers.google.com/tag-platform/devguides/gtag-integration)
- [Google Search Console ownership verification](https://support.google.com/webmasters/answer/9008080)
- [Google sitemap and `lastmod` guidance](https://developers.google.com/search/blog/2023/06/sitemaps-lastmod-ping)
- [Google image SEO guidance](https://developers.google.com/search/docs/appearance/google-images)
- [W3C image alt-text decision tree](https://www.w3.org/WAI/tutorials/images/decision-tree/)

## What it does

- Uses WordPress document-title and canonical APIs instead of replacing them
- Outputs one useful meta description when content is available
- Adds per-content title, description, canonical, noindex, and nofollow controls
- Excludes explicitly noindexed content from WordPress core post sitemaps
- Outputs compact Open Graph and Twitter card metadata
- Outputs a connected WebSite, Organization, WebPage, and Article JSON-LD graph where applicable
- Detects Yoast SEO, Rank Math SEO, All in One SEO, and SEOPress before registering competing frontend output
- Maps the stored WordPress page/post title to its rendered heading during the live audit
- Reports every other rendered H1 as a specific H2 recommendation
- Optionally keeps an exact title heading as H1 and demotes other H1 elements inside main post content
- Automatically loads the official asynchronous Google tag after a valid GA4 Measurement ID is saved
- Accepts a complete customized Google tag when advanced `gtag()` configuration is needed
- Outputs a sanitized Google Search Console ownership-verification token
- Adds direct Google setup links and click-by-click directions beneath each Google field
- Serves the WordPress core sitemap index at `/sitemap.xml` while retaining `/ko-sitemap.xml` as a compatibility alias
- Prevents recognized WordPress sitemap routes from retaining a false post-query 404 status before core renders their XML
- Shows the branded sitemap URL that should be submitted once in Search Console
- Audits Media Library images that are missing alt text
- Provides reviewed candidates from captions, human-authored titles, descriptive filenames, and a clearly labeled filename fallback
- Retains selected alt-text drafts across audit pagination pages in the same browser tab
- Allows 5–100 images per audit page and saves edited non-empty fields even when a checkbox is missed
- Allows intentionally decorative images to keep `alt=""` without remaining in the missing-alt queue
- Optionally assigns alt text on upload when a human-authored caption or title is available
- Runs an administrator-requested, same-site live HTML audit for metadata, schema, title-aware heading hierarchy, and rendered image alternatives
- Groups Settings, Image Alt Text, and Live HTML Audit beneath one primary **SEO Basics** dashboard menu
- Uses 98.5% of the available admin content width for the KO interface and full-width panels

## Lightweight contract

Normal frontend requests always receive:

- No plugin stylesheet
- No full-response output buffer
- No custom sitemap generator
- No content scoring process

Without a Google Analytics ID or complete Google tag, the plugin adds no frontend JavaScript, tracking, or visitor-time external request. After a valid `G-...` ID is saved, Analytics is intentionally automatic: one asynchronous Google library and its small inline queue bootstrap are added to frontend pages. When a validated complete tag is saved, that customized script is output instead and the generated ID-based tag is suppressed. There is no second enable checkbox.

The plugin keeps one compact settings record in WordPress's normal autoloaded option cache, avoiding a separate frontend database query, and uses documented WordPress filters for enabled output.

The optional heading guard adds no asset or network request. It runs at the end of WordPress's normal `the_content` filter only for the main singular page/post loop. DOM parsing is skipped unless the feature is enabled and the rendered content contains a heading.

## Heading hierarchy safety

The live audit is always report-only. For a URL that resolves to a WordPress page or post, it finds the first rendered H1–H6 whose visible text exactly matches the stored title. That heading is reported as the intended H1, and each other H1 is reported by text as an H2 recommendation.

The automatic heading guard is off by default. Enable it only when the theme or builder's separate title output is disabled and the visible title is rendered inside the main post content. In that layout, the guard:

- Promotes the first exact title match from H2–H6 to H1
- Leaves the first exact title H1 in place
- Demotes every other H1 inside that same content to H2
- Preserves heading attributes and child markup
- Makes no change when it cannot identify an exact title heading
- Never buffers or rewrites the complete document, theme header, footer, sidebar, or JavaScript-injected markup

The live audit should be run after enabling the guard to confirm the complete server-rendered page still contains the intended single H1. Theme-level or builder-level headings outside `the_content` remain the responsibility of the active template.

## Google services

The Google integration deliberately avoids OAuth, Google account passwords, client secrets, and refresh tokens.

- Entering a valid GA4 Measurement ID beginning with `G-` immediately enables the Google tag across frontend pages.
- A complete Google tag may be pasted when custom `gtag()` calls or configuration are required. It takes precedence over the Measurement ID and is preserved as entered after script-only, Google-source, capability, and length checks.
- There is no separate Analytics enable switch. Clearing both Analytics fields removes the tag.
- Existing Analytics plugins, hard-coded Google tags, and Tag Manager containers that already install Analytics must be removed first to avoid duplicate pageviews.
- The Search Console field accepts either the verification token or Google's complete verification meta tag and stores only the sanitized token.
- Search Console verification must still be completed in Google's interface after the token is saved.
- Each field includes direct links and concise setup directions drawn from Google's official instructions.
- The displayed `/sitemap.xml` URL is a lightweight alias for WordPress's native sitemap index, not a second sitemap generator. The previous `/ko-sitemap.xml` alias and standard WordPress endpoint remain available for compatibility.
- The branded filename uses WordPress's normal pretty-permalink routing. Sites using plain permalinks receive an admin warning and the compatible core sitemap URL.
- Submit `/sitemap.xml` once in Search Console. New and revised public content is reflected through WordPress; no deprecated Google ping is sent.

Analytics can involve cookies, identifiers, and visitor data. The site owner is responsible for the privacy disclosure and consent behavior required for the site's audience and jurisdiction. The `ko_lseo_google_analytics_should_output` filter allows a consent integration to suppress the tag for a request.

## Image alt-text safety

Alt text describes an image's meaning or function in context. A filename is not automatically a trustworthy description.

The plugin therefore follows these boundaries:

- Existing attachment alt text is never overwritten automatically.
- Images marked decorative remain intentionally empty and leave the missing-alt queue.
- Automatic assignment is opt-in.
- Automatic assignment accepts only a human-authored caption or an attachment title that differs meaningfully from the filename.
- Camera names, generic image names, stock-library IDs, hashes, and numeric filenames are rejected for automatic assignment.
- Descriptive filenames are offered first; when no safer candidate exists, a humanized filename is shown as a clearly labeled manual-review fallback.
- Prefilled filename values are not saved until the user edits the field or explicitly checks its row.
- Selected drafts are stored only in the current browser tab while the user moves between audit pages, then submitted together from any page.
- The live audit reports missing `alt` attributes separately from empty `alt=""` attributes.

An image reused in different contexts can require different alt text. WordPress attachment alt metadata is global, and some builders or JavaScript galleries may not emit it. The live audit inspects server-rendered HTML; browser-only injected images still require browser QA.

## SEO-suite conflict behavior

When a supported full-service SEO plugin is active, KO: SEO Basics suppresses its title, description, canonical, robots, sitemap, social, and schema module. The explicitly configured Google connection, image audit, and live HTML audit remain available because they do not emit competing SEO metadata.

The conflict check relies only on public plugin bootstrap constants, classes, and functions. It does not read or copy another plugin's private implementation.

## Installation

1. Create an installable ZIP whose top-level folder is `ko-lightweight-seo`.
2. In WordPress, open **Plugins > Add New > Upload Plugin**.
3. Upload and activate **KO: SEO Basics**.
4. Open **SEO Basics > Settings** and confirm the enabled modules.
5. Follow the links and directions beneath the Google fields to enter either a GA4 Measurement ID or a complete customized Google tag, plus the optional Search Console HTML-tag verification token.
6. Remove any existing Analytics or Tag Manager installation that would duplicate the new Google tag.
7. Submit the displayed `/sitemap.xml` sitemap once in Search Console.
8. Review existing images under **SEO Basics > Image Alt Text**.
9. Run a representative page through **SEO Basics > Live HTML Audit**.
10. If the visible title is rendered inside post content and separate theme title output is disabled, optionally enable the heading hierarchy guard.
11. Clear page/CDN caches and perform staging QA before production use.

## Validation targets

- WordPress 6.0 or newer
- Tested through WordPress 7.1
- PHP 7.4 or newer

Local source validation:

```bash
find . -name '*.php' -print0 | xargs -0 -n1 php -l
php tests/run.php
```

## Current limits

- No redirect manager
- No keyword or readability score
- No Google OAuth, account-data dashboard, or stored Google credentials
- No unsupported general indexing ping; Google retired the sitemap-ping endpoint
- No taxonomy-specific title or robots editor
- No WooCommerce-specific product schema
- No browser-side monitoring of images injected after page load
- No page-wide heading rewrite; the optional guard is intentionally limited to main post content
- The live audit is same-site, on demand, and limited to the first 5 MB of server HTML

These are explicit scope boundaries for v0.7.0, not hidden background features.

## Developer filters

### `ko_lseo_conflicting_plugin`

Override the detected SEO-suite name. Return an empty string only when another system is known not to emit competing metadata.

### `ko_lseo_meta_description`

Filter the resolved plain-text description for the current request.

### `ko_lseo_schema_graph`

Filter the complete array of JSON-LD graph entities before output.

### `ko_lseo_google_analytics_should_output`

Return false to suppress the configured Google tag for the current request, such as until a consent system permits Analytics. The second argument is the saved Measurement ID, when present; the third is true when the complete-tag field owns output.

### `ko_lseo_google_verification_should_output`

Return false to suppress the configured Search Console verification meta tag for the current request.

## License

GPL-2.0-or-later
