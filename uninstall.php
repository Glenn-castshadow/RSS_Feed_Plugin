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

// Scheduled cron event.
wp_clear_scheduled_hook( 'wra_run_import_jobs' );

// Post meta added to every imported post.
delete_post_meta_by_key( '_wra_source_guid' );
delete_post_meta_by_key( '_wra_source_link' );
delete_post_meta_by_key( '_wra_source_feed' );

// SimplePie feed transients stored in the options table.
global $wpdb;
$like_val     = $wpdb->esc_like( '_transient_feed_' ) . '%';
$like_timeout = $wpdb->esc_like( '_transient_timeout_feed_' ) . '%';
$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$like_val,
		$like_timeout
	)
);
