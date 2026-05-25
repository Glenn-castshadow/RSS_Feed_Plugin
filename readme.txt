=== Curated RSS Aggregator ===
Contributors: castshadow
Tags: rss, feed, aggregator, news, import
Requires at least: 6.0
Tested up to: 6.7
Stable tag: 1.1.0
Requires PHP: 7.4
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Display RSS feeds anywhere via shortcode, block, or Elementor widget — with keyword filtering, AI rewriting, and scheduled post import.

== Description ==

Curated RSS Aggregator lets you pull multiple RSS/Atom feeds into a clean, responsive grid or list — no coding required. Items can be displayed with shortcodes, a Gutenberg block, or an Elementor widget.

**Display features**

* Grid, list, and compact layouts with five card style variants
* Per-feed item limits to balance sources across multiple feeds
* Feed diversity spreading — same-source articles are separated so no two appear side by side when alternatives exist
* Keyword include/exclude filtering (matches title and content)
* Affiliate/referral query parameter injection on all feed links
* Amazon Associates tag rewriting for product URLs
* Fallback images from your Media Library (chosen randomly when a feed item has no image)
* "Load more" AJAX pagination with screen-reader announcements

**Import features**

* Schedule feed items as WordPress posts (draft, publish, pending, or private)
* Optional full-text extraction from the source article URL
* AI rewrite or summarize via OpenAI or OpenRouter
* Per-job categories, tags, and post type
* Duplicate detection by GUID and source URL
* OPML import to bulk-add feed sources (merge or replace)
* Settings and job export/import as JSON (for dev → staging → prod migrations)

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install via the WordPress admin Plugins screen.
2. Activate the plugin.
3. Go to **RSS Aggregator** in the admin sidebar.
4. Add feed URLs under "Display Feeds", then save.
5. Place the shortcode `[curated_rss]` on any page or post.

== Frequently Asked Questions ==

= What shortcode attributes are available? =

`[curated_rss]` accepts: `feeds`, `items`, `per_feed`, `layout` (grid/list/compact), `columns`, `card_style` (default/shadow/flat/outline/none), `image_ratio` (16-9/4-3/3-2/1-1), `show_image`, `show_date`, `show_source`, `show_author`, `show_excerpt`, `max_chars`, `show_read_more`, `read_more_text`, `show_load_more`, `include_keywords`, `exclude_keywords`, `affiliate_name`, `affiliate_value`, `amazon_tag`.

= Does it support Gutenberg? =

Yes. A "Curated RSS Feed" block is available in the Widgets category with all shortcode options exposed as block controls.

= Does it support Elementor? =

Yes. A "Curated RSS Feed" widget is available when Elementor is active.

= Can I automatically import feed items as posts? =

Yes. Create an import job under the RSS Aggregator admin page and enable scheduling. Jobs check feeds on a configurable interval (15 minutes to 24 hours). Items are deduplicated by GUID and source URL.

= Will uninstalling the plugin remove my imported posts? =

No. Imported posts become ordinary WordPress posts and are not deleted. The uninstaller removes plugin settings, import job configs, post meta added by the plugin (`_wra_source_guid`, `_wra_source_link`, `_wra_source_feed`), and cached feed transients.

= How do I migrate settings between environments? =

Use the Export / Import Settings panel (collapsed under the Display Feeds section). Export produces a JSON file with all settings and jobs; the API key is excluded from exports for security. Import merges the file into the current site, preserving the existing API key.

== Screenshots ==

1. Admin settings panel showing feed URLs, fallback images, and referral/AI configuration.
2. Import job configuration with keyword filters and AI rewrite options.
3. Feed health status panel showing per-feed item counts and error messages.
4. Frontend grid layout with shadow card style.

== Changelog ==

= 1.1.0 =
* Added feed diversity spreading — articles from the same source feed are automatically interleaved so no two appear side by side when items from other feeds are available.
* Added GitHub-based automatic updates — the plugin checks for new releases and integrates with the standard WordPress one-click upgrader.

= 1.0.0 =
* Added settings export and import (JSON) for environment migrations.
* Added warnings column to import job run history — feed errors, extraction failures, and AI errors are now logged.
* Added "Load more" focus management and aria-live announcements for screen readers.
* Added focus styles to admin fallback-image remove buttons.
* Validated OPML uploads against file extension and PHP upload error codes.
* Fixed: replaced deprecated `SIMPLEPIE_NAMESPACE_MEDIARSS` constant with the literal URI.
* Fixed: replaced `@unlink` with `wp_delete_file()` in featured image sideload cleanup.
* Improved uninstall: now removes post meta (`_wra_source_guid`, `_wra_source_link`, `_wra_source_feed`) and feed transients.
* Added PHPUnit test skeleton for keyword-filter regression testing.

= 0.6.0 =
* Added "Load more" AJAX pagination.
* Added per-feed health status panel in admin.

= 0.5.0 =
* Added feed cache clear button.
* Added per-job run schedule (15 minutes – 24 hours).
* Added OPML file import (merge or replace mode).

= 0.4.4 =
* Fixed build script OneDrive file-lock issue.

= 0.4.3 =
* Added Media Library fallback images with random selection.
* Fixed staging folder cleanup to prevent ghost plugin after build.

= 0.4.0 =
* Added Amazon Associates link rewriting.
* Added AI rewrite / summarize via OpenAI and OpenRouter.
* Added full-text extraction from source article URLs.

== Upgrade Notice ==

= 1.1.0 =
Adds feed diversity spreading and GitHub-based auto-updates. No database changes required.

= 1.0.0 =
Improved error visibility in import job logs and accessibility improvements for the Load More button. No database changes required.
