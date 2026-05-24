<?php
/**
 * RSS fetching and normalization.
 *
 * @package Curated_RSS_Aggregator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WRA_Feed_Fetcher {
	/**
	 * Current feed cache lifetime.
	 *
	 * @var int
	 */
	private $cache_lifetime = HOUR_IN_SECONDS;

	/**
	 * Fetch feed items from one or more URLs.
	 *
	 * @param array $urls Feed URLs.
	 * @param array $args Fetch args.
	 * @return array
	 */
	public function get_items( $urls, $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'limit'            => 10,
				'cache_minutes'    => 60,
				'include_keywords' => '',
				'exclude_keywords' => '',
				'date_after'       => '',
				'date_before'      => '',
				'fallback_image'   => '',
				'affiliate_name'   => '',
				'affiliate_value'  => '',
			)
		);

		$items = array();
		$urls  = array_filter( array_map( 'esc_url_raw', (array) $urls ) );

		if ( empty( $urls ) ) {
			return $items;
		}

		require_once ABSPATH . WPINC . '/feed.php';

		add_filter( 'wp_feed_cache_transient_lifetime', array( $this, 'filter_cache_lifetime' ) );
		$this->cache_lifetime = max( 60, absint( $args['cache_minutes'] ) * MINUTE_IN_SECONDS );

		foreach ( $urls as $url ) {
			$feed = fetch_feed( $url );

			if ( is_wp_error( $feed ) ) {
				continue;
			}

			$max_items = $feed->get_item_quantity( max( 1, absint( $args['limit'] ) ) );
			foreach ( $feed->get_items( 0, $max_items ) as $item ) {
				$normalized = $this->normalize_item( $item, $url, $args );

				if ( $this->passes_filters( $normalized, $args ) ) {
					$items[] = $normalized;
				}
			}
		}

		remove_filter( 'wp_feed_cache_transient_lifetime', array( $this, 'filter_cache_lifetime' ) );

		usort(
			$items,
			function ( $a, $b ) {
				return (int) $b['timestamp'] <=> (int) $a['timestamp'];
			}
		);

		return array_slice( $items, 0, max( 1, absint( $args['limit'] ) ) );
	}

	/**
	 * Feed cache lifetime callback.
	 *
	 * @return int
	 */
	public function filter_cache_lifetime() {
		return isset( $this->cache_lifetime ) ? (int) $this->cache_lifetime : HOUR_IN_SECONDS;
	}

	/**
	 * Normalize a SimplePie item.
	 *
	 * @param SimplePie_Item $item Feed item.
	 * @param string         $source_url Source feed URL.
	 * @param array          $args Fetch args.
	 * @return array
	 */
	private function normalize_item( $item, $source_url, $args ) {
		$link = $item->get_permalink();
		$link = $this->append_affiliate_params( $link, $args['affiliate_name'], $args['affiliate_value'] );

		$content     = $item->get_content();
		$description = $item->get_description();
		$image       = $this->extract_image( $item, $content . ' ' . $description );

		return array(
			'title'       => wp_strip_all_tags( $item->get_title() ),
			'link'        => esc_url_raw( $link ),
			'guid'        => $item->get_id() ? sanitize_text_field( $item->get_id() ) : md5( $source_url . '|' . $link ),
			'date'        => $item->get_date( get_option( 'date_format' ) ),
			'timestamp'   => $item->get_date( 'U' ) ? (int) $item->get_date( 'U' ) : time(),
			'author'      => $item->get_author() ? sanitize_text_field( $item->get_author()->get_name() ) : '',
			'excerpt'     => wp_trim_words( wp_strip_all_tags( $description ), 35 ),
			'content'     => wp_kses_post( $content ? $content : $description ),
			'image'       => $image ? esc_url_raw( $image ) : esc_url_raw( $args['fallback_image'] ),
			'source_feed' => esc_url_raw( $source_url ),
		);
	}

	/**
	 * Determine if item passes keyword and date filters.
	 *
	 * @param array $item Item.
	 * @param array $args Args.
	 * @return bool
	 */
	private function passes_filters( $item, $args ) {
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

		return true;
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

	/**
	 * Extract a representative image from enclosure, media tags, or content HTML.
	 *
	 * @param SimplePie_Item $item Feed item.
	 * @param string         $html HTML.
	 * @return string
	 */
	private function extract_image( $item, $html ) {
		$enclosure = $item->get_enclosure();
		if ( $enclosure && $enclosure->get_link() && 0 === strpos( (string) $enclosure->get_type(), 'image/' ) ) {
			return $enclosure->get_link();
		}

		$thumbnail = $item->get_item_tags( SIMPLEPIE_NAMESPACE_MEDIARSS, 'thumbnail' );
		if ( ! empty( $thumbnail[0]['attribs']['']['url'] ) ) {
			return $thumbnail[0]['attribs']['']['url'];
		}

		if ( preg_match( '/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $matches ) ) {
			return html_entity_decode( $matches[1] );
		}

		return '';
	}

	/**
	 * Add affiliate/referral query params to feed links.
	 *
	 * @param string $url URL.
	 * @param string $name Param name.
	 * @param string $value Param value.
	 * @return string
	 */
	private function append_affiliate_params( $url, $name, $value ) {
		if ( empty( $url ) || empty( $name ) || '' === $value ) {
			return $url;
		}

		return add_query_arg( sanitize_key( $name ), $value, $url );
	}
}
