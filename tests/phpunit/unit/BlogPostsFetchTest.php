<?php

namespace MediaWiki\Extension\BlogPosts\Tests\Unit;

use MediaWiki\Config\HashConfig;
use MediaWiki\Extension\BlogPosts\BlogPosts;
use MediaWiki\Extension\BlogPosts\Hooks;
use MediaWiki\Http\HttpRequestFactory;
use MediaWikiUnitTestCase;
use MWHttpRequest;
use StatusValue;

/**
 * The fetch path: which URL is requested with which headers, and how each kind
 * of response maps to "posts", "no posts" or "failed".
 *
 * @covers \MediaWiki\Extension\BlogPosts\BlogPosts
 * @covers \MediaWiki\Extension\BlogPosts\Hooks::getWidgetState
 */
class BlogPostsFetchTest extends MediaWikiUnitTestCase {

	private const POST = [
		'title' => [ 'rendered' => 'A &amp; B' ],
		'link' => 'https://blog.example.org/a-b/',
	];

	/**
	 * @param array $blogPostsConfig
	 * @param StatusValue $status
	 * @param int $httpStatus
	 * @param string|null $content
	 * @param array &$sentHeaders Receives every header set on the request
	 * @param string|null &$requestedUrl Receives the URL that was requested
	 * @return BlogPosts
	 */
	private function makeBlogPosts(
		array $blogPostsConfig,
		StatusValue $status,
		int $httpStatus,
		?string $content,
		array &$sentHeaders,
		?string &$requestedUrl
	): BlogPosts {
		$request = $this->createMock( MWHttpRequest::class );
		$request->method( 'setHeader' )->willReturnCallback(
			static function ( $name, $value ) use ( &$sentHeaders ) {
				$sentHeaders[$name] = $value;
			}
		);
		$request->method( 'execute' )->willReturn( $status );
		$request->method( 'getStatus' )->willReturn( $httpStatus );
		$request->method( 'getContent' )->willReturn( $content );
		$request->method( 'getResponseHeader' )->willReturn( 'text/html' );

		$factory = $this->createMock( HttpRequestFactory::class );
		$factory->method( 'create' )->willReturnCallback(
			static function ( $url ) use ( $request, &$requestedUrl ) {
				$requestedUrl = $url;
				return $request;
			}
		);
		// The old code path must not be used: get() cannot carry headers.
		$factory->expects( $this->never() )->method( 'get' );

		$config = new HashConfig( [ 'BlogPostsConfig' => $blogPostsConfig + [
			'blogURL' => 'http://blog-web/?rest_route=/wp/v2/posts/',
			'morePostsUrl' => 'https://blog.example.org',
			'postsPerPage' => 4,
			'initialPage' => 1,
		] ] );

		return new BlogPosts( $config, $factory );
	}

	public function testSendsConfiguredHeadersAndFetchesBlogUrl() {
		$sent = [];
		$url = null;
		$blogPosts = $this->makeBlogPosts(
			[ 'requestHeaders' => [ 'Host' => 'blog.example.org', 'X-Forwarded-Proto' => 'https' ] ],
			StatusValue::newGood( 200 ), 200, json_encode( [ self::POST ] ), $sent, $url
		);

		$posts = $blogPosts->getPosts( 1, 4 );

		$this->assertSame(
			[ 'Host' => 'blog.example.org', 'X-Forwarded-Proto' => 'https' ],
			$sent
		);
		$this->assertStringStartsWith( 'http://blog-web/?rest_route=/wp/v2/posts/&', $url );
		$this->assertStringContainsString( 'per_page=4', $url );
		$this->assertSame(
			[ [ 'image' => '', 'title' => 'A & B', 'url' => 'https://blog.example.org/a-b/' ] ],
			$posts
		);
		$this->assertSame( 'ok', Hooks::getWidgetState( $posts ) );
	}

	public function testNoHeadersByDefault() {
		$sent = [];
		$url = null;
		$blogPosts = $this->makeBlogPosts(
			[], StatusValue::newGood( 200 ), 200, '[]', $sent, $url
		);
		$blogPosts->getPosts( 1, 4 );
		$this->assertSame( [], $sent );
	}

	public function testEmptyBlogIsNotAFailure() {
		$sent = [];
		$url = null;
		$blogPosts = $this->makeBlogPosts(
			[], StatusValue::newGood( 200 ), 200, '[]', $sent, $url
		);
		$posts = $blogPosts->getPosts( 1, 4 );
		$this->assertSame( [], $posts );
		$this->assertSame( 'empty', Hooks::getWidgetState( $posts ) );
	}

	public static function provideFailures(): array {
		return [
			// What prod received (#1627 in kolzchut/kz-infrastructure): the
			// edge's managed-challenge page with a 403.
			'edge challenge, 403' => [
				StatusValue::newFatal( 'http-bad-status', 403, 'Forbidden' ), 403,
				'<!DOCTYPE html><html><head><title>Just a moment...</title>',
			],
			'HTML served with 200' => [
				StatusValue::newGood( 200 ), 200, '<!DOCTYPE html><html></html>',
			],
			'unfollowed redirect' => [ StatusValue::newGood( 301 ), 301, '' ],
			'connection failure' => [
				StatusValue::newFatal( 'http-request-error' ), 0, null,
			],
			'WordPress REST error object' => [
				StatusValue::newGood( 200 ), 200,
				json_encode( [ 'code' => 'rest_no_route', 'message' => 'No route' ] ),
			],
			'JSON scalar' => [ StatusValue::newGood( 200 ), 200, '"oops"' ],
		];
	}

	/**
	 * @dataProvider provideFailures
	 */
	public function testFailuresReturnFalse( StatusValue $status, int $httpStatus, ?string $content ) {
		$sent = [];
		$url = null;
		$blogPosts = $this->makeBlogPosts( [], $status, $httpStatus, $content, $sent, $url );
		$posts = $blogPosts->getPosts( 1, 4 );
		$this->assertFalse( $posts );
		$this->assertSame( 'error', Hooks::getWidgetState( $posts ) );
	}

	public function testMissingBlogUrlFailsWithoutRequesting() {
		$factory = $this->createMock( HttpRequestFactory::class );
		$factory->expects( $this->never() )->method( 'create' );
		$config = new HashConfig( [ 'BlogPostsConfig' => [
			'blogURL' => null, 'postsPerPage' => 4, 'initialPage' => 1,
		] ] );
		$this->assertFalse( ( new BlogPosts( $config, $factory ) )->getPosts( 1, 4 ) );
	}

	public static function provideRequestHeaders(): array {
		return [
			'absent' => [ [], [] ],
			'not an array' => [ [ 'requestHeaders' => 'Host: x' ], [] ],
			'kept' => [
				[ 'requestHeaders' => [ 'Host' => 'blog.example.org' ] ],
				[ 'Host' => 'blog.example.org' ],
			],
			'list entries, empty names and empty or non-scalar values dropped' => [
				[ 'requestHeaders' => [
					'Host: x', '' => 'y', 'X-Empty' => '', 'X-Array' => [ 'a' ], 'X-Int' => 1,
				] ],
				[ 'X-Int' => '1' ],
			],
		];
	}

	/**
	 * @dataProvider provideRequestHeaders
	 */
	public function testGetRequestHeaders( array $config, array $expected ) {
		$this->assertSame( $expected, BlogPosts::getRequestHeaders( $config ) );
	}
}
