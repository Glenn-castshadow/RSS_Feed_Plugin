<?php
/**
 * Feed-to-post importer.
 *
 * @package Curated_RSS_Aggregator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WRA_Importer {
	/**
	 * Feed fetcher.
	 *
	 * @var WRA_Feed_Fetcher
	 */
	private $fetcher;

	/**
	 * Constructor.
	 *
	 * @param WRA_Feed_Fetcher $fetcher Feed fetcher.
	 */
	public function __construct( WRA_Feed_Fetcher $fetcher ) {
		$this->fetcher = $fetcher;
	}

	/**
	 * Run all enabled scheduled jobs.
	 */
	public function run_scheduled_jobs() {
		foreach ( WRA_Plugin::get_import_jobs() as $job ) {
			if ( ! empty( $job['enabled'] ) ) {
				$this->run_job( $job );
			}
		}
	}

	/**
	 * Run a single import job.
	 *
	 * @param array $job Job config.
	 * @return array
	 */
	public function run_job( $job ) {
		$settings = WRA_Plugin::get_settings();
		$urls     = array_filter( array_map( 'trim', preg_split( '/[\r\n,]+/', (string) $job['feeds'] ) ) );
		$items    = $this->fetcher->get_items(
			$urls,
			array(
				'limit'            => absint( $job['limit'] ),
				'cache_minutes'    => absint( $settings['cache_minutes'] ),
				'fallback_image'   => $settings['fallback_image'],
				'include_keywords' => isset( $job['include_keywords'] ) ? $job['include_keywords'] : '',
				'exclude_keywords' => isset( $job['exclude_keywords'] ) ? $job['exclude_keywords'] : '',
				'date_after'       => isset( $job['date_after'] ) ? $job['date_after'] : '',
				'date_before'      => isset( $job['date_before'] ) ? $job['date_before'] : '',
				'affiliate_name'   => $settings['affiliate_name'],
				'affiliate_value'  => $settings['affiliate_value'],
			)
		);

		$result = array(
			'imported' => 0,
			'skipped'  => 0,
		);

		foreach ( $items as $item ) {
			if ( $this->item_exists( $item['guid'], $item['link'] ) ) {
				$result['skipped']++;
				continue;
			}

			$post_id = $this->insert_item( $item, $job );
			if ( $post_id ) {
				$result['imported']++;
			} else {
				$result['skipped']++;
			}
		}

		return $result;
	}

	/**
	 * Check duplicate imported item.
	 *
	 * @param string $guid Source GUID.
	 * @param string $link Source link.
	 * @return bool
	 */
	private function item_exists( $guid, $link ) {
		$query = new WP_Query(
			array(
				'post_type'      => 'any',
				'post_status'    => 'any',
				'fields'         => 'ids',
				'posts_per_page' => 1,
				'meta_query'     => array(
					'relation' => 'OR',
					array(
						'key'   => '_wra_source_guid',
						'value' => $guid,
					),
					array(
						'key'   => '_wra_source_link',
						'value' => $link,
					),
				),
			)
		);

		return $query->have_posts();
	}

	/**
	 * Insert feed item as a post.
	 *
	 * @param array $item Item.
	 * @param array $job Job.
	 * @return int
	 */
	private function insert_item( $item, $job ) {
		$content = ! empty( $job['use_full_content'] ) ? $item['content'] : wpautop( esc_html( $item['excerpt'] ) );
		$content .= sprintf(
			'<p><a href="%s" rel="nofollow noopener" target="_blank">%s</a></p>',
			esc_url( $item['link'] ),
			esc_html__( 'Read the original article', 'curated-rss-aggregator' )
		);

		$post_id = wp_insert_post(
			array(
				'post_title'   => $item['title'],
				'post_content' => $content,
				'post_status'  => isset( $job['post_status'] ) ? sanitize_key( $job['post_status'] ) : 'draft',
				'post_type'    => isset( $job['post_type'] ) ? sanitize_key( $job['post_type'] ) : 'post',
				'post_date'    => ! empty( $job['preserve_date'] ) ? gmdate( 'Y-m-d H:i:s', $item['timestamp'] + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) ) : current_time( 'mysql' ),
				'post_author'  => get_current_user_id() ? get_current_user_id() : 1,
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return 0;
		}

		update_post_meta( $post_id, '_wra_source_guid', $item['guid'] );
		update_post_meta( $post_id, '_wra_source_link', $item['link'] );
		update_post_meta( $post_id, '_wra_source_feed', $item['source_feed'] );

		if ( ! empty( $job['category'] ) ) {
			wp_set_post_terms( $post_id, array( absint( $job['category'] ) ), 'category', true );
		}

		if ( ! empty( $item['image'] ) && ! empty( $job['save_featured_image'] ) ) {
			$this->set_featured_image_from_url( $post_id, $item['image'] );
		}

		return (int) $post_id;
	}

	/**
	 * Sideload an image and set as featured image.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $image_url Image URL.
	 */
	private function set_featured_image_from_url( $post_id, $image_url ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = download_url( $image_url, 20 );
		if ( is_wp_error( $tmp ) ) {
			return;
		}

		$file = array(
			'name'     => basename( parse_url( $image_url, PHP_URL_PATH ) ),
			'tmp_name' => $tmp,
		);

		$attachment_id = media_handle_sideload( $file, $post_id );
		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $tmp );
			return;
		}

		set_post_thumbnail( $post_id, $attachment_id );
	}
}
