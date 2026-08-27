/**
 * CineCore Smart Player - Hardened
 * 1. Auto-Resume (only legitimate postMessage, no fake page-time)
 * 2. Ad-Block Detector (conservative, non-blocking by default)
 *
 * @package CineCore_Theme
 */
document.addEventListener( 'DOMContentLoaded', function () {

	/* -------------------------------------------------
	 * 1. Auto-Resume via localStorage
	 * Only saves when player explicitly reports time via postMessage.
	 * Validates origin, throttles writes, handles corrupted data.
	 * ------------------------------------------------- */
	(function () {
		var player = document.getElementById( 'cinecore-player' );
		if ( ! player ) {
			return;
		}
		// Use post ID as key, fallback to pathname.
		var rawId = player.getAttribute( 'data-post-id' ) || document.body.getAttribute( 'data-post-id' ) || window.location.pathname;
		var safeId = String( rawId ).replace( /[^a-zA-Z0-9_-]/g, '_' ).slice( 0, 64 );
		var storageKey = 'cinecore_resume_' + safeId;
		var hasPrompted = false;
		var lastSaved = 0;
		var trustedOrigins = [
			'https://www.youtube.com',
			'https://youtube.com',
			'https://player.vimeo.com',
			'https://www.youtube-nocookie.com',
			window.location.origin
		];

		function formatTime( s ) {
			s = parseInt( s, 10 );
			if ( isNaN( s ) || s <= 0 ) {
				return '0:00';
			}
			var h = Math.floor( s / 3600 );
			var m = Math.floor( ( s % 3600 ) / 60 );
			var sec = s % 60;
			if ( h > 0 ) {
				return h + ':' + String( m ).padStart( 2, '0' ) + ':' + String( sec ).padStart( 2, '0' );
			}
			return m + ':' + String( sec ).padStart( 2, '0' );
		}

		function safeGet() {
			try {
				var v = localStorage.getItem( storageKey );
				if ( null === v ) {
					return 0;
				}
				var n = parseInt( v, 10 );
				if ( isNaN( n ) || n < 0 || n > 18000 ) {
					localStorage.removeItem( storageKey );
					return 0;
				}
				return n;
			} catch ( e ) {
				return 0;
			}
		}

		function safeSet( t ) {
			try {
				t = parseInt( t, 10 );
				if ( isNaN( t ) || t <= 0 || t > 18000 ) {
					return;
				}
				// Throttle: only write if >5s diff and >5s since last write.
				if ( Math.abs( t - lastSaved ) < 5 ) {
					return;
				}
				// Avoid excessive writes (max 1 per 5s).
				if ( Date.now() - (safeSet._last || 0) < 5000 ) {
					return;
				}
				localStorage.setItem( storageKey, String( t ) );
				lastSaved = t;
				safeSet._last = Date.now();
			} catch ( e ) {}
		}

		function showResumePrompt( seconds ) {
			if ( hasPrompted || ! seconds || seconds < 30 ) {
				return;
			}
			hasPrompted = true;
			var bar = document.createElement( 'div' );
			bar.id = 'cinecore-resume-bar';
			bar.setAttribute( 'role', 'dialog' );
			bar.setAttribute( 'aria-live', 'polite' );
			bar.setAttribute( 'aria-label', 'Resume playback' );
			bar.className = 'fixed bottom-6 left-1/2 -translate-x-1/2 z-50 bg-cinecore-card border border-white/10 rounded-full shadow-2xl px-5 py-3 flex items-center gap-4';
			bar.innerHTML =
				'<span class="text-sm text-white font-medium">Resume from ' + formatTime( seconds ) + '?</span>' +
				'<button type="button" id="cinecore-resume-yes" class="bg-cinecore-accent hover:bg-red-700 text-white text-sm font-semibold px-4 py-1.5 rounded-full transition-colors focus:outline-none focus:ring-2 focus:ring-white">Resume</button>' +
				'<button type="button" id="cinecore-resume-no" class="bg-white/10 hover:bg-white/15 text-white text-sm px-3 py-1.5 rounded-full focus:outline-none focus:ring-2 focus:ring-white">Dismiss</button>';
			document.body.appendChild( bar );
			document.getElementById( 'cinecore-resume-yes' ).addEventListener( 'click', function () {
				try {
					// Validate origin before postMessage.
					var targetOrigin = '*';
					// Try to derive origin from iframe src.
					var src = player.getAttribute( 'src' ) || '';
					if ( src ) {
						try { targetOrigin = new URL( src, window.location.origin ).origin; } catch ( e ) { targetOrigin = '*'; }
					}
					player.contentWindow.postMessage( { type: 'cinecoreSeek', time: parseInt( seconds, 10 ) }, targetOrigin );
				} catch ( e ) {}
				try {
					var src2 = player.getAttribute( 'src' ) || '';
					if ( src2 && ! src2.includes( 'start=' ) && ! src2.includes( '#t=' ) ) {
						var sep = src2.includes( '?' ) ? '&' : '?';
						// Only append start for YouTube-like players that support it; otherwise rely on postMessage.
						if ( /youtube\.com|youtu\.be|vimeo\.com/.test( src2 ) ) {
							player.setAttribute( 'src', src2 + sep + 'start=' + parseInt( seconds, 10 ) );
						}
					}
				} catch ( e ) {}
				bar.remove();
			});
			document.getElementById( 'cinecore-resume-no' ).addEventListener( 'click', function () {
				try { localStorage.removeItem( storageKey ); } catch ( e ) {}
				bar.remove();
			});
			// Escape key dismiss.
			document.addEventListener( 'keydown', function escHandler( ev ) {
				if ( ev.key === 'Escape' && document.getElementById( 'cinecore-resume-bar' ) ) {
					try { localStorage.removeItem( storageKey ); } catch ( e ) {}
					bar.remove();
					document.removeEventListener( 'keydown', escHandler );
				}
			} );
		}

		var resumeTime = safeGet();
		if ( resumeTime ) {
			showResumePrompt( resumeTime );
		}

		// Listen for legitimate time updates - validate origin.
		window.addEventListener( 'message', function ( e ) {
			// Validate origin is trusted or same-origin.
			var originOk = false;
			try {
				if ( trustedOrigins.indexOf( e.origin ) !== -1 ) {
					originOk = true;
				} else if ( e.origin === 'null' ) {
					originOk = false;
				} else {
					// Allow same-origin iframe.
					if ( e.source === player.contentWindow ) {
						originOk = true;
					}
				}
			} catch ( err ) { originOk = false; }
			if ( ! originOk ) {
				return;
			}
			if ( ! e.data || typeof e.data.time === 'undefined' ) {
				// Also handle ended event.
				if ( e.data && e.data.type === 'ended' ) {
					try { localStorage.removeItem( storageKey ); } catch ( err ) {}
				}
				return;
			}
			var t = parseInt( e.data.time, 10 );
			if ( isNaN( t ) || t <= 0 || t > 18000 ) {
				return;
			}
			// Only save if player is visible and not hidden.
			if ( document.hidden ) {
				return;
			}
			safeSet( t );
		} );
		// No fallback interval that fakes page-open time - fail gracefully if no postMessage support.
	})();

	/* -------------------------------------------------
	 * 2. Ad-Block Detector - Conservative
	 * Does NOT block downloads on network failure. Prefers allowing access.
	 * ------------------------------------------------- */
	(function () {
		var downloadWrap = document.querySelector( '.cinecore-downloads-wrap' );
		if ( ! downloadWrap ) {
			return;
		}
		var isOnline = navigator.onLine !== false;
		if ( ! isOnline ) {
			// Don't show modal when offline - would be false positive.
			return;
		}
		var hasShown = false;
		var baitDone = false;

		function showAdBlockModal() {
			if ( hasShown || document.getElementById( 'cinecore-adblock-modal' ) ) {
				return;
			}
			hasShown = true;
			var overlay = document.createElement( 'div' );
			overlay.id = 'cinecore-adblock-modal';
			overlay.setAttribute( 'role', 'dialog' );
			overlay.setAttribute( 'aria-modal', 'true' );
			overlay.setAttribute( 'aria-label', 'Ad-blocker notice' );
			overlay.className = 'fixed inset-0 z-[100] flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm';
			overlay.innerHTML =
				'<div class="bg-cinecore-card border border-white/10 rounded-2xl max-w-md w-full p-8 text-center shadow-2xl">' +
				'<div class="w-14 h-14 mx-auto rounded-full bg-yellow-500/15 border border-yellow-500/30 flex items-center justify-center mb-4">' +
				'<svg class="w-7 h-7 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"/></svg>' +
				'</div>' +
				'<h3 class="text-xl font-bold text-white">Ad-Blocker Detected</h3>' +
				'<p class="text-sm text-gray-400 mt-2 leading-relaxed">Please consider disabling your ad-blocker to support us. Your downloads will still work.</p>' +
				'<div class="flex flex-col sm:flex-row gap-3 justify-center mt-6">' +
				'<button type="button" id="cinecore-adblock-recheck" class="bg-cinecore-accent hover:bg-red-700 text-white font-semibold px-6 py-2.5 rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-white">Recheck</button>' +
				'<button type="button" id="cinecore-adblock-close" class="bg-white/10 hover:bg-white/15 text-white font-medium px-6 py-2.5 rounded-lg focus:outline-none focus:ring-2 focus:ring-white">Continue Anyway</button>' +
				'</div>' +
				'</div>';
			document.body.appendChild( overlay );
			// Do NOT block pointer-events by default - conservative: allow access, just notify.
			// Previously we blocked, now we prefer allowing.
			var closeBtn = document.getElementById( 'cinecore-adblock-close' );
			var recheckBtn = document.getElementById( 'cinecore-adblock-recheck' );
			var focusTrap = function ( ev ) {
				if ( ev.key === 'Escape' ) {
					overlay.remove();
					document.removeEventListener( 'keydown', focusTrap );
				}
			};
			document.addEventListener( 'keydown', focusTrap );
			closeBtn.addEventListener( 'click', function () {
				overlay.remove();
				document.removeEventListener( 'keydown', focusTrap );
			} );
			recheckBtn.addEventListener( 'click', function () {
				overlay.remove();
				document.removeEventListener( 'keydown', focusTrap );
				window.location.reload();
			} );
			// Focus first button for accessibility.
			recheckBtn.focus();
		}

		// Conservative check: only show if BOTH script blocked AND bait hidden, and not offline.
		var scriptBlocked = false;
		var baitBlocked = false;
		var script = document.createElement( 'script' );
		script.src = ( typeof cinecorePlayer !== 'undefined' && cinecorePlayer.adsTestUrl ) ? cinecorePlayer.adsTestUrl : '/ads.js';
		script.async = true;
		var scriptTimer = setTimeout( function () {
			// If script hasn't loaded/errored within 2s, treat as not blocked (network slow).
			script.remove();
			checkBait();
		}, 2000 );
		script.onerror = function () {
			clearTimeout( scriptTimer );
			scriptBlocked = true;
			checkBait();
		};
		script.onload = function () {
			clearTimeout( scriptTimer );
			// If script loaded but didn't set expected global, still check bait - but don't immediately block.
			if ( typeof window.cinecoreAdsLoaded === 'undefined' ) {
				checkBait();
			} else {
				script.remove();
			}
		};
		document.head.appendChild( script );

		function checkBait() {
			if ( baitDone ) {
				return;
			}
			baitDone = true;
			var bait = document.createElement( 'div' );
			bait.className = 'adsbox ad-banner pub_300x250';
			bait.style.cssText = 'width:1px;height:1px;position:absolute;left:-999px;top:-999px;';
			bait.innerHTML = '&nbsp;';
			document.body.appendChild( bait );
			setTimeout( function () {
				var style = window.getComputedStyle( bait );
				if ( bait.offsetHeight === 0 || bait.offsetParent === null || style.display === 'none' || style.visibility === 'hidden' ) {
					baitBlocked = true;
				}
				bait.remove();
				// Only show modal if BOTH script and bait indicate blocking - conservative to avoid false positives on 404.
				if ( scriptBlocked && baitBlocked ) {
					showAdBlockModal();
				} else {
					script.remove();
				}
			}, 600 );
		}
	})();

});
