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
				'image' => $post['_embedded']['wp:featuredmedia'][0]['link'],
				'title' => $post['title']['rendered'],
				'content' => $post['content']['rendered'],
			];
		}, $result );
	}

	public static function createBlogPostsSection( $data ) {
		$templateParser = new TemplateParser( __DIR__ . '/templates' );

		return $templateParser->processTemplate( 'blog-posts', [
			'titleText' => wfMessage( 'blog-posts-title' ),
			'moreText'  => wfMessage( 'blog-posts-more' ),
			'posts'     => $data
		] );
	}

}

