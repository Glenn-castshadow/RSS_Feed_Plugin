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
				'show_image'       => 'yes',
				'show_date'        => 'yes',
				'show_excerpt'     => 'yes',
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

		ob_start();
		?>
		<div class="wra-feed wra-feed--<?php echo esc_attr( $layout ); ?>">
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
						<?php if ( 'yes' === $atts['show_date'] && ! empty( $item['date'] ) ) : ?>
							<div class="wra-feed__meta"><?php echo esc_html( $item['date'] ); ?></div>
						<?php endif; ?>
						<?php if ( 'yes' === $atts['show_excerpt'] && ! empty( $item['excerpt'] ) ) : ?>
							<p class="wra-feed__excerpt"><?php echo esc_html( $item['excerpt'] ); ?></p>
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
	 * Converts block boolean attributes to the 'yes'/'no' strings the shortcode renderer expects,
	 * then delegates to render().
	 *
	 * @param array $atts Block attributes from the editor.
	 * @return string
	 */
	public function render_block( $atts ) {
		$atts['show_image']   = ! empty( $atts['show_image'] ) ? 'yes' : 'no';
		$atts['show_date']    = ! empty( $atts['show_date'] ) ? 'yes' : 'no';
		$atts['show_excerpt'] = ! empty( $atts['show_excerpt'] ) ? 'yes' : 'no';

		return $this->render( $atts );
	}

	/**
	 * Parse feed URL string.
	 *
	 * @param string $feeds Feed URLs.
	 * @return array
	 */
	private function parse_feeds( $feeds ) {
		return array_filter( array_map( 'trim', preg_split( '/[\r\n,]+/', (string) $feeds ) ) );
	}
}
