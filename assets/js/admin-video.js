/**
 * MediaShield — video edit screen meta-box behavior.
 *
 * Extracted from inline <script> blocks in CPT\VideoPostType so the markup
 * carries no inline scripts or onclick handlers (ux-foundation BLOCK gate F2).
 * Platform labels are passed in via wp_localize_script as mediashieldVideoAdmin.
 */
( function () {
	'use strict';

	var data = window.mediashieldVideoAdmin || {};
	var labels = data.labels || {};

	// 1) Auto-detect the platform + video id from the source URL.
	var urlField = document.getElementById( 'ms-video-url' );
	var platformField = document.getElementById( 'ms-platform' );
	var videoIdField = document.getElementById( 'ms-platform-video-id' );
	var platformRow = document.getElementById( 'ms-detected-platform-row' );
	var platformLabel = document.getElementById( 'ms-detected-platform-label' );
	var note = document.getElementById( 'ms-detect-note' );

	var patterns = {
		youtube: [ /(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/ ],
		vimeo: [ /(?:vimeo\.com\/|player\.vimeo\.com\/video\/)(\d+)/ ],
		wistia: [ /(?:wistia\.com\/medias\/|fast\.wistia\.net\/embed\/iframe\/)([a-z0-9]+)/ ],
		bunny: [
			/(?:iframe\.mediadelivery\.net\/embed\/\d+\/)([a-f0-9-]+)/,
			/(?:b-cdn\.net\/)([a-f0-9-]+)/,
			// The Bunny dashboard URL — what you get by copying the address bar
			// while looking at a video in Bunny Stream. It is the single most
			// common thing a customer pastes, and it used to match nothing and
			// fall through to "Self-hosted" with an empty video id, producing a
			// player that hung forever (BC#10225483994). The last path segment
			// is the video GUID, the same shape the working records carry.
			/dash\.bunny\.net\/stream\/\d+\/library\/(?!collections\b)([a-f0-9-]{36})/,
		],
	};

	// Known hosts we can recognise but cannot play from. Matching one of these
	// means "we understood you and this specific URL is not playable", which is
	// a different answer from "unknown URL, assuming self-hosted" — and the one
	// the operator can act on.
	var unplayable = [
		{
			re: /dash\.bunny\.net\/stream\/\d+\/library\/collections\//,
			// A collection is a folder of videos. Its GUID is not a video GUID,
			// so extracting it would save a confidently wrong id — the same
			// silent-bad-data failure this fix exists to end, in a new place.
			msg: labels.bunnyCollection,
		},
		{
			re: /dash\.bunny\.net\//,
			msg: labels.bunnyDashboard,
		},
	];

	if ( urlField && platformField && videoIdField && platformRow && platformLabel ) {
		urlField.addEventListener( 'input', function () {
			var url = this.value.trim();
			var detected = 'self';
			var vid = '';

			for ( var p in patterns ) {
				for ( var i = 0; i < patterns[ p ].length; i++ ) {
					var m = url.match( patterns[ p ][ i ] );
					if ( m ) {
						detected = p;
						vid = m[ 1 ];
						break;
					}
				}
				if ( vid ) {
					break;
				}
			}

			platformField.value = detected;
			videoIdField.value = vid;
			platformLabel.textContent = ( labels[ detected ] || detected ) + ( vid ? ' (' + vid + ')' : '' );
			platformRow.style.display = url ? '' : 'none';

			// Say what happened. "Self-hosted" on its own reads like a
			// successful detection even when it is really "no idea".
			if ( note ) {
				var message = '';

				// Only when detection did NOT produce a usable id. A dashboard URL
				// we successfully read the video GUID out of is a success, and
				// warning about it anyway would train operators to ignore the
				// notice — which is how the silent version of this bug survived.
				if ( url && ! vid ) {
					for ( var u = 0; u < unplayable.length; u++ ) {
						if ( unplayable[ u ].re.test( url ) ) {
							message = unplayable[ u ].msg || '';
							break;
						}
					}

					if ( ! message && 'self' === detected && /^https?:\/\//i.test( url ) && ! /\.(mp4|webm|ogg|ogv|mov|m3u8|mpd)(\?|#|$)/i.test( url ) ) {
						// A URL we could not place, pointing at something that is
						// not obviously a media file. Saving it as self-hosted is
						// still allowed — an operator may know better than we do —
						// but they should hear that we did not recognise it.
						message = labels.unrecognised || '';
					}
				}

				note.textContent = message;
				note.style.display = message ? '' : 'none';
			}
		} );
	}

	// 1b) Upload a video file straight from this screen.
	//
	// "Add New Video" was URL-paste only, so self-hosting meant uploading
	// through the Media Library separately and pasting the URL back - the one
	// thing a buyer tries first, and the one thing the screen could not do
	// (BC#10143668243). The REST route and driver behind this already existed
	// and were already exercised by Pro's frontend upload form; nothing in the
	// admin was calling them.
	//
	// XMLHttpRequest rather than fetch() because it is the only one of the two
	// that reports upload progress, and a large video with no progress bar looks
	// indistinguishable from a page that has hung.
	var fileField = document.getElementById( 'ms-video-file' );
	var uploadBtn = document.getElementById( 'ms-upload-btn' );
	var uploadBar = document.getElementById( 'ms-upload-progress' );
	var uploadMsg = document.getElementById( 'ms-upload-status' );

	if ( fileField && uploadBtn && uploadBar && uploadMsg ) {
		fileField.addEventListener( 'change', function () {
			uploadBtn.disabled = ! this.files.length;
			uploadMsg.textContent = '';
		} );

		uploadBtn.addEventListener( 'click', function () {
			if ( ! fileField.files.length ) {
				return;
			}

			var form = new FormData();
			form.append( 'file', fileField.files[ 0 ] );
			// Fill in the video being edited instead of creating a second one.
			form.append( 'video_id', String( data.postId || 0 ) );

			var titleField = document.getElementById( 'title' );
			if ( titleField && titleField.value ) {
				form.append( 'title', titleField.value );
			}

			var xhr = new XMLHttpRequest();
			xhr.open( 'POST', ( data.restUrl || '/wp-json/mediashield/v1/' ) + 'upload/init' );
			xhr.setRequestHeader( 'X-WP-Nonce', data.nonce || '' );

			uploadBtn.disabled = true;
			fileField.disabled = true;
			uploadBar.value = 0;
			uploadBar.style.display = '';
			uploadMsg.textContent = labels.uploading || 'Uploading…';

			xhr.upload.addEventListener( 'progress', function ( e ) {
				if ( e.lengthComputable ) {
					uploadBar.value = ( e.loaded / e.total ) * 100;
				}
			} );

			var finish = function () {
				uploadBtn.disabled = false;
				fileField.disabled = false;
				uploadBar.style.display = 'none';
			};

			xhr.addEventListener( 'load', function () {
				finish();

				var body = {};
				try {
					body = JSON.parse( xhr.responseText );
				} catch ( err ) {
					body = {};
				}

				if ( xhr.status < 200 || xhr.status >= 300 ) {
					// Show the server's own reason. A bare "upload failed" sends
					// the owner hunting for a cause the response already named -
					// wrong file type, too large, no permission.
					uploadMsg.textContent = ( labels.uploadFailed || 'Upload failed:' ) + ' ' +
						( body.message || xhr.status );
					return;
				}

				// Reflect the result in the fields the operator can see, so the
				// screen agrees with what was just stored rather than needing a
				// reload to tell the truth.
				if ( urlField && body.embed_url ) {
					urlField.value = body.embed_url;
				}
				if ( platformField ) {
					platformField.value = 'self';
				}
				if ( videoIdField && body.platform_video_id ) {
					videoIdField.value = body.platform_video_id;
				}
				if ( platformLabel ) {
					platformLabel.textContent = labels.self || 'Self-hosted / Direct URL';
				}
				if ( platformRow ) {
					platformRow.style.display = '';
				}
				if ( note ) {
					note.textContent = '';
					note.style.display = 'none';
				}

				uploadMsg.textContent = labels.uploadDone || 'Uploaded.';
			} );

			xhr.addEventListener( 'error', function () {
				finish();
				uploadMsg.textContent = labels.uploadNetwork || 'Upload failed.';
			} );

			xhr.send( form );
		} );
	}

	// 2) Copy-to-clipboard buttons (replaces inline onclick + script).
	document.querySelectorAll( '.ms-embed-copy-btn' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var input = document.getElementById( btn.dataset.copy );
			if ( ! input ) {
				return;
			}
			input.select();
			navigator.clipboard.writeText( input.value ).then( function () {
				// .copied swaps the clipboard icon for the check icon via CSS.
				btn.classList.add( 'copied' );
				setTimeout( function () {
					btn.classList.remove( 'copied' );
				}, 2000 );
			} );
		} );
	} );

	// Select the field contents on focus/click (replaces inline onclick="this.select()").
	document.querySelectorAll( '.ms-embed-input' ).forEach( function ( input ) {
		var selectAll = function () {
			input.select();
		};
		input.addEventListener( 'focus', selectAll );
		input.addEventListener( 'click', selectAll );
	} );

	// 3) Toggle end-screen fields when the end-screen option changes.
	var endscreenSelect = document.querySelector( 'select[name="_ms_player_endscreen"]' );
	var endscreenFields = document.getElementById( 'ms-endscreen-fields' );
	if ( endscreenSelect && endscreenFields ) {
		endscreenSelect.addEventListener( 'change', function () {
			endscreenFields.style.display = this.value === 'on' ? '' : 'none';
		} );
	}
} )();
