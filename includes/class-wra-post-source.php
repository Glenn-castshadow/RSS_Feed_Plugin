<?php
/**
 * Post-based feed source — renders imported posts instead of live feeds.
 *
 * @package Curated_RSS_Aggregator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WRA_Post_Source implements WRA_Item_Source {
	/**
	 * Fetch imported posts and return them in the same item shape as WRA_Feed_Fetcher.
	 *
	 * @param array $args {
	 *     @type int    $limit           Max items to return. Default 10.
	 *     @type int    $offset          Number of items to skip. Default 0.
	 *     @type string $post_type       Post type. Default 'post'.
	 *     @type string $post_status     Post status. Default 'publish'.
	 *     @type array  $fallback_images Pool of fallback image URLs.
	 * }
	 * @return array
	 */
	public function get_items( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'limit'           => 10,
				'offset'          => 0,
				'post_type'       => 'post',
				'post_status'     => 'publish',
				'fallback_images' => array(),
			)
		);

		$query = new WP_Query( $this->build_query_args( $args ) );
		$items = array();

		foreach ( $query->posts as $post ) {
			$items[] = $this->map_post( $post, $args );
		}

		return $items;
	}

	/**
	 * Build the WP_Query argument array. Pure function — no DB calls.
	 *
	 * @param array $args Normalised args (same shape as get_items()).
	 * @return array
	 */
	public function build_query_args( $args ) {
		return array(
			'post_type'           => sanitize_key( $args['post_type'] ),
			'post_status'         => sanitize_key( $args['post_status'] ),
			'posts_per_page'      => max( 1, absint( $args['limit'] ) ),
			'offset'              => max( 0, absint( $args['offset'] ) ),
			'orderby'             => 'date',
			'order'               => 'DESC',
			'no_found_rows'       => true,
			'ignore_sticky_posts' => true,
			'meta_query'          => array(
				array(
					'key'     => '_wra_source_link',
					'compare' => 'EXISTS',
				),
			),
		);
	}

	/**
	 * Map a WP_Post to the standard item array shape.
	 *
	 * @param WP_Post $post Post object.
	 * @param array   $args Normalised args (needs fallback_images).
	 * @return array
	 */
	private function map_post( $post, $args ) {
		$id    = $post->ID;
		$image = get_the_post_thumbnail_url( $id, 'large' );

		if ( ! $image ) {
			$pool  = array_values( array_filter( (array) $args['fallback_images'] ) );
			$image = ! empty( $pool ) ? $pool[ array_rand( $pool ) ] : '';
		}

		return WRA_Item::create(
			array(
				'title'       => get_the_title( $id ),
				'link'        => get_permalink( $id ),
				'guid'        => (string) $id,
				'date'        => get_the_date( get_option( 'date_format' ), $id ),
				'timestamp'   => (int) get_the_time( 'U', $id ),
				'author'      => get_the_author_meta( 'display_name', $post->post_author ),
				'excerpt'     => get_the_excerpt( $post ),
				'content'     => '',
				'image'       => esc_url_raw( (string) $image ),
				'source_feed' => (string) get_post_meta( $id, '_wra_source_feed', true ),
			)
		);
	}
}
