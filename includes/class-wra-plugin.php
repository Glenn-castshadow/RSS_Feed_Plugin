<?php
/**
 * Plugin bootstrap.
 *
 * @package Curated_RSS_Aggregator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WRA_Plugin {
	const SETTINGS_OPTION   = 'wra_settings';
	const IMPORTS_OPTION    = 'wra_import_jobs';
	const FEED_LISTS_OPTION = 'wra_feed_lists';
	const CRON_HOOK         = 'wra_run_import_jobs';

	/**
	 * Shared settings repository.
	 *
	 * @var WRA_Settings_Repository
	 */
	private static $repo;

	/**
	 * Shared shortcode instance (used as block/Elementor render callback).
	 *
	 * @var WRA_Shortcode
	 */
	private static $shortcode;

	/**
	 * Lazily create and return the shared settings repository.
	 *
	 * @return WRA_Settings_Repository
	 */
	public static function repository() {
		if ( ! self::$repo ) {
			self::$repo = new WRA_Settings_Repository();
		}
		return self::$repo;
	}

	/**
	 * Start plugin services.
	 */
	public static function init() {
		load_plugin_textdomain( 'curated-rss-aggregator', false, dirname( plugin_basename( WRA_PLUGIN_FILE ) ) . '/languages' );

		$repo = self::repository();
		$repo->migrate_inline_logs();

		$settings    = $repo->get_settings();
		$fetcher     = new WRA_Feed_Fetcher();
		$extractor   = new WRA_Full_Text_Extractor();
		$ai_rewriter = ! empty( $settings['ai_api_key'] ) ? new WRA_AI_Rewriter( $settings ) : null;
		$importer    = new WRA_Importer( $fetcher, $repo, $extractor, $ai_rewriter );

		$post_source     = new WRA_Post_Source();
		self::$shortcode = new WRA_Shortcode( $fetcher, $post_source, $repo );
		new WRA_Admin( $fetcher, $importer, $repo );

		$warmer = new WRA_Feed_Warmer( $fetcher, $repo );

		add_action( 'init', array( __CLASS__, 'register_block' ) );
		add_action( 'init', array( __CLASS__, 'migrate_cron_schedule' ) );
		add_action( 'elementor/widgets/register', array( __CLASS__, 'register_elementor_widget' ) );
		add_action( self::CRON_HOOK, array( $importer, 'run_scheduled_jobs' ) );
		// Refresh the display-feed cache on the same schedule so visitor-facing
		// renders read a warm cache instead of fetching feeds inline.
		add_action( self::CRON_HOOK, array( $warmer, 'warm' ) );
		add_filter( 'cron_schedules', array( __CLASS__, 'add_cron_schedules' ) );
		add_action( 'wp_ajax_wra_load_more', array( self::$shortcode, 'ajax_load_more' ) );
		add_action( 'wp_ajax_nopriv_wra_load_more', array( self::$shortcode, 'ajax_load_more' ) );
		add_filter( 'get_canonical_url', array( __CLASS__, 'filter_canonical_url' ), 10, 2 );
	}

	/**
	 * Expose the shared shortcode instance (used by the Elementor widget render callback).
	 *
	 * @return WRA_Shortcode
	 */
	public static function get_shortcode() {
		return self::$shortcode;
	}

	/**
	 * Use source URLs as canonical URLs for imported posts when enabled.
	 *
	 * @param string  $canonical_url Canonical URL.
	 * @param WP_Post $post          Post object.
	 * @return string
	 */
	public static function filter_canonical_url( $canonical_url, $post ) {
		if ( ! $post || empty( $post->ID ) ) {
			return $canonical_url;
		}

		$source = get_post_meta( $post->ID, '_wra_canonical_source', true );
		return $source ? esc_url_raw( $source ) : $canonical_url;
	}

	/**
	 * Register the Elementor widget when Elementor is active.
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager.
	 */
	public static function register_elementor_widget( $widgets_manager ) {
		require_once WRA_PLUGIN_DIR . 'includes/class-wra-elementor-widget.php';
		$widgets_manager->register( new WRA_Elementor_Widget() );
	}

	/**
	 * Register the Gutenberg block type.
	 *
	 * Called on the 'init' hook so register_block_type() is available.
	 */
	public static function register_block() {
		wp_register_script(
			'wra-block-editor',
			WRA_PLUGIN_URL . 'assets/js/block.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render', 'wp-i18n' ),
			WRA_VERSION,
			true
		);

		wp_register_style(
			'wra-public-block',
			WRA_PLUGIN_URL . 'assets/css/public.css',
			array(),
			WRA_VERSION
		);

		register_block_type(
			WRA_PLUGIN_DIR . 'blocks/curated-rss',
			array(
				'editor_script'   => 'wra-block-editor',
				'editor_style'    => 'wra-public-block',
				'style'           => 'wra-public-block',
				'render_callback' => array( self::$shortcode, 'render_block' ),
			)
		);
	}

	/**
	 * Plugin activation.
	 */
	public static function activate() {
		add_filter( 'cron_schedules', array( __CLASS__, 'add_cron_schedules' ) );

		if ( false === get_option( self::SETTINGS_OPTION ) ) {
			add_option( self::SETTINGS_OPTION, WRA_Settings_Repository::DEFAULTS );
		}

		if ( false === get_option( self::IMPORTS_OPTION ) ) {
			add_option( self::IMPORTS_OPTION, array() );
		}

		if ( false === get_option( self::FEED_LISTS_OPTION ) ) {
			add_option( self::FEED_LISTS_OPTION, array() );
		}

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'wra_every_15_minutes', self::CRON_HOOK );
		}
	}

	/**
	 * Plugin deactivation.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Custom cron intervals.
	 *
	 * @param array $schedules Schedules.
	 * @return array
	 */
	/**
	 * Upgrade the cron event to the 15-minute base interval for existing installs.
	 *
	 * Called on 'init' so the cron_schedules filter is already registered.
	 */
	public static function migrate_cron_schedule() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );
		if ( ! $timestamp ) {
			return;
		}
		$event = wp_get_scheduled_event( self::CRON_HOOK );
		if ( $event && 'wra_every_15_minutes' !== $event->schedule ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'wra_every_15_minutes', self::CRON_HOOK );
		}
	}

	public static function add_cron_schedules( $schedules ) {
		$schedules['wra_every_15_minutes'] = array(
			'interval' => 15 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 15 minutes', 'curated-rss-aggregator' ),
		);
		$schedules['wra_every_30_minutes'] = array(
			'interval' => 30 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 30 minutes', 'curated-rss-aggregator' ),
		);

		return $schedules;
	}

	/**
	 * Settings with defaults.
	 *
	 * @deprecated Prefer the injected WRA_Settings_Repository. Kept as a thin
	 *             delegator for backward compatibility with external callers.
	 * @return array
	 */
	public static function get_settings() {
		return self::repository()->get_settings();
	}

	/**
	 * Resolve fallback image attachment IDs to full-size URLs.
	 *
	 * @deprecated Prefer the injected WRA_Settings_Repository.
	 * @return string[]
	 */
	public static function get_fallback_images() {
		return self::repository()->get_fallback_images();
	}

	/**
	 * Import jobs.
	 *
	 * @deprecated Prefer the injected WRA_Settings_Repository.
	 * @return array
	 */
	public static function get_import_jobs() {
		return self::repository()->get_import_jobs();
	}

	/**
	 * Named feed lists.
	 *
	 * @deprecated Prefer the injected WRA_Settings_Repository.
	 * @return array Associative array keyed by list slug.
	 */
	public static function get_feed_lists() {
		return self::repository()->get_feed_lists();
	}
}
