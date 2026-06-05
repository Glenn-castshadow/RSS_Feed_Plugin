<?php
/**
 * Plugin Name: Curated RSS Aggregator
 * Description: Display RSS feeds anywhere and optionally import filtered feed items as WordPress posts.
 * Version: 1.2.5
 * Author: Local Build
 * License: GPL-2.0-or-later
 * Text Domain: curated-rss-aggregator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WRA_VERSION', '1.2.5' );
define( 'WRA_PLUGIN_FILE', __FILE__ );
define( 'WRA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WRA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Autoload plugin classes/interfaces from includes/.
 *
 * Maps WRA_Foo_Bar => includes/class-wra-foo-bar.php, removing the hand-ordered
 * require_once chain (and its load-order footguns). Only handles the plugin's
 * own WRA_ prefix and only loads files that exist, so it never interferes with
 * other autoloaders.
 */
spl_autoload_register(
	function ( $class ) {
		if ( 0 !== strpos( $class, 'WRA_' ) ) {
			return;
		}
		$file = WRA_PLUGIN_DIR . 'includes/class-' . str_replace( '_', '-', strtolower( $class ) ) . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

register_activation_hook( __FILE__, array( 'WRA_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WRA_Plugin', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'WRA_Plugin', 'init' ) );

add_action(
	'plugins_loaded',
	function () {
		( new WRA_GitHub_Updater( WRA_PLUGIN_FILE ) )->init();
	}
);
