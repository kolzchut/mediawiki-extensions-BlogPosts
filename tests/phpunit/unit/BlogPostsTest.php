<?php

namespace Tests\Unit;

use MediaWikiUnitTestCase;

/**
 * @coversDefaultClass \BlogPosts
 */
class BlogPostsTest extends MediaWikiUnitTestCase {
	/**
	 * @covers ::GetPosts
	 * @return void
	 */
	public function testGetPosts() {
	}

	/**
	 * @covers ::CreateBlogPostsSection
	 * @return void
	 */
	public function testCreateBlogPostsSection() {
	}

	/**
	 * @covers ::getBestImageSize
	 * @dataProvider provideGetBestImageSizeData
	 *
	 * @param array $post The post data to test
	 * @param int $minHeight The minimum height requirement
	 * @param string|null $expected The expected image URL or null
	 * @throws \ReflectionException
	 */
	public function testGetBestImageSize( array $post, int $minHeight, ?string $expected ) {
		$result = $this->invokeGetBestImageSize( $post, $minHeight );
		$this->assertSame( $expected, $result );
	}

	/**
	 * Data provider for testGetBestImageSize
	 *
	 * @return array[]
	 */
	public function provideGetBestImageSizeData(): array {
		return [
			'select medium when it meets requirements' => [
				[
					'_embedded' => [
						'wp:featuredmedia' => [
							0 => [
								'media_details' => [
									'sizes' => [
										'thumbnail' => [
											'width' => 150,
											'height' => 150,
											'source_url' => 'https://example.com/thumb.jpg'
										],
										'medium' => [
											'width' => 300,
											'height' => 200,
											'source_url' => 'https://example.com/medium.jpg'
										],
										'large' => [
											'width' => 800,
											'height' => 600,
											'source_url' => 'https://example.com/large.jpg'
										]
									]
								]
							]
						]
					]
				],
				// min height
				200,
				'https://example.com/medium.jpg'
			],
			'select thumbnail when minimum height is small' => [
				[
					'_embedded' => [
						'wp:featuredmedia' => [
							0 => [
								'media_details' => [
									'sizes' => [
										'thumbnail' => [
											'width' => 150,
											'height' => 150,
											'source_url' => 'https://example.com/thumb.jpg'
										],
										'medium' => [
											'width' => 300,
											'height' => 200,
											'source_url' => 'https://example.com/medium.jpg'
										]
									]
								]
							]
						]
					]
				],
				// min height
				100,
				'https://example.com/thumb.jpg'
			],
			'select larger size when smaller ones don\'t meet requirement' => [
				[
					'_embedded' => [
						'wp:featuredmedia' => [
							0 => [
								'media_details' => [
									'sizes' => [
										'thumbnail' => [
											'width' => 150,
											'height' => 150,
											'source_url' => 'https://example.com/thumb.jpg'
										],
										'medium' => [
											'width' => 300,
											'height' => 200,
											'source_url' => 'https://example.com/medium.jpg'
										],
										'large' => [
											'width' => 800,
											'height' => 600,
											'source_url' => 'https://example.com/large.jpg'
										]
									]
								]
							]
						]
					]
				],
				// min height
				400,
				'https://example.com/large.jpg'
			],
			'return largest available when none meet requirement' => [
				[
					'_embedded' => [
						'wp:featuredmedia' => [
							0 => [
								'media_details' => [
									'sizes' => [
										'thumbnail' => [
											'width' => 150,
											'height' => 150,
											'source_url' => 'https://example.com/thumb.jpg'
										],
										'medium' => [
											'width' => 300,
											'height' => 200,
											'source_url' => 'https://example.com/medium.jpg'
										]
									]
								],
								'source_url' => 'https://example.com/original.jpg'
							]
						]
					]
				],
				// min height
				500,
				'https://example.com/medium.jpg'
			],
			'return original source when no sizes available' => [
				[
					'_embedded' => [
						'wp:featuredmedia' => [
							0 => [
								'source_url' => 'https://example.com/original.jpg',
								'media_details' => [
									// Empty sizes array rather than missing sizes key
									'sizes' => []
								]
							]
						]
					]
				],
				// min height
				200,
				'https://example.com/original.jpg'
			],
			'handle non-standard size names' => [
				[
					'_embedded' => [
						'wp:featuredmedia' => [
							0 => [
								'media_details' => [
									'sizes' => [
										'custom-small' => [
											'width' => 250,
											'height' => 180,
											'source_url' => 'https://example.com/custom-small.jpg'
										],
										'custom-large' => [
											'width' => 550,
											'height' => 400,
											'source_url' => 'https://example.com/custom-large.jpg'
										]
									]
								]
							]
						]
					]
				],
				// min height
				300,
				'https://example.com/custom-large.jpg'
			],
			'return null for missing featuredmedia' => [
				[
					'_embedded' => []
				],
				// min height
				200,
				null
			],
			'return null when sizes are empty' => [
				[
					'_embedded' => [
						'wp:featuredmedia' => [
							0 => [
								'media_details' => [
									'sizes' => []
								]
							]
						]
					]
				],
				// min height
				200,
				null
			],
			'return null when media_details is empty array' => [
				[
					'_embedded' => [
						'wp:featuredmedia' => [
							0 => [
								'media_details' => []
							]
						]
					]
				],
				// min height
				200,
				null
			],
			'return null when source_url exists but sizes key is missing' => [
				[
					'_embedded' => [
						'wp:featuredmedia' => [
							0 => [
								'source_url' => 'https://example.com/fallback.jpg',
								'media_details' => [
									// sizes key is missing
								]
							]
						]
					]
				],
				// min height
				200,
				'https://example.com/fallback.jpg'
			],
			'handle malformed size data gracefully' => [
				[
					'_embedded' => [
						'wp:featuredmedia' => [
							0 => [
								'media_details' => [
									'sizes' => [
										'thumbnail' => [
											// missing height
											'width' => 150,
											'source_url' => 'https://example.com/thumb.jpg'
										],
										'medium' => [
											'width' => 300,
											// missing source_url
											'height' => 200
										],
										'valid-size' => [
											'width' => 400,
											'height' => 300,
											'source_url' => 'https://example.com/valid.jpg'
										]
									]
								]
							]
						]
					]
				],
				// min height
				200,
				'https://example.com/valid.jpg'
			]
		];
	}

