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
		curl_setopt( $curl, CURLOPT_URL, $wgBlogPostsConfig['blogURL'] . '&' . $data );
		curl_setopt( $curl, CURLOPT_RETURNTRANSFER, 1 );

		$result = curl_exec( $curl );
		$result = json_decode( $result );

		if ( !$result ) {
			return false;
		}

	}

	public static function createBlogPostsSection() {
		$templateParser = new TemplateParser( __DIR__ . '/templates' );

		return $templateParser->processTemplate( 'blog-posts', [
			'titleText' => wfMessage( 'blog-posts-title' ),
			'moreText'  => wfMessage( 'blog-posts-more' ),
			'posts'     => [
				[
					'title' => 'title 1',
					'content' => 'content 1'
				],
				[
					'title' => 'title 2',
					'content' => 'content 2'
				]
			]
		] );
	}

}

