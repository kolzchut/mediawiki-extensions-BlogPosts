<?php

class BlogPosts {

	/**
	 * Get posts from a remote WordPress API
	 *
	 * @param int $page
	 * @param int $limit
	 * @return array|bool
	 */
	public static function getPosts( int $page, int $limit ) {
		global $wgBlogPostsConfig;

		$data = [
			'page'   => $page ? $page : 1,
			'per_page' => $limit ? $limit : $wgBlogPostsConfig['postsPerPage']
		];

		$data = http_build_query( $data );

		$curl = curl_init();
		curl_setopt( $curl, CURLOPT_CUSTOMREQUEST, 'GET' );
		curl_setopt( $curl, CURLOPT_URL, $wgBlogPostsConfig['blogURL'] . '&_embed&' . $data );
		curl_setopt( $curl, CURLOPT_RETURNTRANSFER, 1 );

		$result = curl_exec( $curl );

		$result = json_decode( $result, true );

		if ( !$result || array_key_exists( 'code', $result ) ) {
			return false;
		}

		return array_map( static function ( $post ) {
			return [
				'image' => html_entity_decode( $post['_embedded']['wp:featuredmedia'][0]['media_details']['sizes']['large']['source_url'] ),
				'title' => html_entity_decode( $post['title']['rendered'] ),
				'url'   => html_entity_decode( $post['link'] )
			];
		}, $result );
	}

	public static function createBlogPostsSection( $input, array $args, Parser $parser, PPFrame $frame ) {
		global $wgBlogPostsConfig;

		$parser->getOutput()->addModuleStyles( 'ext.BlogPosts.styles' );
		$templateParser = new TemplateParser( __DIR__ . '/templates' );

		$initialPage = $wgBlogPostsConfig['initialPage'];
		$postsPerPage = $wgBlogPostsConfig['postsPerPage'];
		$data = self::getPosts( $initialPage, $postsPerPage );

		$html = $templateParser->processTemplate( 'blog-posts', [
			'titleText' => wfMessage( 'blog-posts-title' ),
			'moreText'  => wfMessage( 'blog-posts-more' ),
			'morePostsUrl' => $wgBlogPostsConfig['morePostsUrl'],
			'posts'     => $data
		] );

		return [ $html, 'markerType' => 'nowiki' ];
	}
}
