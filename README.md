# Curated RSS Aggregator

A WordPress RSS aggregator plugin inspired by common RSS curation workflows: display feeds with a shortcode, filter content, add referral parameters, and import feed items as posts on a schedule.

## Features

- Display unlimited RSS feed URLs with `[curated_rss]`.
- Grid, list, and compact layouts.
- Feed image extraction from enclosures, Media RSS thumbnails, or item HTML.
- Fallback image support.
- Include and exclude keyword filters.
- Optional referral query parameters on outbound feed links.
- Feed-to-post import jobs with duplicate protection.
- Scheduled imports through WP-Cron.
- Manual "Run Now" import action.
- Draft, published, pending, or private import status.
- Optional full feed content import when the feed provides it.
- Optional source publish date preservation.
- Optional featured image sideloading.

## Installation

1. Copy this folder to `wp-content/plugins/curated-rss-aggregator`.
2. Activate **Curated RSS Aggregator** in WordPress.
3. Open **RSS Aggregator** in the WordPress admin menu.

## Shortcode

Basic usage:

```text
[curated_rss items="6" layout="grid"]
```

With custom feeds and filters:

```text
[curated_rss feeds="https://example.com/feed,https://example.org/rss" items="8" layout="list" include_keywords="wordpress,seo" exclude_keywords="sponsored"]
```

Supported attributes:

- `feeds`: comma-separated feed URLs. Defaults to the admin feed list.
- `items`: number of items to show.
- `layout`: `grid`, `list`, or `compact`.
- `show_image`: `yes` or `no`.
- `show_date`: `yes` or `no`.
- `show_excerpt`: `yes` or `no`.
- `include_keywords`: comma-separated terms. At least one must match.
- `exclude_keywords`: comma-separated terms. Any match removes the item.
- `affiliate_name`: query parameter name for outbound links.
- `affiliate_value`: query parameter value for outbound links.

## Notes

This first version does not include paid-service integrations such as OpenAI, OpenRouter, WordAI, SpinnerChief, Amazon Advertising API, or external full-text scraping. It provides clean extension points for those features later while keeping the initial plugin installable and self-contained.
