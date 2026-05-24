<?php
/**
 * Uninstall cleanup.
 *
 * @package Curated_RSS_Aggregator
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'wra_settings' );
delete_option( 'wra_import_jobs' );
wp_clear_scheduled_hook( 'wra_run_import_jobs' );
