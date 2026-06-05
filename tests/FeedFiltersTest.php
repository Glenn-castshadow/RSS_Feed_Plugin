<?php
/**
 * Unit tests for WRA_Feed_Filter keyword/date/advanced filter logic.
 *
 * The filtering rules live in their own class with a public passes() method, so
 * these tests call it directly rather than reaching through the fetcher.
 *
 * @package Curated_RSS_Aggregator
 */

use PHPUnit\Framework\TestCase;

class FeedFiltersTest extends TestCase {

	/** @var WRA_Feed_Filter */
	private $filter;

	protected function setUp(): void {
		$this->filter = new WRA_Feed_Filter();
	}

	// ---- helpers -------------------------------------------------------

	private function call( array $item, array $args ): bool {
		return $this->filter->passes( $item, $args );
	}

	private function make_item( string $title, string $content = '', int $timestamp = 0 ): array {
		return array(
			'title'     => $title,
			'content'   => $content,
			'excerpt'   => '',
			'author'    => '',
			'image'     => '',
			'date'      => '',
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

	// ---- advanced filters ---------------------------------------------

	public function test_advanced_filters_all_mode_requires_every_rule(): void {
		$item           = $this->make_item( 'Bourbon Review', 'Independent tasting notes.' );
		$item['author'] = 'Staff';

		$this->assertTrue( $this->call( $item, array(
			'advanced_filters' => array(
				array( 'field' => 'title', 'operator' => 'contains', 'value' => 'bourbon' ),
				array( 'field' => 'author', 'operator' => 'not_equals', 'value' => 'Sponsored' ),
			),
		) ) );

		$this->assertFalse( $this->call( $item, array(
			'advanced_filters' => array(
				array( 'field' => 'title', 'operator' => 'contains', 'value' => 'bourbon' ),
				array( 'field' => 'content', 'operator' => 'contains', 'value' => 'rum' ),
			),
		) ) );
	}

	public function test_advanced_filters_any_mode_accepts_one_match(): void {
		$item = $this->make_item( 'Single Malt Notes', 'Peated scotch.' );

		$this->assertTrue( $this->call( $item, array(
			'advanced_mode'    => 'any',
			'advanced_filters' => array(
				array( 'field' => 'title', 'operator' => 'contains', 'value' => 'bourbon' ),
				array( 'field' => 'content', 'operator' => 'contains', 'value' => 'scotch' ),
			),
		) ) );
	}

	public function test_advanced_filter_can_require_image(): void {
		$item          = $this->make_item( 'Photo Essay' );
		$item['image'] = 'https://example.com/image.jpg';

		$this->assertTrue( $this->call( $item, array(
			'advanced_filters' => array(
				array( 'field' => 'image', 'operator' => 'not_empty', 'value' => '' ),
			),
		) ) );
	}

	public function test_advanced_filter_regex_matches_title(): void {
		$item = $this->make_item( 'Batch 12 Review' );

		$this->assertTrue( $this->call( $item, array(
			'advanced_filters' => array(
				array( 'field' => 'title', 'operator' => 'regex', 'value' => 'batch\s+\d+' ),
			),
		) ) );
	}
}
