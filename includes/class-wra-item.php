<?php
/**
 * Canonical feed-item shape.
 *
 * Both WRA_Feed_Fetcher and WRA_Post_Source emit items through this factory so
 * the contract consumed by WRA_Shortcode lives in exactly one place instead of
 * being re-implied by every producer's docblock.
 *
 * @package Curated_RSS_Aggregator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WRA_Item {
	/**
	 * The keys every normalized item carries, in canonical order.
	 *
	 * @var string[]
	 */
	const KEYS = array(
		'title',
		'link',
		'guid',
		'date',
		'timestamp',
		'author',
		'excerpt',
		'content',
		'image',
		'source_feed',
	);

	/**
	 * Build a normalized item, filling any missing keys with safe defaults.
	 *
	 * Unknown keys in $fields are dropped so a producer can never leak an
	 * unexpected shape downstream.
	 *
	 * @param array $fields Partial item data.
	 * @return array Item with every key in self::KEYS present.
	 */
	public static function create( array $fields = array() ) {
		$defaults = array(
			'title'       => '',
			'link'        => '',
			'guid'        => '',
			'date'        => '',
			'timestamp'   => 0,
			'author'      => '',
			'excerpt'     => '',
			'content'     => '',
			'image'       => '',
			'source_feed' => '',
		);

		$item              = array_merge( $defaults, array_intersect_key( $fields, $defaults ) );
		$item['timestamp'] = (int) $item['timestamp'];

		return $item;
	}
}
