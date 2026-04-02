/**
 * MediaShield Protection — Anti-download measures.
 *
 * Blocks right-click, Ctrl+S/Cmd+S, loads video src via JS to prevent
 * easy source inspection. Adds "Protected by MediaShield" badge.
 *
 * @package MediaShield
 */
(function () {
	'use strict';

	var config = window.mediashieldConfig || {};
	var protConfig = config.protection || {};

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
