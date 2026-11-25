<?php

namespace MediaWiki\Extension\BlogPosts\Tests\Unit;

use DOMDocument;
use DOMElement;
use DOMXPath;
use HashBagOStuff;
use MediaWiki\Extension\BlogPosts\BlogPosts;
use MediaWiki\Extension\BlogPosts\Hooks;
use MediaWiki\Html\TemplateParser;
use MediaWikiUnitTestCase;

/**
 * What the widget's templates print for a post, from the WordPress REST
 * response fixture to the HTML: every value is text, the title's entities
 * are decoded first, and only http(s) URLs become a link or an image.
 *
 * @covers \MediaWiki\Extension\BlogPosts\BlogPosts::formatPost
 * @covers \MediaWiki\Extension\BlogPosts\BlogPosts::sanitizeUrl
 * @covers \MediaWiki\Extension\BlogPosts\Hooks::getTemplatePosts
 */
class BlogPostRenderTest extends MediaWikiUnitTestCase {

	private const IMAGE = 'https://blog.example.org/wp-content/uploads/a.jpg';

	/**
	 * A post as the wp/v2/posts route returns it with _embed=wp:featuredmedia.
	 *
	 * @param string $renderedTitle
	 * @param string $link
	 * @param string|null $image
	 * @return array
	 */
	private static function wpPost( string $renderedTitle, string $link, ?string $image = self::IMAGE ): array {
		$post = [
			'id' => 1,
			'link' => $link,
			'title' => [ 'rendered' => $renderedTitle ],
		];
		if ( $image !== null ) {
			$post['_embedded']['wp:featuredmedia'][0] = [ 'source_url' => $image ];
		}
		return $post;
	}

	/**
	 * The whole widget, as the tag hook renders it: blog-posts.mustache, which
	 * pulls each post in through the blog-post partial. Rendering the partial
	 * on its own hides context-lookup differences inside it.
	 *
	 * @param array ...$wpPosts
	 * @return string
	 */
	private function render( array ...$wpPosts ): string {
		$templateParser = new TemplateParser( __DIR__ . '/../../../templates', new HashBagOStuff() );
		return $templateParser->processTemplate( 'blog-posts', [
			'titleText' => 'Blog',
			'moreText' => 'More',
			'morePostsUrl' => 'https://blog.example.org',
			'state' => 'ok',
			'posts' => Hooks::getTemplatePosts( array_map( [ BlogPosts::class, 'formatPost' ], $wpPosts ) ),
		] );
	}

	private function parse( string $html ): DOMXPath {
		$doc = new DOMDocument();
		$this->assertTrue( $doc->loadHTML(
			'<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>',
			LIBXML_NOERROR | LIBXML_NOWARNING
		) );
		return new DOMXPath( $doc );
	}

	/**
	 * @param DOMElement $element
	 * @return string[] The element's attribute names, sorted
	 */
	private static function attributeNames( DOMElement $element ): array {
		$names = [];
		foreach ( $element->attributes as $attribute ) {
			$names[] = $attribute->nodeName;
		}
		sort( $names );
		return $names;
	}

	public function testMarkupInTitleAndLinkIsPrintedAsText() {
		$link = 'https://blog.example.org/?p=1&quot; data-x=&quot;1';
		$html = $this->render( self::wpPost( '&lt;i data-x=1&gt;m&lt;/i&gt;', $link ) );
		$xpath = $this->parse( $html );

		$this->assertSame( 0, $xpath->query( '//i' )->length, $html );
		$this->assertSame( 0, $xpath->query( '//*[@data-x]' )->length, $html );

		$anchors = $xpath->query( '//a[@class="blog-post"]' );
		$this->assertSame( 1, $anchors->length );
		$anchor = $anchors->item( 0 );
		// The quote in the link stays inside href: no attribute is added.
		$this->assertSame( [ 'class', 'href', 'rel', 'target' ], self::attributeNames( $anchor ) );
		$this->assertSame( 'https://blog.example.org/?p=1" data-x="1', $anchor->getAttribute( 'href' ) );

		$title = $xpath->query( '//div[@class="blog-post-title"]' )->item( 0 );
		$this->assertSame( '<i data-x=1>m</i>', $title->textContent );
		$this->assertSame( 0, $xpath->query( '//div[@class="blog-post-title"]/*' )->length );
	}

