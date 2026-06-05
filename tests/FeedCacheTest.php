<?php
/**
 * Unit tests for WRA_Feed_Cache decision + key logic (the I/O-free parts of
 * the stale-while-revalidate cache).
 *
 * @package Curated_RSS_Aggregator
 */

use PHPUnit\Framework\TestCase;

class FeedCacheTest extends TestCase {

	/** @var WRA_Feed_Cache */
	private $cache;

	protected function setUp(): void {
		$this->cache = new WRA_Feed_Cache();
	}

	private function entry( int $age_seconds, int $now ): array {
		return array( 'items' => array( array( 'title' => 'x' ) ), 'time' => $now - $age_seconds );
	}

	// ---- decide() ------------------------------------------------------

	public function test_missing_entry_is_a_miss(): void {
		$this->assertSame( 'miss', $this->cache->decide( false, 600, 1000 ) );
	}

	public function test_malformed_entry_is_a_miss(): void {
		$this->assertSame( 'miss', $this->cache->decide( array( 'items' => 'nope' ), 600, 1000 ) );
		$this->assertSame( 'miss', $this->cache->decide( array( 'time' => 1 ), 600, 1000 ) );
	}

	public function test_fresh_entry_is_served_fresh(): void {
		$now = 100000;
		$this->assertSame( 'serve_fresh', $this->cache->decide( $this->entry( 100, $now ), 600, $now ) );
	}

	public function test_entry_exactly_at_window_is_still_fresh(): void {
		$now = 100000;
		$this->assertSame( 'serve_fresh', $this->cache->decide( $this->entry( 600, $now ), 600, $now ) );
	}

	public function test_stale_entry_is_served_stale_and_refreshed(): void {
		$now = 100000;
		$this->assertSame( 'serve_stale_and_refresh', $this->cache->decide( $this->entry( 601, $now ), 600, $now ) );
	}

	// ---- key() ---------------------------------------------------------

	public function test_key_is_prefixed_and_deterministic(): void {
		$args = array( 'source' => 'feed', 'urls' => array( 'https://a.test/feed' ), 'limit' => 6 );
		$key  = $this->cache->key( $args );
		$this->assertStringStartsWith( WRA_Feed_Cache::PREFIX, $key );
		$this->assertSame( $key, $this->cache->key( $args ) );
	}

	public function test_different_args_produce_different_keys(): void {
		$a = $this->cache->key( array( 'source' => 'feed', 'limit' => 6 ) );
		$b = $this->cache->key( array( 'source' => 'feed', 'limit' => 7 ) );
		$this->assertNotSame( $a, $b );
	}
}
