<?php
/**
 * Uninstall cleanup.
 *
 * Removes all plugin data: options, scheduled cron event, post meta added to
 * imported posts, and SimplePie feed transients cached in the options table.
 * Imported posts themselves are kept — they become ordinary WordPress posts.
 *
 * @package Curated_RSS_Aggregator
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Plugin options.
delete_option( 'wra_settings' );
delete_option( 'wra_import_jobs' );
delete_option( 'wra_feed_lists' );
delete_option( 'wra_job_logs' );

// Scheduled cron events.
wp_clear_scheduled_hook( 'wra_run_import_jobs' );
wp_clear_scheduled_hook( 'wra_refresh_feed_items' );

// Post meta added to every imported post.
delete_post_meta_by_key( '_wra_source_guid' );
delete_post_meta_by_key( '_wra_source_link' );
delete_post_meta_by_key( '_wra_source_feed' );

// SimplePie feed transients and the plugin's stale-while-revalidate item cache.
global $wpdb;
$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_feed_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_feed_' ) . '%',
		$wpdb->esc_like( '_transient_wra_items_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_wra_items_' ) . '%'
	)
);
