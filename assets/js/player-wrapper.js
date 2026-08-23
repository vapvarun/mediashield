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
		create: function ( target, videoId, options ) {
			options = options || {};
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
				var pv = { modestbranding: 1, rel: 0, fs: 0 };
				if ( options.autoplay === true ) pv.autoplay = 1;
				if ( options.loop === true ) { pv.loop = 1; pv.playlist = videoId; }
				if ( options.muted === true ) pv.mute = 1;
				if ( options.controls === false ) pv.controls = 0;
				adapter._player = new YT.Player( target, {
					videoId: videoId,
					playerVars: pv,
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
		create: function ( target, videoId, options ) {
			options = options || {};
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
					var vimeoOpts = { id: videoId, responsive: true, fullscreen: false };
					if ( options.autoplay === true ) vimeoOpts.autoplay = true;
					if ( options.loop === true ) vimeoOpts.loop = true;
					if ( options.muted === true ) vimeoOpts.muted = true;
					if ( options.controls === false ) vimeoOpts.controls = false;
					adapter._player = new Vimeo.Player( target, vimeoOpts );

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
				} )
				.catch( function ( err ) {
					// SDK load failed (ad-blocker, CSP, offline CDN). Fall back to a
					// raw <iframe> so the video still plays — adapter._player stays
					// null so heartbeat/milestone tracking is silently disabled, but
					// every adapter method is null-guarded so nothing throws.
					if ( target && ! target.querySelector( 'iframe' ) ) {
						var iframe = document.createElement( 'iframe' );
						iframe.src = 'https://player.vimeo.com/video/' + encodeURIComponent( videoId );
						iframe.style.width = '100%';
						iframe.style.aspectRatio = '16/9';
						iframe.style.border = '0';
						iframe.setAttribute( 'allow', 'autoplay; fullscreen; picture-in-picture' );
						iframe.setAttribute( 'allowfullscreen', 'true' );
						target.appendChild( iframe );
					}
					if ( typeof console !== 'undefined' && console.warn ) {
						console.warn( 'MediaShield: Vimeo Player SDK failed to load — falling back to raw iframe, watch tracking is disabled.', err );
					}
					if ( adapter._readyCb ) adapter._readyCb();
				} );

			return adapter;
		},
	};

	// ─── Wistia Adapter ──────────────────────────────────────────

	var WistiaAdapter = {
		create: function ( target, hashedId, options ) {
			options = options || {};
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

			// Create Wistia embed div inside target. Per-video options become
			// `wistia_async_<id>` modifier classes (the documented runtime API
			// for declarative Wistia config).
			var embedClasses = [ 'wistia_embed', 'wistia_async_' + hashedId ];
			if ( options.autoplay === true ) embedClasses.push( 'autoPlay=true' );
			if ( options.loop === true ) embedClasses.push( 'endVideoBehavior=loop' );
			if ( options.muted === true ) embedClasses.push( 'volume=0' );
			if ( options.controls === false ) embedClasses.push( 'playbar=false', 'fullscreenButton=false', 'volumeControl=false', 'settingsControl=false', 'playbackRateControl=false', 'controlsVisibleOnLoad=false' );
			var embedDiv = document.createElement( 'div' );
			embedDiv.className = embedClasses.join( ' ' );
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
		create: function ( target, sourceUrl, streamUrl, options ) {
			options = options || {};
			var adapter = {
				_video: null, _shakaPlayer: null, _hls: null,
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
					// hls.js keeps a worker and buffered segments alive; without
					// this a playlist that swaps videos leaks one per switch.
					if ( adapter._hls ) {
						adapter._hls.destroy();
						adapter._hls = null;
					}
				},
			};

			var video = document.createElement( 'video' );
			// Per-video overrides. `controls` defaults true; the others default false.
			video.controls = options.controls === false ? false : true;
			video.autoplay = options.autoplay === true;
			video.loop     = options.loop === true;
			video.muted    = options.muted === true;
			video.setAttribute( 'controlsList', 'nodownload nofullscreen noremoteplayback' );
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

			var url        = streamUrl || sourceUrl;
			var isAdaptive = !! url && ( url.indexOf( '.m3u8' ) > -1 || url.indexOf( '.mpd' ) > -1 );

			if ( ! isAdaptive ) {
				video.src = url;
				return adapter;
			}

			// Shaka first when a site supplies it — it is the only one of the
			// three that also handles DASH and DRM.
			if ( typeof shaka !== 'undefined' ) {
				shaka.polyfill.installAll();
				var player = new shaka.Player( video );
				adapter._shakaPlayer = player;
				player.load( url ).catch( function ( err ) {
					console.warn( 'MediaShield: Shaka load error', err );
					video.src = sourceUrl || url;
				} );
				return adapter;
			}

			// hls.js first wherever Media Source Extensions exist, falling back
			// to the element only where they don't (iOS Safari). This is the
			// order hls.js itself documents, and canPlayType cannot replace it:
			// Chromium answers "maybe" for the HLS mime type while being unable
			// to play it, so trusting canPlayType would hand Chrome a source it
			// silently fails on — the exact browser this library is here for.
			if ( url.indexOf( '.m3u8' ) > -1 && typeof Hls !== 'undefined' && Hls.isSupported() ) {
				var hls = new Hls( { enableWorker: true } );
				adapter._hls = hls;
				hls.on( Hls.Events.ERROR, function ( event, data ) {
					// Only fatal errors are worth acting on; hls.js recovers
					// from the rest on its own.
					if ( ! data || ! data.fatal ) {
						return;
					}
					console.warn( 'MediaShield: HLS error', data.type );
					hls.destroy();
					adapter._hls = null;
					video.src = sourceUrl || url;
				} );
				hls.loadSource( url );
				hls.attachMedia( video );
				return adapter;
			}

			// iOS Safari plays HLS natively and has no Media Source, so this is
			// the correct path there rather than a fallback. On a browser with
			// neither, the element surfaces a real error instead of failing
			// silently.
			video.src = url;

			return adapter;
		},
	};

	// ─── Iframe Adapter (Bunny Stream embed pages & generic embeds) ──
	//
	// Bunny Stream "embed" URLs (iframe.mediadelivery.net / player.mediadelivery.net)
	// are HTML player pages, NOT video files — a <video> element cannot render them.
	// This adapter loads them in an <iframe>. Cross-origin iframes expose no
	// playback position/duration, so tracking + watermark are best-effort (0).

	var IframeAdapter = {
		create: function ( target, sourceUrl, options ) {
			options = options || {};
			var adapter = {
				_video: null, _iframe: null, _pjs: null,
				_position: 0, _duration: 0, _playing: false,
				_readyCb: null, _endedCb: null, _seekedCb: null,
				getPosition: function () { return adapter._position; },
				getDuration: function () { return adapter._duration; },
				isPlaying: function () { return adapter._playing; },
				seekTo: function ( s ) { if ( adapter._pjs ) { try { adapter._pjs.setCurrentTime( s ); } catch ( e ) {} } },
				play: function () { if ( adapter._pjs ) { try { adapter._pjs.play(); } catch ( e ) {} } },
				pause: function () { if ( adapter._pjs ) { try { adapter._pjs.pause(); } catch ( e ) {} } },
				onReady: function ( cb ) { adapter._readyCb = cb; },
				onEnded: function ( cb ) { adapter._endedCb = cb; },
				onSeeked: function ( cb ) { adapter._seekedCb = cb; },
				destroy: function () { if ( adapter._iframe ) adapter._iframe.remove(); },
			};

			var iframe = document.createElement( 'iframe' );
			iframe.src = sourceUrl;
			iframe.setAttribute( 'frameborder', '0' );
			iframe.setAttribute( 'allow', 'autoplay; fullscreen; picture-in-picture; encrypted-media' );
			iframe.setAttribute( 'allowfullscreen', '' );
			iframe.style.width = '100%';
			iframe.style.height = '100%';
			iframe.style.border = '0';
			iframe.style.display = 'block';
			target.appendChild( iframe );
			adapter._iframe = iframe;

			// player.js gives a unified postMessage API for compatible embeds
			// (Bunny Stream, Vimeo, Wistia). It unlocks real position + seek +
			// play/pause on the otherwise-opaque cross-origin iframe — which is
			// exactly what watch-tracking, prevent-forward-skip, and in-video ad
			// breaks all need. If it fails to load or handshake, the iframe
			// still plays; we fall back to onReady so the session/tracking path
			// keeps running (degraded: position stays 0).
			var readyFired = false;
			var fireReady  = function () {
				if ( ! readyFired ) {
					readyFired = true;
					if ( adapter._readyCb ) { adapter._readyCb(); }
				}
			};

			loadSDK( 'https://cdn.embed.ly/player-0.1.0.min.js', function () { return window.playerjs; } )
				.then( function () {
					if ( ! window.playerjs ) { fireReady(); return; }
					try {
						var pjs = new window.playerjs.Player( iframe );
						pjs.on( 'ready', function () {
							adapter._pjs = pjs;
							try { pjs.getDuration( function ( d ) { adapter._duration = d || 0; } ); } catch ( e ) {}
							pjs.on( 'timeupdate', function ( d ) {
								if ( d && typeof d.seconds === 'number' ) { adapter._position = d.seconds; }
								if ( d && typeof d.duration === 'number' && d.duration ) { adapter._duration = d.duration; }
							} );
							pjs.on( 'play', function () { adapter._playing = true; } );
							pjs.on( 'pause', function () { adapter._playing = false; } );
							pjs.on( 'seeked', function ( d ) {
								var s = ( d && typeof d.seconds === 'number' ) ? d.seconds : adapter._position;
								if ( adapter._seekedCb ) { adapter._seekedCb( s ); }
							} );
							pjs.on( 'ended', function () { adapter._playing = false; if ( adapter._endedCb ) { adapter._endedCb(); } } );
							fireReady();
						} );
						// Safety: if the handshake never resolves, still start the session.
						setTimeout( fireReady, 4000 );
					} catch ( e ) { fireReady(); }
				} )
				.catch( function () { fireReady(); } );

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

		// Per-video playback options. Tri-state via data attrs: "1" = on, "0" =
		// off, missing = adapter default. Anything other than "1"/"0" is ignored.
		function tri( v ) {
			if ( v === '1' ) return true;
			if ( v === '0' ) return false;
			return undefined;
		}
		var options = {
			autoplay: tri( target.dataset.autoplay ),
			loop:     tri( target.dataset.loop ),
			muted:    tri( target.dataset.muted ),
			controls: tri( target.dataset.controls ),
		};

		switch ( platform ) {
			case 'youtube':
				return platformVideoId ? YouTubeAdapter.create( target, platformVideoId, options ) : null;
			case 'vimeo':
				return platformVideoId ? VimeoAdapter.create( target, platformVideoId, options ) : null;
			case 'wistia':
				return platformVideoId ? WistiaAdapter.create( target, platformVideoId, options ) : null;
			case 'bunny':
				// Prefer a direct stream (HLS playlist / MP4) when one is known:
				// it plays in a real <video>, which is what makes the watermark,
				// speed control, accurate progress and in-video ad breaks work.
				// The iframe embed is an opaque cross-origin player page — it
				// cannot be paused or seeked, so a mid-roll would pause nothing.
				if ( streamUrl ) {
					return NativeAdapter.create( target, '', streamUrl, options );
				}
				// Bunny embed URLs are HTML player pages → must use an <iframe>.
				return sourceUrl ? IframeAdapter.create( target, sourceUrl, options ) : null;
			case 'self':
				return NativeAdapter.create( target, sourceUrl, streamUrl, options );
			default:
				// Unknown platform: a direct media file plays in a <video>
				// (full tracking); anything else is treated as an HTML embed
				// page and rendered in an <iframe> — a <video> element can't
				// render an HTML page (same class of bug the 'bunny' case
				// above guards against). Tracking is limited on cross-origin
				// iframes.
				if ( ! sourceUrl ) return null;
				return /\.(mp4|webm|ogg|ogv|mov|m3u8)(\?|#|$)/i.test( sourceUrl )
					? NativeAdapter.create( target, sourceUrl, streamUrl, options )
					: IframeAdapter.create( target, sourceUrl, options );
		}
	}

	// ─── Player Features (admin-configurable) ───────────────────

	/**
	 * Toggle fullscreen on the given container.
	 */
	function toggleFullscreen( container ) {
		if ( document.fullscreenElement ) {
			document.exitFullscreen();
		} else {
			container.requestFullscreen().catch( function () {} );
		}
	}

	/**
	 * Build a speed control menu using safe DOM methods.
	 */
	function buildSpeedControl( el, container ) {
		var speedControl = document.createElement( 'div' );
		speedControl.className = 'ms-speed-control';

		var speedBtn = document.createElement( 'button' );
		speedBtn.className = 'ms-speed-btn';
		speedBtn.setAttribute( 'aria-label', 'Playback speed' );
		speedBtn.textContent = '1x';
		speedControl.appendChild( speedBtn );

		var menu = document.createElement( 'div' );
		menu.className = 'ms-speed-menu';

		var speeds = [ 0.5, 0.75, 1, 1.25, 1.5, 2 ];
		speeds.forEach( function ( s ) {
			var opt = document.createElement( 'button' );
			opt.className = 'ms-speed-option' + ( s === 1 ? ' is-active' : '' );
			opt.dataset.speed = s;
			opt.textContent = s + 'x';
			opt.addEventListener( 'click', function ( e ) {
				e.stopPropagation();
				if ( el._msAdapter && el._msAdapter._video ) {
					el._msAdapter._video.playbackRate = s;
				}
				speedBtn.textContent = s + 'x';
				menu.querySelectorAll( '.ms-speed-option' ).forEach( function ( o ) { o.classList.remove( 'is-active' ); } );
				opt.classList.add( 'is-active' );
				speedControl.classList.remove( 'is-open' );
			} );
			menu.appendChild( opt );
		} );

		speedControl.appendChild( menu );

		speedBtn.addEventListener( 'click', function ( e ) {
			e.stopPropagation();
			speedControl.classList.toggle( 'is-open' );
		} );

		document.addEventListener( 'click', function () {
			speedControl.classList.remove( 'is-open' );
		} );

		container.appendChild( speedControl );
	}

	/**
	 * Build an end screen overlay using safe DOM methods.
	 */
	function buildEndScreen( container, adapter, endscreenConfig ) {
		if ( container.querySelector( '.ms-endscreen' ) ) return;

		var overlay = document.createElement( 'div' );
		overlay.className = 'ms-endscreen';

		var content = document.createElement( 'div' );
		content.className = 'ms-endscreen__content';

		var msg = document.createElement( 'p' );
		msg.textContent = ( endscreenConfig && endscreenConfig.text ) || 'Thanks for watching!';
		content.appendChild( msg );

		var endUrl = endscreenConfig && endscreenConfig.url;
		if ( endUrl ) {
			var cta = document.createElement( 'a' );
			cta.href = endUrl;
			cta.className = 'ms-endscreen__cta';
			cta.textContent = 'Continue \u2192';
			content.appendChild( cta );
		}

		var replayBtn = document.createElement( 'button' );
		replayBtn.className = 'ms-endscreen__replay';
		replayBtn.textContent = 'Replay';
		replayBtn.addEventListener( 'click', function () {
			overlay.remove();
			adapter.seekTo( 0 );
			adapter.play();
		} );
		content.appendChild( replayBtn );

		overlay.appendChild( content );
		container.appendChild( overlay );
	}

	/**
	 * Apply admin-configurable player features to a container/adapter pair.
	 * Speed control is ONLY for native <video> (self/bunny). Keyboard, sticky,
	 * and end screen work on ALL platforms.
	 */
	function applyPlayerFeatures( el, adapter ) {
		var playerConfig = config.player;
		if ( ! playerConfig ) return;

		// Per-video overrides from data-player-overrides JSON attribute.
		var target = el.querySelector( '.ms-player-target' );
		var overridesJson = target ? target.dataset.playerOverrides : '';
		var overrides = {};
		if ( overridesJson ) {
			try { overrides = JSON.parse( overridesJson ); } catch ( e ) { /* ignore */ }
		}

		// Helper: resolve per-video override (true/false) or fall back to global setting.
		function feat( key ) {
			if ( typeof overrides[ key ] !== 'undefined' ) return !! overrides[ key ];
			return !! playerConfig[ key ];
		}
		function featText( key ) {
			return overrides[ key ] || playerConfig[ key ] || '';
		}

		var container = el;
		var platform = el.dataset.platform || '';

		// ── Speed control (native video only — YouTube/Vimeo/Wistia have their own) ──
		if ( feat( 'speedControl' ) && ( platform === 'self' || platform === 'bunny' ) && adapter._video ) {
			buildSpeedControl( el, container );
		}

		// ── Prevent forward skipping (watch enforcement / CLE compliance) ──
		// Allows rewind; clamps any forward seek past the furthest point the
		// learner has actually watched. Native <video> is controlled directly;
		// cross-origin embeds (Bunny / Vimeo / Wistia) are driven through the
		// player.js postMessage API. Additive — when the option is off nothing
		// here runs and playback is untouched. Keyboard ArrowRight is also
		// suppressed below so the 5s skip-forward can't bypass the clamp.
		var msPreventSeek = feat( 'preventForwardSeek' );
		if ( msPreventSeek ) {
			var msMaxWatched = 0;
			var MS_SEEK_TOL  = 1.5; // Slack so normal playback isn't clamped.

			var msAdvance = function ( pos ) {
				// Advance the high-water mark only in playback-sized steps so a
				// forward jump never counts as "watched".
				if ( pos > msMaxWatched && pos - msMaxWatched < 5 ) {
					msMaxWatched = pos;
				}
			};

			if ( adapter._video ) {
				adapter._video.addEventListener( 'timeupdate', function () {
					msAdvance( adapter._video.currentTime );
				} );
				adapter._video.addEventListener( 'seeking', function () {
					if ( adapter._video.currentTime > msMaxWatched + MS_SEEK_TOL ) {
						adapter._video.currentTime = msMaxWatched;
					}
				} );
			} else if ( adapter.onSeeked ) {
				// player.js-backed embed (Bunny etc.) — the adapter already owns
				// the player.js handshake; reuse it instead of opening a second.
				setInterval( function () { msAdvance( adapter.getPosition() ); }, 500 );
				adapter.onSeeked( function ( s ) {
					if ( s > msMaxWatched + MS_SEEK_TOL ) { adapter.seekTo( msMaxWatched ); }
				} );
			}
		}

		// ── Keyboard shortcuts (scoped to player focus — all platforms) ──
		if ( feat( 'keyboard' ) ) {
			container.setAttribute( 'tabindex', '0' );
			container.addEventListener( 'keydown', function ( e ) {
				var a = el._msAdapter;
				if ( ! a ) return;
				switch ( e.key ) {
					case ' ':
						e.preventDefault();
						a.isPlaying() ? a.pause() : a.play();
						break;
					case 'ArrowLeft':
						e.preventDefault();
						a.seekTo( Math.max( 0, a.getPosition() - 5 ) );
						break;
					case 'ArrowRight':
						e.preventDefault();
						if ( ! msPreventSeek ) { a.seekTo( a.getPosition() + 5 ); }
						break;
					case 'ArrowUp':
						e.preventDefault();
						if ( a._video ) { a._video.volume = Math.min( 1, a._video.volume + 0.1 ); }
						break;
					case 'ArrowDown':
						e.preventDefault();
						if ( a._video ) { a._video.volume = Math.max( 0, a._video.volume - 0.1 ); }
						break;
					case 'f':
					case 'F':
						e.preventDefault();
						toggleFullscreen( container );
						break;
					case 'm':
					case 'M':
						if ( a._video ) { a._video.muted = ! a._video.muted; }
						break;
				}
			} );
		}

		// ── Sticky / floating player on scroll (all platforms) ──
		// The observer must NOT watch the player itself: once `.ms-sticky-player`
		// applies `position: fixed`, the player re-enters the viewport in its
		// new corner, the observer fires `isIntersecting=true`, the class is
		// removed, the element falls back to its original off-screen position,
		// and the cycle restarts \u2014 visible to the user as flicker. Watching a
		// 1px in-flow sentinel placed just before the player avoids the loop.
		if ( feat( 'sticky' ) ) {
			var stickyDismissed = false;
			var sentinel = document.createElement( 'div' );
			sentinel.className = 'ms-sticky-sentinel';
			sentinel.setAttribute( 'aria-hidden', 'true' );
			sentinel.style.cssText = 'width:100%;height:1px;pointer-events:none;';
			container.parentNode.insertBefore( sentinel, container );

			var observer = new IntersectionObserver( function ( entries ) {
				entries.forEach( function ( entry ) {
					if ( stickyDismissed ) return;
					if ( ! entry.isIntersecting && adapter.isPlaying() ) {
						container.classList.add( 'ms-sticky-player' );
						if ( ! container.querySelector( '.ms-sticky-close' ) ) {
							var closeBtn = document.createElement( 'button' );
							closeBtn.className = 'ms-sticky-close';
							closeBtn.textContent = '\u00D7';
							closeBtn.setAttribute( 'aria-label', 'Close sticky player' );
							closeBtn.addEventListener( 'click', function ( e ) {
								e.stopPropagation();
								container.classList.remove( 'ms-sticky-player' );
								stickyDismissed = true;
								closeBtn.remove();
							} );
							container.appendChild( closeBtn );
						}
					} else {
						container.classList.remove( 'ms-sticky-player' );
						var existing = container.querySelector( '.ms-sticky-close' );
						if ( existing ) existing.remove();
					}
				} );
			} );
			observer.observe( sentinel );
		}

		// ── End screen overlay (all platforms) ──
		if ( feat( 'endscreen' ) ) {
			var endscreenConfig = {
				text: featText( 'endscreenText' ),
				url: featText( 'endscreenUrl' ),
			};
			adapter.onEnded( function () {
				buildEndScreen( container, adapter, endscreenConfig );
			} );
		}
	}

	/**
	 * Resolve whether the Resume Playback prompt is enabled for a player.
	 *
	 * Reads the per-video `data-player-overrides` JSON `resume` flag first
	 * (true/false), falling back to the global `config.player.resume` setting —
	 * the same precedence `applyPlayerFeatures()` uses for every other feature.
	 */
	function resumeEnabled( el ) {
		var target = el.querySelector( '.ms-player-target' );
		var overridesJson = target ? target.dataset.playerOverrides : '';
		if ( overridesJson ) {
			try {
				var overrides = JSON.parse( overridesJson );
				if ( typeof overrides.resume !== 'undefined' ) return !! overrides.resume;
			} catch ( e ) { /* ignore malformed JSON, fall through to global */ }
		}
		return !! ( config.player && config.player.resume );
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

		// Protection tiers (per-video `_ms_protection_level`):
		//   none     — free preview: adapter + player features, no gate.
		//   basic    — login gate + right-click block (protection.js); NO watermark,
		//              NO session tracking, NO milestones (we never start a session,
		//              so no token is issued and the watermark/tracker scripts — both
		//              keyed off the `player-ready` token event — stay dormant).
		//   standard — login gate + watermark + session tracking + milestones.
		//   strict   — everything in `standard` plus devtools detection + source
		//              hiding forced on regardless of the global toggles
		//              (protection.js reads data-protection-level for this).

		// No protection — free preview.
		if ( protectionLevel === 'none' ) {
			var adapter = createAdapter( el );
			if ( adapter ) {
				el._msAdapter = adapter;
				adapter.onReady( function () {
					applyPlayerFeatures( el, adapter );
				} );
			}
			return;
		}

		// Login gate (all non-none tiers).
		// Skip the client-side shortcut when the video opts into an alternative
		// access path (any data-access-type value) so /session/start can run and
		// return the proper reason for whichever extension owns that path.
		//
		// `requireLogin` is the operator's setting. When it is off we must NOT
		// short-circuit here: /session/start is the authoritative gate and it
		// still enforces per-video role rules, allowed domains and the
		// mediashield_can_watch filter chain. Returning early on `isLoggedIn`
		// alone made the setting inert - a guest never reached the server
		// decision that would have let them watch.
		// wp_localize_script stringifies the payload: PHP `true` arrives as
		// "1" and PHP `false` as "" - never as a JS boolean. So this is a
		// truthiness test, matching how `isLoggedIn` is read above, NOT a
		// `!== false` identity test (which "" would silently fail).
		// A missing key means an older cached config, so default to gating.
		var requiresLogin = ( 'requireLogin' in config ) ? !! config.requireLogin : true;
		if ( requiresLogin && ! config.isLoggedIn && ! el.dataset.accessType ) {
			showLoginOverlay( el );
			return;
		}

		// Create platform adapter.
		var playerAdapter = createAdapter( el );
		if ( ! playerAdapter ) return;
		el._msAdapter = playerAdapter;

		// Basic tier: player features only, no session/tracking/watermark/milestones.
		if ( protectionLevel === 'basic' ) {
			playerAdapter.onReady( function () {
				applyPlayerFeatures( el, playerAdapter );
			} );
			return;
		}

		// Standard / strict: full treatment — start session (tracking + watermark
		// + milestones) and apply player features after the adapter is ready.
		playerAdapter.onReady( function () {
			applyPlayerFeatures( el, playerAdapter );
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

				// Concurrent-stream limit (HTTP 429) — show the operator-configured
				// message inline so the viewer knows why the player is idle. The
				// REST handler returns the localised text in `data.message`.
				if ( status === 429 || data.code === 'concurrent_limit' ) {
					showErrorOverlay( el, data.message || 'Too many active streams. Please close another video first.' );
					window.dispatchEvent( new CustomEvent( 'mediashield:concurrent-limit', {
						bubbles: true,
						detail: { el: el, videoId: videoId, message: data.message },
					} ) );
					return;
				}

				// Handle access-denied responses (403 or error codes).
				if ( status === 403 || data.code === 'access_denied' || data.code === 'login_required' ) {
					var reason = data.code || 'access_denied';

					// Show login overlay for login_required when user is not logged in.
					if ( reason === 'login_required' && ! config.isLoggedIn ) {
						showLoginOverlay( el );
						return;
					}

					// Give upstream listeners (custom access integrations) the
					// chance to handle the denial BEFORE the generic error overlay
					// paints — otherwise their own UI renders on top of a redundant
					// denial overlay. Listeners suppress the fallback by calling
					// event.preventDefault().
					var ev = new CustomEvent( 'mediashield:access-denied', {
						bubbles: true,
						cancelable: true,
						detail: { el: el, videoId: videoId, reason: reason },
					} );
					var handled = ! window.dispatchEvent( ev );

					if ( ! handled ) {
						var msg = data.message || ( config.messages && config.messages.accessDenied ) || 'You do not have access to this video.';
						showErrorOverlay( el, msg );
					}
					return;
				}

				if ( data.session_token ) {
					el.dataset.sessionToken = data.session_token;

					// Resume position via adapter — only when Resume Playback is
					// enabled for this video. Server already gates the position
					// (returns 0 when disabled); this is the matching client guard
					// so the prompt respects the per-video override / global setting
					// consistently with the other player features.
					if ( data.resume_position > 0 && resumeEnabled( el ) ) {
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

	// ─── Error Overlay (generic) ────────────────────────────────
	//
	// Used for non-recoverable session-start errors: concurrent stream limit,
	// access denied when no upstream listener handles the dispatch, etc. Sits
	// on top of the player container so the viewer can read why the player
	// isn't loading. Single instance per container — repeated calls replace.
	function showErrorOverlay( el, message ) {
		var existing = el.querySelector( '.ms-error-overlay' );
		if ( existing ) existing.remove();

		var overlay = document.createElement( 'div' );
		overlay.className = 'ms-error-overlay';
		overlay.setAttribute( 'role', 'alert' );
		overlay.setAttribute( 'aria-live', 'polite' );

		var inner = document.createElement( 'div' );
		inner.className = 'ms-error-message';

		var text = document.createElement( 'p' );
		text.textContent = String( message || '' );

		inner.appendChild( text );
		overlay.appendChild( inner );
		el.appendChild( overlay );
	}

	// ─── Login Overlay ───────────────────────────────────────────

	function showLoginOverlay( el ) {
		var messages = config.messages || {};

		var overlay = document.createElement( 'div' );
		overlay.className = 'ms-login-overlay';
		overlay.setAttribute( 'role', 'dialog' );
		overlay.setAttribute( 'aria-modal', 'true' );
		// Screen-reader label mirrors the admin-configured overlay text so it
		// stays in sync with the visible message (not a separate hardcoded key).
		overlay.setAttribute( 'aria-label', messages.loginOverlay || 'Login required' );

		var message = document.createElement( 'div' );
		message.className = 'ms-login-message';

		var text = document.createElement( 'p' );
		text.textContent = messages.loginOverlay || 'Please log in to watch this video.';

		var link = document.createElement( 'a' );
		link.href = config.loginUrl || '/wp-login.php';
		link.className = 'ms-login-button';
		link.textContent = messages.loginButton || 'Log In';

		message.appendChild( text );
		message.appendChild( link );
		overlay.appendChild( message );
		el.appendChild( overlay );

		// Focus the login link for keyboard accessibility.
		link.focus();

		// Focus trap: keep Tab within the overlay.
		overlay.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' ) {
				overlay.remove();
				return;
			}

			if ( e.key !== 'Tab' ) return;

			var focusable = overlay.querySelectorAll( 'a, button, input, [tabindex]:not([tabindex="-1"])' );
			if ( focusable.length === 0 ) return;

			var first = focusable[0];
			var last = focusable[ focusable.length - 1 ];

			if ( e.shiftKey ) {
				if ( document.activeElement === first ) {
					e.preventDefault();
					last.focus();
				}
			} else {
				if ( document.activeElement === last ) {
					e.preventDefault();
					first.focus();
				}
			}
		} );
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
