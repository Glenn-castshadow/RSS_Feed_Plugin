<?php
/**
 * Full-text extraction from source URLs.
 *
 * @package Curated_RSS_Aggregator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WRA_Full_Text_Extractor {
	/**
	 * XPath expressions to try for main content, in priority order.
	 *
	 * @var array
	 */
	private static $content_selectors = array(
		'//article',
		'//*[@role="main"]',
		'//main',
		'//*[contains(@class,"entry-content")]',
		'//*[contains(@class,"post-content")]',
		'//*[contains(@class,"article-content")]',
		'//*[contains(@class,"article-body")]',
		'//*[contains(@class,"post-body")]',
		'//*[@id="content"]',
		'//*[contains(@class,"content")]',
	);

	/**
	 * Tags whose subtrees are stripped before content extraction.
	 *
	 * @var array
	 */
	private static $strip_tags = array(
		'script', 'style', 'nav', 'header', 'footer',
		'aside', 'form', 'iframe', 'button', 'noscript',
	);

	/**
	 * Fetch and extract main content from a URL.
	 *
	 * Returns an empty string on failure so callers can fall back to feed content.
	 *
	 * @param string      $url     Source URL.
	 * @param int         $timeout HTTP timeout in seconds.
	 * @param string|null $error   Optional. Set to an error message string on failure.
	 * @return string Sanitized HTML content, or empty string on failure.
	 */
	public function extract( $url, $timeout = 15, &$error = null ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => $timeout,
				'user-agent' => 'Mozilla/5.0 (compatible; WP RSS Aggregator/' . WRA_VERSION . ')',
				'headers'    => array(
					'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			$error = $response->get_error_message();
			return '';
		}

		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			$error = sprintf( 'HTTP %d', (int) wp_remote_retrieve_response_code( $response ) );
			return '';
		}

		$html = wp_remote_retrieve_body( $response );
		if ( empty( $html ) ) {
			return '';
		}

		return $this->parse_content( $html );
	}

	/**
	 * Parse raw HTML and return the best content section.
	 *
	 * @param string $html Raw HTML.
	 * @return string
	 */
	private function parse_content( $html ) {
		$dom = new DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="UTF-8">' . $html );
		libxml_clear_errors();
		libxml_use_internal_errors( false );

		$xpath = new DOMXPath( $dom );

		foreach ( self::$strip_tags as $tag ) {
			foreach ( iterator_to_array( $dom->getElementsByTagName( $tag ) ) as $node ) {
				if ( $node->parentNode ) {
					$node->parentNode->removeChild( $node );
				}
			}
		}

		$content_node = null;
		foreach ( self::$content_selectors as $xp ) {
			$result = $xpath->query( $xp );
			if ( $result && $result->length > 0 ) {
				$content_node = $result->item( 0 );
				break;
			}
		}

		if ( null === $content_node ) {
			$bodies = $dom->getElementsByTagName( 'body' );
			if ( $bodies->length > 0 ) {
				$content_node = $bodies->item( 0 );
			}
		}

		if ( null === $content_node ) {
			return '';
		}

		return $this->inner_html( $content_node );
	}

	/**
	 * Return the sanitized inner HTML of a DOMNode.
	 *
	 * @param DOMNode $node Node.
	 * @return string
	 */
	private function inner_html( $node ) {
		$html = '';
		foreach ( $node->childNodes as $child ) {
			$html .= $node->ownerDocument->saveHTML( $child );
		}

		return wp_kses_post( $html );
	}
}
