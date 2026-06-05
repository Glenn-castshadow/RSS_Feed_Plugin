<?php
/**
 * Unit tests for WRA_Settings_Repository::build_log_stack() — the pure
 * (I/O-free) part of the run-log storage.
 *
 * @package Curated_RSS_Aggregator
 */

use PHPUnit\Framework\TestCase;

class SettingsRepositoryTest extends TestCase {

	/** @var WRA_Settings_Repository */
	private $repo;

	protected function setUp(): void {
		$this->repo = new WRA_Settings_Repository();
	}

	public function test_new_entry_is_prepended_newest_first(): void {
		$stack = $this->repo->build_log_stack(
			array( array( 'imported' => 1 ) ),
			array( 'imported' => 2 ),
			20
		);
		$this->assertSame( 2, $stack[0]['imported'] );
		$this->assertSame( 1, $stack[1]['imported'] );
	}

	public function test_stack_is_capped(): void {
		$existing = array();
		for ( $i = 0; $i < 25; $i++ ) {
			$existing[] = array( 'imported' => $i );
		}
		$stack = $this->repo->build_log_stack( $existing, array( 'imported' => 99 ), 20 );
		$this->assertCount( 20, $stack );
		$this->assertSame( 99, $stack[0]['imported'] );
	}

	public function test_cap_is_at_least_one(): void {
		$stack = $this->repo->build_log_stack( array( array( 'a' => 1 ) ), array( 'a' => 2 ), 0 );
		$this->assertCount( 1, $stack );
		$this->assertSame( 2, $stack[0]['a'] );
	}

	public function test_empty_existing_yields_single_entry(): void {
		$stack = $this->repo->build_log_stack( array(), array( 'imported' => 5 ), 20 );
		$this->assertCount( 1, $stack );
		$this->assertSame( 5, $stack[0]['imported'] );
	}

	public function test_defaults_constant_covers_settings_schema(): void {
		$this->assertArrayHasKey( 'cache_minutes', WRA_Settings_Repository::DEFAULTS );
		$this->assertSame( 60, WRA_Settings_Repository::DEFAULTS['cache_minutes'] );
	}
}
