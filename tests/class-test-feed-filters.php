<?php
/**
 * Unit tests for WRA_Feed_Fetcher keyword/date filter logic.
 *
 * These tests exercise the private passes_filters() method via reflection so
 * changes to the filtering logic can be caught before a release.
 *
 * @package Curated_RSS_Aggregator
 */

use PHPUnit\Framework\TestCase;

class Test_Feed_Filters extends TestCase {

	/** @var WRA_Feed_Fetcher */
	private $fetcher;

	/** @var ReflectionMethod */
	private $passes_filters;

	protected function setUp(): void {
		$this->fetcher        = new WRA_Feed_Fetcher();
		$method               = new ReflectionMethod( WRA_Feed_Fetcher::class, 'passes_filters' );
		$method->setAccessible( true );
		$this->passes_filters = $method;
	}

	// ---- helpers -------------------------------------------------------

	private function call( array $item, array $args ): bool {
		$defaults = array(
			'include_keywords' => '',
			'exclude_keywords' => '',
			'date_after'       => '',
			'date_before'      => '',
		);
		return $this->passes_filters->invoke( $this->fetcher, $item, array_merge( $defaults, $args ) );
	}

	private function make_item( string $title, string $content = '', int $timestamp = 0 ): array {
		return array(
			'title'     => $title,
			'content'   => $content,
			'excerpt'   => '',
			'timestamp' => $timestamp ?: time(),
		);
	}

	// ---- include_keywords ----------------------------------------------

	public function test_include_keyword_matches_title(): void {
		$item = $this->make_item( 'Bourbon Review: Buffalo Trace' );
		$this->assertTrue( $this->call( $item, array( 'include_keywords' => 'bourbon' ) ) );
	}

	public function test_include_keyword_matches_content(): void {
		$item = $this->make_item( 'Weekend Picks', 'A great bourbon finish.' );
		$this->assertTrue( $this->call( $item, array( 'include_keywords' => 'bourbon' ) ) );
	}

	public function test_include_keyword_no_match_returns_false(): void {
		$item = $this->make_item( 'Wine Tasting Notes', 'Great wine.' );
		$this->assertFalse( $this->call( $item, array( 'include_keywords' => 'bourbon' ) ) );
	}

	public function test_include_keyword_case_insensitive(): void {
		$item = $this->make_item( 'BOURBON barrel aged' );
		$this->assertTrue( $this->call( $item, array( 'include_keywords' => 'Bourbon' ) ) );
	}

	public function test_include_multiple_keywords_any_match_passes(): void {
		$item = $this->make_item( 'Single Malt Scotch' );
		// 'bourbon' doesn't match but 'scotch' does — OR semantics.
		$this->assertTrue( $this->call( $item, array( 'include_keywords' => 'bourbon,scotch' ) ) );
	}

	public function test_include_multiple_keywords_none_match_fails(): void {
		$item = $this->make_item( 'Beer Review' );
		$this->assertFalse( $this->call( $item, array( 'include_keywords' => 'bourbon,scotch' ) ) );
	}

	// ---- exclude_keywords ----------------------------------------------

	public function test_exclude_keyword_removes_item(): void {
		$item = $this->make_item( 'Bourbon Review — sponsored content' );
		$this->assertFalse( $this->call( $item, array( 'exclude_keywords' => 'sponsored' ) ) );
	}

	public function test_exclude_keyword_does_not_affect_non_matching(): void {
		$item = $this->make_item( 'Bourbon Review' );
		$this->assertTrue( $this->call( $item, array( 'exclude_keywords' => 'sponsored' ) ) );
	}

	public function test_exclude_keyword_case_insensitive(): void {
		$item = $this->make_item( 'Ad: Sponsored Post' );
		$this->assertFalse( $this->call( $item, array( 'exclude_keywords' => 'SPONSORED' ) ) );
	}

	// ---- combined include + exclude ------------------------------------

	public function test_include_and_exclude_include_wins_when_exclude_absent(): void {
		$item = $this->make_item( 'Bourbon barrel pick' );
		$this->assertTrue( $this->call( $item, array(
			'include_keywords' => 'bourbon',
			'exclude_keywords' => 'sponsored',
		) ) );
	}

	public function test_include_and_exclude_exclude_takes_priority(): void {
		$item = $this->make_item( 'Bourbon sponsored post' );
		$this->assertFalse( $this->call( $item, array(
			'include_keywords' => 'bourbon',
			'exclude_keywords' => 'sponsored',
		) ) );
	}

	// ---- date filters -------------------------------------------------

	public function test_date_after_passes_newer_item(): void {
		$item = $this->make_item( 'Recent post', '', strtotime( '2025-06-01' ) );
		$this->assertTrue( $this->call( $item, array( 'date_after' => '2025-05-01' ) ) );
	}

	public function test_date_after_blocks_older_item(): void {
		$item = $this->make_item( 'Old post', '', strtotime( '2025-04-01' ) );
		$this->assertFalse( $this->call( $item, array( 'date_after' => '2025-05-01' ) ) );
	}

	public function test_date_before_passes_older_item(): void {
		$item = $this->make_item( 'Old post', '', strtotime( '2025-01-01' ) );
		$this->assertTrue( $this->call( $item, array( 'date_before' => '2025-06-01' ) ) );
	}

	public function test_date_before_blocks_newer_item(): void {
		$item = $this->make_item( 'Future post', '', strtotime( '2025-12-01' ) );
		$this->assertFalse( $this->call( $item, array( 'date_before' => '2025-06-01' ) ) );
	}

	// ---- no filters applied -------------------------------------------

	public function test_no_filters_always_passes(): void {
		$item = $this->make_item( 'Anything', 'Any content.' );
		$this->assertTrue( $this->call( $item, array() ) );
	}
}
