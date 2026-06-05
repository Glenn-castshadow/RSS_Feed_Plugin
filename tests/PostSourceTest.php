<?php
/**
 * Unit tests for WRA_Post_Source::build_query_args().
 *
 * build_query_args() is pure — no WP_Query, no DB — so it runs fine in the
 * stub harness.
 *
 * @package Curated_RSS_Aggregator
 */

use PHPUnit\Framework\TestCase;

class PostSourceTest extends TestCase {

	/** @var WRA_Post_Source */
	private $source;

	protected function setUp(): void {
		$this->source = new WRA_Post_Source();
	}

	// ---- defaults -------------------------------------------------------

	public function test_defaults_post_type_is_post(): void {
		$q = $this->source->build_query_args( array(
			'limit'       => 6,
			'offset'      => 0,
			'post_type'   => 'post',
			'post_status' => 'publish',
		) );
		$this->assertSame( 'post', $q['post_type'] );
	}

	public function test_defaults_post_status_is_publish(): void {
		$q = $this->source->build_query_args( array(
			'limit'       => 6,
			'offset'      => 0,
			'post_type'   => 'post',
			'post_status' => 'publish',
		) );
		$this->assertSame( 'publish', $q['post_status'] );
	}

	public function test_no_found_rows_is_true(): void {
		$q = $this->source->build_query_args( array(
			'limit'       => 6,
			'offset'      => 0,
			'post_type'   => 'post',
			'post_status' => 'publish',
		) );
		$this->assertTrue( $q['no_found_rows'] );
	}

	public function test_ignore_sticky_posts_is_true(): void {
		$q = $this->source->build_query_args( array(
			'limit'       => 6,
			'offset'      => 0,
			'post_type'   => 'post',
			'post_status' => 'publish',
		) );
		$this->assertTrue( $q['ignore_sticky_posts'] );
	}

	// ---- _wra_source_link EXISTS constraint ----------------------------

	public function test_meta_query_restricts_to_imported_posts(): void {
		$q = $this->source->build_query_args( array(
			'limit'       => 6,
			'offset'      => 0,
			'post_type'   => 'post',
			'post_status' => 'publish',
		) );
		$meta = $q['meta_query'];
		$this->assertIsArray( $meta );
		$this->assertCount( 1, $meta );
		$this->assertSame( '_wra_source_link', $meta[0]['key'] );
		$this->assertSame( 'EXISTS', $meta[0]['compare'] );
	}

	// ---- limit / offset -------------------------------------------------

	public function test_limit_sets_posts_per_page(): void {
		$q = $this->source->build_query_args( array(
			'limit'       => 12,
			'offset'      => 0,
			'post_type'   => 'post',
			'post_status' => 'publish',
		) );
		$this->assertSame( 12, $q['posts_per_page'] );
	}

	public function test_offset_passes_through(): void {
		$q = $this->source->build_query_args( array(
			'limit'       => 6,
			'offset'      => 6,
			'post_type'   => 'post',
			'post_status' => 'publish',
		) );
		$this->assertSame( 6, $q['offset'] );
	}

	public function test_limit_minimum_is_one(): void {
		$q = $this->source->build_query_args( array(
			'limit'       => 0,
			'offset'      => 0,
			'post_type'   => 'post',
			'post_status' => 'publish',
		) );
		$this->assertSame( 1, $q['posts_per_page'] );
	}

	// ---- custom type / status ------------------------------------------

	public function test_custom_post_type_passes_through(): void {
		$q = $this->source->build_query_args( array(
			'limit'       => 6,
			'offset'      => 0,
			'post_type'   => 'whiskey_review',
			'post_status' => 'publish',
		) );
		$this->assertSame( 'whiskey_review', $q['post_type'] );
	}

	public function test_custom_post_status_passes_through(): void {
		$q = $this->source->build_query_args( array(
			'limit'       => 6,
			'offset'      => 0,
			'post_type'   => 'post',
			'post_status' => 'draft',
		) );
		$this->assertSame( 'draft', $q['post_status'] );
	}

	// ---- ordering ------------------------------------------------------

	public function test_orderby_is_date_desc(): void {
		$q = $this->source->build_query_args( array(
			'limit'       => 6,
			'offset'      => 0,
			'post_type'   => 'post',
			'post_status' => 'publish',
		) );
		$this->assertSame( 'date', $q['orderby'] );
		$this->assertSame( 'DESC', $q['order'] );
	}
}
