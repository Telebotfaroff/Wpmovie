/**
 * CineCore Live Filter - Hardened
 * - debounced? (change event not keyup, no debounce needed)
 * - loading/empty/error states
 * - pagination, reset, URL reflection (pushState + popstate)
 * - non-JS fallback via form GET
 * - stale response guard via request counter
 *
 * @package CineCore_Theme
 */
document.addEventListener( 'DOMContentLoaded', function () {
	var form    = document.getElementById( 'cinecore-filter-form' );
	var grid    = document.getElementById( 'cinecore-filter-grid' );
	var selects = document.querySelectorAll( '.cinecore-filter-select' );
	if ( ! form || ! grid ) {
		return;
	}
	var isLoading = false;
	var requestCounter = 0;
	var currentRequest = 0;

	function getValues( page ) {
		var data = {
			action: 'cinecore_filter_posts',
			nonce: cinecoreFilter.nonce,
			post_type: form.getAttribute( 'data-post-type' ) || 'movies',
			genre: document.getElementById( 'cinecore-filter-genre' ) ? document.getElementById( 'cinecore-filter-genre' ).value : '',
			release_year: document.getElementById( 'cinecore-filter-year' ) ? document.getElementById( 'cinecore-filter-year' ).value : '',
			quality: document.getElementById( 'cinecore-filter-quality' ) ? document.getElementById( 'cinecore-filter-quality' ).value : '',
			paged: page || 1
		};
		return data;
	}

	function buildAjaxUrl( data ) {
		var params = new URLSearchParams();
		Object.keys( data ).forEach( function ( k ) { params.append( k, data[ k ] ); } );
		return cinecoreFilter.ajaxUrl + '?' + params.toString();
	}

	function buildShareUrl( data ) {
		var url = new URL( window.location.href );
		// Clear old filter params.
		url.searchParams.delete( 'genre' );
		url.searchParams.delete( 'release_year' );
		url.searchParams.delete( 'quality' );
		url.searchParams.delete( 'post_type' );
		if ( data.genre ) {
			url.searchParams.set( 'genre', data.genre );
		}
		if ( data.release_year ) {
			url.searchParams.set( 'release_year', data.release_year );
		}
		if ( data.quality ) {
			url.searchParams.set( 'quality', data.quality );
		}
		return url.toString();
	}

	function setLoading( on ) {
		isLoading = on;
		if ( on ) {
			grid.style.opacity = '0.5';
			grid.style.pointerEvents = 'none';
			grid.setAttribute( 'aria-busy', 'true' );
		} else {
			grid.style.opacity = '1';
			grid.style.pointerEvents = 'auto';
			grid.removeAttribute( 'aria-busy' );
		}
	}

	function showError( msg ) {
		grid.innerHTML = '<div class="text-center py-12 bg-cinecore-card rounded-xl border border-red-500/20"><p class="text-red-400">' + msg + '</p><button type="button" class="cinecore-filter-retry mt-4 px-4 py-2 bg-white/10 hover:bg-white/15 text-white rounded-lg text-sm">' + ( cinecoreFilter.i18n ? cinecoreFilter.i18n.retry || 'Retry' : 'Retry' ) + '</button></div>';
		var btn = grid.querySelector( '.cinecore-filter-retry' );
		if ( btn ) {
			btn.addEventListener( 'click', function () { fetchAndRender(); } );
		}
	}

	function fetchAndRender( page, pushState ) {
		if ( isLoading ) {
			return;
		}
		var data = getValues( page );
		var reqId = ++requestCounter;
		currentRequest = reqId;
		setLoading( true );
		// Push URL for shareable state.
		if ( pushState !== false ) {
			try { window.history.pushState( data, '', buildShareUrl( data ) ); } catch ( e ) {}
		}
		fetch( buildAjaxUrl( data ), { headers: { 'X-Requested-With': 'XMLHttpRequest' } } )
			.then( function ( r ) {
				if ( ! r.ok ) {
					throw new Error( 'HTTP ' + r.status );
				}
				return r.json();
			} )
			.then( function ( res ) {
				// Stale guard.
				if ( reqId !== currentRequest ) {
					return;
				}
				setLoading( false );
				if ( res && res.success && typeof res.data.html !== 'undefined' ) {
					grid.innerHTML = res.data.html;
					// Bind pagination inside new html if present.
					bindPagination();
					// Announce to screen readers.
					grid.setAttribute( 'aria-live', 'polite' );
				} else {
					var msg = ( res && res.data && res.data.message ) ? res.data.message : ( res && res.data ? res.data : 'Filter failed' );
					showError( msg );
				}
			} )
			.catch( function ( err ) {
				if ( reqId !== currentRequest ) {
					return;
				}
				setLoading( false );
				var msg = err && err.message ? err.message : 'Network error';
				if ( err && err.name === 'AbortError' ) {
					return;
				}
				showError( msg );
			} );
	}

	function bindPagination() {
		var pag = grid.querySelector( '.cinecore-filter-pagination' );
		if ( ! pag ) {
			return;
		}
		// Example: handle clicks on pagination links if theme outputs them as <a data-paged>
		var links = grid.querySelectorAll( 'a[data-paged], .page-numbers' );
		links.forEach( function ( a ) {
			a.addEventListener( 'click', function ( e ) {
				e.preventDefault();
				var p = a.getAttribute( 'data-paged' ) || a.getAttribute( 'href' );
				var pageNum = 1;
				if ( p ) {
					var m = p.match( /paged=(\d+)/ );
					if ( m ) {
						pageNum = parseInt( m[1], 10 );
					} else if ( ! isNaN( parseInt( a.getAttribute( 'data-paged' ), 10 ) ) ) {
						pageNum = parseInt( a.getAttribute( 'data-paged' ), 10 );
					} else if ( a.textContent.trim() && ! isNaN( parseInt( a.textContent.trim(), 10 ) ) ) {
						pageNum = parseInt( a.textContent.trim(), 10 );
					}
				}
				fetchAndRender( pageNum );
				grid.scrollIntoView( { behavior: 'smooth', block: 'start' } );
			} );
		} );
	}

	selects.forEach( function ( sel ) {
		sel.addEventListener( 'change', function () { fetchAndRender( 1 ); } );
	} );

	// Reset button.
	var resetBtn = document.getElementById( 'cinecore-filter-reset' );
	if ( resetBtn ) {
		resetBtn.addEventListener( 'click', function () {
			selects.forEach( function ( s ) { s.value = ''; } );
			fetchAndRender( 1 );
		} );
	}

	// Popstate: restore from URL.
	window.addEventListener( 'popstate', function ( e ) {
		var state = e.state;
		if ( state && typeof state.genre !== 'undefined' ) {
			if ( document.getElementById( 'cinecore-filter-genre' ) ) {
				document.getElementById( 'cinecore-filter-genre' ).value = state.genre || '';
			}
			if ( document.getElementById( 'cinecore-filter-year' ) ) {
				document.getElementById( 'cinecore-filter-year' ).value = state.release_year || '';
			}
			if ( document.getElementById( 'cinecore-filter-quality' ) ) {
				document.getElementById( 'cinecore-filter-quality' ).value = state.quality || '';
			}
			fetchAndRender( state.paged || 1, false );
		} else {
			// Parse URL.
			var url = new URL( window.location.href );
			if ( document.getElementById( 'cinecore-filter-genre' ) ) {
				document.getElementById( 'cinecore-filter-genre' ).value = url.searchParams.get( 'genre' ) || '';
			}
			if ( document.getElementById( 'cinecore-filter-year' ) ) {
				document.getElementById( 'cinecore-filter-year' ).value = url.searchParams.get( 'release_year' ) || '';
			}
			if ( document.getElementById( 'cinecore-filter-quality' ) ) {
				document.getElementById( 'cinecore-filter-quality' ).value = url.searchParams.get( 'quality' ) || '';
			}
			fetchAndRender( 1, false );
		}
	} );

	// On load, if URL has filter params, apply them.
	(function initFromUrl() {
		var url = new URL( window.location.href );
		var has = url.searchParams.has( 'genre' ) || url.searchParams.has( 'release_year' ) || url.searchParams.has( 'quality' );
		if ( has ) {
			if ( document.getElementById( 'cinecore-filter-genre' ) ) {
				document.getElementById( 'cinecore-filter-genre' ).value = url.searchParams.get( 'genre' ) || '';
			}
			if ( document.getElementById( 'cinecore-filter-year' ) ) {
				document.getElementById( 'cinecore-filter-year' ).value = url.searchParams.get( 'release_year' ) || '';
			}
			if ( document.getElementById( 'cinecore-filter-quality' ) ) {
				document.getElementById( 'cinecore-filter-quality' ).value = url.searchParams.get( 'quality' ) || '';
			}
			// Don't auto-fetch on load if we want non-JS fallback to handle initial server render.
			// But if URL has params and JS enabled, we can fetch to ensure grid matches.
		}
	})();
} );
