<?php

class BlogPosts {

	public static function getPosts( Int $page, Int $limit ) {
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

		return array_map( function( $post ) {
			return [
				'image' => html_entity_decode( $post['_embedded']['wp:featuredmedia'][0]['link'] ),
				'title' => html_entity_decode( $post['title']['rendered'] ),
				'url'   => html_entity_decode( $post['link'] )
			];
		}, $result );
	}

	public static function createBlogPostsSection( $input, array $args, Parser $parser, PPFrame $frame ) {
		global $wgBlogPostsConfig;

		$parser->getOutput()->addModules( 'ext.BlogPosts' );
		$templateParser = new TemplateParser( __DIR__ . '/templates' );

		$initialPage = $wgBlogPostsConfig['initialPage'];
		$postsPerPage = $wgBlogPostsConfig['postsPerPage'];
		$data = self::getPosts( $initialPage, $postsPerPage );

		return $templateParser->processTemplate( 'blog-posts', [
			'titleText' => wfMessage( 'blog-posts-title' ),
			'moreText'  => wfMessage( 'blog-posts-more' ),
			'posts'     => $data
		] );
	}
}

