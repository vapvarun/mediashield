/**
 * MediaShield Watermark — Canvas overlay rendering.
 *
 * Renders username + IP as semi-transparent text on a canvas overlay.
 * Swaps position at configurable intervals. Mobile-responsive sizing.
 * Re-renders on container resize. Pauses video if canvas is removed.
 *
 * @package MediaShield
 */
(function () {
	'use strict';

	var config = window.mediashieldConfig || {};

	// Watermark text size in px per font_size setting: [desktop, mobile].
	var FONT_SIZES = {
		small: [ 12, 10 ],
		medium: [ 16, 12 ],
		large: [ 22, 16 ],
	};

	window.addEventListener('mediashield:player-ready', function (e) {
		var detail = e.detail;
		if (!detail.el) return;

		var wmConfig = detail.watermarkConfig || config.watermark || {};
		if (!wmConfig.enabled) return;

		initWatermark(detail.el, wmConfig);
	});

	/**
	 * Initialize watermark on a player element.
	 *
	 * @param {HTMLElement} el       Player container.
	 * @param {Object}      wmConfig Watermark configuration.
	 */
	function initWatermark(el, wmConfig) {
		var canvas = el.querySelector('.ms-watermark-canvas');
		if (!canvas) return;

		// Watermark canvas is purely visual — hide from assistive technology.
		canvas.setAttribute('aria-hidden', 'true');

		var ctx = canvas.getContext('2d');
		var positions = ['top-left', 'top-right', 'bottom-left', 'bottom-right', 'center'];
		// Owner option: keep the watermark off the middle of the lesson.
		if (wmConfig.edges_only) {
			positions.pop();
		}
		var currentPos = 0;
		var SEP = ' · ';
		var GAP = 8;

		// Player chrome sitting in the corners (badge top-left, speed top-right,
		// fullscreen button bottom-right) and the browser's own control bar,
		// which the DOM cannot measure. The watermark is drawn clear of them.
		// ponytail: 64px covers the Chrome, Firefox and Safari bars; measure if one grows.
		function insets(rect) {
			var top = 0;
			var bottom = 0;
			el.querySelectorAll('.ms-badge, .ms-speed-control, .ms-fullscreen-btn').forEach(function (node) {
				var r = node.getBoundingClientRect();
				if (!r.height) return;
				if (r.top - rect.top < rect.height / 2) {
					top = Math.max(top, r.bottom - rect.top);
				} else {
					bottom = Math.max(bottom, rect.bottom - r.top);
				}
			});
			var video = el.querySelector('video');
			if (video && video.controls) {
				bottom = Math.max(bottom, 64);
			}
			return { top: top + GAP, bottom: bottom + GAP };
		}

		// Split the text into lines no wider than maxW, breaking between the
		// fields Pro joins with SEP. The font steps down until every line fits
		// the width AND the block fits maxH, the room between the top chrome
		// and the bottom chrome; otherwise a tall block on a short player was
		// pushed up under the badge and speed button.
		function layout(text, maxW, maxH, fontSize) {
			var parts = text.split(SEP);
			for (var size = fontSize; size >= 8; size--) {
				ctx.font = size + 'px sans-serif';
				var lines = [];
				var line = '';
				var fits = true;
				for (var i = 0; i < parts.length; i++) {
					var next = line ? line + SEP + parts[i] : parts[i];
					if (line && ctx.measureText(next).width > maxW) {
						lines.push(line);
						next = parts[i];
					}
					line = next;
					if (ctx.measureText(line).width > maxW) fits = false;
				}
				lines.push(line);
				var lineH = Math.round(size * 1.25);
				if ((fits && lines.length * lineH <= maxH) || size === 8) {
					var w = 0;
					lines.forEach(function (l) { w = Math.max(w, ctx.measureText(l).width); });
					return { lines: lines, size: size, lineH: lineH, width: Math.min(w, maxW) };
				}
			}
		}

		function render() {
			var rect = el.getBoundingClientRect();
			canvas.width = rect.width;
			canvas.height = rect.height;

			ctx.clearRect(0, 0, canvas.width, canvas.height);

			// Owner-chosen size (Pro sends font_size small/medium/large), one
			// step smaller on narrow players. Medium is the historic 16/12.
			var isMobile = rect.width < 640;
			var sizes = FONT_SIZES[ wmConfig.font_size ] || FONT_SIZES.medium;
			var fontSize = sizes[ isMobile ? 1 : 0 ];

			// 0 is a valid opacity (the owner chose it); only a missing value
			// falls back, to the Settings default.
			var opacity = parseFloat(wmConfig.opacity);
			ctx.fillStyle = wmConfig.color || '#ffffff';
			ctx.globalAlpha = isNaN(opacity) ? 0.5 : opacity;

			// Build watermark text. Mobile: username only. Desktop: username + IP.
			var text = wmConfig.text || '';
			if (!isMobile && wmConfig.ip) {
				text += (text ? SEP : '') + wmConfig.ip; // A guest has no name: no leading separator.
			}

			if (!text) return;

			var padding = isMobile ? 12 : 20;
			var edge = insets(rect);
			var top = Math.max(padding, edge.top);
			var bottom = canvas.height - Math.max(padding, edge.bottom);
			var block = layout(text, canvas.width - 2 * padding, bottom - top, fontSize);
			var lineH = block.lineH;
			var blockH = lineH * block.lines.length;

			var pos = positions[currentPos % positions.length];
			var x, y;

			switch (pos) {
				case 'top-left':
					x = padding;
					y = top;
					break;
				case 'top-right':
					x = canvas.width - block.width - padding;
					y = top;
					break;
				case 'bottom-left':
					x = padding;
					y = bottom - blockH;
					break;
				case 'bottom-right':
					x = canvas.width - block.width - padding;
					y = bottom - blockH;
					break;
				case 'center':
				default:
					x = (canvas.width - block.width) / 2;
					y = (canvas.height - blockH) / 2;
					break;
			}

			// A player too short for the block even at 8px (a ~270px player with
			// every Pro field and a long email) keeps the text on canvas; it can
			// then touch the chrome. ponytail: drop to fewer fields if that matters.
			y = Math.max(0, Math.min(y, canvas.height - blockH));
			ctx.textBaseline = 'top';
			block.lines.forEach(function (l, i) {
				ctx.fillText(l, Math.max(padding, x), y + i * lineH);
			});
			ctx.globalAlpha = 1;
		}

		// After every player-ready listener has run, so the badge exists and
		// is measured.
		setTimeout(render, 0);

		// Position swap interval.
		var swapMs = (parseInt(wmConfig.swap_interval, 10) || 30) * 1000;
		var swapTimer = setInterval(function () {
			currentPos++;
			render();
		}, swapMs);

		// Re-render on resize.
		var ro = null;
		if (window.ResizeObserver) {
			ro = new ResizeObserver(function () {
				render();
			});
			ro.observe(el);
		}

		// Pause video if canvas is removed from DOM or hidden via CSS (anti-tamper).
		// Also clears the swap interval to prevent leaked timers.
		function triggerAntiTamper() {
			clearInterval(swapTimer);
			if (ro) ro.disconnect();
			mo.disconnect();
			var video = el.querySelector('video');
			if (video) video.pause();
			var iframes = el.querySelectorAll('iframe');
			iframes.forEach(function (iframe) {
				iframe.style.display = 'none';
			});
		}

		var mo = new MutationObserver(function (mutations) {
			for (var i = 0; i < mutations.length; i++) {
				var mutation = mutations[i];

				// Check for canvas removal.
				if (mutation.type === 'childList') {
					for (var j = 0; j < mutation.removedNodes.length; j++) {
						if (mutation.removedNodes[j] === canvas) {
							triggerAntiTamper();
							return;
						}
					}
				}

				// Check for style/class changes that would hide the canvas.
				if (mutation.type === 'attributes' && mutation.target === canvas) {
					var style = window.getComputedStyle(canvas);
					if (
						style.display === 'none' ||
						style.visibility === 'hidden' ||
						parseFloat(style.opacity) === 0
					) {
						triggerAntiTamper();
						return;
					}
				}
			}
		});
		mo.observe(el, { childList: true, attributes: true, attributeFilter: ['style', 'class'], subtree: true });
	}
})();
