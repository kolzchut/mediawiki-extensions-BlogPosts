<?php

class GetPostsAPI extends ApiBase {

	public function __construct( $main, $moduleName ) {
		parent::__construct( $main, $moduleName );
	}

	protected function getAllowedParams() {
		return [
			'page' => [
				ApiBase::PARAM_TYPE => 'integer',
				ApiBase::PARAM_REQUIRED => true
			]
		];
	}

	public function execute() {
		global $wgBlogPostsConfig;

		$queryResult = $this->getResult();
		$params      = $this->extractRequestParams();

		$postsPerPage = $wgBlogPostsConfig['postsPerPage'];

		$result = BlogPosts::getPosts( $params['page'], $postsPerPage );

		if ( $result ) {
			$output = [
				'success' => (int)true,
				'data' => $result
			];
		} else {
			$output = [ 'success' => (int)false ];
		}

		$queryResult->addValue( null, 'posts', $output );
	}

}