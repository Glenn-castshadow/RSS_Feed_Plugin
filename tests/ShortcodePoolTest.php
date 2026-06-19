<?php
/**
 * Unit tests for WRA_Shortcode::resolve_feed_source().
 *
 * Pure static resolver — no WP_Query, no shortcode instance — so it runs in the
 * stub harness.
 *
 * @package Curated_RSS_Aggregator
 */

use PHPUnit\Framework\TestCase;

class ShortcodePoolTest extends TestCase {

	/** @return array Lists keyed by slug, matching the saved-option shape. */
	private function lists(): array {
		return array(
			'tech-news' => array(
				'id'    => 'tech-news',
				'name'  => 'Tech News',
				'feeds' => "https://a.example/feed\nhttps://b.example/feed",
			),
		);
	}

	public function test_pool_overrides_raw_feeds(): void {
		$out = WRA_Shortcode::resolve_feed_source(
			array( 'feed_list' => 'tech-news', 'feeds' => 'https://typed.example/feed' ),
			$this->lists()
		);
		$this->assertSame( "https://a.example/feed\nhttps://b.example/feed", $out );
	}

	public function test_raw_feeds_used_when_no_pool(): void {
		$out = WRA_Shortcode::resolve_feed_source(
			array( 'feed_list' => '', 'feeds' => 'https://typed.example/feed' ),
			$this->lists()
		);
		$this->assertSame( 'https://typed.example/feed', $out );
	}

	public function test_unknown_slug_falls_back_to_raw_feeds(): void {
		$out = WRA_Shortcode::resolve_feed_source(
			array( 'feed_list' => 'does-not-exist', 'feeds' => 'https://typed.example/feed' ),
			$this->lists()
		);
		$this->assertSame( 'https://typed.example/feed', $out );
	}

	public function test_empty_params_return_empty_string(): void {
		$out = WRA_Shortcode::resolve_feed_source( array(), $this->lists() );
		$this->assertSame( '', $out );
	}
}