	public function testAttributeBreakoutInImageUrlStaysInSrc() {
		$image = 'https://blog.example.org/a.jpg" onerror="alert(1)';
		$xpath = $this->parse( $this->render(
			self::wpPost( 'T', 'https://blog.example.org/t/', $image )
		) );

		$images = $xpath->query( '//img' );
		$this->assertSame( 1, $images->length );
		$this->assertSame( [ 'alt', 'src' ], self::attributeNames( $images->item( 0 ) ) );
		$this->assertSame( $image, $images->item( 0 )->getAttribute( 'src' ) );
	}

	public static function provideLegitimateTitles(): array {
		return [
			'numeric entity (wptexturize dash)' => [ 'Rights &#8211; and duties', 'Rights – and duties' ],
			'curly quotes' => [ '&#8220;Quoted&#8221;', '“Quoted”' ],
			'ampersand' => [ 'A &amp; B', 'A & B' ],
			'apostrophe' => [ 'Don&#8217;t', 'Don’t' ],
			'Hebrew' => [ 'זכויות עובדים &#8211; מדריך', 'זכויות עובדים – מדריך' ],
			'Arabic' => [ 'حقوق العمال', 'حقوق العمال' ],
			'markup in the rendered title is dropped, its text kept' => [
				'An <em>important</em> post', 'An important post',
			],
		];
	}

	/**
	 * @dataProvider provideLegitimateTitles
	 */
	public function testLegitimateTitlesRenderAsBefore( string $rendered, string $expectedText ) {
		$html = $this->render( self::wpPost( $rendered, 'https://blog.example.org/%d7%96/' ) );
		$xpath = $this->parse( $html );

		$title = $xpath->query( '//div[@class="blog-post-title"]' )->item( 0 );
		$this->assertSame( $expectedText, $title->textContent );
		$this->assertSame( 0, $xpath->query( '//div[@class="blog-post-title"]/*' )->length );

		$anchor = $xpath->query( '//a[@class="blog-post"]' )->item( 0 );
		$this->assertSame( 'https://blog.example.org/%d7%96/', $anchor->getAttribute( 'href' ) );
		$this->assertSame( '_blank', $anchor->getAttribute( 'target' ) );
		$this->assertSame( self::IMAGE, $xpath->query( '//img' )->item( 0 )->getAttribute( 'src' ) );
	}

	public function testPostWithoutHttpLinkOrImageRendersWithoutThem() {
		$xpath = $this->parse( $this->render(
			self::wpPost( 'T', 'javascript:alert(1)', 'data:image/svg+xml,<svg onload=alert(1)>' )
		) );

		$this->assertSame( 0, $xpath->query( '//a[@class="blog-post"][@href]' )->length );
		$this->assertSame( 0, $xpath->query( '//img' )->length );
		$this->assertSame( 0, $xpath->query( '//svg' )->length );
		// The card itself is still there, with its title.
		$this->assertSame( 1, $xpath->query( '//a[@class="blog-post"]' )->length );
		$this->assertSame( 'T', $xpath->query( '//div[@class="blog-post-title"]' )->item( 0 )->textContent );
	}

	public function testPostWithoutFeaturedImageHasNoImg() {
		$xpath = $this->parse( $this->render( self::wpPost( 'T', 'https://blog.example.org/t/', null ) ) );
		$this->assertSame( 0, $xpath->query( '//img' )->length );
		$this->assertSame( 1, $xpath->query( '//div[@class="blog-post-image"]' )->length );
	}

