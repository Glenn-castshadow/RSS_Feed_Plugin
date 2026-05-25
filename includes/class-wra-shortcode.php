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
				'include_keywords' => '',
				'exclude_keywords' => '',
				'affiliate_name'   => $settings['affiliate_name'],
				'affiliate_value'  => $settings['affiliate_value'],
			),
			$atts,
			'curated_rss'
		);

		$urls  = $this->parse_feeds( $atts['feeds'] );
		$items = $this->fetcher->get_items(
			$urls,
			array(
				'limit'            => absint( $atts['items'] ),
				'cache_minutes'    => absint( $settings['cache_minutes'] ),
				'fallback_image'   => $settings['fallback_image'],
				'include_keywords' => sanitize_text_field( $atts['include_keywords'] ),
				'exclude_keywords' => sanitize_text_field( $atts['exclude_keywords'] ),
				'affiliate_name'   => sanitize_key( $atts['affiliate_name'] ),
				'affiliate_value'  => sanitize_text_field( $atts['affiliate_value'] ),
			)
		);

		wp_enqueue_style( 'wra-public' );

		if ( empty( $items ) ) {
			return '<div class="wra-feed-empty">' . esc_html__( 'No feed items found.', 'curated-rss-aggregator' ) . '</div>';
		}

		$layout = in_array( $atts['layout'], array( 'grid', 'list', 'compact' ), true ) ? $atts['layout'] : 'grid';
		$columns = absint( $atts['columns'] );

		$valid_card_styles = array( 'default', 'shadow', 'flat', 'outline', 'none' );
		$card_style = in_array( $atts['card_style'], $valid_card_styles, true ) ? $atts['card_style'] : 'default';

		$valid_ratios = array( '16-9', '4-3', '1-1', '3-2' );
		$image_ratio = in_array( $atts['image_ratio'], $valid_ratios, true ) ? $atts['image_ratio'] : '16-9';

		$read_more_text = ! empty( $atts['read_more_text'] )
			? $atts['read_more_text']
			: __( 'Read more', 'curated-rss-aggregator' );

		$max_chars = absint( $atts['max_chars'] );

		// Build wrapper class list.
		$wrapper_classes = array( 'wra-feed', 'wra-feed--' . $layout );
		if ( 'default' !== $card_style ) {
			$wrapper_classes[] = 'wra-feed--card-' . $card_style;
		}
		if ( '16-9' !== $image_ratio ) {
			$wrapper_classes[] = 'wra-feed--ratio-' . $image_ratio;
		}

		// Explicit column count overrides the auto-fit grid (grid layout only).
		$wrapper_style = '';
		if ( $columns > 0 && 'grid' === $layout ) {
			$wrapper_style = ' style="' . esc_attr( 'grid-template-columns: repeat(' . $columns . ', 1fr);' ) . '"';
		}

		ob_start();
		?>
		<div class="<?php echo esc_attr( implode( ' ', $wrapper_classes ) ); ?>"<?php echo $wrapper_style; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<?php foreach ( $items as $item ) : ?>
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
			<?php endforeach; ?>
		</div>
		<?php
		return ob_get_clean();
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
		foreach ( array( 'show_image', 'show_date', 'show_source', 'show_author', 'show_excerpt', 'show_read_more' ) as $key ) {
			$atts[ $key ] = ! empty( $atts[ $key ] ) ? 'yes' : 'no';
		}

		return $this->render( $atts );
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
