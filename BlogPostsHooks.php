<?php

class BlogPostsHooks {

	static public function onParserFirstCallInit( Parser &$parser ) {
		$parser->setHook( 'blogposts', 'BlogPosts::createBlogPostsSection' );
	}

	/**
	 * Hook: ResourceLoaderGetConfigVars called right before
	 * ResourceLoaderStartUpModule::getConfig returns
	 *
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/ResourceLoaderGetConfigVars
	 *
	 * @param &$vars array of variables to be added into the output of the startup module.
	 *
	 * @return true
	 */
	public static function onResourceLoaderGetConfigVars( &$vars ) {
		global $wgBlogPostsConfig;
		$vars['wgBlogPostsConfig'] = $wgBlogPostsConfig;

		return true;
	}

}
