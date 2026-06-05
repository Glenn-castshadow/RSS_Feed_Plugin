<?php
/**
 * Contract for anything that can supply normalized feed items.
 *
 * Implemented by WRA_Feed_Fetcher (live feeds) and WRA_Post_Source (imported
 * posts). WRA_Shortcode selects an implementation and treats the result the
 * same way regardless of origin.
 *
 * @package Curated_RSS_Aggregator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface WRA_Item_Source {
	/**
	 * Return normalized items (each shaped by WRA_Item::create()).
	 *
	 * @param array $args Source-specific arguments. Must honour 'limit' and
	 *                    'offset' so callers can paginate uniformly.
	 * @return array List of items.
	 */
	public function get_items( array $args = array() );
}
