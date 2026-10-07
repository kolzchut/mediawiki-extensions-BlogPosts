<?php

namespace MediaWiki\Extension\BlogPosts;

use MediaWiki\Config\Config;
use MediaWiki\Hook\ParserFirstCallInitHook;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Parser\Parser;
use MediaWiki\ResourceLoader\Hook\ResourceLoaderGetConfigVarsHook;
use PPFrame;
use TemplateParser;

class Hooks implements ParserFirstCallInitHook, ResourceLoaderGetConfigVarsHook {

	/**
	 * Parser-cache lifetime, in seconds, of a page whose widget could not fetch
	 * its posts. Without it a single failed fetch is cached for the full
	 * parser-cache lifetime, so a blog outage of seconds empties the widget for
	 * days, and fixing the cause changes nothing until the page is re-parsed.
	 */
	public const FAILED_FETCH_CACHE_EXPIRY = 300;

	/**
	 * The only BlogPostsConfig keys exposed to client-side code. blogURL and
	 * requestHeaders describe how the SERVER reaches the blog, which may be an
	 * address that only resolves inside the hosting network; no client code
	 * uses them, so they are not published in every page's mw.config.
	 */
	private const CLIENT_CONFIG_KEYS = [ 'morePostsUrl', 'postsPerPage', 'initialPage' ];

	public function __construct(
		private readonly Config $config,
		private readonly HttpRequestFactory $httpRequestFactory,
	) {
	}

	public function onParserFirstCallInit( $parser ): void {
		$parser->setHook( 'blogposts', [ $this, 'createBlogPostsSection' ] );
	}

	/**
	 * @param array &$vars
	 * @param string $skin
	 * @param Config $config
	 */
	public function onResourceLoaderGetConfigVars( array &$vars, $skin, Config $config ): void {
		$vars['wgBlogPostsConfig'] = array_intersect_key(
			$this->config->get( 'BlogPostsConfig' ),
			array_flip( self::CLIENT_CONFIG_KEYS )
		);
	}

	/**
	 * Parser tag hook handler for <blogposts>
	 *
	 * @param string $input
	 * @param array $args
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @return array
	 */
	public function createBlogPostsSection( $input, array $args, Parser $parser, PPFrame $frame ): array {
		$blogPostsConfig = $this->config->get( 'BlogPostsConfig' );

		$parser->getOutput()->addModuleStyles( [ 'ext.BlogPosts.styles' ] );
		$templateParser = new TemplateParser( __DIR__ . '/../templates' );

		$blogPosts = new BlogPosts( $this->config, $this->httpRequestFactory );
		$initialPage = $blogPostsConfig['initialPage'];
		$postsPerPage = $blogPostsConfig['postsPerPage'];
		$data = $blogPosts->getPosts( $initialPage, $postsPerPage );

		if ( $data === false ) {
			// The reason is in the BlogPosts log channel. Retry soon rather than
			// caching an empty widget for the full parser-cache lifetime.
			$parser->getOutput()->updateCacheExpiry( self::FAILED_FETCH_CACHE_EXPIRY );
		}

		$html = $templateParser->processTemplate( 'blog-posts', [
			'titleText' => wfMessage( 'blog-posts-title' ),
			'moreText' => wfMessage( 'blog-posts-more' ),
			'morePostsUrl' => $blogPostsConfig['morePostsUrl'],
			'state' => self::getWidgetState( $data ),
			'posts' => $data
		] );

		return [ $html, 'markerType' => 'nowiki' ];
	}

	/**
	 * What the rendered widget tells whoever inspects it about how it was
	 * built, as its data-blogposts-state attribute: "ok" (posts rendered),
	 * "empty" (the blog answered with no posts) or "error" (the fetch failed;
	 * see the BlogPosts log channel). The last two look identical to a reader.
	 *
	 * @param array|false $data
	 * @return string
	 */
	public static function getWidgetState( $data ): string {
		if ( $data === false ) {
			return 'error';
		}
		return $data ? 'ok' : 'empty';
	}

}
