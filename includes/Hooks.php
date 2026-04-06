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
		$vars['wgBlogPostsConfig'] = $this->config->get( 'BlogPostsConfig' );
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

		$html = $templateParser->processTemplate( 'blog-posts', [
			'titleText' => wfMessage( 'blog-posts-title' ),
			'moreText' => wfMessage( 'blog-posts-more' ),
			'morePostsUrl' => $blogPostsConfig['morePostsUrl'],
			'posts' => $data
		] );

		return [ $html, 'markerType' => 'nowiki' ];
	}

}
