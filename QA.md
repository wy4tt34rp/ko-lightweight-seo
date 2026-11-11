# KO: SEO Basics v0.7.0 QA Record

Date: September 1, 2026

## Validated source

- Canonical source: `public-plugin-repos/ko-lightweight-seo`
- Branch: `main`
- Remote: none configured
- Commit: none created
- Plugin version: `0.7.0`
- Release archive: `archived zips/ko-seo-basics-v0.7.0.zip`
- Release SHA-256: `f2ac1a4308c86cb2e43628ce445083c544901ef37abdcc1c95ecc51ea4fc689d`
- Release size: `43,482` bytes

The public plugin name remains `KO: SEO Basics`. The existing plugin directory, settings option, internal prefixes, and admin-page slugs remain unchanged. The v0.5.0 and v0.6.0 release ZIPs were preserved.

## Live diagnosis boundary

The user's Search Console screenshot showed:

- `/ko-sitemap.xml`: submitted September 1, 2026, status `Couldn't fetch`
- `/sitemap.xml`: submitted May 31, 2019, last read March 14, 2025, historical status `Success`

Read-only public requests were then made with normal browser and Googlebot user agents. At diagnosis time, all of these YogaCentrix URLs returned `HTTP 404` while emitting sitemap XML with an XML content type:

- `/ko-sitemap.xml`
- `/sitemap.xml`
- `/wp-sitemap.xml`
- `/?sitemap=index`
- `/wp-sitemap-posts-page-1.xml`

The same result occurred with unique cache-busting query values and a `Cache-Control: no-cache` request header. The returned W3 Total Cache comment identified the response as `Page is 404`, but the repeat result did not establish W3 Total Cache as the original source of the status. The confirmed fault is the public HTTP 404 response, not malformed XML and not the filename alone.

No live WordPress setting, cache, plugin file, Search Console submission, or content record was changed during diagnosis.

## v0.7.0 scope

- Makes `/sitemap.xml` the primary URL displayed and recommended by the plugin.
- Retains `/ko-sitemap.xml` as a compatibility alias so existing links and Search Console submissions do not immediately break.
- Continues to leave `/wp-sitemap.xml` and WordPress's provider sitemaps available.
- Adds both friendly routes to WordPress's native sitemap index instead of generating or storing a second XML document.
- Adds a narrow `pre_handle_404` safeguard for recognized sitemap indexes, stylesheets, and registered sitemap providers.
- Explicitly returns `200` before WordPress's generic post-query 404 decision for those recognized routes.
- Does not preempt an existing filter result, a disabled sitemap service, an unrelated request, or an unregistered provider.
- Leaves WordPress's native renderer responsible for XML output and for restoring a real 404 when a registered provider page is empty.
- Updates the Settings screen, Search Console directions, public readme, and source documentation to recommend `sitemap.xml`.

## Static and behavior checks

- Every canonical PHP file passed `php -l` with no syntax errors.
- `php -d error_reporting=E_ALL tests/run.php` passed.
- `assets/admin.js` passed Node syntax validation.
- The behavior suite verifies both rewrite aliases, the new primary and legacy URLs, one-time rewrite refresh, deactivation cleanup, canonical protection, explicit `200` handling for a recognized index, pass-through behavior for unrelated requests, and hook registration.
- The existing Analytics, Search Console, metadata, schema, heading, image-alt, unified-navigation, width, and sitemap-exclusion tests also passed.

## WordPress integration and HTTP checks

The source and then the extracted final ZIP were loaded into a disposable runtime:

- WordPress `7.1`
- PHP `8.5.7`
- SQLite Database Integration `3.0.1`
- SQLite driver `3.0.1`
- Theme `twentytwentyfive`

Validated outcomes:

- The saved rewrite rules mapped both `^sitemap\.xml$` and `^ko\-sitemap\.xml$` to the WordPress core sitemap index.
- The recognized index and registered `posts` provider bypassed the generic 404 decision.
- An unregistered provider was not force-approved by the plugin filter.
- The Settings screen displayed `/sitemap.xml`, instructed the user to submit `sitemap.xml`, and identified `ko-sitemap.xml` as the compatibility route.
- The existing primary `SEO Basics` menu and its three submenus remained intact.
- `/sitemap.xml` returned `200 OK`, an XML content type, and valid XML.
- `/ko-sitemap.xml` returned `200 OK`, an XML content type, and valid XML.
- `/wp-sitemap.xml` returned `200 OK`, an XML content type, and valid XML.
- `/wp-sitemap-posts-page-1.xml` returned `200 OK`, an XML content type, and valid XML.
- `/wp-sitemap-posts-page-999.xml`, a genuinely empty registered provider page, still returned `404 Not Found`.
- The local server log contained no plugin warning or fatal error.

## Final package checks

- The ZIP contains one top-level `ko-lightweight-seo` directory.
- It includes only the main plugin file, uninstall routine, public `readme.txt`, `includes/`, and `assets/`.
- It excludes `.git`, source tests, QA notes, source-only documentation, `.DS_Store`, and `__MACOSX` metadata.
- `unzip -t` reported no compressed-data errors.
- Every packaged PHP file passed `php -l`.
- The packaged JavaScript passed Node syntax validation.
- Extracted files matched the prepared release tree byte-for-byte.
- The extracted final ZIP passed the WordPress 7.1 integration and HTTP assertions.

## Outstanding validation boundaries

- The v0.7.0 package was not installed on YogaCentrix or any production site.
- The production 404 repair remains unconfirmed until v0.7.0 is installed, rewrite rules refresh during upgrade, all WordPress/CDN caches are purged, and the public response is checked again.
- Search Console should be resubmitted only after `https://yogacentrix.com/sitemap.xml` is publicly confirmed as `200 OK` XML.
- No Git remote was created, and nothing was committed or pushed.
- PHP 7.4 is the declared minimum based on used language features; the disposable runtime used PHP 8.5.7.
