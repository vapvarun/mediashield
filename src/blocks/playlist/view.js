/**
 * MediaShield Playlist Block — Frontend view.
 *
 * Handles playlist playback: click to switch video, auto-play next
 * with countdown timer, loop, shuffle. Reads data attributes from
 * render.php output.
 *
 * @package MediaShield
 */
(function () {
	'use strict';

	function init() {
		var players = document.querySelectorAll( '.ms-playlist-player' );
		players.forEach( initPlaylist );
	}

	function initPlaylist( container ) {
		if ( container.dataset.msPlaylistInit ) return;
		container.dataset.msPlaylistInit = '1';

		var config = {
			autoplay: container.dataset.autoplay === '1',
			countdown: parseInt( container.dataset.countdown, 10 ) || 5,
			loop: container.dataset.loop === '1',
			shuffle: container.dataset.shuffle === '1',
		};

		var items = Array.from( container.querySelectorAll( '.ms-playlist-item' ) );
		var mainPlayer = container.querySelector( '.ms-protected-player' );
		var countdownEl = container.querySelector( '.ms-playlist-countdown' );
		var timerEl = container.querySelector( '.ms-countdown-timer' );
		var currentIndex = 0;
		var countdownInterval = null;

		// Click to switch video.
		items.forEach( function ( item, idx ) {
			item.addEventListener( 'click', function () {
				switchToVideo( idx );
			} );
			item.addEventListener( 'keydown', function ( e ) {
				if ( e.key === 'Enter' ) switchToVideo( idx );
			} );
			item.setAttribute( 'role', 'button' );
			item.setAttribute( 'tabindex', '0' );
		} );

		// Listen for video end to trigger auto-play.
		function setupVideoEndListener() {
			var video = mainPlayer.querySelector( 'video' );
			if ( video ) {
				video.addEventListener( 'ended', onVideoEnded );
			}
		}

		function onVideoEnded() {
			if ( ! config.autoplay ) return;

			var nextIndex = getNextIndex();
			if ( nextIndex === null ) return;

			startCountdown( nextIndex );
		}

		function getNextIndex() {
			if ( config.shuffle ) {
				var available = items.filter( function ( _, i ) { return i !== currentIndex; } );
				if ( available.length === 0 ) return config.loop ? 0 : null;
				var randomItem = available[ Math.floor( Math.random() * available.length ) ];
				return parseInt( randomItem.dataset.index, 10 );
			}

			var next = currentIndex + 1;
			if ( next >= items.length ) {
				return config.loop ? 0 : null;
			}
			return next;
		}

		function startCountdown( nextIndex ) {
			if ( countdownEl ) countdownEl.style.display = '';
			var remaining = config.countdown;
			if ( timerEl ) timerEl.textContent = remaining;

			clearInterval( countdownInterval );
			countdownInterval = setInterval( function () {
				remaining--;
				if ( timerEl ) timerEl.textContent = remaining;

				if ( remaining <= 0 ) {
					clearInterval( countdownInterval );
					if ( countdownEl ) countdownEl.style.display = 'none';
					switchToVideo( nextIndex );
				}
			}, 1000 );
		}

		function switchToVideo( idx ) {
			clearInterval( countdownInterval );
			if ( countdownEl ) countdownEl.style.display = 'none';

			currentIndex = idx;

			// Update active class.
			items.forEach( function ( item, i ) {
				item.classList.toggle( 'is-active', i === idx );
			} );

			var item = items[ idx ];
			if ( ! item ) return;

			var sourceUrl = item.dataset.sourceUrl || '';
			var platform = item.dataset.platform || 'self';
			var videoId = item.dataset.videoId || '0';
			var protection = item.dataset.protectionLevel || 'standard';

			// Update main player.
			mainPlayer.dataset.videoId = videoId;
			mainPlayer.dataset.platform = platform;
			mainPlayer.dataset.protectionLevel = protection;

			// Remove old init flag so player-wrapper.js re-initializes.
			delete mainPlayer.dataset.msInitialized;
			delete mainPlayer.dataset.msProtected;

			var inner = mainPlayer.querySelector( '.ms-player-inner' );
			if ( ! inner ) return;

			// Clear and rebuild inner content.
			while ( inner.firstChild ) {
				inner.removeChild( inner.firstChild );
			}

			if ( platform === 'self' && sourceUrl ) {
				var video = document.createElement( 'video' );
				video.controls = true;
				video.setAttribute( 'controlsList', 'nodownload' );
				video.preload = 'metadata';
				var source = document.createElement( 'source' );
				source.src = sourceUrl;
				source.type = 'video/mp4';
				video.appendChild( source );
				inner.appendChild( video );

				video.addEventListener( 'ended', onVideoEnded );
				if ( config.autoplay ) {
					video.play().catch( function () {} );
				}
			} else if ( sourceUrl ) {
				var iframe = document.createElement( 'iframe' );
				iframe.src = sourceUrl;
				iframe.setAttribute( 'frameborder', '0' );
				iframe.setAttribute( 'allow', 'autoplay; fullscreen; picture-in-picture' );
				iframe.setAttribute( 'allowfullscreen', '' );
				inner.appendChild( iframe );
			}

			// Re-trigger player-wrapper initialization.
			window.dispatchEvent( new CustomEvent( 'mediashield:playlist-switch', {
				detail: { el: mainPlayer, videoId: parseInt( videoId, 10 ) },
			} ) );
		}

		// Initial setup.
		setupVideoEndListener();
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
})();
