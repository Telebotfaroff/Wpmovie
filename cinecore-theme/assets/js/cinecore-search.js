/**
 * CineCore Live Search - Vanilla JS
 *
 * Debounced keyup -> AJAX -> dropdown
 *
 * @package CineCore_Theme
 */
document.addEventListener( 'DOMContentLoaded', function () {
	var input   = document.getElementById( 'cinecore-search-input' );
	var results = document.getElementById( 'cinecore-search-results' );
	var list    = document.getElementById( 'cinecore-search-list' );
	var loader  = document.getElementById( 'cinecore-search-loader' );
	var empty   = document.getElementById( 'cinecore-search-empty' );

	if ( ! input || ! results || ! list ) {
		return;
	}

	var debounceTimer = null;
	var controller    = null;

	function showResults() {
		results.classList.remove( 'hidden' );
	}

	function hideResults() {
		results.classList.add( 'hidden' );
	}

	function escapeHtml( str ) {
		var div = document.createElement( 'div' );
		div.appendChild( document.createTextNode( str ) );
		return div.innerHTML;
	}

	function renderItems( items ) {
		list.innerHTML = '';
		if ( ! items || ! items.length ) {
			if ( empty ) {
				empty.classList.remove( 'hidden' );
				empty.textContent = cinecoreSearch.i18n.noResults;
			}
			return;
		}
		if ( empty ) {
			empty.classList.add( 'hidden' );
		}
		items.forEach( function ( item ) {
			var li = document.createElement( 'li' );

			var poster = item.poster
				? '<img src="' + encodeURI( item.poster ) + '" alt="' + escapeHtml( item.title ) + '" class="w-10 h-14 object-cover rounded-md bg-cinecore-surface flex-shrink-0" loading="lazy">'
				: '<div class="w-10 h-14 rounded-md bg-cinecore-surface border border-white/5 flex items-center justify-center flex-shrink-0"><span class="text-[10px] text-gray-500">No Img</span></div>';

			var typeLabel = item.type === 'tv_shows' ? 'TV' : 'Movie';
			var typeClass = item.type === 'tv_shows' ? 'bg-blue-600' : 'bg-cinecore-accent';

			li.innerHTML =
				'<a href="' + encodeURI( item.permalink ) + '" class="flex items-center gap-3 p-3 hover:bg-white/5 rounded-lg transition-colors no-underline">' +
				poster +
				'<span class="flex-1 min-w-0">' +
				'<span class="block text-sm font-medium text-white truncate">' + escapeHtml( item.title ) + '</span>' +
				'<span class="inline-flex items-center gap-1 mt-1"><span class="' + typeClass + ' text-white text-[10px] font-bold px-1.5 py-0.5 rounded">' + escapeHtml( typeLabel ) + '</span></span>' +
				'</span>' +
				'</a>';
			list.appendChild( li );
		} );
	}

	function fetchResults( query ) {
		if ( controller && controller.abort ) {
			controller.abort();
		}
		controller = new AbortController();

		if ( loader ) {
			loader.classList.remove( 'hidden' );
		}
		if ( empty ) {
			empty.classList.add( 'hidden' );
		}
		showResults();

		var url =
			cinecoreSearch.ajaxUrl +
			'?action=cinecore_live_search&q=' +
			encodeURIComponent( query );

		// Append nonce if localized.
		if ( cinecoreSearch.nonce ) {
			url += '&nonce=' + encodeURIComponent( cinecoreSearch.nonce );
		}

		fetch( url, { signal: controller.signal } )
			.then( function ( r ) {
				return r.json();
			} )
			.then( function ( res ) {
				if ( loader ) {
					loader.classList.add( 'hidden' );
				}
				if ( res && res.success ) {
					renderItems( res.data );
				} else {
					renderItems( [] );
				}
			} )
			.catch( function ( err ) {
				if ( err && err.name === 'AbortError' ) {
					return;
				}
				if ( loader ) {
					loader.classList.add( 'hidden' );
				}
				renderItems( [] );
			} );
	}

	input.addEventListener( 'keyup', function () {
		var q = input.value.trim();

		if ( debounceTimer ) {
			clearTimeout( debounceTimer );
		}

		if ( q.length < 2 ) {
			hideResults();
			list.innerHTML = '';
			if ( loader ) {
				loader.classList.add( 'hidden' );
			}
			return;
		}

		debounceTimer = setTimeout( function () {
			fetchResults( q );
		}, 300 );
	} );

	// Hide on click outside.
	document.addEventListener( 'click', function ( e ) {
		if ( ! e.target.closest( '#cinecore-search-wrap' ) ) {
			hideResults();
		}
	} );

	// Show on focus if has value.
	input.addEventListener( 'focus', function () {
		if ( input.value.trim().length >= 2 && list.children.length ) {
			showResults();
		}
	} );
} );