	/**
	 * @covers ::arrayGet
	 * @dataProvider provideArrayGetData
	 *
	 * @param array $array The input array to test
	 * @param string $path The dot-notation path to access
	 * @param mixed $default The default value to return
	 * @param mixed $expected The expected result
	 * @throws \ReflectionException
	 */
	public function testArrayGet( array $array, string $path, $default, $expected ) {
		$result = $this->invokeArrayGet( $array, $path, $default );
		$this->assertSame( $expected, $result );
	}

	/**
	 * Data provider for testArrayGet
	 *
	 * @return array[]
	 */
	public function provideArrayGetData(): array {
		return [
			'basic access' => [
				[ 'user' => [ 'name' => 'John' ] ],
				'user.name',
				null,
				'John'
			],
			'missing key returns default' => [
				[ 'user' => [ 'name' => 'John' ] ],
				'user.email',
				null,
				null
			],
			'custom default value' => [
				[ 'user' => [ 'name' => 'John' ] ],
				'user.age',
				25,
				25
			],
			'deeply nested path' => [
				[
					'post' => [
						'_embedded' => [
							'wp:featuredmedia' => [
								0 => [
									'media_details' => [
										'sizes' => [
											'large' => [
												'source_url' => 'https://example.com/image.jpg'
											]
										]
									]
								]
							]
						]
					]
				],
				'post._embedded.wp:featuredmedia.0.media_details.sizes.large.source_url',
				null,
				'https://example.com/image.jpg'
			],
			'missing intermediate key' => [
				[
					'post' => [
						'_embedded' => [
							'wp:featuredmedia' => [
								0 => [
									'media_details' => []
								]
							]
						]
					]
				],
				'post._embedded.wp:featuredmedia.0.media_details.sizes.large.source_url',
				null,
				null
			],
			'non-array intermediate value' => [
				[ 'user' => 'John' ],
				'user.name',
				null,
				null
			],
			'numeric keys in path' => [
				[ 'users' => [ [ 'name' => 'John' ], [ 'name' => 'Jane' ] ] ],
				'users.1.name',
				null,
				'Jane'
			],
			'empty path returns original array' => [
				[ 'name' => 'John' ],
				'',
				null,
				[ 'name' => 'John' ]
			],
			'null value in array' => [
				[ 'user' => [ 'name' => null ] ],
				'user.name',
				'default',
				null
			]
		];
	}

	/**
	 * Helper method to invoke the private static arrayGet method
	 *
	 * @param array $array
	 * @param string $path
	 * @param mixed $default
	 * @return mixed
	 * @throws \ReflectionException
	 */
	private function invokeArrayGet( array $array, string $path, $default = null ) {
		// Using reflection to access private static method
		$reflection = new \ReflectionClass( '\BlogPosts' );
		$method = $reflection->getMethod( 'arrayGet' );
		$method->setAccessible( true );

		return $method->invokeArgs( null, [ $array, $path, $default ] );
	}

	/**
	 * Helper method to invoke the private static getBestImageSize method
	 *
	 * @param array $post
	 * @param int $minHeight
	 * @return mixed
	 * @throws \ReflectionException
	 */
	private function invokeGetBestImageSize( array $post, int $minHeight ) {
		// Using reflection to access private static method
		$reflection = new \ReflectionClass( '\BlogPosts' );
		$method = $reflection->getMethod( 'getBestImageSize' );
		$method->setAccessible( true );

		return $method->invokeArgs( null, [ $post, $minHeight ] );
	}
}
