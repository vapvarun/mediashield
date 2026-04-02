/**
 * MediaShield Tracker — 30-second heartbeat session tracking.
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

		startTracking( detail.el, detail.token, detail.video, detail.adapter );
	} );

	/**
	 * Start heartbeat tracking for a player.
	 */
	function startTracking( el, token, video, adapter ) {
		var duration = ( video && video.duration ) || 0;
		var intervalMs = config.interval || 30000;

		var session = {
			el: el,
			token: token,
			duration: duration,
			adapter: adapter || el._msAdapter || null,
			intervalId: null,
		};

		session.intervalId = setInterval( function () {
			sendHeartbeat( session );
		}, intervalMs );

		activeSessions.push( session );
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

		fetch( config.restUrl + 'session/heartbeat', {
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
			body: JSON.stringify( {
				token: session.token,
				position: position,
				duration: duration,
				playing: playing,
				focused: focused,
			} ),
		} )
			.then( function ( res ) {
				if ( res.ok ) {
					session.failCount = 0;
				} else {
					session.failCount = ( session.failCount || 0 ) + 1;
					if ( session.failCount >= 3 ) {
						console.warn( 'MediaShield: heartbeat failed 3 times, stopping for token ' + session.token );
						session.stopped = true;
						if ( session.intervalId ) {
							clearInterval( session.intervalId );
							session.intervalId = null;
						}
					}
				}
			} )
			.catch( function ( err ) {
				session.failCount = ( session.failCount || 0 ) + 1;
				console.warn( 'MediaShield: heartbeat failed', err );
				if ( session.failCount >= 3 ) {
					console.warn( 'MediaShield: heartbeat failed 3 times, stopping for token ' + session.token );
					session.stopped = true;
					if ( session.intervalId ) {
						clearInterval( session.intervalId );
						session.intervalId = null;
					}
				}
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
	 * End all sessions on page unload.
	 */
	function endAllSessions() {
		activeSessions.forEach( function ( session ) {
			if ( session.intervalId ) clearInterval( session.intervalId );

			var data = JSON.stringify( { token: session.token } );

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
