( function ( mw, $ ) {
	'use strict';

	var config = mw.config.get( 'wgBlogPostsConfig' ),
		template = mw.template.get( 'ext.BlogPosts', 'blog-post.mustache' );

	mw.blogposts = {
		currentPage: config.initialPage,
		getPosts: function ( page ) {
			return $.ajax( {
				method: 'GET',
				url: mw.config.get( 'wgServer' ) + mw.config.get( 'wgScriptPath' ) + '/api.php',
				data: {
					action: 'get-posts',
					page: page,
					format: 'json'
				}
			} );
		},
		appendPosts: function ( posts ) {
			var rendered, postsWrap = $( '.blog-posts-wrap' );
			postsWrap.empty();

			$.each( posts, function ( index, post ) {
				rendered = template.render( post );
				postsWrap.append( rendered );
			} );
		}
	};

	$( document ).ready( function () {

		$( '.blog-posts-more' ).click( function ( event ) {
			var nextPage = mw.blogposts.currentPage + 1;
			event.preventDefault();
			$( this ).attr( 'disabled', true );

			mw.blogposts.getPosts( nextPage )
				.then( function ( response, error ) {
					if ( !response.posts.success ) {
						$( this ).attr( 'disabled', false );
						return;
					}

					mw.blogposts.currentPage = nextPage;

					mw.blogposts.appendPosts( response.posts.data );
					$( this ).attr( 'disabled', false );
				}.bind( this ) );
		} );
	} );

}( mediaWiki, jQuery ) );
