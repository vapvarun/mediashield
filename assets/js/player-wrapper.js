/**
 * MediaShield Player Wrapper — Platform Adapter System
 *
 * Creates platform-specific players via official APIs (YouTube IFrame API,
 * Vimeo Player SDK, Wistia E-v1, Shaka Player). No more raw iframes.
 *
 * Each adapter implements: getPosition(), getDuration(), isPlaying(),
 * seekTo(), onReady(), onEnded(). Stored on el._msAdapter for tracker/watermark.
 *
 * @package MediaShield
 */
(function () {
	'use strict';

	var config = window.mediashieldConfig || {};
	var sdkLoading = {};

	// ─── SDK Loader ──────────────────────────────────────────────

	function loadSDK( url, globalCheck ) {
		if ( sdkLoading[ url ] ) return sdkLoading[ url ];
		if ( globalCheck && globalCheck() ) return Promise.resolve();

		sdkLoading[ url ] = new Promise( function ( resolve, reject ) {
			var s = document.createElement( 'script' );
			s.src = url;
			s.async = true;
			s.onload = function () { resolve(); };
			s.onerror = function () { reject( new Error( 'Failed to load: ' + url ) ); };
			document.head.appendChild( s );
		} );
		return sdkLoading[ url ];
	}

	// ─── YouTube Adapter ─────────────────────────────────────────

	var YouTubeAdapter = {
		create: function ( target, videoId ) {
			var adapter = {
				_player: null, _position: 0, _duration: 0, _playing: false,
				_readyCb: null, _endedCb: null, _pollId: null,
				getPosition: function () { return adapter._position; },
				getDuration: function () { return adapter._duration; },
				isPlaying: function () { return adapter._playing; },
				seekTo: function ( s ) { if ( adapter._player ) adapter._player.seekTo( s, true ); },
				play: function () { if ( adapter._player ) adapter._player.playVideo(); },
				pause: function () { if ( adapter._player ) adapter._player.pauseVideo(); },
				onReady: function ( cb ) { adapter._readyCb = cb; },
				onEnded: function ( cb ) { adapter._endedCb = cb; },
				destroy: function () {
					if ( adapter._pollId ) clearInterval( adapter._pollId );
					if ( adapter._player ) adapter._player.destroy();
				},
			};

			function initPlayer() {
				adapter._player = new YT.Player( target, {
					videoId: videoId,
					playerVars: { autoplay: 0, modestbranding: 1, rel: 0, fs: 0 },
					events: {
						onReady: function () {
							adapter._duration = adapter._player.getDuration() || 0;
							adapter._pollId = setInterval( function () {
								if ( adapter._player && adapter._player.getCurrentTime ) {
									adapter._position = adapter._player.getCurrentTime();
									adapter._duration = adapter._player.getDuration() || adapter._duration;
								}
							}, 500 );
							if ( adapter._readyCb ) adapter._readyCb();
						},
						onStateChange: function ( e ) {
							adapter._playing = ( e.data === YT.PlayerState.PLAYING );
							if ( e.data === YT.PlayerState.ENDED && adapter._endedCb ) {
								adapter._endedCb();
							}
						},
					},
				} );
			}

			// YouTube IFrame API loads asynchronously with a global callback.
			if ( window.YT && window.YT.Player ) {
				initPlayer();
			} else {
				window.onYouTubeIframeAPIReady = ( function ( prev ) {
					return function () {
						if ( prev ) prev();
						initPlayer();
					};
				} )( window.onYouTubeIframeAPIReady );
				loadSDK( 'https://www.youtube.com/iframe_api', function () { return window.YT && window.YT.Player; } );
			}

			return adapter;
		},
	};

	// ─── Vimeo Adapter ───────────────────────────────────────────

	var VimeoAdapter = {
		create: function ( target, videoId ) {
			var adapter = {
				_player: null, _position: 0, _duration: 0, _playing: false,
				_readyCb: null, _endedCb: null,
				getPosition: function () { return adapter._position; },
				getDuration: function () { return adapter._duration; },
				isPlaying: function () { return adapter._playing; },
				seekTo: function ( s ) { if ( adapter._player ) adapter._player.setCurrentTime( s ); },
				play: function () { if ( adapter._player ) adapter._player.play(); },
				pause: function () { if ( adapter._player ) adapter._player.pause(); },
				onReady: function ( cb ) { adapter._readyCb = cb; },
				onEnded: function ( cb ) { adapter._endedCb = cb; },
				destroy: function () { if ( adapter._player ) adapter._player.destroy(); },
			};

			loadSDK( 'https://player.vimeo.com/api/player.js', function () { return window.Vimeo; } )
				.then( function () {
					adapter._player = new Vimeo.Player( target, {
						id: videoId, responsive: true, fullscreen: false,
					} );

					adapter._player.on( 'timeupdate', function ( data ) {
						adapter._position = data.seconds;
						adapter._duration = data.duration;
					} );
					adapter._player.on( 'play', function () { adapter._playing = true; } );
					adapter._player.on( 'pause', function () { adapter._playing = false; } );
					adapter._player.on( 'ended', function () {
						adapter._playing = false;
						if ( adapter._endedCb ) adapter._endedCb();
					} );
					adapter._player.ready().then( function () {
						adapter._player.getDuration().then( function ( d ) {
							adapter._duration = d;
						} );
						if ( adapter._readyCb ) adapter._readyCb();
					} );
				} );

			return adapter;
		},
	};

	// ─── Wistia Adapter ──────────────────────────────────────────

	var WistiaAdapter = {
		create: function ( target, hashedId ) {
			var adapter = {
				_video: null, _position: 0, _duration: 0, _playing: false,
				_readyCb: null, _endedCb: null,
				getPosition: function () { return adapter._position; },
				getDuration: function () { return adapter._duration; },
				isPlaying: function () { return adapter._playing; },
				seekTo: function ( s ) { if ( adapter._video ) adapter._video.time( s ); },
				play: function () { if ( adapter._video ) adapter._video.play(); },
				pause: function () { if ( adapter._video ) adapter._video.pause(); },
				onReady: function ( cb ) { adapter._readyCb = cb; },
				onEnded: function ( cb ) { adapter._endedCb = cb; },
				destroy: function () { if ( adapter._video ) adapter._video.remove(); },
			};

			// Create Wistia embed div inside target.
			var embedDiv = document.createElement( 'div' );
			embedDiv.className = 'wistia_embed wistia_async_' + hashedId;
			embedDiv.style.width = '100%';
			embedDiv.style.height = '100%';
			target.appendChild( embedDiv );

			window._wq = window._wq || [];
			window._wq.push( {
				id: hashedId,
				onReady: function ( video ) {
					adapter._video = video;
					adapter._duration = video.duration();
					video.bind( 'secondchange', function ( s ) {
						adapter._position = s;
					} );
					video.bind( 'play', function () { adapter._playing = true; } );
					video.bind( 'pause', function () { adapter._playing = false; } );
					video.bind( 'end', function () {
						adapter._playing = false;
						if ( adapter._endedCb ) adapter._endedCb();
					} );
					if ( adapter._readyCb ) adapter._readyCb();
				},
			} );

			loadSDK( 'https://fast.wistia.com/assets/external/E-v1.js', function () { return window.Wistia; } );

			return adapter;
		},
	};

	// ─── Self-Hosted / Bunny Adapter (Shaka Player or <video>) ──

	var NativeAdapter = {
		create: function ( target, sourceUrl, streamUrl ) {
			var adapter = {
				_video: null, _shakaPlayer: null,
				_readyCb: null, _endedCb: null,
				getPosition: function () { return adapter._video ? adapter._video.currentTime : 0; },
				getDuration: function () { return adapter._video && isFinite( adapter._video.duration ) ? adapter._video.duration : 0; },
				isPlaying: function () { return adapter._video ? ( ! adapter._video.paused && ! adapter._video.ended ) : false; },
				seekTo: function ( s ) { if ( adapter._video ) adapter._video.currentTime = s; },
				play: function () { if ( adapter._video ) adapter._video.play(); },
				pause: function () { if ( adapter._video ) adapter._video.pause(); },
				onReady: function ( cb ) { adapter._readyCb = cb; },
				onEnded: function ( cb ) { adapter._endedCb = cb; },
				destroy: function () {
					if ( adapter._shakaPlayer ) adapter._shakaPlayer.destroy();
				},
			};

			var video = document.createElement( 'video' );
			video.controls = true;
			video.setAttribute( 'controlsList', 'nodownload nofullscreen' );
			video.preload = 'metadata';
			video.style.width = '100%';
			video.style.display = 'block';
			target.appendChild( video );
			adapter._video = video;

			video.addEventListener( 'loadedmetadata', function () {
				if ( adapter._readyCb ) adapter._readyCb();
			} );
			video.addEventListener( 'ended', function () {
				if ( adapter._endedCb ) adapter._endedCb();
			} );

			var url = streamUrl || sourceUrl;

			// Use Shaka Player for HLS/DASH, native for MP4.
			if ( url && ( url.indexOf( '.m3u8' ) > -1 || url.indexOf( '.mpd' ) > -1 ) && typeof shaka !== 'undefined' ) {
				shaka.polyfill.installAll();
				var player = new shaka.Player( video );
				adapter._shakaPlayer = player;
				player.load( url ).catch( function ( err ) {
					console.warn( 'MediaShield: Shaka load error', err );
					video.src = sourceUrl; // Fallback to direct URL.
				} );
			} else {
				video.src = url;
			}

			return adapter;
		},
	};

	// ─── Adapter Factory ─────────────────────────────────────────

	function createAdapter( el ) {
		var target = el.querySelector( '.ms-player-target' );
		if ( ! target ) return null;

		var platform = el.dataset.platform;
		var platformVideoId = target.dataset.platformVideoId || '';
		var sourceUrl = target.dataset.sourceUrl || '';
		var streamUrl = target.dataset.streamUrl || '';

		switch ( platform ) {
			case 'youtube':
				return platformVideoId ? YouTubeAdapter.create( target, platformVideoId ) : null;
			case 'vimeo':
				return platformVideoId ? VimeoAdapter.create( target, platformVideoId ) : null;
			case 'wistia':
				return platformVideoId ? WistiaAdapter.create( target, platformVideoId ) : null;
			case 'bunny':
				return NativeAdapter.create( target, sourceUrl, streamUrl );
			case 'self':
				return NativeAdapter.create( target, sourceUrl, streamUrl );
			default:
				// Generic iframe fallback — limited tracking.
				if ( sourceUrl ) return NativeAdapter.create( target, sourceUrl, '' );
				return null;
		}
	}

	// ─── Player Init ─────────────────────────────────────────────

	function init() {
		var players = document.querySelectorAll( '.ms-protected-player' );
		players.forEach( initPlayer );
		observeDynamicEmbeds();
	}

	function initPlayer( el ) {
		if ( el.dataset.msInitialized ) return;
		el.dataset.msInitialized = '1';

		var protectionLevel = el.dataset.protectionLevel || 'standard';
		var videoId = parseInt( el.dataset.videoId, 10 ) || 0;

		// No protection — free preview.
		if ( protectionLevel === 'none' ) {
			var adapter = createAdapter( el );
			if ( adapter ) el._msAdapter = adapter;
			return;
		}

		// Login gate.
		if ( ! config.isLoggedIn ) {
			showLoginOverlay( el );
			return;
		}

		// Create platform adapter.
		var playerAdapter = createAdapter( el );
		if ( ! playerAdapter ) return;
		el._msAdapter = playerAdapter;

		// Start session after adapter is ready.
		playerAdapter.onReady( function () {
			startSession( el, videoId, playerAdapter );
		} );
	}

	function startSession( el, videoId, adapter ) {
		if ( ! videoId ) {
			window.dispatchEvent( new CustomEvent( 'mediashield:player-ready', {
				detail: { el: el, videoId: 0, adapter: adapter },
			} ) );
			return;
		}

		fetch( config.restUrl + 'session/start', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
			body: JSON.stringify( { video_id: videoId } ),
		} )
			.then( function ( res ) {
				return res.json().then( function ( data ) {
					return { status: res.status, data: data };
				} );
			} )
			.then( function ( result ) {
				var status = result.status;
				var data = result.data;

				// Handle access-denied responses (403 or error codes).
				if ( status === 403 || data.code === 'access_denied' || data.code === 'email_gate_required' || data.code === 'login_required' ) {
					var reason = data.code || 'access_denied';

					// Show login overlay for login_required when user is not logged in.
					if ( reason === 'login_required' && ! config.isLoggedIn ) {
						showLoginOverlay( el );
						return;
					}

					window.dispatchEvent( new CustomEvent( 'mediashield:access-denied', {
						bubbles: true,
						detail: { el: el, videoId: videoId, reason: reason },
					} ) );
					return;
				}

				if ( data.session_token ) {
					el.dataset.sessionToken = data.session_token;

					// Resume position via adapter.
					if ( data.resume_position > 0 ) {
						showResumePrompt( el, data.resume_position, adapter );
					}

					window.dispatchEvent( new CustomEvent( 'mediashield:player-ready', {
						detail: {
							el: el,
							videoId: videoId,
							token: data.session_token,
							resumePosition: data.resume_position,
							watermarkConfig: data.watermark_config,
							video: data.video,
							adapter: adapter,
						},
					} ) );
				}
			} )
			.catch( function ( err ) {
				console.warn( 'MediaShield: session start failed', err );
			} );
	}

	// ─── Login Overlay ───────────────────────────────────────────

	function showLoginOverlay( el ) {
		var overlay = document.createElement( 'div' );
		overlay.className = 'ms-login-overlay';

		var message = document.createElement( 'div' );
		message.className = 'ms-login-message';

		var text = document.createElement( 'p' );
		text.textContent = config.loginMessage || 'Please log in to watch this video.';

		var link = document.createElement( 'a' );
		link.href = config.loginUrl || '/wp-login.php';
		link.className = 'ms-login-button';
		link.textContent = 'Log In';

		message.appendChild( text );
		message.appendChild( link );
		overlay.appendChild( message );
		el.appendChild( overlay );
	}

	// ─── Resume Prompt ───────────────────────────────────────────

	function showResumePrompt( el, position, adapter ) {
		var mins = Math.floor( position / 60 );
		var secs = Math.floor( position % 60 );
		var timeStr = mins + ':' + ( secs < 10 ? '0' : '' ) + secs;

		var toast = document.createElement( 'div' );
		toast.className = 'ms-resume-toast';

		var span = document.createElement( 'span' );
		span.textContent = 'Resume from ' + timeStr + '?';

		var yesBtn = document.createElement( 'button' );
		yesBtn.className = 'ms-resume-yes';
		yesBtn.textContent = 'Resume';
		yesBtn.addEventListener( 'click', function () {
			adapter.seekTo( position );
			adapter.play();
			toast.remove();
		} );

		var noBtn = document.createElement( 'button' );
		noBtn.className = 'ms-resume-no';
		noBtn.textContent = 'Start Over';
		noBtn.addEventListener( 'click', function () { toast.remove(); } );

		toast.appendChild( span );
		toast.appendChild( yesBtn );
		toast.appendChild( noBtn );
		el.appendChild( toast );

		setTimeout( function () { if ( toast.parentNode ) toast.remove(); }, 10000 );
	}

	// ─── Custom Fullscreen ───────────────────────────────────────

	function initFullscreenButtons() {
		document.querySelectorAll( '.ms-fullscreen-btn' ).forEach( function ( btn ) {
			if ( btn.dataset.msInit ) return;
			btn.dataset.msInit = '1';

			btn.addEventListener( 'click', function () {
				var container = btn.closest( '.ms-protected-player' );
				if ( ! container ) return;

				if ( document.fullscreenElement ) {
					document.exitFullscreen();
				} else {
					container.requestFullscreen().catch( function () {} );
				}
			} );
		} );
	}

	// ─── Dynamic Embed Observer ──────────────────────────────────

	function observeDynamicEmbeds() {
		var observer = new MutationObserver( function ( mutations ) {
			mutations.forEach( function ( mutation ) {
				mutation.addedNodes.forEach( function ( node ) {
					if ( node.nodeType !== 1 ) return;
					if ( node.classList && node.classList.contains( 'ms-protected-player' ) ) {
						initPlayer( node );
					}
					var nested = node.querySelectorAll ? node.querySelectorAll( '.ms-protected-player' ) : [];
					nested.forEach( initPlayer );
				} );
			} );
		} );

		observer.observe( document.body, { childList: true, subtree: true } );
	}

	// ─── Boot ────────────────────────────────────────────────────

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			init();
			initFullscreenButtons();
		} );
	} else {
		init();
		initFullscreenButtons();
	}
})();
