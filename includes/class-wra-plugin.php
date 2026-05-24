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
	const SETTINGS_OPTION = 'wra_settings';
	const IMPORTS_OPTION  = 'wra_import_jobs';
	const CRON_HOOK       = 'wra_run_import_jobs';

	/**
	 * Start plugin services.
	 */
	public static function init() {
		load_plugin_textdomain( 'curated-rss-aggregator', false, dirname( plugin_basename( WRA_PLUGIN_FILE ) ) . '/languages' );

		$fetcher  = new WRA_Feed_Fetcher();
		$importer = new WRA_Importer( $fetcher );

		new WRA_Shortcode( $fetcher );
		new WRA_Admin( $fetcher, $importer );

		add_action( self::CRON_HOOK, array( $importer, 'run_scheduled_jobs' ) );
		add_filter( 'cron_schedules', array( __CLASS__, 'add_cron_schedules' ) );
	}

	/**
	 * Plugin activation.
	 */
	public static function activate() {
		add_filter( 'cron_schedules', array( __CLASS__, 'add_cron_schedules' ) );

		if ( false === get_option( self::SETTINGS_OPTION ) ) {
			add_option(
				self::SETTINGS_OPTION,
				array(
					'feeds'          => '',
					'cache_minutes'  => 60,
					'fallback_image' => '',
					'affiliate_name' => '',
					'affiliate_value'=> '',
				)
			);
		}

		if ( false === get_option( self::IMPORTS_OPTION ) ) {
			add_option( self::IMPORTS_OPTION, array() );
		}

		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'wra_every_30_minutes', self::CRON_HOOK );
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
	public static function add_cron_schedules( $schedules ) {
		$schedules['wra_every_30_minutes'] = array(
			'interval' => 30 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 30 minutes', 'curated-rss-aggregator' ),
		);

		return $schedules;
	}

	/**
	 * Settings with defaults.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$defaults = array(
			'feeds'           => '',
			'cache_minutes'   => 60,
			'fallback_image'  => '',
			'affiliate_name'  => '',
			'affiliate_value' => '',
		);

		return wp_parse_args( get_option( self::SETTINGS_OPTION, array() ), $defaults );
	}

	/**
	 * Import jobs.
	 *
	 * @return array
	 */
	public static function get_import_jobs() {
		$jobs = get_option( self::IMPORTS_OPTION, array() );
		return is_array( $jobs ) ? $jobs : array();
	}
}
