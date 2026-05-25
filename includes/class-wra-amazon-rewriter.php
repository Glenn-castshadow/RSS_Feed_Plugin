<?php
/**
 * Amazon affiliate link rewriting.
 *
 * @package Curated_RSS_Aggregator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WRA_Amazon_Rewriter {

	/**
	 * Amazon country domains (without www).
	 *
	 * @var string[]
	 */
	private static $amazon_domains = array(
		'amazon.com',
		'amazon.co.uk',
		'amazon.de',
		'amazon.ca',
		'amazon.fr',
		'amazon.it',
		'amazon.es',
		'amazon.co.jp',
		'amazon.com.au',
		'amazon.in',
		'amazon.com.mx',
		'amazon.com.br',
		'amazon.nl',
		'amazon.se',
		'amazon.pl',
		'amazon.sg',
		'amazon.ae',
		'amazon.sa',
		'amazon.com.tr',
	);

	/**
	 * Whether a URL points to an Amazon product page.
	 *
	 * @param string $url URL to test.
	 * @return bool
	 */
	public static function is_amazon_product_url( $url ) {
		$host = strtolower( (string) parse_url( $url, PHP_URL_HOST ) );
		$host = preg_replace( '/^www\./', '', $host );

		if ( ! in_array( $host, self::$amazon_domains, true ) ) {
			return false;
		}

		$path = (string) parse_url( $url, PHP_URL_PATH );

		return (bool) preg_match( '#/(?:dp|gp/product|exec/obidos/ASIN|ASIN)/[A-Z0-9]{10}#i', $path );
	}

	/**
	 * Add (or replace) the Associates tag on an Amazon product URL.
	 *
	 * Non-Amazon URLs and non-product URLs are returned unchanged.
	 *
	 * @param string $url URL.
	 * @param string $tag Associates tag.
	 * @return string
	 */
	public static function rewrite_url( $url, $tag ) {
		if ( empty( $tag ) || ! self::is_amazon_product_url( $url ) ) {
			return $url;
		}

		return add_query_arg( 'tag', sanitize_text_field( $tag ), remove_query_arg( 'tag', $url ) );
	}

	/**
	 * Rewrite Amazon product links inside an HTML string.
	 *
	 * @param string $html HTML content.
	 * @param string $tag  Associates tag.
	 * @return string
	 */
	public static function rewrite_content( $html, $tag ) {
		if ( empty( $tag ) || empty( $html ) ) {
			return $html;
		}

		return preg_replace_callback(
			'/href=["\']([^"\']*)["\']/i',
			function ( $matches ) use ( $tag ) {
				$rewritten = self::rewrite_url( $matches[1], $tag );
				return 'href="' . esc_url( $rewritten ) . '"';
			},
			$html
		);
	}
}
