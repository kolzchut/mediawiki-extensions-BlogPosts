<?php

namespace MediaWiki\Extension\BlogPosts;

use MediaWiki\Config\Config;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;

class BlogPosts {

	private LoggerInterface $logger;

	public function __construct(
		private readonly Config $config,
		private readonly HttpRequestFactory $httpRequestFactory,
	) {
		$this->logger = LoggerFactory::getInstance( 'BlogPosts' );
	}

	/**
	 * Get posts from a remote WordPress API
	 *
	 * @param int $page
	 * @param int $limit
	 * @return array|false
	 */
	public function getPosts( int $page, int $limit ): array|false {
		$blogPostsConfig = $this->config->get( 'BlogPostsConfig' );

		if ( !$blogPostsConfig['blogURL'] ) {
			$this->logger->error( 'BlogPosts: blogURL is not configured' );
			return false;
		}

		$data = [
			'page' => $page ?: 1,
			'per_page' => $limit ?: $blogPostsConfig['postsPerPage']
		];

		$url = $blogPostsConfig['blogURL'] . '&_embed=wp:featuredmedia&' . http_build_query( $data );

		$response = $this->httpRequestFactory->get( $url, [], __METHOD__ );

		if ( $response === null ) {
			$this->logger->error( 'BlogPosts: HTTP request failed for URL {url}', [ 'url' => $url ] );
			return false;
		}

		$result = json_decode( $response, true );

		if ( $result === null ) {
			$this->logger->error( 'BlogPosts: Failed to decode JSON response from {url}', [ 'url' => $url ] );
			return false;
		}

		if ( !$result || array_key_exists( 'code', $result ) ) {
			$this->logger->warning(
				'BlogPosts: API returned error from {url}: {code}',
				[
					'url' => $url,
					'code' => $result['code'] ?? 'empty response',
				]
			);
			return false;
		}

		return array_map( static function ( $post ) {
			// Get the featured image with progressive fallback to larger sizes
			$img = self::getBestImageSize( $post, 200 );

			return [
				'image' => $img ? html_entity_decode( $img ) : '',
				'title' => html_entity_decode( $post['title']['rendered'] ),
				'url' => html_entity_decode( $post['link'] )
			];
		}, $result );
	}

	/**
	 * Get the best image size based on minimum height requirement
	 *
	 * @param array $post The post data
	 * @param int $minHeight Minimum height requirement (default 200px)
	 * @return string|null Image URL or null if no suitable image found
	 */
	private static function getBestImageSize( array $post, int $minHeight = 200 ): ?string {
		$featuredMedia = self::arrayGet( $post, '_embedded.wp:featuredmedia.0' );
		if ( !$featuredMedia ) {
			return null;
		}

		// Store source_url as potential fallback
		$sourceUrl = isset( $featuredMedia['source_url'] ) ? $featuredMedia['source_url'] : null;

		// Check if featured media exists
		$sizes = self::arrayGet( $post, '_embedded.wp:featuredmedia.0.media_details.sizes' );
		if ( !is_array( $sizes ) ) {
			return $sourceUrl;
		}

		// Define the size preference order for WordPress standard sizes
		$sizePreference = [
			// Usually 150x150
			'thumbnail',
			// Usually ~300px wide
			'medium',
			// Usually ~768px wide
			'medium_large',
			// Usually ~1024px wide
			'large',
			// Original size
			'full'
		];

		// First try: find the smallest size that meets the minimum height
		foreach ( $sizePreference as $sizeName ) {
			if ( isset( $sizes[$sizeName] ) &&
				isset( $sizes[$sizeName]['height'] ) &&
				$sizes[$sizeName]['height'] >= $minHeight &&
				isset( $sizes[$sizeName]['source_url'] ) ) {
				return $sizes[$sizeName]['source_url'];
			}
		}

		// Second try: if no size meets the minimum, get the largest available
		$largestSize = null;
		$largestHeight = 0;

		foreach ( $sizes as $size ) {
			if ( isset( $size['height'] ) &&
				isset( $size['source_url'] ) &&
				$size['height'] > $largestHeight ) {
				$largestHeight = $size['height'];
				$largestSize = $size['source_url'];
			}
		}

		// Fall back to the original image URL if no suitable size found
		return $largestSize ?: $sourceUrl;
	}

	/**
	 * @param array $array
	 * @param string|int|null $key
	 * @param mixed $default Default value to return if path not found
	 * @return mixed
	 */
	private static function arrayGet( $array, $key, $default = null ) {
		if ( $key === null || $key === '' ) {
			return $array;
		}

		$current = $array;

		foreach ( explode( '.', $key ) as $segment ) {
			if ( !is_array( $current ) || !array_key_exists( $segment, $current ) ) {
				return $default;
			}
			$current = $current[$segment];
		}

		return $current;
	}

}
