/**
 * MediaShield Protection -- Anti-download measures.
 *
 * Blocks right-click, Ctrl+S/Cmd+S, loads video src via JS to prevent
 * easy source inspection. Adds "Protected by MediaShield" badge.
 * Detects DevTools via window-size delta and debugger timing; fires
 * `mediashield:devtools-detected` event and optionally pauses playback.
 *
 * @package MediaShield
 */
(function () {
	'use strict';

	var config = window.mediashieldConfig || {};
	var protConfig = config.protection || {};
	var devtoolsState = { detected: false, reportedSession: false };

	/**
	 * Initialize protection on all players.
	 */
	function init() {
		var players = document.querySelectorAll('.ms-protected-player');
		players.forEach(function (el) {
			if (el.dataset.protectionLevel === 'none') return;
			initProtection(el);
		});

		// Also init on dynamically added players.
		window.addEventListener('mediashield:player-ready', function (e) {
			if (e.detail && e.detail.el) {
				initProtection(e.detail.el);
			}
		});

		if (protConfig.detect_devtools !== false) {
			initDevtoolsDetection();
		}
	}

	/**
	 * Initialize protection on a single player.
	 *
	 * @param {HTMLElement} el Player container.
	 */
	function initProtection(el) {
		if (el.dataset.msProtected) return;
		el.dataset.msProtected = '1';

		// Block right-click.
		if (protConfig.block_right_click !== false) {
			el.addEventListener('contextmenu', function (e) {
				e.preventDefault();
				return false;
			});
		}

		// Add nodownload to video controls.
		var videos = el.querySelectorAll('video');
		videos.forEach(function (video) {
			video.setAttribute('controlsList', 'nodownload');

			// Hide source: move src to data attribute, load via JS.
			if (protConfig.hide_source !== false) {
				var src = video.getAttribute('src');
				if (src) {
					video.removeAttribute('src');
					video.dataset.msSrc = src;
					video.src = src; // Re-assign via JS (harder to find in "View Source").
				}
			}
		});

		// Add "Protected by MediaShield" badge.
		var wmConfig = config.watermark || {};
		if (wmConfig.show_badge !== false) {
			addBadge(el);
		}
	}

	/**
	 * Add the "Protected by MediaShield" badge to a player.
	 *
	 * @param {HTMLElement} el Player container.
	 */
	function addBadge(el) {
		if (el.querySelector('.ms-badge')) return;

		var badge = document.createElement('div');
		badge.className = 'ms-badge';
		badge.textContent = 'Protected by MediaShield';
		el.appendChild(badge);
	}

	/**
	 * Initialize DevTools detection.
	 *
	 * Two strategies run in parallel:
	 *   1. Size-delta check -- docked DevTools widen outerWidth/outerHeight
	 *      vs innerWidth/innerHeight. Threshold 200px avoids false positives
	 *      on browser scrollbars and OS chrome.
	 *   2. Debugger-timing check -- a `debugger;` statement is a no-op when
	 *      DevTools is closed; when DevTools is open, the browser pauses,
	 *      yielding a multi-hundred-millisecond gap we can measure.
	 *
	 * Mobile viewports and touch devices skip detection (too many false
	 * positives from on-screen keyboards and orientation changes).
	 */
	function initDevtoolsDetection() {
		// Skip on touch / small-screen devices.
		var isTouch = ('ontouchstart' in window) || (navigator.maxTouchPoints || 0) > 1;
		var isSmall = window.innerWidth < 1024;
		if (isTouch || isSmall) return;

		var sizeCheckMs = 1000;
		var debuggerCheckMs = 4000;
		var sizeThreshold = 200;

		// Strategy 1: size delta, debounced.
		var sizeTimer = null;
		function runSizeCheck() {
			var widthDelta = window.outerWidth - window.innerWidth;
			var heightDelta = window.outerHeight - window.innerHeight;
			if (widthDelta > sizeThreshold || heightDelta > sizeThreshold) {
				onDetected('size_delta');
			}
		}
		setInterval(runSizeCheck, sizeCheckMs);
		window.addEventListener('resize', function () {
			if (sizeTimer) clearTimeout(sizeTimer);
			sizeTimer = setTimeout(runSizeCheck, 300);
		});

		// Strategy 2: debugger-timing check.
		function runDebuggerCheck() {
			var start = performance.now();
			// eslint-disable-next-line no-debugger
			debugger;
			var elapsed = performance.now() - start;
			if (elapsed > 100) {
				onDetected('debugger_timing');
			}
		}
		setInterval(runDebuggerCheck, debuggerCheckMs);
	}

	/**
	 * Handle a DevTools detection event.
	 *
	 * Fires once per page load (subsequent fires are silent). Dispatches a
	 * CustomEvent for other scripts to react, reports to the server via
	 * sendBeacon, and optionally pauses video + shows an overlay.
	 *
	 * @param {string} strategy The detection strategy that fired.
	 */
	function onDetected(strategy) {
		if (devtoolsState.detected) return;
		devtoolsState.detected = true;

		// Dispatch CustomEvent -- other scripts (tracker, player) may react.
		try {
			window.dispatchEvent(
				new CustomEvent('mediashield:devtools-detected', { detail: { strategy: strategy } })
			);
		} catch (err) { /* ignore */ }

		// Report to server (rate-limited server-side; one report per session).
		reportToServer(strategy);

		// Optional: pause video + show overlay.
		if (protConfig.pause_on_devtools === true) {
			pauseAllVideos();
			showOverlay();
		}
	}

	/**
	 * Report detection event to server via sendBeacon.
	 *
	 * @param {string} strategy Detection strategy.
	 */
	function reportToServer(strategy) {
		if (devtoolsState.reportedSession) return;
		devtoolsState.reportedSession = true;

		var restUrl = config.restUrl || '';
		var nonce = config.nonce || '';
		var endpoint = restUrl ? restUrl + 'protection/devtools-event' : null;
		if (!endpoint) return;

		var payload = JSON.stringify({
			strategy: strategy,
			url: window.location.href,
			ua: navigator.userAgent,
			screen: window.innerWidth + 'x' + window.innerHeight
		});

		try {
			if (navigator.sendBeacon) {
				var blob = new Blob([payload], { type: 'application/json' });
				var url = endpoint + (endpoint.indexOf('?') === -1 ? '?' : '&') + '_wpnonce=' + encodeURIComponent(nonce);
				navigator.sendBeacon(url, blob);
			} else {
				fetch(endpoint, {
					method: 'POST',
					credentials: 'same-origin',
					headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce },
					body: payload,
					keepalive: true
				}).catch(function () { /* ignore */ });
			}
		} catch (err) { /* ignore */ }
	}

	/**
	 * Pause all MediaShield-protected videos on the page.
	 */
	function pauseAllVideos() {
		document.querySelectorAll('.ms-protected-player video').forEach(function (v) {
			try { v.pause(); } catch (err) { /* ignore */ }
		});
	}

	/**
	 * Show the DevTools-detected overlay using DOM methods (no innerHTML).
	 */
	function showOverlay() {
		if (document.querySelector('.ms-devtools-overlay')) return;

		var overlay = document.createElement('div');
		overlay.className = 'ms-devtools-overlay';
		overlay.setAttribute('role', 'dialog');
		overlay.setAttribute('aria-modal', 'true');

		var inner = document.createElement('div');
		inner.className = 'ms-devtools-overlay__inner';

		var h = document.createElement('h3');
		h.textContent = protConfig.devtools_title || 'Developer Tools Detected';

		var p = document.createElement('p');
		p.textContent = protConfig.devtools_message || 'Please close developer tools to continue watching this video.';

		inner.appendChild(h);
		inner.appendChild(p);
		overlay.appendChild(inner);
		document.body.appendChild(overlay);
	}

	// Block Ctrl+S / Cmd+S only when focus is inside a protected player.
	if (protConfig.block_keyboard !== false) {
		document.addEventListener('keydown', function (e) {
			if ((e.ctrlKey || e.metaKey) && e.key === 's') {
				var target = e.target;
				if (target && target.closest && target.closest('.ms-protected-player')) {
					e.preventDefault();
					return false;
				}
			}
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
