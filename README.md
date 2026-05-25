# Curated RSS Aggregator

A WordPress plugin for displaying and importing RSS feeds. Show feeds anywhere with a shortcode or Gutenberg block, filter by keyword, limit items per source, and optionally import feed items as WordPress posts — with optional full-text extraction and AI rewrite/summarize.

**Current version:** 0.5.0

---

## Features

### Display
- `[curated_rss]` shortcode and native Gutenberg block
- Grid, list, and compact layouts
- Configurable column count, image aspect ratio, and card style
- Show/hide image, date, source domain, author, excerpt, and "Read more" link
- Per-feed item cap — prevents one high-volume feed from filling all slots
- Character limit on excerpts
- Keyword include/exclude filters
- Referral/affiliate query parameters appended to outbound links
- Fallback image support

### Import jobs
- Import feed items as any post type; configurable per-job schedule (15 min to 24 hours)
- Per-job: post status, post type, item limit, keyword filters, date range
- Full-text extraction — fetches the source article body instead of the feed snippet
- AI rewrite or summarize via OpenAI or OpenRouter
- Optional featured image sideloading
- Optional source publish-date preservation
- Duplicate protection (by GUID and URL)
- Manual "Run Now" button in admin
- Manual feed cache clear button
- OPML file import (merge or replace existing feed list)

---

## Installation

### From the zip (recommended)

1. Download `curated-rss-aggregator-0.5.0.zip` from the `dist/` folder or a GitHub release.
2. In WordPress go to **Plugins → Add New → Upload Plugin**.
3. Upload the zip and click **Install Now**, then **Activate**.
4. Open **RSS Aggregator** in the WordPress admin sidebar.

### From source

1. Clone this repository into `wp-content/plugins/curated-rss-aggregator`.
2. Activate **Curated RSS Aggregator** in WordPress.

---

## Building the plugin zip

From the repository root on Windows (PowerShell):

```powershell
.\scripts\build-plugin.ps1 -Version "0.5.0"
```

The zip is written to `dist/curated-rss-aggregator-0.5.0.zip`.  
Omit `-Version` to produce `dist/curated-rss-aggregator.zip`.

> **Note:** The build script uses .NET's `ZipArchive` directly to ensure zip entry paths use forward slashes. PowerShell's `Compress-Archive` writes Windows backslashes, which breaks PHP's `ZipArchive` extraction on Linux servers.

Tagged GitHub releases starting with `v` (e.g. `v0.3.1`) automatically build and attach a plugin zip via the included workflow.

---

## Shortcode

```
[curated_rss]
```

All attributes are optional. Example with common options:

```
[curated_rss
  feeds="https://example.com/feed
https://example.org/rss"
  items="9"
  per_feed="2"
  layout="grid"
  columns="3"
  card_style="shadow"
  show_source="yes"
  show_read_more="yes"
  max_chars="160"
]
```

### All attributes

| Attribute | Default | Description |
|---|---|---|
| `feeds` | *(admin list)* | Feed URLs, one per line or comma-separated |
| `items` | `6` | Total items to display |
| `per_feed` | `0` | Max items from any single feed (0 = no limit) |
| `layout` | `grid` | `grid` · `list` · `compact` |
| `columns` | `0` | Grid columns (0 = auto-fit) |
| `card_style` | `default` | `default` · `shadow` · `flat` · `outline` · `none` |
| `image_ratio` | `16-9` | `16-9` · `4-3` · `3-2` · `1-1` |
| `show_image` | `yes` | `yes` · `no` |
| `show_date` | `yes` | `yes` · `no` |
| `show_source` | `no` | `yes` · `no` — shows the feed's domain |
| `show_author` | `no` | `yes` · `no` |
| `show_excerpt` | `yes` | `yes` · `no` |
| `max_chars` | `0` | Excerpt character limit (0 = no limit) |
| `show_read_more` | `no` | `yes` · `no` |
| `read_more_text` | `Read more` | Label for the read-more link |
| `include_keywords` | *(none)* | Comma-separated; item must match at least one |
| `exclude_keywords` | *(none)* | Comma-separated; matching items are removed |
| `affiliate_name` | *(admin setting)* | Query parameter name appended to links |

