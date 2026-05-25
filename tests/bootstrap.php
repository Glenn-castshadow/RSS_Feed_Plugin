<?php
/**
 * PHPUnit bootstrap — minimal WordPress stubs for standalone unit tests.
 *
 * Only the functions and constants used by the classes under test are stubbed
 * here. To run integration tests against a real WordPress install, replace
 * this file with a standard WP test bootstrap.
 *
 * Run: vendor/bin/phpunit
 */

// Constants used by the plugin files.
define( 'ABSPATH', dirname( __DIR__ ) . DIRECTORY_SEPARATOR );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'MINUTE_IN_SECONDS', 60 );

// --- WordPress function stubs -------------------------------------------

if ( ! function_exists( 'wp_parse_args' ) ) {
	function wp_parse_args( $args, $defaults = array() ) {
		return array_merge( (array) $defaults, (array) $args );
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $string, $remove_breaks = false ) {
		$string = strip_tags( (string) $string );
		if ( $remove_breaks ) {
			$string = preg_replace( '/[\r\n\t ]+/', ' ', $string );
		}
		return trim( $string );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) {
		return filter_var( (string) $url, FILTER_SANITIZE_URL ) ?: '';
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $maybeint ) {
		return abs( (int) $maybeint );
	}
}

// Load the class under test.
require_once dirname( __DIR__ ) . '/includes/class-wra-feed-fetcher.php';
