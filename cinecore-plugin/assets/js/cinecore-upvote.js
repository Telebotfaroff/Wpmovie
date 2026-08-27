/**
 * CineCore Upvote - Vanilla JS
 *
 * @package CineCore
 */
document.addEventListener( 'DOMContentLoaded', function () {
	var buttons = document.querySelectorAll( '.cinecore-upvote-btn' );
	if ( ! buttons.length || typeof cinecoreUpvote === 'undefined' ) {
		return;
	}

	buttons.forEach( function ( btn ) {
		btn.addEventListener( 'click', function ( e ) {
			e.preventDefault();
			if ( btn.disabled ) {
				return;
			}
			var postId = btn.getAttribute( 'data-post-id' );
			if ( ! postId ) {
				return;
			}
			var countEl = document.getElementById( 'cinecore-upvote-count-' + postId );
			var originalText = btn.textContent;
			btn.disabled = true;
			btn.textContent = '...';

			var body = new URLSearchParams({
				action: 'cinecore_upvote_subtitle',
				nonce: cinecoreUpvote.nonce,
				post_id: postId
			});

			fetch( cinecoreUpvote.ajaxUrl, {
				method: 'POST',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
				body: body.toString()
			})
			.then( function ( r ) { return r.json(); } )
			.then( function ( res ) {
				if ( res && res.success ) {
					if ( countEl ) {
						countEl.textContent = res.data.count;
					}
					btn.textContent = res.data.message || 'Upvoted!';
					btn.classList.add( 'bg-green-600', 'text-white' );
				} else {
					btn.textContent = ( res && res.data ) ? res.data : cinecoreUpvote.i18n.voted;
					setTimeout( function () { btn.textContent = originalText; btn.disabled = false; }, 2000 );
				}
			})
			.catch( function () {
				btn.textContent = cinecoreUpvote.i18n.error;
				setTimeout( function () { btn.textContent = originalText; btn.disabled = false; }, 2000 );
			});
		});
	});
});