| `affiliate_value` | *(admin setting)* | Query parameter value |

---

## Gutenberg block

Search for **Curated RSS Feed** in the block inserter (Widgets category). All shortcode attributes are available as block sidebar controls organised into four panels:

- **Feed Settings** — URLs, item count, per-feed cap
- **Style** — layout, columns, card style, image ratio
- **Display** — image, date, source, author, excerpt, max chars, read-more
- **Keyword Filters** — include / exclude terms

The block renders server-side, so the editor preview reflects live feed data.

---

## Elementor widget

Search for **Curated RSS Feed** in the Elementor panel (General category). The widget exposes the same four control sections as the Gutenberg block:

- **Feed Settings** — URLs, item count, per-feed cap
- **Style** — layout, columns, card style, image ratio
- **Display** — image, date, source, author, excerpt, max chars, read-more
- **Keyword Filters** — include / exclude terms

The widget renders via the same server-side PHP code as the shortcode. Requires Elementor (free or Pro) to be active; the widget is silently skipped when Elementor is not installed.

---

## Import jobs

Go to **RSS Aggregator → Create Import Job**.

| Option | Description |
|---|---|
| Feed URLs | One URL per line |
| Items per run | Maximum posts created each cron run |
| Post status | `draft` · `publish` · `pending` · `private` |
| Post type | Any registered post type slug |
| Include / Exclude keywords | Same logic as the shortcode |
| Category | Assign imported posts to a WordPress category |
| Tags | Comma-separated tag names applied to every imported post |
| Date after / before | Only import items within this date range |
| Run on schedule | Enable or pause the cron job |
| Run every | 15 min · 30 min · 1 h · 2 h · 6 h · 12 h · 24 h |
| Use full feed content | Use `<content:encoded>` when the feed provides it |
| Fetch full text from source URL | Scrapes the article body from the source page (slower) |
| AI processing | None · Rewrite · Summarize (requires AI settings below) |
| Custom AI instructions | Appended to the AI system prompt |
| Save image as featured | Sideloads the first found image |
| Preserve source date | Uses the feed item's publish date instead of now |

---

## AI rewrite / summarize

In **RSS Aggregator → Display Feeds → AI Rewrite / Summarize**:

1. Choose **Provider** — OpenAI or OpenRouter.
2. Enter your **API Key** (never displayed after saving).
3. Optionally set a **Model** (defaults to `gpt-4o-mini`).

Then on each import job set **AI processing** to *Rewrite* or *Summarize*.

OpenRouter accepts any model listed at `openrouter.ai/models`. For OpenAI use standard chat model IDs such as `gpt-4o` or `gpt-4o-mini`.

---

## Amazon Associates

Under **Display Feeds → Amazon Associates**, enter your Associates tag (e.g. `yourstore-20`).

When set, the plugin automatically appends `?tag=yourstore-20` to any Amazon product URL it encounters — including:

- Feed item links rendered by the shortcode and Gutenberg block
- Amazon links embedded in post content imported by import jobs

Supported Amazon domains: amazon.com, amazon.co.uk, amazon.de, amazon.ca, amazon.fr, amazon.it, amazon.es, amazon.co.jp, amazon.com.au, amazon.in, amazon.com.mx, amazon.com.br, amazon.nl, amazon.se, amazon.pl, amazon.sg, amazon.ae, amazon.sa, amazon.com.tr.

Only product pages are tagged (URLs containing `/dp/`, `/gp/product/`, `/exec/obidos/ASIN/`, or `/ASIN/` followed by a 10-character ASIN).

---

## Referral parameters

Under **Display Feeds → Referral Parameters**, set a global query name and value that gets appended to every outbound feed link (shortcode and block). Individual shortcodes can override these with `affiliate_name` and `affiliate_value` attributes.

---

## Requirements

- WordPress 5.8+
- PHP 7.2+
- No external dependencies beyond WordPress core
