/**
 * MediaShield Player Wrapper
 *
 * Scans the DOM for .ms-protected-player elements and initializes
 * protection, watermark, and tracker per video. Also observes for
 * dynamically injected iframes (lazy-loaded by page builders).
 *
 * @package MediaShield
 */
(function () {
	'use strict';

	var config = window.mediashieldConfig || {};

	/**
	 * Initialize all protected players on the page.
	 */
	function init() {
		var players = document.querySelectorAll('.ms-protected-player');
		players.forEach(initPlayer);

		// Watch for dynamically added iframes (Elementor, Divi, etc.).
		observeDynamicEmbeds();
	}

	/**
	 * Initialize a single protected player element.
	 *
	 * @param {HTMLElement} el The .ms-protected-player container.
	 */
	function initPlayer(el) {
		if (el.dataset.msInitialized) {
			return;
		}
		el.dataset.msInitialized = '1';

		var protectionLevel = el.dataset.protectionLevel || 'standard';
		var videoId = parseInt(el.dataset.videoId, 10) || 0;

		// No protection — free preview / trailer.
		if (protectionLevel === 'none') {
			return;
		}

		// Login gate.
		if (!config.isLoggedIn && protectionLevel !== 'none') {
			showLoginOverlay(el);
			return;
		}

		// Start session and initialize components.
		startSession(el, videoId);
	}

	/**
	 * Show login overlay on a player using safe DOM methods.
	 *
	 * @param {HTMLElement} el Player container.
	 */
	function showLoginOverlay(el) {
		var overlay = document.createElement('div');
		overlay.className = 'ms-login-overlay';

		var message = document.createElement('div');
		message.className = 'ms-login-message';

		var text = document.createElement('p');
		text.textContent = config.loginMessage || 'Please log in to watch this video.';

		var link = document.createElement('a');
		link.href = config.loginUrl || '/wp-login.php';
		link.className = 'ms-login-button';
		link.textContent = 'Log In';

		message.appendChild(text);
		message.appendChild(link);
		overlay.appendChild(message);

		// Hide the actual video.
		var inner = el.querySelector('.ms-player-inner');
		if (inner) {
			inner.style.visibility = 'hidden';
		}

		el.appendChild(overlay);
	}

	/**
	 * Start a watch session via REST API, then init watermark + tracker.
	 *
	 * @param {HTMLElement} el      Player container.
	 * @param {number}      videoId Video CPT post ID.
	 */
	function startSession(el, videoId) {
		if (!videoId) {
			window.dispatchEvent(new CustomEvent('mediashield:player-ready', { detail: { el: el, videoId: 0 } }));
			return;
		}

		fetch(config.restUrl + 'session/start', {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': config.nonce,
			},
			body: JSON.stringify({ video_id: videoId }),
		})
			.then(function (res) { return res.json(); })
			.then(function (data) {
				if (data.session_token) {
					el.dataset.sessionToken = data.session_token;
					el.dataset.resumePosition = data.resume_position || 0;

					if (data.resume_position > 0) {
						showResumePrompt(el, data.resume_position);
					}

					window.dispatchEvent(new CustomEvent('mediashield:player-ready', {
						detail: {
							el: el,
							videoId: videoId,
							token: data.session_token,
							resumePosition: data.resume_position,
							watermarkConfig: data.watermark_config,
							video: data.video,
						},
					}));
				}
			})
			.catch(function (err) {
				console.warn('MediaShield: session start failed', err);
			});
	}

	/**
	 * Show a "Resume from X:XX?" toast using safe DOM methods.
	 *
	 * @param {HTMLElement} el       Player container.
	 * @param {number}      position Resume position in seconds.
	 */
	function showResumePrompt(el, position) {
		var mins = Math.floor(position / 60);
		var secs = Math.floor(position % 60);
		var timeStr = mins + ':' + (secs < 10 ? '0' : '') + secs;

		var toast = document.createElement('div');
		toast.className = 'ms-resume-toast';

		var span = document.createElement('span');
		span.textContent = 'Resume from ' + timeStr + '?';

		var yesBtn = document.createElement('button');
		yesBtn.className = 'ms-resume-yes';
		yesBtn.textContent = 'Resume';
		yesBtn.addEventListener('click', function () {
			seekPlayer(el, position);
			toast.remove();
		});

		var noBtn = document.createElement('button');
		noBtn.className = 'ms-resume-no';
		noBtn.textContent = 'Start Over';
		noBtn.addEventListener('click', function () {
			toast.remove();
		});

		toast.appendChild(span);
		toast.appendChild(yesBtn);
		toast.appendChild(noBtn);
		el.appendChild(toast);

		setTimeout(function () {
			if (toast.parentNode) {
				toast.remove();
			}
		}, 10000);
	}

	/**
	 * Seek the video player to a specific position.
	 *
	 * @param {HTMLElement} el       Player container.
	 * @param {number}      position Seconds to seek to.
	 */
	function seekPlayer(el, position) {
		var video = el.querySelector('video');
		if (video && video.readyState >= 1) {
			video.currentTime = position;
		} else if (video) {
			video.addEventListener('loadedmetadata', function () {
				video.currentTime = position;
			}, { once: true });
		}
	}

	/**
	 * Observe for dynamically injected video iframes.
	 */
	function observeDynamicEmbeds() {
		var videoPatterns = /youtube\.com\/embed|youtube-nocookie\.com\/embed|player\.vimeo\.com|iframe\.mediadelivery\.net|wistia/i;

		var observer = new MutationObserver(function (mutations) {
			mutations.forEach(function (mutation) {
				mutation.addedNodes.forEach(function (node) {
					if (node.nodeType !== 1) return;

					var iframes = node.tagName === 'IFRAME' ? [node] : (node.querySelectorAll ? Array.from(node.querySelectorAll('iframe')) : []);

					iframes.forEach(function (iframe) {
						var src = iframe.getAttribute('src') || iframe.getAttribute('data-src') || '';
						if (videoPatterns.test(src) && !iframe.closest('.ms-protected-player')) {
							wrapDynamicEmbed(iframe);
						}
					});
				});
			});
		});

		observer.observe(document.body, { childList: true, subtree: true });
	}

	/**
	 * Wrap a dynamically injected iframe using DOM methods.
	 *
	 * @param {HTMLIFrameElement} iframe The iframe element.
	 */
	function wrapDynamicEmbed(iframe) {
		var wrapper = document.createElement('div');
		wrapper.className = 'ms-protected-player';
		wrapper.dataset.videoId = '0';
		wrapper.dataset.platform = 'iframe';
		wrapper.dataset.protectionLevel = config.defaultProtection || 'standard';
		wrapper.dataset.playerType = 'standard';

		var inner = document.createElement('div');
		inner.className = 'ms-player-inner';

		var canvas = document.createElement('canvas');
		canvas.className = 'ms-watermark-canvas';

		var overlay = document.createElement('div');
		overlay.className = 'ms-protection-overlay';

		iframe.parentNode.insertBefore(wrapper, iframe);
		inner.appendChild(iframe);
		wrapper.appendChild(inner);
		wrapper.appendChild(canvas);
		wrapper.appendChild(overlay);

		initPlayer(wrapper);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
