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
	 * Full-text extractor (optional).
	 *
	 * @var WRA_Full_Text_Extractor|null
	 */
	private $extractor;

	/**
	 * AI rewriter/summarizer (optional, null when no API key is configured).
	 *
	 * @var WRA_AI_Rewriter|null
	 */
	private $ai_rewriter;

	/**
	 * Settings repository.
	 *
	 * @var WRA_Settings_Repository
	 */
	private $repo;

	/**
	 * Constructor.
	 *
	 * @param WRA_Feed_Fetcher             $fetcher     Feed fetcher.
	 * @param WRA_Settings_Repository      $repo        Settings repository.
	 * @param WRA_Full_Text_Extractor|null $extractor   Full-text extractor.
	 * @param WRA_AI_Rewriter|null         $ai_rewriter AI rewriter.
	 */
	public function __construct( WRA_Feed_Fetcher $fetcher, WRA_Settings_Repository $repo, $extractor = null, $ai_rewriter = null ) {
		$this->fetcher     = $fetcher;
		$this->repo        = $repo;
		$this->extractor   = $extractor;
		$this->ai_rewriter = $ai_rewriter;
	}

	/**
	 * Run all enabled scheduled jobs.
	 */
	public function run_scheduled_jobs() {
		$now = current_time( 'timestamp' );

		foreach ( $this->repo->get_import_jobs() as $job ) {
			if ( empty( $job['enabled'] ) ) {
				continue;
			}

			$frequency = isset( $job['frequency'] ) ? (int) $job['frequency'] : 30;
			$log       = ! empty( $job['id'] ) ? $this->repo->get_job_log( $job['id'] ) : array();
			$last_run  = ! empty( $log[0]['time'] ) ? strtotime( $log[0]['time'] ) : 0;

			if ( $now >= $last_run + $frequency * MINUTE_IN_SECONDS ) {
				$this->run_job( $job );
			}
		}
	}

	/**
	 * Run a single import job.
	 *
	 * @param array $job Job config.
	 * @return array { imported: int, skipped: int, warnings: string[] }
	 */
	public function run_job( $job ) {
		$settings    = $this->repo->get_settings();
		$urls        = array_filter( array_map( 'trim', preg_split( '/[\r\n,]+/', (string) $job['feeds'] ) ) );
		$feed_errors = array();
		$fallbacks   = $this->repo->get_fallback_images();

		if ( ! empty( $job['fallback_image_url'] ) ) {
			array_unshift( $fallbacks, esc_url_raw( $job['fallback_image_url'] ) );
		}

		$items = $this->fetcher->get_items(
			array(
				'urls'             => $urls,
				'limit'            => absint( $job['limit'] ),
				'cache_minutes'    => absint( $settings['cache_minutes'] ),
				'fallback_images'  => $fallbacks,
				'include_keywords' => isset( $job['include_keywords'] ) ? $job['include_keywords'] : '',
				'exclude_keywords' => isset( $job['exclude_keywords'] ) ? $job['exclude_keywords'] : '',
				'advanced_filters'  => isset( $job['advanced_filters'] ) ? $job['advanced_filters'] : array(),
				'advanced_mode'     => isset( $job['advanced_filter_mode'] ) ? $job['advanced_filter_mode'] : 'all',
				'date_after'       => isset( $job['date_after'] ) ? $job['date_after'] : '',
				'date_before'      => isset( $job['date_before'] ) ? $job['date_before'] : '',
				'affiliate_name'   => $settings['affiliate_name'],
				'affiliate_value'  => $settings['affiliate_value'],
				'amazon_tag'       => $settings['amazon_tag'],
			),
			$feed_errors
		);

		$amazon_tag = isset( $settings['amazon_tag'] ) ? $settings['amazon_tag'] : '';

		$result = array(
			'imported' => 0,
			'skipped'  => 0,
			'warnings' => array(),
		);

		// Seed warnings with any feed-fetch failures.
		foreach ( $feed_errors as $url => $message ) {
			$result['warnings'][] = sprintf( 'Feed fetch failed (%s): %s', $url, $message );
		}

		foreach ( $items as $item ) {
			if ( $this->item_exists( $item['guid'], $item['link'] ) ) {
				$result['skipped']++;
				continue;
			}

			$inserted = $this->insert_item( $item, $job, $amazon_tag );
			if ( $inserted['post_id'] ) {
				$result['imported']++;
			} else {
				$result['skipped']++;
			}

			if ( ! empty( $inserted['warnings'] ) ) {
				$result['warnings'] = array_merge( $result['warnings'], $inserted['warnings'] );
			}
		}

		// Record this run in the dedicated log store (newest first, capped).
		// Logs live apart from the jobs option so concurrent runs don't have to
		// rewrite the whole jobs blob just to append a line.
		if ( ! empty( $job['id'] ) ) {
			$this->repo->append_job_log(
				$job['id'],
				array(
					'time'     => current_time( 'mysql' ),
					'imported' => $result['imported'],
					'skipped'  => $result['skipped'],
					'warnings' => array_slice( $result['warnings'], 0, WRA_Settings_Repository::LOG_CAP ),
				)
			);
		}

		return $result;
	}

	/**
	 * Check whether a feed item has already been imported.
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
	 * Insert a feed item as a WordPress post.
	 *
	 * Content pipeline: feed content → full-text extraction (optional) → AI rewrite/summarize (optional).
	 *
	 * @param array  $item       Feed item.
	 * @param array  $job        Job config.
	 * @param string $amazon_tag Amazon Associates tag.
	 * @return array { post_id: int, warnings: string[] }
	 */
	private function insert_item( $item, $job, $amazon_tag = '' ) {
		$warnings = array();

		$import_full_post = ! empty( $job['import_full_post'] );

		// Start with feed content unless this job intentionally imports excerpts.
		$content = ( $import_full_post || ! empty( $job['use_full_content'] ) ) ? $item['content'] : wpautop( esc_html( $item['excerpt'] ) );

		// Full-text extraction fetches the source article body.
		if ( ( $import_full_post || ! empty( $job['full_text_extraction'] ) ) && null !== $this->extractor ) {
			$extract_error = null;
			$extracted     = $this->extractor->extract( $item['link'], 15, $extract_error );
			if ( ! empty( $extracted ) ) {
				$content = $extracted;
				if ( method_exists( $this->extractor, 'get_last_image' ) && $this->extractor->get_last_image() ) {
					$item['image'] = $this->extractor->get_last_image();
				}
			} elseif ( null !== $extract_error ) {
				$warnings[] = sprintf( 'Full-text extraction failed for "%s": %s', $item['link'], $extract_error );
			}
		}

		if ( $import_full_post && ! empty( $item['image'] ) && false === stripos( $content, '<img' ) ) {
			$content = sprintf(
				'<figure class="wra-imported-image"><img src="%s" alt="" loading="lazy"></figure>',
				esc_url( $item['image'] )
			) . $content;
		}

		// AI rewrite/summarize.
		$ai_mode = isset( $job['ai_mode'] ) ? $job['ai_mode'] : 'none';
		if ( 'none' !== $ai_mode && null !== $this->ai_rewriter ) {
			$ai_prompt = isset( $job['ai_prompt'] ) ? $job['ai_prompt'] : '';
			$ai_error  = null;
			$content   = $this->ai_rewriter->process( $content, $item['title'], $ai_mode, $ai_prompt, $ai_error );
			if ( null !== $ai_error ) {
				$warnings[] = sprintf( 'AI %s failed for "%s": %s', $ai_mode, $item['title'], $ai_error );
			}
		}

		// Rewrite Amazon links in the assembled content.
		if ( ! empty( $amazon_tag ) ) {
			$content = WRA_Amazon_Rewriter::rewrite_content( $content, $amazon_tag );
		}

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
			$warnings[] = sprintf( 'wp_insert_post failed for "%s": %s', $item['title'], $post_id->get_error_message() );
			return array( 'post_id' => 0, 'warnings' => $warnings );
		}

		update_post_meta( $post_id, '_wra_source_guid', $item['guid'] );
		update_post_meta( $post_id, '_wra_source_link', $item['link'] );
		update_post_meta( $post_id, '_wra_source_feed', $item['source_feed'] );
		if ( ! empty( $job['enable_canonical'] ) ) {
			update_post_meta( $post_id, '_wra_canonical_source', esc_url_raw( $item['link'] ) );
		}

		$category_ids = array();
		if ( ! empty( $job['category'] ) ) {
			$category_ids[] = absint( $job['category'] );
		}

		$mapped_category = $this->match_category_mapping( $item, isset( $job['category_mappings'] ) ? $job['category_mappings'] : array() );
		if ( $mapped_category ) {
			$category_ids[] = $mapped_category;
		}

		$category_ids = array_values( array_unique( array_filter( array_map( 'absint', $category_ids ) ) ) );
		if ( ! empty( $category_ids ) ) {
			wp_set_post_terms( $post_id, $category_ids, 'category', true );
		}

		if ( ! empty( $job['tags'] ) ) {
			$tag_names = array_filter( array_map( 'trim', explode( ',', $job['tags'] ) ) );
			if ( ! empty( $tag_names ) ) {
				wp_set_post_terms( $post_id, $tag_names, 'post_tag', true );
			}
		}

		if ( ! empty( $item['image'] ) && ! empty( $job['save_featured_image'] ) ) {
			$this->set_featured_image_from_url( $post_id, $item['image'] );
		}

		return array( 'post_id' => (int) $post_id, 'warnings' => $warnings );
	}

	/**
	 * Match keyword-to-category mappings against a feed item.
	 *
	 * @param array $item     Feed item.
	 * @param array $mappings Mapping configs.
	 * @return int Category ID, or 0 when no mapping matches.
	 */
	private function match_category_mapping( $item, $mappings ) {
		if ( empty( $mappings ) || ! is_array( $mappings ) ) {
			return 0;
		}

		$haystack = strtolower(
			wp_strip_all_tags(
				$item['title'] . ' ' . $item['excerpt'] . ' ' . $item['content'] . ' ' . $item['author']
			)
		);

		foreach ( $mappings as $mapping ) {
			if ( empty( $mapping['keywords'] ) || empty( $mapping['category'] ) ) {
				continue;
			}

			$keywords = array_filter( array_map( 'trim', explode( ',', $mapping['keywords'] ) ) );
			foreach ( $keywords as $keyword ) {
				if ( '' !== $keyword && false !== strpos( $haystack, strtolower( $keyword ) ) ) {
					return $this->resolve_category_id( $mapping['category'] );
				}
			}
		}

		return 0;
	}

	/**
	 * Resolve category ID from an ID, slug, or name.
	 *
	 * @param string|int $category Category identifier.
	 * @return int
	 */
	private function resolve_category_id( $category ) {
		if ( is_numeric( $category ) ) {
			return absint( $category );
		}

		$term = get_category_by_slug( sanitize_title( $category ) );
		if ( ! $term ) {
			$term = get_term_by( 'name', sanitize_text_field( $category ), 'category' );
		}

		return $term && ! is_wp_error( $term ) ? absint( $term->term_id ) : 0;
	}

	/**
	 * Sideload an image URL and set it as the post's featured image.
	 *
	 * @param int    $post_id   Post ID.
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
			wp_delete_file( $tmp );
			return;
		}

		set_post_thumbnail( $post_id, $attachment_id );
	}
}
