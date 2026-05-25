<?php
/**
 * Plugin Name: Curated RSS Aggregator
 * Description: Display RSS feeds anywhere and optionally import filtered feed items as WordPress posts.
 * Version: 0.5.0
 * Author: Local Build
 * License: GPL-2.0-or-later
 * Text Domain: curated-rss-aggregator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WRA_VERSION', '0.5.0' );
define( 'WRA_PLUGIN_FILE', __FILE__ );
define( 'WRA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WRA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once WRA_PLUGIN_DIR . 'includes/class-wra-amazon-rewriter.php';
require_once WRA_PLUGIN_DIR . 'includes/class-wra-feed-fetcher.php';
require_once WRA_PLUGIN_DIR . 'includes/class-wra-full-text-extractor.php';
require_once WRA_PLUGIN_DIR . 'includes/class-wra-ai-rewriter.php';
require_once WRA_PLUGIN_DIR . 'includes/class-wra-importer.php';
require_once WRA_PLUGIN_DIR . 'includes/class-wra-shortcode.php';
require_once WRA_PLUGIN_DIR . 'includes/class-wra-admin.php';
require_once WRA_PLUGIN_DIR . 'includes/class-wra-plugin.php';

register_activation_hook( __FILE__, array( 'WRA_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WRA_Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'WRA_Plugin', 'init' ) );

