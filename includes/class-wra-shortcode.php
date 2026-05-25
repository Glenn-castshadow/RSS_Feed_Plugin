<?php
/**
 * Public shortcode rendering.
 *
 * @package Curated_RSS_Aggregator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WRA_Shortcode {
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

		add_shortcode( 'curated_rss', array( $this, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	/**
	 * Register public assets.
	 */
	public function register_assets() {
		wp_register_style( 'wra-public', WRA_PLUGIN_URL . 'assets/css/public.css', array(), WRA_VERSION );
		wp_register_script( 'wra-public', WRA_PLUGIN_URL . 'assets/js/public.js', array(), WRA_VERSION, true );
		wp_localize_script(
			'wra-public',
			'wra_public',
			array(
				'ajax_url'     => admin_url( 'admin-ajax.php' ),
				/* translators: %d: number of new items appended to the feed */
				'items_loaded' => __( '%d more items loaded.', 'curated-rss-aggregator' ),
			)
		);
	}

	/**
	 * Render shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function render( $atts ) {
		$settings = WRA_Plugin::get_settings();
		$atts     = shortcode_atts(
			array(
				'feeds'            => $settings['feeds'],
				'items'            => 6,
				'per_feed'         => 0,
				'layout'           => 'grid',
				'columns'          => 0,
				'image_ratio'      => '16-9',
				'card_style'       => 'default',
				'show_image'       => 'yes',
				'show_date'        => 'yes',
				'show_source'      => 'no',
				'show_author'      => 'no',
				'show_excerpt'     => 'yes',
				'max_chars'        => 0,
				'show_read_more'   => 'no',
				'read_more_text'   => '',
				'show_load_more'   => 'no',
				'include_keywords' => '',
				'exclude_keywords' => '',
				'affiliate_name'   => $settings['affiliate_name'],
				'affiliate_value'  => $settings['affiliate_value'],
				'amazon_tag'       => $settings['amazon_tag'],
			),
			$atts,
			'curated_rss'
		);

		$urls           = $this->parse_feeds( $atts['feeds'] );
		$limit          = max( 1, absint( $atts['items'] ) );
		$show_load_more = 'yes' === $atts['show_load_more'];

		$items = $this->fetcher->get_items(
			$urls,
			array(
				'limit'            => $show_load_more ? $limit + 1 : $limit,
				'per_feed'         => absint( $atts['per_feed'] ),
				'cache_minutes'    => absint( $settings['cache_minutes'] ),
				'fallback_images'  => WRA_Plugin::get_fallback_images(),
				'include_keywords' => sanitize_text_field( $atts['include_keywords'] ),
				'exclude_keywords' => sanitize_text_field( $atts['exclude_keywords'] ),
				'affiliate_name'   => sanitize_key( $atts['affiliate_name'] ),
				'affiliate_value'  => sanitize_text_field( $atts['affiliate_value'] ),
				'amazon_tag'       => sanitize_text_field( $atts['amazon_tag'] ),
			)
		);

		$has_more = false;

		wp_enqueue_style( 'wra-public' );

		if ( $show_load_more ) {
			$has_more = count( $items ) > $limit;
			if ( $has_more ) {
				array_pop( $items );
			}
			wp_enqueue_script( 'wra-public' );
		}

		if ( empty( $items ) ) {
			return '<div class="wra-feed-empty">' . esc_html__( 'No feed items found.', 'curated-rss-aggregator' ) . '</div>';
		}

		$layout    = in_array( $atts['layout'], array( 'grid', 'list', 'compact' ), true ) ? $atts['layout'] : 'grid';
		$columns   = absint( $atts['columns'] );

		$valid_card_styles = array( 'default', 'shadow', 'flat', 'outline', 'none' );
		$card_style        = in_array( $atts['card_style'], $valid_card_styles, true ) ? $atts['card_style'] : 'default';

		$valid_ratios = array( '16-9', '4-3', '1-1', '3-2' );
		$image_ratio  = in_array( $atts['image_ratio'], $valid_ratios, true ) ? $atts['image_ratio'] : '16-9';

		$wrapper_classes = array( 'wra-feed', 'wra-feed--' . $layout );
		if ( 'default' !== $card_style ) {
			$wrapper_classes[] = 'wra-feed--card-' . $card_style;
		}
		if ( '16-9' !== $image_ratio ) {
			$wrapper_classes[] = 'wra-feed--ratio-' . $image_ratio;
		}

		$wrapper_style = '';
		if ( $columns > 0 && 'grid' === $layout ) {
			$wrapper_style = ' style="' . esc_attr( 'grid-template-columns: repeat(' . $columns . ', 1fr);' ) . '"';
		}

		$output = '';

		if ( $show_load_more ) {
			$output .= '<div class="wra-load-more-container">';
			// Visually hidden; updated by JS to announce how many items loaded.
			$output .= '<div class="wra-announcer" aria-live="polite" aria-atomic="true"></div>';
		}

		$output .= '<div class="' . esc_attr( implode( ' ', $wrapper_classes ) ) . '"' . $wrapper_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		if ( $show_load_more ) {
			$output .= ' data-wra-params="' . esc_attr( wp_json_encode( $this->get_load_more_params( $atts ) ) ) . '"';
			$output .= ' data-wra-nonce="' . esc_attr( wp_create_nonce( 'wra_load_more' ) ) . '"';
		}

		$output .= '>';
		$output .= $this->render_items( $items, $atts );
		$output .= '</div>';

		if ( $show_load_more ) {
			if ( $has_more ) {
				$output .= '<div class="wra-load-more-wrap" data-wra-offset="' . esc_attr( $limit ) . '">';
				$output .= '<button type="button" class="wra-load-more button">' . esc_html__( 'Load more', 'curated-rss-aggregator' ) . '</button>';
				$output .= '</div>';
			}
			$output .= '</div>'; // .wra-load-more-container
		}

		return $output;
	}

	/**
	 * Render callback for the Gutenberg block.
	 *
	 * Block delivers booleans; shortcode render() expects 'yes'/'no' strings.
	 *
	 * @param array $atts Block attributes.
	 * @return string
	 */
	public function render_block( $atts ) {
		foreach ( array( 'show_image', 'show_date', 'show_source', 'show_author', 'show_excerpt', 'show_read_more', 'show_load_more' ) as $key ) {
			$atts[ $key ] = ! empty( $atts[ $key ] ) ? 'yes' : 'no';
		}

		return $this->render( $atts );
	}

	/**
	 * AJAX handler for "Load more" requests.
	 */
	public function ajax_load_more() {
		check_ajax_referer( 'wra_load_more', 'nonce' );

		$params = isset( $_POST['params'] ) ? json_decode( sanitize_text_field( wp_unslash( $_POST['params'] ) ), true ) : null;
		if ( ! is_array( $params ) ) {
			wp_send_json_error( array( 'message' => 'Invalid params' ) );
		}

		$offset   = max( 0, absint( isset( $_POST['offset'] ) ? $_POST['offset'] : 0 ) );
		$limit    = max( 1, absint( isset( $params['items'] ) ? $params['items'] : 6 ) );
		$settings = WRA_Plugin::get_settings();
		$urls     = $this->parse_feeds( isset( $params['feeds'] ) ? $params['feeds'] : '' );

		$items = $this->fetcher->get_items(
			$urls,
			array(
				'limit'            => $limit + 1,
				'offset'           => $offset,
				'per_feed'         => absint( isset( $params['per_feed'] ) ? $params['per_feed'] : 0 ),
				'cache_minutes'    => absint( $settings['cache_minutes'] ),
				'fallback_images'  => WRA_Plugin::get_fallback_images(),
				'include_keywords' => sanitize_text_field( isset( $params['include_keywords'] ) ? $params['include_keywords'] : '' ),
				'exclude_keywords' => sanitize_text_field( isset( $params['exclude_keywords'] ) ? $params['exclude_keywords'] : '' ),
				'affiliate_name'   => sanitize_key( isset( $params['affiliate_name'] ) ? $params['affiliate_name'] : '' ),
				'affiliate_value'  => sanitize_text_field( isset( $params['affiliate_value'] ) ? $params['affiliate_value'] : '' ),
				'amazon_tag'       => sanitize_text_field( isset( $params['amazon_tag'] ) ? $params['amazon_tag'] : '' ),
			)
		);

		$has_more = count( $items ) > $limit;
		if ( $has_more ) {
			array_pop( $items );
		}

		$string_keys = array( 'show_image', 'show_date', 'show_source', 'show_author', 'show_excerpt', 'show_read_more', 'read_more_text' );
		$atts        = array_merge(
			array(
				'show_image'     => 'yes',
				'show_date'      => 'yes',
				'show_source'    => 'no',
				'show_author'    => 'no',
				'show_excerpt'   => 'yes',
				'max_chars'      => 0,
				'show_read_more' => 'no',
				'read_more_text' => '',
			),
			array_intersect_key(
				array_map( 'sanitize_text_field', array_filter( $params, 'is_string' ) ),
				array_flip( $string_keys )
			)
		);
		$atts['max_chars'] = absint( isset( $params['max_chars'] ) ? $params['max_chars'] : 0 );

		wp_send_json_success(
			array(
				'html'     => $this->render_items( $items, $atts ),
				'has_more' => $has_more,
			)
		);
	}

	/**
	 * Render article elements for a set of items.
	 *
	 * @param array $items Feed items.
	 * @param array $atts  Display options (show_image, show_date, etc.).
	 * @return string HTML.
	 */
	private function render_items( $items, $atts ) {
		$max_chars      = absint( isset( $atts['max_chars'] ) ? $atts['max_chars'] : 0 );
		$read_more_text = ! empty( $atts['read_more_text'] )
			? $atts['read_more_text']
			: __( 'Read more', 'curated-rss-aggregator' );

		ob_start();
		foreach ( $items as $item ) : ?>
			<article class="wra-feed__item">
				<?php if ( 'yes' === $atts['show_image'] && ! empty( $item['image'] ) ) : ?>
					<a class="wra-feed__image-link" href="<?php echo esc_url( $item['link'] ); ?>" target="_blank" rel="nofollow noopener">
						<img class="wra-feed__image" src="<?php echo esc_url( $item['image'] ); ?>" alt="" loading="lazy">
					</a>
				<?php endif; ?>
				<div class="wra-feed__body">
					<h3 class="wra-feed__title">
						<a href="<?php echo esc_url( $item['link'] ); ?>" target="_blank" rel="nofollow noopener">
							<?php echo esc_html( $item['title'] ); ?>
						</a>
					</h3>
					<?php
					$meta_parts = array();
					if ( 'yes' === $atts['show_date'] && ! empty( $item['date'] ) ) {
						$meta_parts[] = '<span class="wra-feed__date">' . esc_html( $item['date'] ) . '</span>';
					}
					if ( 'yes' === $atts['show_source'] && ! empty( $item['source_feed'] ) ) {
						$host = (string) parse_url( $item['source_feed'], PHP_URL_HOST );
						if ( $host ) {
							$meta_parts[] = '<span class="wra-feed__source">' . esc_html( $host ) . '</span>';
						}
					}
					if ( 'yes' === $atts['show_author'] && ! empty( $item['author'] ) ) {
						$meta_parts[] = '<span class="wra-feed__author">' . esc_html( $item['author'] ) . '</span>';
					}
					?>
					<?php if ( ! empty( $meta_parts ) ) : ?>
						<div class="wra-feed__meta">
							<?php
							// Each part is already escaped above; only the separator is added here.
							echo implode( '<span class="wra-feed__meta-sep" aria-hidden="true"> · </span>', $meta_parts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							?>
						</div>
					<?php endif; ?>
					<?php if ( 'yes' === $atts['show_excerpt'] && ! empty( $item['excerpt'] ) ) : ?>
						<?php
						$excerpt = $item['excerpt'];
						if ( $max_chars > 0 && mb_strlen( $excerpt ) > $max_chars ) {
							$trimmed    = mb_substr( $excerpt, 0, $max_chars );
							$last_space = mb_strrpos( $trimmed, ' ' );
							$excerpt    = ( false !== $last_space ? mb_substr( $trimmed, 0, $last_space ) : $trimmed ) . '…';
						}
						?>
						<p class="wra-feed__excerpt"><?php echo esc_html( $excerpt ); ?></p>
					<?php endif; ?>
					<?php if ( 'yes' === $atts['show_read_more'] ) : ?>
						<a class="wra-feed__read-more" href="<?php echo esc_url( $item['link'] ); ?>" target="_blank" rel="nofollow noopener">
							<?php echo esc_html( $read_more_text ); ?> <span aria-hidden="true">&#8594;</span>
						</a>
					<?php endif; ?>
				</div>
			</article>
		<?php endforeach;
		return ob_get_clean();
	}

	/**
	 * Build the params object sent to the AJAX load-more handler.
	 *
	 * @param array $atts Shortcode atts.
	 * @return array
	 */
	private function get_load_more_params( $atts ) {
		return array(
			'feeds'            => $atts['feeds'],
			'items'            => absint( $atts['items'] ),
			'per_feed'         => absint( $atts['per_feed'] ),
			'show_image'       => $atts['show_image'],
			'show_date'        => $atts['show_date'],
			'show_source'      => $atts['show_source'],
			'show_author'      => $atts['show_author'],
			'show_excerpt'     => $atts['show_excerpt'],
			'max_chars'        => absint( $atts['max_chars'] ),
			'show_read_more'   => $atts['show_read_more'],
			'read_more_text'   => $atts['read_more_text'],
			'include_keywords' => $atts['include_keywords'],
			'exclude_keywords' => $atts['exclude_keywords'],
			'affiliate_name'   => $atts['affiliate_name'],
			'affiliate_value'  => $atts['affiliate_value'],
			'amazon_tag'       => $atts['amazon_tag'],
		);
	}

	/**
	 * Parse feed URL string into an array.
	 *
	 * @param string $feeds Feed URLs.
	 * @return array
	 */
	private function parse_feeds( $feeds ) {
		return array_filter( array_map( 'trim', preg_split( '/[\r\n,]+/', (string) $feeds ) ) );
	}
}
