( function ( mw, $ ) {
	'use strict';

	var config = mw.config.get( 'wgBlogPostsConfig' ),
		template = mw.template.get( 'ext.BlogPosts', 'blog-post.mustache' ),
		api = new mw.Api();

	mw.blogposts = {
		currentPage: config.initialPage,
		getPosts: function ( page ) {
			return api.get( {
				action: 'get-posts',
				page: page
			} );
		},
		appendPosts: function ( posts ) {
			var rendered, postsWrap = $( '.blog-posts-wrap' );

			$.each( posts, function ( index, post ) {
				rendered = template.render( post );
				postsWrap.append( rendered );
			} );
		}
	};

	$( document ).ready( function () {
		var $btn = $( '.blog-posts-more-btn' ),
			$msg = $( '.blog-posts-more-loading-text' );

		$btn.click( function ( event ) {
			var nextPage = mw.blogposts.currentPage + 1;
			event.preventDefault();
			$btn.toggle();
			$msg.toggle();

			mw.blogposts.getPosts( nextPage )
				.then( function ( response, error ) {
					if ( !response.posts.success ) {
						$btn.toggle();
						$msg.toggle();
						return;
					}

					mw.blogposts.currentPage = nextPage;

					mw.blogposts.appendPosts( response.posts.data );
					$btn.toggle();
					$msg.toggle();
				} );
		} );
	} );

}( mediaWiki, jQuery ) );
