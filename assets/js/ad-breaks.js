/**
 * MediaShield In-Video Ad Breaks
 *
 * YouTube / Prime-style ad insertion: pre-roll, mid-rolls (at timecodes), and
 * post-roll. MediaShield owns the *engine* (pause the main video, overlay the
 * ad, count down, optionally allow skip, resume); the ad *creatives* come from
 * whatever ad plugin the site runs — they are rendered server-side and handed
 * to the player as HTML on `data-ad-breaks` (see Player\Renderer +
 * `mediashield_ad_break_creative` filter). This keeps MediaShield decoupled
 * from any specific ad plugin.
 *
 * Requires a controllable adapter (native <video> or an API adapter with
 * getPosition/seekTo/play/pause). The opaque Bunny *iframe* embed cannot be
 * paused/resumed for mid-rolls, so ad breaks only engage when the video plays
 * through the native HLS path (see player-wrapper.js bunny case).
 *
 * Wiring: listens for the `mediashield:player-ready` event that
 * player-wrapper.js dispatches once the adapter + session are ready.
 *
 * @package MediaShield
 */
( function () {
	'use strict';

	// A mid-roll fires once its time is reached; tolerance avoids missing the
	// exact frame between timeupdate ticks.
	var MID_TOLERANCE = 1.0;

	/**
	 * Record an in-video ad impression / click into WB Ad Manager analytics,
	 * via the same admin-ajax endpoint the rest of the ad stack uses. Config is
	 * localised by MediaShield's bridge as `window.mediashieldAds`. No-op when
	 * the ad plugin / config is absent.
	 *
	 * @param {number} adId      WB Ad Manager ad post ID.
	 * @param {string} eventType 'impression' | 'click'.
	 */
	function trackAd( adId, eventType ) {
		var cfg = window.mediashieldAds;
		if ( ! cfg || ! cfg.ajaxUrl || ! adId ) {
			return;
		}
		var body = new URLSearchParams();
		body.set( 'action', cfg.action || 'wbam_track_event' );
		body.set( 'nonce', cfg.nonce || '' );
		body.set( 'ad_id', adId );
		body.set( 'event_type', eventType );
		body.set( 'placement', cfg.placement || 'mediashield_video' );
		try {
			// keepalive lets the request finish even when a click navigates the
			// page away; credentials carry the cookie the nonce check needs.
			fetch( cfg.ajaxUrl, { method: 'POST', body: body, credentials: 'same-origin', keepalive: true } );
		} catch ( e ) { /* tracking is best-effort */ }
	}

	window.addEventListener( 'mediashield:player-ready', function ( e ) {
		var detail  = e.detail || {};
		var el      = detail.el;
		var adapter = detail.adapter;
		if ( ! el || ! adapter ) {
			return;
		}

		var breaks = parseBreaks( el );
		if ( ! breaks.length ) {
			return;
		}

		// The engine needs to be able to pause + resume the underlying video.
		// The opaque iframe adapter (Bunny embed) exposes no-op play/pause, so
		// mid-rolls would pause nothing — only run once the adapter is
		// controllable (native <video>, or an API adapter once its handshake
		// lands and sets _player/_pjs).
		//
		// Race: player-wrapper dispatches `player-ready` as soon as the watch
		// session starts, which can WIN the race against the player.js handshake
		// (it has its own 4s safety fallback). When that happens, _pjs is still
		// null at this instant but appears a moment later. So we don't bail on
		// the first miss — we poll briefly for the adapter to become
		// controllable, and only give up if it never does.
		var isControllable = function () {
			return !! ( adapter._video || adapter._player || adapter._pjs );
		};

		if ( isControllable() ) {
			new AdController( el, adapter, breaks ).start();
			return;
		}

		var waited   = 0;
		var graceId  = setInterval( function () {
			waited += 250;
			if ( isControllable() ) {
				clearInterval( graceId );
				new AdController( el, adapter, breaks ).start();
			} else if ( waited >= 15000 ) {
				clearInterval( graceId );
				if ( window.console && console.info ) {
					console.info( 'MediaShield: in-video ad breaks need a controllable player (native HLS / API). Skipping on opaque embed.' );
				}
			}
		}, 250 );
	} );

	/**
	 * Read + normalise the ad-break list from the player element.
	 */
	function parseBreaks( el ) {
		var raw = el.dataset.adBreaks || '';
		if ( ! raw ) {
			return [];
		}
		var list;
		try {
			list = JSON.parse( raw );
		} catch ( err ) {
			return [];
		}
		if ( ! Array.isArray( list ) ) {
			return [];
		}
		return list
			.filter( function ( b ) {
				return b && b.html && ( b.type === 'pre' || b.type === 'mid' || b.type === 'post' );
			} )
			.map( function ( b, i ) {
				return {
					id:        b.id || ( 'break-' + i ),
					// The WB Ad Manager ad post ID — carried through so impression
					// and click events track against the right ad. Must not be
					// dropped here or trackAd() receives undefined and no-ops.
					adId:      b.adId || 0,
					// Sponsor / ad name — labels the timeline marker so the
					// operator can see which ad runs at which position.
					label:     b.label ? String( b.label ) : '',
					type:      b.type,
					at:        b.type === 'mid' ? Math.max( 0, parseFloat( b.at ) || 0 ) : 0,
					skipAfter: ( typeof b.skipAfter === 'number' && b.skipAfter >= 0 ) ? b.skipAfter : null,
					html:      String( b.html ),
					played:    false,
				};
			} )
			.sort( function ( a, b ) { return a.at - b.at; } );
	}

	/**
	 * Drives ad playback for one player.
	 *
	 * @param {HTMLElement} el      The .ms-protected-player container.
	 * @param {Object}      adapter The platform adapter (getPosition/seekTo/play/pause).
	 * @param {Array}       breaks  Normalised ad breaks.
	 */
	function AdController( el, adapter, breaks ) {
		this.el      = el;
		this.adapter = adapter;
		this.breaks  = breaks;
		this.pre     = breaks.filter( function ( b ) { return b.type === 'pre'; } );
		this.mid     = breaks.filter( function ( b ) { return b.type === 'mid'; } );
		this.post    = breaks.filter( function ( b ) { return b.type === 'post'; } );
		this.inAd    = false;
		this.pollId  = null;
		this.markers = null;
	}

	AdController.prototype.start = function () {
		var self = this;

		// Mark every ad position on a slim timeline under the player (YouTube /
		// Prime style) so the operator and viewer can see where ads run.
		self.renderMarkers();

		// Pre-roll: hold the main video until the pre ads are done.
		if ( self.pre.length ) {
			self.adapter.pause();
			self.playQueue( self.pre.slice(), function () {
				self.adapter.play();
				self.watchMidrolls();
			} );
		} else {
			self.watchMidrolls();
		}

		// Post-roll: when the main video ends.
		if ( self.post.length && self.adapter.onEnded ) {
			self.adapter.onEnded( function () {
				if ( self.post.every( function ( b ) { return b.played; } ) ) {
					return;
				}
				self.playQueue( self.post.slice(), function () {} );
			} );
		}
	};

	/**
	 * Format seconds as M:SS (or H:MM:SS) for marker labels.
	 */
	function fmtTime( s ) {
		s = Math.max( 0, Math.floor( s ) );
		var h = Math.floor( s / 3600 );
		var m = Math.floor( ( s % 3600 ) / 60 );
		var sec = s % 60;
		var pad = function ( n ) { return ( n < 10 ? '0' : '' ) + n; };
		return h > 0 ? h + ':' + pad( m ) + ':' + pad( sec ) : m + ':' + pad( sec );
	}

	/**
	 * Render a slim ad-break timeline under the player: a track with the live
	 * playhead plus one marker per ad position (pre-roll at the start, each
	 * mid-roll at its timecode, post-roll at the end). Markers carry the
	 * sponsor name + timecode and flip to a "played" state once shown, so the
	 * operator can see at a glance where ads sit and which have run.
	 */
	AdController.prototype.renderMarkers = function () {
		var self = this;
		if ( ! self.breaks.length || self.markers ) {
			return;
		}
		// Operator can hide the ruler (ms_ads_show_markers).
		if ( window.mediashieldAds && window.mediashieldAds.showMarkers === false ) {
			return;
		}

		// Duration: prefer the live player value, fall back to the renderer's
		// data-duration so mid-roll markers can be placed even before the first
		// timeupdate tick lands.
		var target  = self.el.querySelector( '.ms-player-target' );
		var metaDur = target ? parseInt( target.dataset.duration, 10 ) || 0 : 0;

		var wrap = document.createElement( 'div' );
		wrap.className = 'ms-ad-timeline';

		var caption = document.createElement( 'span' );
		caption.className = 'ms-ad-timeline__caption';
		caption.textContent = 'Ad breaks';
		wrap.appendChild( caption );

		var track = document.createElement( 'div' );
		track.className = 'ms-ad-timeline__track';

		var progress = document.createElement( 'div' );
		progress.className = 'ms-ad-timeline__progress';
		track.appendChild( progress );

		// One marker per break.
		self.breaks.forEach( function ( b ) {
			var dur = self.adapter.getDuration ? ( self.adapter.getDuration() || metaDur ) : metaDur;
			var pct;
			if ( b.type === 'pre' ) {
				pct = 0;
			} else if ( b.type === 'post' ) {
				pct = 100;
			} else {
				pct = dur > 0 ? Math.min( 100, Math.max( 0, ( b.at / dur ) * 100 ) ) : 0;
			}

			var mark = document.createElement( 'span' );
			mark.className = 'ms-ad-timeline__marker ms-ad-timeline__marker--' + b.type;
			mark.style.left = pct + '%';
			var when = b.type === 'pre' ? 'Pre-roll' : ( b.type === 'post' ? 'Post-roll' : fmtTime( b.at ) );
			mark.setAttribute( 'title', 'Ad' + ( b.label ? ' · ' + b.label : '' ) + ' · ' + when );
			mark.setAttribute( 'data-label', ( b.label || 'Ad' ) + ' · ' + when );
			b._marker = mark;
			track.appendChild( mark );
		} );

		wrap.appendChild( track );

		// Insert directly after the player container so it reads as the player's
		// own ad-break ruler.
		if ( self.el.parentNode ) {
			self.el.parentNode.insertBefore( wrap, self.el.nextSibling );
		} else {
			self.el.appendChild( wrap );
		}
		self.markers = { wrap: wrap, progress: progress, metaDur: metaDur };

		// Keep the playhead + marker placement + played-state in sync.
		setInterval( function () {
			var dur = ( self.adapter.getDuration ? self.adapter.getDuration() : 0 ) || self.markers.metaDur;
			var pos = self.adapter.getPosition ? self.adapter.getPosition() : 0;
			if ( dur > 0 ) {
				self.markers.progress.style.width = Math.min( 100, ( pos / dur ) * 100 ) + '%';
				// Re-place mid markers once a real duration is known.
				self.breaks.forEach( function ( b ) {
					if ( b.type === 'mid' && b._marker ) {
						b._marker.style.left = Math.min( 100, ( b.at / dur ) * 100 ) + '%';
					}
				} );
			}
			self.breaks.forEach( function ( b ) {
				if ( b.played && b._marker && ! b._marker.classList.contains( 'is-played' ) ) {
					b._marker.classList.add( 'is-played' );
				}
			} );
		}, 1000 );
	};

	/**
	 * Poll playback position and fire any due mid-roll.
	 */
	AdController.prototype.watchMidrolls = function () {
		var self = this;
		if ( ! self.mid.length ) {
			return;
		}
		self.pollId = setInterval( function () {
			if ( self.inAd ) {
				return;
			}
			var pos = self.adapter.getPosition ? self.adapter.getPosition() : 0;
			for ( var i = 0; i < self.mid.length; i++ ) {
				var b = self.mid[ i ];
				if ( ! b.played && pos >= b.at && pos < b.at + 5 + MID_TOLERANCE ) {
					// Remember exactly where the lesson was so it resumes from
					// there after the ad, never from the beginning.
					var resumeAt = pos;
					self.adapter.pause();
					self.playQueue( [ b ], function () {
						if ( self.adapter.seekTo ) { self.adapter.seekTo( resumeAt ); }
						self.adapter.play();
					} );
					break;
				}
			}
		}, 500 );
	};

	/**
	 * Play a queue of ad breaks one after another, then call done().
	 */
	AdController.prototype.playQueue = function ( queue, done ) {
		var self = this;
		if ( ! queue.length ) {
			done();
			return;
		}
		var b = queue.shift();
		self.showAd( b, function () {
			b.played = true;
			self.playQueue( queue, done );
		} );
	};

	/**
	 * Render one ad overlay with a countdown + optional skip, then onComplete().
	 */
	AdController.prototype.showAd = function ( b, onComplete ) {
		var self = this;
		self.inAd = true;

		var overlay = document.createElement( 'div' );
		overlay.className = 'ms-ad-overlay';
		overlay.setAttribute( 'role', 'dialog' );
		overlay.setAttribute( 'aria-label', 'Advertisement' );

		var label = document.createElement( 'span' );
		label.className = 'ms-ad-overlay__label';
		label.textContent = 'Ad';
		overlay.appendChild( label );

		var body = document.createElement( 'div' );
		body.className = 'ms-ad-overlay__body';
		// Creative HTML is produced + escaped server-side by the ad plugin via
		// the `mediashield_ad_break_creative` filter; we inject it as-is.
		body.innerHTML = b.html;
		// Record a click when the viewer interacts with the creative.
		body.addEventListener( 'click', function () { trackAd( b.adId, 'click' ); } );
		overlay.appendChild( body );

		var bar = document.createElement( 'div' );
		bar.className = 'ms-ad-overlay__bar';
		overlay.appendChild( bar );

		var skipBtn = document.createElement( 'button' );
		skipBtn.type = 'button';
		skipBtn.className = 'ms-ad-overlay__skip';
		bar.appendChild( skipBtn );

		self.el.appendChild( overlay );

		// Record the impression the moment the ad actually plays (not at page
		// render) so mid-roll counts reflect real views.
		trackAd( b.adId, 'impression' );

		// Resolve the ad's running length from its creative (a <video> ad) or a
		// fixed display time for an HTML/image ad.
		var adVideo  = body.querySelector( 'video' );
		var duration = adVideo && isFinite( adVideo.duration ) ? adVideo.duration : 12;

		// Mandatory viewing (ms_ads_require_full_view): no Skip button at all —
		// the viewer must watch the whole ad before the lesson resumes. Otherwise
		// honour the per-break skip delay.
		var forceFull = !! ( window.mediashieldAds && window.mediashieldAds.requireFullView );
		var skipAt    = forceFull ? null : ( ( b.skipAfter !== null ) ? b.skipAfter : null );

		var finished = false;
		function finish() {
			if ( finished ) { return; }
			finished = true;
			clearInterval( countId );
			if ( overlay.parentNode ) { overlay.remove(); }
			self.inAd = false;
			onComplete();
		}

		// Auto-play a video creative; HTML/image ads just count down.
		if ( adVideo ) {
			adVideo.addEventListener( 'ended', finish );
			// A broken / unreachable creative must never lock the lesson behind a
			// no-skip ad — release on error and via a hard backstop.
			adVideo.addEventListener( 'error', finish );
			setTimeout( finish, 90000 );
			adVideo.play().catch( function () {
				// Sound-autoplay blocked — retry muted so a mandatory ad still
				// plays through to completion without needing a click.
				adVideo.muted = true;
				adVideo.play().catch( function () {} );
			} );
		}

		var elapsed = 0;
		var countId = setInterval( function () {
			elapsed += 1;
			var remain = Math.max( 0, Math.ceil( ( adVideo && isFinite( adVideo.duration ) ? adVideo.duration : duration ) - ( adVideo ? adVideo.currentTime : elapsed ) ) );
			if ( skipAt !== null && elapsed >= skipAt ) {
				skipBtn.disabled = false;
				skipBtn.textContent = 'Skip Ad →';
			} else if ( skipAt !== null ) {
				skipBtn.disabled = true;
				skipBtn.textContent = 'Skip in ' + Math.max( 0, skipAt - elapsed ) + 's';
			} else {
				skipBtn.disabled = true;
				skipBtn.textContent = 'Ad · ' + remain + 's';
			}
			if ( ! adVideo && elapsed >= duration ) {
				finish();
			}
		}, 1000 );

		// Initialise the button label immediately.
		if ( skipAt !== null ) {
			skipBtn.disabled = skipAt > 0;
			skipBtn.textContent = skipAt > 0 ? ( 'Skip in ' + skipAt + 's' ) : 'Skip Ad →';
		} else {
			skipBtn.disabled = true;
			skipBtn.textContent = 'Ad';
		}
		skipBtn.addEventListener( 'click', function () {
			if ( ! skipBtn.disabled ) { finish(); }
		} );
	};
} )();
