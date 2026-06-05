<?php
/**
 * Unit tests for WRA_Feed_Warmer::collect_display_feeds().
 *
 * Exercises the URL-gathering logic (global feeds + every feed list, de-duped)
 * without touching WP-Cron or the network.
 *
 * @package Curated_RSS_Aggregator
 */

use PHPUnit\Framework\TestCase;

/** Repository stub returning fixed feed lists, no DB access. */
class WarmerFakeRepo extends WRA_Settings_Repository {
	/** @var array */
	public $lists = array();

	public function get_feed_lists() {
		return $this->lists;
	}
}

class FeedWarmerTest extends TestCase {

	private function collect( array $settings, array $lists ): array {
		$repo        = new WarmerFakeRepo();
		$repo->lists = $lists;
		$warmer      = new WRA_Feed_Warmer( new WRA_Feed_Fetcher(), $repo );

		$method = new ReflectionMethod( WRA_Feed_Warmer::class, 'collect_display_feeds' );
		$method->setAccessible( true );
		$urls = $method->invoke( $warmer, $settings );
		sort( $urls );
		return $urls;
	}

	public function test_merges_global_and_list_feeds_and_dedupes(): void {
		$urls = $this->collect(
			array( 'feeds' => "https://main.com/feed, https://shared.com/feed" ),
			array(
				'a' => array( 'feeds' => "https://a.com/feed\nhttps://shared.com/feed" ),
				'b' => array( 'feeds' => 'https://b.com/feed' ),
			)
		);

		$this->assertSame(
			array( 'https://a.com/feed', 'https://b.com/feed', 'https://main.com/feed', 'https://shared.com/feed' ),
			$urls
		);
	}

	public function test_empty_settings_and_lists_yield_no_urls(): void {
		$this->assertSame( array(), $this->collect( array( 'feeds' => '' ), array() ) );
	}

	public function test_handles_missing_feeds_key_on_list(): void {
		$urls = $this->collect(
			array( 'feeds' => 'https://main.com/feed' ),
			array( 'x' => array( 'name' => 'No feeds key' ) )
		);
		$this->assertSame( array( 'https://main.com/feed' ), $urls );
	}
}
