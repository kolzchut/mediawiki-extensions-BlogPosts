<?php

namespace MediaWiki\Extension\BlogPosts;

use ApiBase;
use ApiMain;
use MediaWiki\Config\Config;
use MediaWiki\Http\HttpRequestFactory;
use Wikimedia\ParamValidator\ParamValidator;

class GetPostsAPI extends ApiBase {

	private Config $config;
	private HttpRequestFactory $httpRequestFactory;

	/**
	 * @param ApiMain $main
	 * @param string $moduleName
	 * @param Config $config
	 * @param HttpRequestFactory $httpRequestFactory
	 */
	public function __construct(
		ApiMain $main,
		string $moduleName,
		Config $config,
		HttpRequestFactory $httpRequestFactory
	) {
		parent::__construct( $main, $moduleName );
		$this->config = $config;
		$this->httpRequestFactory = $httpRequestFactory;
	}

	/**
	 * @return array
	 */
	protected function getAllowedParams(): array {
		return [
			'page' => [
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true
			]
		];
	}

	public function execute(): void {
		$queryResult = $this->getResult();
		$params = $this->extractRequestParams();

		$blogPostsConfig = $this->config->get( 'BlogPostsConfig' );
		$postsPerPage = $blogPostsConfig['postsPerPage'];

		$blogPosts = new BlogPosts( $this->config, $this->httpRequestFactory );
		$result = $blogPosts->getPosts( $params['page'], $postsPerPage );

		if ( $result ) {
			$output = [
				'success' => 1,
				'data' => $result
			];
		} else {
			$output = [ 'success' => 0 ];
		}

		$queryResult->addValue( null, 'posts', $output );
	}

}
