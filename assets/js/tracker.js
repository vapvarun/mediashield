/**
 * MediaShield Tracker — 30-second heartbeat session tracking, reporting the
 * stretches of video actually played so "watched" is time spent watching.
 *
 * Reads position/duration from el._msAdapter (platform adapter) instead
 * of DOM queries. Works with YouTube, Vimeo, Wistia, Bunny, and self-hosted.
 *
 * @package MediaShield
 */
(function () {
	'use strict';

	var config = window.mediashieldConfig || {};
	var activeSessions = [];

	window.addEventListener( 'mediashield:player-ready', function ( e ) {
		var detail = e.detail;
		if ( ! detail.token || ! detail.el ) return;

		startTracking( detail.el, detail.token, detail.video, detail.adapter, detail.watch );
	} );

	/**
	 * Start heartbeat tracking for a player.
	 */
	function startTracking( el, token, video, adapter, watch ) {
		var duration = ( video && video.duration ) || 0;
		var intervalMs = config.interval || 30000;

		var session = {
			el: el,
			token: token,
			duration: duration,
			adapter: adapter || el._msAdapter || null,
			intervalId: null,
			// Stretches of the video played since the last report (Basecamp
			// 10530930729): "watched" is time watched, so a jump ahead starts
			// a new stretch and the skipped part is never reported.
			spans: [],
			open: null,
			lastPos: null,
			watchedPct: watch ? watch.watched_pct : null,
			// The lesson's own % when the LMS placed one on the player
			// (data-watch-required), else the site-wide one.
			requiredPct: lessonRequired( el ) || ( watch ? watch.required_pct : 0 ),
			earlySent: false,
		};

		session.sampleId = setInterval( function () {
			sample( session );
		}, 1000 );

		session.intervalId = setInterval( function () {
			sendHeartbeat( session );
		}, intervalMs );

		activeSessions.push( session );
	}

	/**
	 * The % a lesson asks for on this player, 0 when none: the LMS passes it
	 * as [mediashield watch_required="N"] (Learnomy 2.4.1+).
	 *
	 * @param {HTMLElement} el Player container.
	 * @return {number}
	 */
	function lessonRequired( el ) {
		var n = el && el.dataset ? parseInt( el.dataset.watchRequired, 10 ) : 0;
		return n > 0 ? Math.min( 100, n ) : 0;
	}

	/**
	 * Once a second: extend the stretch being played, or close it on a pause
	 * or a jump. A step of more than 2.5 s in one second is a seek, not play.
	 */
	function sample( session ) {
		var pos = getPlayerPosition( session.el, session.adapter ) || 0;
		var playing = isPlaying( session.el, session.adapter );
		var last = session.lastPos;
		session.lastPos = pos;
		if ( ! playing || null === last || pos <= last || pos - last > 2.5 ) {
			// Paused since the last sample: keep the part played before it.
			if ( ! playing && session.open && pos > last && pos - last <= 2.5 ) {
				session.open[ 1 ] = pos;
			}
			closeSpan( session );
			if ( playing ) session.open = [ pos, pos ];
			return;
		}
		if ( ! session.open ) session.open = [ last, pos ];
		session.open[ 1 ] = pos;

		// With "Require X% watched" on, report as soon as the member gets
		// there, so the lesson's Complete turns on without waiting for the
		// next 30-second beat. Once per session.
		if ( session.requiredPct && null !== session.watchedPct && ! session.earlySent && session.duration > 0 ) {
			var pending = pendingSeconds( session );
			if ( session.watchedPct + ( pending / session.duration ) * 100 >= session.requiredPct ) {
				session.earlySent = true;
				sendHeartbeat( session );
			}
		}
	}

	function closeSpan( session ) {
		if ( session.open && session.open[ 1 ] > session.open[ 0 ] ) {
			session.spans.push( [ session.open[ 0 ], session.open[ 1 ] ] );
		}
		session.open = null;
	}

	function pendingSeconds( session ) {
		var total = session.open ? session.open[ 1 ] - session.open[ 0 ] : 0;
		session.spans.forEach( function ( s ) { total += s[ 1 ] - s[ 0 ]; } );
		return total;
	}

	/**
	 * The stretches to report now; the one still playing carries on from here.
	 */
	function takeSpans( session ) {
		var out = session.spans.slice();
		if ( session.open && session.open[ 1 ] > session.open[ 0 ] ) {
			out.push( [ session.open[ 0 ], session.open[ 1 ] ] );
			session.open = [ session.open[ 1 ], session.open[ 1 ] ];
		}
		session.spans = [];
		return out.map( function ( s ) {
			return [ Math.round( s[ 0 ] * 10 ) / 10, Math.round( s[ 1 ] * 10 ) / 10 ];
		} );
	}

	/**
	 * Send a heartbeat to the REST API.
	 */
	function sendHeartbeat( session ) {
		var position = getPlayerPosition( session.el, session.adapter );
		var duration = session.duration || getPlayerDuration( session.el, session.adapter );
		var playing = isPlaying( session.el, session.adapter );
		var focused = document.hasFocus();

		if ( duration > 0 ) {
			session.duration = duration;
		}

		var sent = takeSpans( session );
		// Not stored (dropped connection, 5xx, rate limit): report them next time.
		var requeue = function () {
			session.spans = sent.concat( session.spans );
		};

		fetch( config.restUrl + 'session/heartbeat', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
			body: JSON.stringify( {
				token: session.token,
				position: position,
				duration: duration,
				playing: playing,
				focused: focused,
				spans: sent,
			} ),
		} )
			.then( function ( res ) {
				if ( res.ok ) {
					session.failCount = 0;
					window.mediashieldRequest.readJSON( res.clone() ).then( function ( data ) {
						if ( data && 'number' === typeof data.watched_pct ) {
							session.watchedPct = data.watched_pct;
							session.requiredPct = lessonRequired( session.el ) || data.required_pct || 0;
							// Still short after the early beat: allow another.
							session.earlySent = session.watchedPct >= session.requiredPct;
						}
					// ms-silent-ok: the share is only a hint for an early beat.
					} ).catch( function () {} );
					return;
				}
				// A 5xx is the server having a moment, like a dropped
				// connection: keep the 30 s beat so progress is recorded once
				// it answers again. Only refusals count towards stopping.
				if ( res.status >= 500 || 429 === res.status ) {
					requeue();
					console.warn( 'MediaShield: heartbeat not answered (' + res.status + '), will retry' );
					return;
				}
				session.failCount = ( session.failCount || 0 ) + 1;
				if ( session.failCount >= 3 ) {
					console.warn( 'MediaShield: heartbeat refused 3 times, stopping for token ' + session.token );
					session.stopped = true;
					if ( session.intervalId ) {
						clearInterval( session.intervalId );
						session.intervalId = null;
					}
				}
			} )
			.catch( function ( err ) {
				// Dropped connection: transient, the next beat tries again.
				// Stopping here lost every later position of the viewing.
				requeue();
				console.warn( 'MediaShield: heartbeat failed, will retry', err );
			} );
	}

	/**
	 * Get playback position — uses adapter API or falls back to DOM.
	 */
	function getPlayerPosition( el, adapter ) {
		if ( adapter && adapter.getPosition ) return adapter.getPosition();
		if ( el._msAdapter && el._msAdapter.getPosition ) return el._msAdapter.getPosition();
		var video = el.querySelector( 'video' );
		return video ? video.currentTime || 0 : 0;
	}

	/**
	 * Get video duration — uses adapter API or falls back to DOM.
	 */
	function getPlayerDuration( el, adapter ) {
		if ( adapter && adapter.getDuration ) return adapter.getDuration();
		if ( el._msAdapter && el._msAdapter.getDuration ) return el._msAdapter.getDuration();
		var video = el.querySelector( 'video' );
		return video && isFinite( video.duration ) ? video.duration : 0;
	}

	/**
	 * Check if playing — uses adapter API or falls back to DOM.
	 */
	function isPlaying( el, adapter ) {
		if ( adapter && adapter.isPlaying ) return adapter.isPlaying();
		if ( el._msAdapter && el._msAdapter.isPlaying ) return el._msAdapter.isPlaying();
		var video = el.querySelector( 'video' );
		return video ? ! video.paused && ! video.ended : false;
	}

	/**
	 * The session/end body: the token plus the place reached, so a viewer who
	 * leaves before the first heartbeat still has a place to resume.
	 */
	function finalBeat( session ) {
		var duration = session.duration || getPlayerDuration( session.el, session.adapter ) || 0;
		return {
			token: session.token,
			position: getPlayerPosition( session.el, session.adapter ) || 0,
			duration: duration,
			spans: takeSpans( session ),
		};
	}

	/**
	 * End all sessions on page unload.
	 */
	function endAllSessions() {
		activeSessions.forEach( function ( session ) {
			if ( session.intervalId ) clearInterval( session.intervalId );
			if ( session.sampleId ) clearInterval( session.sampleId );

			var data = JSON.stringify( finalBeat( session ) );

			if ( navigator.sendBeacon ) {
				var blob = new Blob( [ data ], { type: 'application/json' } );
				navigator.sendBeacon(
					config.restUrl + 'session/end?_wpnonce=' + encodeURIComponent( config.nonce ),
					blob
				);
			}
		} );

		activeSessions = [];
	}

	/**
	 * Pause all heartbeat intervals without ending sessions.
	 */
	function pauseAllHeartbeats() {
		activeSessions.forEach( function ( session ) {
			if ( session.intervalId ) {
				clearInterval( session.intervalId );
				session.intervalId = null;
			}
		} );
	}

	/**
	 * Resume heartbeat intervals for all active sessions.
	 */
	function resumeAllHeartbeats() {
		var intervalMs = config.interval || 30000;

		activeSessions.forEach( function ( session ) {
			if ( ! session.intervalId && ! session.stopped ) {
				session.intervalId = setInterval( function () {
					sendHeartbeat( session );
				}, intervalMs );
			}
		} );
	}

	// One player is going away while the page stays (a playlist moved to its
	// next video): close that session now instead of leaving it to time out.
	window.addEventListener( 'mediashield:player-destroy', function ( e ) {
		var el = e.detail && e.detail.el;
		activeSessions = activeSessions.filter( function ( session ) {
			if ( session.el !== el ) return true;

			if ( session.intervalId ) clearInterval( session.intervalId );
			if ( session.sampleId ) clearInterval( session.sampleId );
			fetch( config.restUrl + 'session/end', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
				body: JSON.stringify( finalBeat( session ) ),
				keepalive: true,
			// ms-silent-ok: the page is unloading; nobody is left to tell.
			} ).catch( function () {} );
			return false;
		} );
	} );

	window.addEventListener( 'beforeunload', endAllSessions );

	window.addEventListener( 'pagehide', endAllSessions );

	document.addEventListener( 'visibilitychange', function () {
		if ( document.visibilityState === 'hidden' ) {
			pauseAllHeartbeats();
		} else {
			resumeAllHeartbeats();
		}
	} );
})();
