<?php
/**
 * Unit tests for WRA_Full_Text_Extractor parsing behavior.
 *
 * @package Curated_RSS_Aggregator
 */

use PHPUnit\Framework\TestCase;

class FullTextExtractorTest extends TestCase {

	/** @var WRA_Full_Text_Extractor */
	private $extractor;

	/** @var ReflectionMethod */
	private $parse_content;

	protected function setUp(): void {
		$this->extractor     = new WRA_Full_Text_Extractor();
		$method              = new ReflectionMethod( WRA_Full_Text_Extractor::class, 'parse_content' );
		$method->setAccessible( true );
		$this->parse_content = $method;
	}

	private function parse( string $html, string $base_url = 'https://example.com/news/post/' ): string {
		return $this->parse_content->invoke( $this->extractor, $html, $base_url );
	}

	public function test_source_article_images_are_kept_and_resolved(): void {
		$html = '<html><body><article><p>Lead paragraph.</p><img data-src="/images/story.jpg" alt="Story"></article></body></html>';

		$content = $this->parse( $html );

		$this->assertStringContainsString( '<p>Lead paragraph.</p>', $content );
		$this->assertStringContainsString( 'src="https://example.com/images/story.jpg"', $content );
		$this->assertStringContainsString( 'loading="lazy"', $content );
		$this->assertSame( 'https://example.com/images/story.jpg', $this->extractor->get_last_image() );
	}

	public function test_script_and_navigation_content_are_removed(): void {
		$html = '<html><body><article><nav>Menu</nav><p>Real article.</p><script>alert(1)</script></article></body></html>';

		$content = $this->parse( $html );

		$this->assertStringContainsString( 'Real article.', $content );
		$this->assertStringNotContainsString( 'Menu', $content );
		$this->assertStringNotContainsString( 'alert', $content );
	}
}
