/**
 * MediaShield Playlist — Frontend View Script.
 *
 * Drives the queue under a playlist's player: click to switch video, play the
 * next one automatically with a countdown, loop, shuffle.
 *
 * The player itself is the ordinary protected player. Moving to another item
 * asks the server for that video's player and swaps it in; player-wrapper.js
 * picks the new element up by itself and gives it a session, watermark and
 * tracking like any other. This script never builds a player of its own.
 *
 * @package MediaShield
 */

( function () {
	'use strict';

	function init() {
		document.querySelectorAll( '.ms-playlist-player' ).forEach( initPlaylist );
	}

	function initPlaylist( container ) {
		if ( container.dataset.msPlaylistInit ) return;
		container.dataset.msPlaylistInit = '1';

		var shared = window.mediashieldConfig || {};
		var config = {
			autoplay: container.dataset.autoplay === '1',
			countdown: parseInt( container.dataset.countdown, 10 ) || 5,
			loop: container.dataset.loop === '1',
			shuffle: container.dataset.shuffle === '1',
		};

		var items = Array.from( container.querySelectorAll( '.ms-playlist-item' ) );
		var main = container.querySelector( '.ms-playlist-main' );
		var countdownEl = container.querySelector( '.ms-playlist-countdown' );
		var timerEl = container.querySelector( '.ms-countdown-timer' );
		// The server opens the playlist on the first item this viewer may watch.
		var currentIndex = Math.max( 0, items.findIndex( function ( it ) { return it.classList.contains( 'is-active' ); } ) );
		var countdownInterval = null;
		var request = 0;

		items.forEach( function ( item, idx ) {
			item.addEventListener( 'click', function () {
				switchToVideo( idx );
			} );
			item.addEventListener( 'keydown', function ( e ) {
				if ( e.key === 'Enter' || e.key === ' ' ) {
					e.preventDefault();
					switchToVideo( idx );
				}
			} );
			item.setAttribute( 'role', 'button' );
			item.setAttribute( 'tabindex', '0' );
		} );

		// A video file's row shows its first frame, read when the row comes
		// into view, so a long playlist does not load every file at once.
		var frames = container.querySelectorAll( 'video[data-frame-src]' );
		var showFrame = function ( video ) {
			video.preload = 'metadata';
			video.src = video.dataset.frameSrc + '#t=0.5';
			video.removeAttribute( 'data-frame-src' );
		};
		if ( frames.length && 'IntersectionObserver' in window ) {
			var seen = new IntersectionObserver( function ( entries ) {
				entries.forEach( function ( entry ) {
					if ( entry.isIntersecting ) {
						seen.unobserve( entry.target );
						showFrame( entry.target );
					}
				} );
			} );
			frames.forEach( function ( v ) { seen.observe( v ); } );
		} else {
			frames.forEach( showFrame );
		}

		// player-wrapper.js fires this on the player element for every
		// platform, so the queue advances on YouTube, Vimeo, Wistia and Bunny
		// as well as on a self-hosted <video>.
		container.addEventListener( 'mediashield:ended', function () {
			if ( ! config.autoplay ) return;

			var nextIndex = getNextIndex();
			if ( nextIndex !== null ) startCountdown( nextIndex );
		} );

		// Autoplay moves only to items this viewer may watch (not .is-locked).
		function getNextIndex() {
			var open = items.map( function ( _, i ) { return i; } ).filter( function ( i ) {
				return ! items[ i ].classList.contains( 'is-locked' );
			} );
			if ( config.shuffle ) {
				var others = open.filter( function ( i ) { return i !== currentIndex; } );
				if ( others.length === 0 ) return config.loop && open.length ? open[ 0 ] : null;
				return others[ Math.floor( Math.random() * others.length ) ];
			}

			var after = open.filter( function ( i ) { return i > currentIndex; } );
			if ( after.length ) return after[ 0 ];
			return config.loop && open.length ? open[ 0 ] : null;
		}

		function stopCountdown() {
			clearInterval( countdownInterval );
			if ( countdownEl ) countdownEl.style.display = 'none';
		}

		function startCountdown( nextIndex ) {
			var remaining = config.countdown;
			if ( countdownEl ) countdownEl.style.display = '';
			if ( timerEl ) timerEl.textContent = remaining;

			clearInterval( countdownInterval );
			countdownInterval = setInterval( function () {
				remaining--;
				if ( timerEl ) timerEl.textContent = remaining;

				if ( remaining <= 0 ) {
					stopCountdown();
					switchToVideo( nextIndex );
				}
			}, 1000 );
		}

		function showError() {
			var p = document.createElement( 'p' );
			p.className = 'ms-playlist-notice';
			p.setAttribute( 'role', 'alert' );
			p.textContent = ( shared.messages && shared.messages.playlistFailed ) || 'This video could not be loaded. Please try again.';
			main.insertBefore( p, main.firstChild );
		}

		function switchToVideo( idx ) {
			var item = items[ idx ];
			if ( ! item || ! main || ! shared.restUrl ) return;

			stopCountdown();
			var old = main.querySelector( '.ms-playlist-notice' );
			if ( old ) old.remove();

			// A later click wins over a slower earlier one.
			var mine = ++request;
			main.setAttribute( 'aria-busy', 'true' );

			// The page the playlist is on, for a grant scoped to it (AccessControl::context()).
			var shown = main.querySelector( '.ms-protected-player' );
			var ctx = ( shown && shown.dataset.msContext ) || container.dataset.msContext || '';
			var url = shared.restUrl + 'playlists/' + container.dataset.playlistId + '/player/' + item.dataset.videoId;
			if ( ctx ) url += ( url.indexOf( '?' ) > -1 ? '&' : '?' ) + 'ms_context=' + encodeURIComponent( ctx );
			container.dataset.msContext = ctx;
			fetch( url, {
				headers: shared.nonce ? { 'X-WP-Nonce': shared.nonce } : {},
			} )
				.then( function ( res ) {
					return window.mediashieldRequest.readJSON( res );
				} )
				.then( function ( data ) {
					if ( mine !== request ) return;

					var holder = document.createElement( 'div' );
					holder.innerHTML = data.html || '';
					var next = holder.querySelector( '.ms-protected-player' );
					if ( ! next ) throw new Error( 'No player in response' );

					// The viewer asked for this video (or the queue reached it),
					// so it starts playing rather than waiting for another click.
					var target = next.querySelector( '.ms-player-target' );
					if ( target ) target.dataset.autoplay = '1';

					var current = main.querySelector( '.ms-protected-player' );
					if ( current ) {
						// Lets the tracker end the old session and the adapter
						// release its player before the element goes away.
						window.dispatchEvent( new CustomEvent( 'mediashield:player-destroy', { detail: { el: current } } ) );
						current.replaceWith( next );
					} else {
						main.insertBefore( next, main.firstChild );
					}

					currentIndex = idx;
					items.forEach( function ( it, i ) {
						it.classList.toggle( 'is-active', i === idx );
						if ( i === idx ) {
							it.setAttribute( 'aria-current', 'true' );
						} else {
							it.removeAttribute( 'aria-current' );
						}
					} );
				} )
				.catch( function () {
					if ( mine === request ) showError();
				} )
				.then( function () {
					if ( mine === request ) main.removeAttribute( 'aria-busy' );
				} );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
