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
		var currentPos = 0;

		function render() {
			var rect = el.getBoundingClientRect();
			canvas.width = rect.width;
			canvas.height = rect.height;

			ctx.clearRect(0, 0, canvas.width, canvas.height);

			// Mobile-responsive font size.
			var isMobile = rect.width < 640;
			var fontSize = isMobile ? 12 : 16;

			ctx.font = fontSize + 'px sans-serif';
			ctx.fillStyle = wmConfig.color || '#ffffff';
			ctx.globalAlpha = wmConfig.opacity || 0.3;

			// Build watermark text. Mobile: username only. Desktop: username + IP.
			var text = wmConfig.text || '';
			if (!isMobile && wmConfig.ip) {
				text += ' \u00B7 ' + wmConfig.ip;
			}

			if (!text) return;

			var metrics = ctx.measureText(text);
			var textW = metrics.width;
			var textH = fontSize;
			var padding = 20;

			var pos = positions[currentPos % positions.length];
			var x, y;

			switch (pos) {
				case 'top-left':
					x = padding;
					y = padding + textH;
					break;
				case 'top-right':
					x = canvas.width - textW - padding;
					y = padding + textH;
					break;
				case 'bottom-left':
					x = padding;
					y = canvas.height - padding;
					break;
				case 'bottom-right':
					x = canvas.width - textW - padding;
					y = canvas.height - padding;
					break;
				case 'center':
				default:
					x = (canvas.width - textW) / 2;
					y = canvas.height / 2;
					break;
			}

			ctx.fillText(text, x, y);
			ctx.globalAlpha = 1;
		}

		render();

		// Position swap interval.
		var swapMs = (wmConfig.swap_interval || 20) * 1000;
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