	public function testWidgetEscapesEveryPost() {
		$html = $this->render(
			self::wpPost( '&lt;b&gt;one&lt;/b&gt;', 'https://blog.example.org/1/' ),
			self::wpPost( 'two &#8211; 2', 'javascript:alert(1)', null ),
			self::wpPost( 'three', 'https://blog.example.org/3/', 'https://blog.example.org/3.jpg' )
		);
		$xpath = $this->parse( $html );

		$this->assertSame( 0, $xpath->query( '//b' )->length, $html );
		$titles = [];
		foreach ( $xpath->query( '//div[@class="blog-post-title"]' ) as $node ) {
			$titles[] = $node->textContent;
		}
		$this->assertSame( [ '<b>one</b>', 'two – 2', 'three' ], $titles );

		// Each card carries its own post's link and image, and only those.
		$hrefs = [];
		foreach ( $xpath->query( '//a[@class="blog-post"]' ) as $node ) {
			$hrefs[] = $node->hasAttribute( 'href' ) ? $node->getAttribute( 'href' ) : null;
		}
		$this->assertSame( [ 'https://blog.example.org/1/', null, 'https://blog.example.org/3/' ], $hrefs );
		$srcs = [];
		foreach ( $xpath->query( '//div[@class="blog-post-image"]' ) as $node ) {
			$img = $xpath->query( 'img', $node )->item( 0 );
			$srcs[] = $img ? $img->getAttribute( 'src' ) : null;
		}
		$this->assertSame( [ self::IMAGE, null, 'https://blog.example.org/3.jpg' ], $srcs );
		$this->assertSame( 'https://blog.example.org', $xpath->query( '//a[@class="blog-posts-more-link"]' )
			->item( 0 )->getAttribute( 'href' ) );
	}

	public function testTemplatePostsMarkMissingLinkAndImage() {
		$this->assertSame( [], Hooks::getTemplatePosts( false ) );
		$this->assertSame(
			[ [
				'image' => '', 'title' => 'T', 'url' => 'https://blog.example.org/',
				'hasUrl' => true, 'hasImage' => false,
			] ],
			Hooks::getTemplatePosts( [ [ 'image' => '', 'title' => 'T', 'url' => 'https://blog.example.org/' ] ] )
		);
	}

	public function testFormatPostToleratesMissingFields() {
		$this->assertSame(
			[ 'image' => '', 'title' => '', 'url' => '' ],
			BlogPosts::formatPost( [ 'id' => 1 ] )
		);
		$this->assertSame(
			[ 'image' => '', 'title' => '', 'url' => '' ],
			BlogPosts::formatPost( [ 'title' => [ 'rendered' => [ 'x' ] ], 'link' => [ 'x' ] ] )
		);
	}

	public static function provideUrls(): array {
		return [
			'https' => [ 'https://blog.example.org/a/', 'https://blog.example.org/a/' ],
			'http' => [ 'http://blog.example.org/a/', 'http://blog.example.org/a/' ],
			'upper-case scheme' => [ 'HTTPS://blog.example.org/', 'HTTPS://blog.example.org/' ],
			'entities decoded' => [ 'https://blog.example.org/?a=1&amp;b=2', 'https://blog.example.org/?a=1&b=2' ],
			'percent-encoded Hebrew slug' => [
				'https://blog.example.org/%d7%96%d7%9b/', 'https://blog.example.org/%d7%96%d7%9b/',
			],
			'surrounding whitespace trimmed' => [ " https://blog.example.org/ \n", 'https://blog.example.org/' ],
			'javascript' => [ 'javascript:alert(1)', '' ],
			'javascript, entity-encoded' => [ '&#106;avascript:alert(1)', '' ],
			'javascript, tab in scheme' => [ "java\tscript:alert(1)", '' ],
			'data' => [ 'data:text/html,<script>alert(1)</script>', '' ],
			'vbscript' => [ 'vbscript:msgbox(1)', '' ],
			'protocol-relative' => [ '//evil.example/', '' ],
			'relative path' => [ '/a/b', '' ],
			'no host' => [ 'https:///a', '' ],
			'scheme only' => [ 'https://', '' ],
			'control character' => [ "https://blog.example.org/\x01", '' ],
			'empty' => [ '', '' ],
			'null' => [ null, '' ],
			'array' => [ [ 'https://blog.example.org/' ], '' ],
		];
	}

	/**
	 * @dataProvider provideUrls
	 * @param mixed $url
	 * @param string $expected
	 */
	public function testSanitizeUrl( $url, string $expected ) {
		$this->assertSame( $expected, BlogPosts::sanitizeUrl( $url ) );
	}
}
