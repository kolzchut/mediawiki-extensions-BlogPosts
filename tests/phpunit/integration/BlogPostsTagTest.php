<?php

namespace MediaWiki\Extension\BlogPosts\Tests\Integration;

use DOMDocument;
use DOMXPath;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Page\PageReferenceValue;
use MediaWikiIntegrationTestCase;
use MWHttpRequest;
use ParserOptions;
use StatusValue;

/**
 * The <blogposts> tag end to end through the parser, with the blog's REST
 * response served from a fixture: what reaches the page is escaped text.
 *
 * @covers \MediaWiki\Extension\BlogPosts\Hooks
 * @covers \MediaWiki\Extension\BlogPosts\BlogPosts
 */
class BlogPostsTagTest extends MediaWikiIntegrationTestCase {

	private function parseWithPosts( array $posts ): string {
		$request = $this->createMock( MWHttpRequest::class );
		$request->method( 'execute' )->willReturn( StatusValue::newGood( 200 ) );
		$request->method( 'getStatus' )->willReturn( 200 );
		$request->method( 'getContent' )->willReturn( json_encode( $posts ) );

		$factory = $this->createMock( HttpRequestFactory::class );
		$factory->method( 'create' )->willReturn( $request );

		$this->overrideConfigValue( 'BlogPostsConfig', [
			'blogURL' => 'http://blog-web/?rest_route=/wp/v2/posts/',
			'requestHeaders' => [],
			'morePostsUrl' => 'https://blog.example.org',
			'postsPerPage' => 4,
			'initialPage' => 1,
		] );
		$this->setService( 'HttpRequestFactory', $factory );

		$parser = $this->getServiceContainer()->getParserFactory()->create();
		return $parser->parse(
			'<blogposts />',
			PageReferenceValue::localReference( NS_MAIN, 'BlogPostsTagTest' ),
			ParserOptions::newFromAnon()
		)->getRawText();
	}

	public function testPostFieldsReachThePageEscaped() {
		$html = $this->parseWithPosts( [ [
			'title' => [ 'rendered' => '&lt;i data-x=1&gt;m&lt;/i&gt; &#8211; זכויות' ],
			'link' => 'https://blog.example.org/?p=1&quot; data-x=&quot;1',
			'_embedded' => [ 'wp:featuredmedia' => [ [ 'source_url' => 'javascript:alert(1)' ] ] ],
		] ] );

		$this->assertStringContainsString( 'data-blogposts-state="ok"', $html );
		$this->assertStringNotContainsString( '<i', $html );
		$this->assertStringNotContainsString( '<img', $html );
		$this->assertStringNotContainsString( 'javascript:', $html );

		$doc = new DOMDocument();
		$doc->loadHTML( '<?xml encoding="UTF-8"><html><body>' . $html . '</body></html>',
			LIBXML_NOERROR | LIBXML_NOWARNING );
		$xpath = new DOMXPath( $doc );
		$this->assertSame( 0, $xpath->query( '//*[@data-x]' )->length );
		$this->assertSame(
			'<i data-x=1>m</i> – זכויות',
			$xpath->query( '//div[@class="blog-post-title"]' )->item( 0 )->textContent
		);
		$this->assertSame(
			'https://blog.example.org/?p=1" data-x="1',
			$xpath->query( '//a[@class="blog-post"]' )->item( 0 )->getAttribute( 'href' )
		);
	}
}
