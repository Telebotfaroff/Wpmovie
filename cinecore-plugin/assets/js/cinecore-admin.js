/**
 * CineCore TMDB Admin JS
 *
 * Handles AJAX fetch for TMDB data on movies/tv_shows edit screens.
 *
 * @package Nexcine\CineCore
 */

(function ( $ ) {
	'use strict';

	$( function () {
		var $button = $( '#cinecore-fetch-tmdb' );
		var $input  = $( '#cinecore_tmdb_id' );
		var $status = $( '#cinecore-tmdb-status' );

		if ( ! $button.length ) {
			return;
		}

		$button.on( 'click', function ( e ) {
			e.preventDefault();

			var tmdbId   = $input.val().trim();
			var postType = $( '#post_type' ).val() || cinecoreTmdb.postType || 'movies';
			var postId   = $( '#post_ID' ).val() || cinecoreTmdb.postId || 0;

			if ( '' === tmdbId ) {
				$status.html( '<span style="color:#d63638;">' + cinecoreTmdb.i18n.enterId + '</span>' );
				return;
			}

			// Loading state.
			$button.prop( 'disabled', true ).text( cinecoreTmdb.i18n.fetching );
			$status.html( '<span class="spinner is-active" style="float:none;margin:0;visibility:visible;"></span> ' + cinecoreTmdb.i18n.loading );

			$.post(
				cinecoreTmdb.ajaxUrl,
				{
					action: 'cinecore_fetch_tmdb',
					nonce: cinecoreTmdb.nonce,
					tmdb_id: tmdbId,
					post_type: postType,
					post_id: postId
				},
				function ( response ) {
					$button.prop( 'disabled', false ).text( cinecoreTmdb.i18n.fetchButton );

					if ( response.success ) {
						var data = response.data;

						// 1. Populate Title field (Classic + Gutenberg compatibility).
						var $title = $( '#title' );
						if ( $title.length ) {
							$title.val( data.title ).trigger( 'input' ).trigger( 'change' );
						}
						// Gutenberg title via WP data if available.
						if ( window.wp && window.wp.data && window.wp.data.dispatch ) {
							try {
								window.wp.data.dispatch( 'core/editor' ).editPost( { title: data.title } );
							} catch ( err ) {}
						}

						// 2. Populate Editor/Content field.
						var overview = data.overview || '';
						if ( typeof tinymce !== 'undefined' && tinymce.get( 'content' ) && ! tinymce.get( 'content' ).isHidden() ) {
							tinymce.get( 'content' ).setContent( overview );
						}
						if ( $( '#content' ).length ) {
							$( '#content' ).val( overview );
						}
						// Gutenberg content.
						if ( window.wp && window.wp.data && window.wp.data.dispatch ) {
							try {
								window.wp.data.dispatch( 'core/editor' ).editPost( { content: overview } );
							} catch ( err ) {}
						}

						$status.html( '<span style="color:#00a32a;">' + cinecoreTmdb.i18n.success + '</span>' );
					} else {
						var msg = response.data ? response.data : cinecoreTmdb.i18n.error;
						$status.html( '<span style="color:#d63638;">' + msg + '</span>' );
					}
				}
			).fail( function ( xhr ) {
				$button.prop( 'disabled', false ).text( cinecoreTmdb.i18n.fetchButton );
				var msg = cinecoreTmdb.i18n.error;
				if ( xhr.responseJSON && xhr.responseJSON.data ) {
					msg = xhr.responseJSON.data;
				}
				$status.html( '<span style="color:#d63638;">' + msg + '</span>' );
			} );
		} );
	} );
})( jQuery );
