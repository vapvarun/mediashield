/**
 * MediaShield Tracker — 30-second heartbeat session tracking.
 *
 * Sends playback position to REST API at configurable intervals.
 * Uses navigator.sendBeacon on page unload for session end.
 *
 * @package MediaShield
 */
(function () {
	'use strict';

	var config = window.mediashieldConfig || {};
	var activeSessions = [];

	window.addEventListener('mediashield:player-ready', function (e) {
		var detail = e.detail;
		if (!detail.token || !detail.el) return;

		startTracking(detail.el, detail.token, detail.video);
	});

	/**
	 * Start heartbeat tracking for a player.
	 *
	 * @param {HTMLElement} el    Player container.
	 * @param {string}      token Session token.
	 * @param {Object}      video Video metadata.
	 */
	function startTracking(el, token, video) {
		var duration = (video && video.duration) || 0;
		var intervalMs = config.interval || 30000;

		var session = {
			el: el,
			token: token,
			duration: duration,
			intervalId: null,
		};

		session.intervalId = setInterval(function () {
			sendHeartbeat(session);
		}, intervalMs);

		activeSessions.push(session);
	}

	/**
	 * Send a heartbeat to the REST API.
	 *
	 * @param {Object} session Session object.
	 */
	function sendHeartbeat(session) {
		var position = getPlayerPosition(session.el);
		var duration = session.duration || getPlayerDuration(session.el);
		var playing = isPlaying(session.el);
		var focused = document.hasFocus();

		// Update duration if we got it from the player.
		if (duration > 0) {
			session.duration = duration;
		}

		fetch(config.restUrl + 'session/heartbeat', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': config.nonce,
			},
			body: JSON.stringify({
				token: session.token,
				position: position,
				duration: duration,
				playing: playing,
				focused: focused,
			}),
		}).catch(function (err) {
			console.warn('MediaShield: heartbeat failed', err);
		});
	}

	/**
	 * Get current playback position from a player element.
	 *
	 * @param {HTMLElement} el Player container.
	 * @return {number} Position in seconds.
	 */
	function getPlayerPosition(el) {
		var video = el.querySelector('video');
		if (video) {
			return video.currentTime || 0;
		}
		return 0;
	}

	/**
	 * Get video duration from a player element.
	 *
	 * @param {HTMLElement} el Player container.
	 * @return {number} Duration in seconds.
	 */
	function getPlayerDuration(el) {
		var video = el.querySelector('video');
		if (video && video.duration && isFinite(video.duration)) {
			return video.duration;
		}
		return 0;
	}

	/**
	 * Check if the player is currently playing.
	 *
	 * @param {HTMLElement} el Player container.
	 * @return {boolean}
	 */
	function isPlaying(el) {
		var video = el.querySelector('video');
		if (video) {
			return !video.paused && !video.ended;
		}
		// For iframes, assume playing if visible.
		return true;
	}

	/**
	 * End all sessions on page unload using sendBeacon.
	 */
	function endAllSessions() {
		activeSessions.forEach(function (session) {
			if (session.intervalId) {
				clearInterval(session.intervalId);
			}

			var data = JSON.stringify({ token: session.token });

			// sendBeacon is fire-and-forget, works during unload.
			if (navigator.sendBeacon) {
				var blob = new Blob([data], { type: 'application/json' });
				navigator.sendBeacon(
					config.restUrl + 'session/end?_wpnonce=' + encodeURIComponent(config.nonce),
					blob
				);
			}
		});

		activeSessions = [];
	}

	window.addEventListener('beforeunload', endAllSessions);
	document.addEventListener('visibilitychange', function () {
		if (document.visibilityState === 'hidden') {
			endAllSessions();
		}
	});
})();
