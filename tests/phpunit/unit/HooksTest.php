<?php

namespace MediaWiki\Extension\BlogPosts\Tests\Unit;

use MediaWiki\Config\HashConfig;
use MediaWiki\Extension\BlogPosts\Hooks;
use MediaWiki\Http\HttpRequestFactory;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\BlogPosts\Hooks
 */
class HooksTest extends MediaWikiUnitTestCase {

	public function testClientConfigOmitsServerSideFetchSettings() {
		$config = new HashConfig( [ 'BlogPostsConfig' => [
			'blogURL' => 'http://blog-web/?rest_route=/wp/v2/posts/',
			'requestHeaders' => [ 'Host' => 'blog.example.org' ],
			'morePostsUrl' => 'https://blog.example.org',
			'postsPerPage' => 4,
			'initialPage' => 1,
		] ] );
		$hooks = new Hooks( $config, $this->createNoOpMock( HttpRequestFactory::class ) );

		$vars = [];
		$hooks->onResourceLoaderGetConfigVars( $vars, 'vector', $config );

		$this->assertSame( [
			'morePostsUrl' => 'https://blog.example.org',
			'postsPerPage' => 4,
			'initialPage' => 1,
		], $vars['wgBlogPostsConfig'] );
	}

	public function testWidgetState() {
		$this->assertSame( 'error', Hooks::getWidgetState( false ) );
		$this->assertSame( 'empty', Hooks::getWidgetState( [] ) );
		$this->assertSame( 'ok', Hooks::getWidgetState( [ [ 'title' => 'x' ] ] ) );
	}
}
