<?php
/**
 * Keyword, date, and structured filtering for feed items.
 *
 * Extracted from WRA_Feed_Fetcher so the fetcher is responsible only for
 * fetching/normalizing. These rules are import-oriented (the shortcode passes
 * only include/exclude keywords) and are independently unit-tested.
 *
 * @package Curated_RSS_Aggregator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WRA_Feed_Filter {
	/**
	 * Determine whether an item passes keyword, date, and advanced filters.
	 *
	 * @param array $item Normalized item.
	 * @param array $args {
	 *     @type string $include_keywords Comma/line separated keywords; item must match one.
	 *     @type string $exclude_keywords Comma/line separated keywords; any match rejects.
	 *     @type array  $advanced_filters Structured filter rules.
	 *     @type string $advanced_mode    'all' or 'any'.
	 *     @type string $date_after       Lower date bound (strtotime-parseable).
	 *     @type string $date_before      Upper date bound (strtotime-parseable).
	 * }
	 * @return bool
	 */
	public function passes( $item, $args ) {
		$args = array_merge(
			array(
				'include_keywords' => '',
				'exclude_keywords' => '',
				'advanced_filters' => array(),
				'advanced_mode'    => 'all',
				'date_after'       => '',
				'date_before'      => '',
			),
			$args
		);

		$haystack = strtolower( $item['title'] . ' ' . wp_strip_all_tags( $item['content'] ) . ' ' . $item['excerpt'] );

		$include_keywords = $this->split_keywords( $args['include_keywords'] );
		if ( ! empty( $include_keywords ) ) {
			$matched = false;
			foreach ( $include_keywords as $keyword ) {
				if ( false !== strpos( $haystack, strtolower( $keyword ) ) ) {
					$matched = true;
					break;
				}
			}
			if ( ! $matched ) {
				return false;
			}
		}

		foreach ( $this->split_keywords( $args['exclude_keywords'] ) as $keyword ) {
			if ( false !== strpos( $haystack, strtolower( $keyword ) ) ) {
				return false;
			}
		}

		if ( ! empty( $args['date_after'] ) && strtotime( $args['date_after'] ) > $item['timestamp'] ) {
			return false;
		}

		if ( ! empty( $args['date_before'] ) && strtotime( $args['date_before'] . ' 23:59:59' ) < $item['timestamp'] ) {
			return false;
		}

		if ( ! empty( $args['advanced_filters'] ) && is_array( $args['advanced_filters'] ) ) {
			$mode    = isset( $args['advanced_mode'] ) && 'any' === $args['advanced_mode'] ? 'any' : 'all';
			$matched = 0;
			$total   = 0;

			foreach ( $args['advanced_filters'] as $filter ) {
				if ( empty( $filter['field'] ) || empty( $filter['operator'] ) ) {
					continue;
				}

				$total++;
				if ( $this->matches_advanced_filter( $item, $filter ) ) {
					$matched++;
				} elseif ( 'all' === $mode ) {
					return false;
				}
			}

			if ( $total > 0 && 'any' === $mode && 0 === $matched ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Determine whether an item matches a structured import filter.
	 *
	 * @param array $item   Item.
	 * @param array $filter Filter config.
	 * @return bool
	 */
	private function matches_advanced_filter( $item, $filter ) {
		$operator = isset( $filter['operator'] ) ? sanitize_key( $filter['operator'] ) : '';
		$field    = isset( $filter['field'] ) ? sanitize_key( $filter['field'] ) : '';
		$needle   = isset( $filter['value'] ) ? (string) $filter['value'] : '';
		$value    = $this->get_filter_value( $item, $field );

		if ( in_array( $operator, array( 'empty', 'not_empty' ), true ) ) {
			$is_empty = '' === trim( wp_strip_all_tags( (string) $value ) );
			return 'empty' === $operator ? $is_empty : ! $is_empty;
		}

		if ( 'date_after' === $operator || 'date_before' === $operator ) {
			$needle_time = strtotime( $needle );
			$item_time   = 'date' === $field ? (int) $item['timestamp'] : strtotime( $value );
			if ( ! $needle_time || ! $item_time ) {
				return false;
			}
			return 'date_after' === $operator ? $item_time >= $needle_time : $item_time <= $needle_time;
		}

		$value_text  = strtolower( wp_strip_all_tags( (string) $value ) );
		$needle_text = strtolower( wp_strip_all_tags( $needle ) );

		switch ( $operator ) {
			case 'contains':
				return '' !== $needle_text && false !== strpos( $value_text, $needle_text );
			case 'not_contains':
				return '' === $needle_text || false === strpos( $value_text, $needle_text );
			case 'equals':
				return $value_text === $needle_text;
			case 'not_equals':
				return $value_text !== $needle_text;
			case 'regex':
				return '' !== $needle && 1 === @preg_match( '/' . str_replace( '/', '\/', $needle ) . '/i', (string) $value );
		}

		return true;
	}

	/**
	 * Get an item field value for structured filtering.
	 *
	 * @param array  $item  Item.
	 * @param string $field Field key.
	 * @return string
	 */
	private function get_filter_value( $item, $field ) {
		switch ( $field ) {
			case 'title':
			case 'author':
			case 'excerpt':
			case 'content':
			case 'image':
			case 'source_feed':
				return isset( $item[ $field ] ) ? (string) $item[ $field ] : '';
			case 'description':
				return isset( $item['excerpt'] ) ? (string) $item['excerpt'] : '';
			case 'date':
				return isset( $item['date'] ) ? (string) $item['date'] : '';
		}

		return '';
	}

	/**
	 * Split comma or line separated keywords.
	 *
	 * @param string $keywords Keywords.
	 * @return array
	 */
	private function split_keywords( $keywords ) {
		if ( empty( $keywords ) ) {
			return array();
		}

		return array_filter( array_map( 'trim', preg_split( '/[\r\n,]+/', (string) $keywords ) ) );
	}
}
