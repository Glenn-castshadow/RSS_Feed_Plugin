<?php
/**
 * Unit tests for WRA_Item::create() — the canonical item factory.
 *
 * @package Curated_RSS_Aggregator
 */

use PHPUnit\Framework\TestCase;

class ItemTest extends TestCase {

	public function test_create_fills_all_canonical_keys(): void {
		$item = WRA_Item::create();
		foreach ( WRA_Item::KEYS as $key ) {
			$this->assertArrayHasKey( $key, $item );
		}
	}

	public function test_create_preserves_supplied_values(): void {
		$item = WRA_Item::create( array(
			'title' => 'Hello',
			'link'  => 'https://example.com/post',
		) );
		$this->assertSame( 'Hello', $item['title'] );
		$this->assertSame( 'https://example.com/post', $item['link'] );
	}

	public function test_create_drops_unknown_keys(): void {
		$item = WRA_Item::create( array( 'bogus' => 'nope', 'title' => 'ok' ) );
		$this->assertArrayNotHasKey( 'bogus', $item );
		$this->assertSame( 'ok', $item['title'] );
	}

	public function test_create_casts_timestamp_to_int(): void {
		$item = WRA_Item::create( array( 'timestamp' => '1700000000' ) );
		$this->assertSame( 1700000000, $item['timestamp'] );
	}

	public function test_create_defaults_are_empty_and_zero(): void {
		$item = WRA_Item::create();
		$this->assertSame( '', $item['title'] );
		$this->assertSame( 0, $item['timestamp'] );
	}
}
