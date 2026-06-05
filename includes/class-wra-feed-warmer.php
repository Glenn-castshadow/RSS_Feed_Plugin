<?php
/**
 * Keeps the SimplePie feed cache warm from WP-Cron.
 *
 * Front-end rendering (shortcode/block/Elementor) fetches feeds inline, so a
 * cold or expired transient cache makes a visitor's request block on sequential
 * live HTTP fetches. Running this on the existing 15-minute cron refreshes
 * expired feeds in the background, so visitor-facing renders almost always read
 * a warm cache.
 *
 * @package Curated_RSS_Aggregator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WRA_Feed_Warmer {
	/**
	 * Feed fetcher.
	 *
	 * @var WRA_Feed_Fetcher
	 */
	private $fetcher;

	/**
	 * Settings repository.
	 *
	 * @var WRA_Settings_Repository
	 */
	private $repo;

	/**
	 * Constructor.
	 *
	 * @param WRA_Feed_Fetcher        $fetcher Feed fetcher.
	 * @param WRA_Settings_Repository $repo    Settings repository.
	 */
	public function __construct( WRA_Feed_Fetcher $fetcher, WRA_Settings_Repository $repo ) {
		$this->fetcher = $fetcher;
		$this->repo    = $repo;
	}

	/**
	 * Refresh the feed cache for every feed used for display.
	 *
	 * Calls get_feed_health(), which runs fetch_feed() per URL and therefore
	 * repopulates any expired SimplePie transient. Feeds whose cache is still
	 * valid are skipped by SimplePie, so this is cheap when nothing has expired.
	 */
	public function warm() {
		$settings = $this->repo->get_settings();
		$urls     = $this->collect_display_feeds( $settings );

		if ( empty( $urls ) ) {
			return;
		}

		// Errors are reported per-feed and ignored here; warming is best-effort.
		$this->fetcher->get_feed_health( $urls, absint( $settings['cache_minutes'] ) );
	}

	/**
	 * Gather every feed URL that can appear on the front end: the global default
	 * feeds plus every named feed list.
	 *
	 * @param array $settings Plugin settings.
	 * @return string[] Unique, trimmed feed URLs.
	 */
	private function collect_display_feeds( $settings ) {
		$urls = $this->split( isset( $settings['feeds'] ) ? $settings['feeds'] : '' );

		foreach ( $this->repo->get_feed_lists() as $list ) {
			$urls = array_merge( $urls, $this->split( isset( $list['feeds'] ) ? $list['feeds'] : '' ) );
		}

		return array_values( array_unique( array_filter( $urls ) ) );
	}

	/**
	 * Split a comma/line separated feed string into trimmed URLs.
	 *
	 * @param string $feeds Raw feed string.
	 * @return string[]
	 */
	private function split( $feeds ) {
		return array_filter( array_map( 'trim', preg_split( '/[\r\n,]+/', (string) $feeds ) ) );
	}
}
