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
	 * First usable image found during the most recent extraction.
	 *
	 * @var string
	 */
	private $last_image = '';

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
		$this->last_image = '';

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

		return $this->parse_content( $html, $url );
	}

	/**
	 * Return the first image found during the most recent extraction.
	 *
	 * @return string
	 */
	public function get_last_image() {
		return $this->last_image;
	}

	/**
	 * Parse raw HTML and return the best content section.
	 *
	 * @param string $html     Raw HTML.
	 * @param string $base_url Source page URL for relative images.
	 * @return string
	 */
	private function parse_content( $html, $base_url = '' ) {
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

		$this->normalize_images( $content_node, $base_url );

		return $this->inner_html( $content_node );
	}

	/**
	 * Normalize image tags so imported posts keep article images.
	 *
	 * @param DOMNode $content_node Content root node.
	 * @param string  $base_url     Source page URL for relative images.
	 */
	private function normalize_images( $content_node, $base_url ) {
		if ( ! $content_node instanceof DOMElement && ! $content_node instanceof DOMDocument ) {
			return;
		}

		$images = $content_node->getElementsByTagName( 'img' );
		foreach ( iterator_to_array( $images ) as $img ) {
			$src = $this->get_image_src( $img );
			$src = $this->resolve_url( $src, $base_url );

			if ( empty( $src ) ) {
				if ( $img->parentNode ) {
					$img->parentNode->removeChild( $img );
				}
				continue;
			}

			$img->setAttribute( 'src', esc_url_raw( $src ) );
			$img->setAttribute( 'loading', 'lazy' );

			if ( ! $img->hasAttribute( 'alt' ) ) {
				$img->setAttribute( 'alt', '' );
			}

			if ( empty( $this->last_image ) ) {
				$this->last_image = esc_url_raw( $src );
			}
		}
	}

	/**
	 * Get a source URL from normal or lazy-loaded image attributes.
	 *
	 * @param DOMElement $img Image node.
	 * @return string
	 */
	private function get_image_src( DOMElement $img ) {
		foreach ( array( 'data-src', 'data-lazy-src', 'data-original', 'src' ) as $attr ) {
			$value = trim( $img->getAttribute( $attr ) );
			if ( '' !== $value && 0 !== strpos( $value, 'data:' ) ) {
				return $value;
			}
		}

		$srcset = trim( $img->getAttribute( 'srcset' ) );
		if ( '' === $srcset ) {
			$srcset = trim( $img->getAttribute( 'data-srcset' ) );
		}

		if ( '' === $srcset ) {
			return '';
		}

		$candidates = array_map( 'trim', explode( ',', $srcset ) );
		foreach ( $candidates as $candidate ) {
			$parts = preg_split( '/\s+/', $candidate );
			if ( ! empty( $parts[0] ) ) {
				return $parts[0];
			}
		}

		return '';
	}

	/**
	 * Resolve a possibly-relative URL against a source page URL.
	 *
	 * @param string $url      URL to resolve.
	 * @param string $base_url Source page URL.
	 * @return string
	 */
	private function resolve_url( $url, $base_url ) {
		$url = html_entity_decode( trim( (string) $url ) );
		if ( '' === $url || 0 === strpos( $url, 'data:' ) ) {
			return '';
		}

		if ( preg_match( '#^https?://#i', $url ) ) {
			return $url;
		}

		$base = wp_parse_url( $base_url );
		if ( empty( $base['scheme'] ) || empty( $base['host'] ) ) {
			return $url;
		}

		if ( 0 === strpos( $url, '//' ) ) {
			return $base['scheme'] . ':' . $url;
		}

		$root = $base['scheme'] . '://' . $base['host'];
		if ( ! empty( $base['port'] ) ) {
			$root .= ':' . $base['port'];
		}

		if ( 0 === strpos( $url, '/' ) ) {
			return $root . $url;
		}

		$path = isset( $base['path'] ) ? $base['path'] : '/';
		$dir  = preg_replace( '#/[^/]*$#', '/', $path );

		return $this->normalize_path_url( $root . $dir . $url );
	}

	/**
	 * Collapse dot segments in a URL path.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	private function normalize_path_url( $url ) {
		$parts = wp_parse_url( $url );
		if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) || empty( $parts['path'] ) ) {
			return $url;
		}

		$segments = array();
		foreach ( explode( '/', $parts['path'] ) as $segment ) {
			if ( '' === $segment || '.' === $segment ) {
				continue;
			}
			if ( '..' === $segment ) {
				array_pop( $segments );
				continue;
			}
			$segments[] = $segment;
		}

		$normalized = $parts['scheme'] . '://' . $parts['host'];
		if ( ! empty( $parts['port'] ) ) {
			$normalized .= ':' . $parts['port'];
		}
		$normalized .= '/' . implode( '/', $segments );

		if ( ! empty( $parts['query'] ) ) {
			$normalized .= '?' . $parts['query'];
		}

		return $normalized;
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
