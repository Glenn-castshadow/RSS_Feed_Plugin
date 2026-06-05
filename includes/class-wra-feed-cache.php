<?php
/**
 * Stale-while-revalidate cache for rendered feed items.
 *
 * The front end stores the normalized item list for a given set of fetch
 * parameters in a transient. On render it serves whatever is cached
 * immediately — even if stale — and, when stale, schedules a one-off cron event
 * to refresh it in the background. A visitor therefore never blocks on a live
 * RSS fetch except the very first time a given configuration is rendered with no
 * cache at all.
 *
 * @package Curated_RSS_Aggregator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WRA_Feed_Cache {
	/** Transient key prefix (also used by the admin "Clear feed cache" sweep). */
	const PREFIX = 'wra_items_';

	/** Cron hook fired to refresh a stale entry in the background. */
	const REFRESH_HOOK = 'wra_refresh_feed_items';

	/** How long a stored entry is retained so it can be served while stale (7 days). */
	const STORE_TTL = 604800;

	/**
	 * Transient key for a set of fetch args.
	 *
	 * @param array $args Resolved fetch arguments.
	 * @return string
	 */
	public function key( array $args ) {
		return self::PREFIX . md5( (string) wp_json_encode( $args ) );
	}

	/**
	 * Decide how to satisfy a render given the stored entry and freshness window.
	 *
	 * Pure (no I/O) so it can be unit-tested.
	 *
	 * @param mixed $stored       Stored entry (array with 'items' and 'time') or false.
	 * @param int   $fresh_window Seconds an entry is considered fresh.
	 * @param int   $now          Current unix timestamp.
	 * @return string One of 'serve_fresh', 'serve_stale_and_refresh', 'miss'.
	 */
	public function decide( $stored, $fresh_window, $now ) {
		if ( ! is_array( $stored ) || ! isset( $stored['items'], $stored['time'] ) || ! is_array( $stored['items'] ) ) {
			return 'miss';
		}

		if ( ( (int) $now - (int) $stored['time'] ) <= (int) $fresh_window ) {
			return 'serve_fresh';
		}

		return 'serve_stale_and_refresh';
	}

	/**
	 * Return cached items, serving stale + scheduling a refresh when needed, and
	 * falling back to a one-time synchronous fetch only on a complete miss.
	 *
	 * @param array    $args         Resolved fetch arguments (also the cache key + refresh payload).
	 * @param int      $fresh_window Seconds an entry stays fresh.
	 * @param callable $fetch        Performs the live fetch; returns an item array.
	 * @return array Items.
	 */
	public function get( array $args, $fresh_window, callable $fetch ) {
		$key      = $this->key( $args );
		$stored   = get_transient( $key );
		$decision = $this->decide( $stored, $fresh_window, time() );

		if ( 'serve_fresh' === $decision ) {
			return $stored['items'];
		}

		if ( 'serve_stale_and_refresh' === $decision ) {
			$this->schedule_refresh( $args );
			return $stored['items'];
		}

		// Complete miss: fetch once now and store it.
		$items = $fetch();
		$this->store( $key, $items );
		return $items;
	}

	/**
	 * Persist an item list under the given key.
	 *
	 * @param string $key   Transient key (from key()).
	 * @param array  $items Items to store.
	 */
	public function store( $key, array $items ) {
		set_transient(
			$key,
			array(
				'items' => $items,
				'time'  => time(),
			),
			self::STORE_TTL
		);
	}

	/**
	 * Schedule a background refresh for these args, unless one is already queued.
	 *
	 * @param array $args Resolved fetch arguments.
	 */
	public function schedule_refresh( array $args ) {
		if ( ! wp_next_scheduled( self::REFRESH_HOOK, array( $args ) ) ) {
			wp_schedule_single_event( time() + 1, self::REFRESH_HOOK, array( $args ) );
		}
	}
}
