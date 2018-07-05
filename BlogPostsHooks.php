<?php

class BlogPostsHooks {

	public static function onBeforePageDisplay( OutputPage &$out, Skin &$skin ) {
		BlogPosts::getPosts(1, 4);
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
