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
	var pullZones = data.bunnyPullZones || {};

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
			// Mirrors Platforms::bunny_from_url() in PHP, which re-derives and
			// stores the canonical shape on save; this only drives the label.
			/(?:(?:iframe|player)\.mediadelivery\.net|video\.bunnycdn\.com)\/(?:embed|play)\/\d+\/([a-f0-9-]{36})/i,
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

			// A pull-zone file (vz-xxxx.b-cdn.net/{guid}/...) names no library,
			// so it is Bunny only when the host is one of this site's connected
			// pull zones - the same map Platforms::bunny_from_url() reads.
			if ( ! vid ) {
				var zone = url.match( /^https?:\/\/([^\/?#]+)\/([a-f0-9-]{36})(?:[\/?#]|$)/i );
				if ( zone && pullZones[ zone[ 1 ].toLowerCase() ] ) {
					detected = 'bunny';
					vid = zone[ 2 ].toLowerCase();
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

					if ( ! message && /\.b-cdn\.net\//i.test( url ) ) {
						message = labels.bunnyStreamFile || '';
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
	var uploadWrap = document.getElementById( 'ms-upload-progress-wrap' );
	var uploadPct = document.getElementById( 'ms-upload-pct' );
	var uploadMsg = document.getElementById( 'ms-upload-status' );

	if ( fileField && uploadBtn && uploadBar && uploadMsg ) {
		// True from the moment a request starts until it finishes or fails.
		// Every control checks it: in Chrome a disabled file input still
		// takes a file dropped or set on it and fires `change`, which used to
		// re-enable Upload mid-upload, and a second click then created a
		// second (orphaned, billed) Bunny video (Basecamp 10530929713).
		var busy = false;

		var fill = function ( template, values ) {
			var i = 0;
			return String( template || '' ).replace( /%(\d+\$)?s/g, function ( m, pos ) {
				var idx = pos ? parseInt( pos, 10 ) - 1 : i++;
				return values[ idx ] !== undefined ? values[ idx ] : '';
			} );
		};

		var sizeLabel = function ( bytes ) {
			var mb = bytes / ( 1024 * 1024 );
			return ( mb >= 10 ? Math.round( mb ) : Math.round( mb * 10 ) / 10 ) + ' MB';
		};

		// The file is over the server's limit: say so with both numbers and
		// what to do, and send nothing.
		var tooLarge = function ( file ) {
			var max = parseInt( data.maxUploadBytes, 10 ) || 0;
			if ( ! max || file.size <= max ) {
				return false;
			}
			uploadMsg.textContent = fill( labels.tooLarge, [ sizeLabel( file.size ), data.maxUploadLabel || sizeLabel( max ) ] ) + ' ';
			if ( 'bunny' === data.uploadDriver && data.browseUrl ) {
				var link = document.createElement( 'a' );
				link.href = data.browseUrl;
				link.textContent = labels.tooLargeBunny || '';
				uploadMsg.appendChild( link );
			} else {
				uploadMsg.appendChild( document.createTextNode( labels.tooLargeOther || '' ) );
			}
			return true;
		};

		var showProgress = function ( pct ) {
			if ( uploadWrap ) {
				uploadWrap.hidden = false;
			}
			if ( null === pct ) {
				// Indeterminate: the site is handing the file to the platform
				// and cannot report how far that has got.
				uploadBar.removeAttribute( 'value' );
				if ( uploadPct ) {
					uploadPct.textContent = '';
				}
				return;
			}
			uploadBar.value = pct;
			if ( uploadPct ) {
				uploadPct.textContent = pct + '%';
			}
		};

		fileField.addEventListener( 'change', function () {
			if ( busy ) {
				return;
			}
			uploadMsg.textContent = '';
			var file = this.files.length ? this.files[ 0 ] : null;
			uploadBtn.disabled = ! file || tooLarge( file );
		} );

		// done (optional) runs only after a successful upload.
		var startUpload = function ( done ) {
			if ( busy || ! fileField.files.length ) {
				return;
			}

			var file = fileField.files[ 0 ];
			if ( tooLarge( file ) ) {
				uploadBtn.disabled = true;
				return;
			}

			var form = new FormData();
			form.append( 'file', file );
			// Fill in the video being edited instead of creating a second one.
			form.append( 'video_id', String( data.postId || 0 ) );

			var titleField = document.getElementById( 'title' );
			if ( titleField && titleField.value ) {
				form.append( 'title', titleField.value );
			}

			var xhr = new XMLHttpRequest();
			xhr.open( 'POST', ( data.restUrl || '/wp-json/mediashield/v1/' ) + 'upload/init' );
			xhr.setRequestHeader( 'X-WP-Nonce', data.nonce || '' );

			busy = true;
			uploadBtn.disabled = true;
			fileField.disabled = true;
			showProgress( 0 );
			uploadMsg.textContent = fill( labels.uploadSending, [ '0%' ] );

			xhr.upload.addEventListener( 'progress', function ( e ) {
				if ( ! e.lengthComputable ) {
					return;
				}
				var pct = Math.min( 100, Math.floor( ( e.loaded / e.total ) * 100 ) );
				showProgress( pct );
				uploadMsg.textContent = fill( labels.uploadSending, [ pct + '%' ] );
			} );

			// Every byte reached the site; the server now sends it on to the
			// target, which on a large file takes minutes. Say where it is.
			xhr.upload.addEventListener( 'load', function () {
				if ( 'self_hosted' === data.uploadDriver ) {
					return;
				}
				showProgress( null );
				uploadMsg.textContent = fill( labels.uploadHandoff, [ data.uploadTarget || '' ] );
			} );

			var finish = function () {
				busy = false;
				uploadBtn.disabled = ! fileField.files.length;
				fileField.disabled = false;
				if ( uploadWrap ) {
					uploadWrap.hidden = true;
				}
			};

			xhr.addEventListener( 'load', function () {
				finish();

				var body = null;
				try {
					body = JSON.parse( xhr.responseText );
				} catch ( err ) {
					body = null;
				}

				if ( xhr.status < 200 || xhr.status >= 300 || ! body ) {
					// Show the server's own reason. A bare "upload failed" sends
					// the owner hunting for a cause the response already named -
					// wrong file type, too large, no permission. An answer that
					// is not JSON (a 502 page) gets the calm network sentence,
					// not "Upload failed: 502".
					var failure = body || { code: 'invalid_json' };
					if ( ! body ) {
						failure.code = 'invalid_json';
					}
					uploadMsg.textContent = ( labels.uploadFailed || 'Upload failed:' ) + ' ' +
						window.mediashieldRequest.message( failure );
					return;
				}

				// Reflect the result in the fields the operator can see, so the
				// screen agrees with what was just stored rather than needing a
				// reload to tell the truth.
				if ( urlField && body.embed_url ) {
					urlField.value = body.embed_url;
				}
				// The driver the server chose - Pro's default upload target may
				// be Bunny or Vimeo, not this site.
				var uploaded = body.platform || 'self';
				if ( platformField ) {
					platformField.value = uploaded;
				}
				if ( videoIdField && body.platform_video_id ) {
					videoIdField.value = body.platform_video_id;
				}
				if ( platformLabel ) {
					platformLabel.textContent = labels[ uploaded ] || uploaded;
				}
				if ( platformRow ) {
					platformRow.style.display = '';
				}
				if ( note ) {
					note.textContent = '';
					note.style.display = 'none';
				}

				uploadMsg.textContent = labels.uploadDone || 'Uploaded.';
				// The file is stored now; clear it so a later save does not
				// upload it a second time.
				fileField.value = '';
				uploadBtn.disabled = true;
				if ( done ) {
					done();
				}
			} );

			var failed = function () {
				finish();
				uploadMsg.textContent = ( labels.uploadFailed || 'Upload failed:' ) + ' ' + window.mediashieldRequest.message( { code: 'network' } );
			};
			xhr.addEventListener( 'error', failed );
			xhr.addEventListener( 'abort', failed );
			xhr.addEventListener( 'timeout', failed );

			xhr.send( form );
		};

		uploadBtn.addEventListener( 'click', function () {
			startUpload( null );
		} );

		// Pressing Publish or Update with a file chosen but not uploaded used to
		// save an empty video with no warning. Upload first, then save with the
		// same button the owner pressed.
		var postForm = fileField.form;
		if ( postForm ) {
			postForm.addEventListener( 'submit', function ( e ) {
				if ( busy ) {
					// Already uploading; saving now would store an empty video.
					e.preventDefault();
					return;
				}
				if ( ! fileField.files.length ) {
					return;
				}
				e.preventDefault();
				var submitter = e.submitter || null;
				startUpload( function () {
					if ( postForm.requestSubmit ) {
						postForm.requestSubmit( submitter );
					} else {
						postForm.submit();
					}
				} );
			} );
		}
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

	// Tags: suggestions for the tag being typed after a comma. A datalist
	// matches the whole field, so after "one, t" nothing matched and only the
	// first tag could be picked (Basecamp 10536871245). Each suggestion keeps
	// what is already typed and offers only tags not typed yet.
	var tagsField = document.getElementById( 'ms-add-tags' );
	var tagsList  = document.getElementById( 'ms-tag-names' );
	if ( tagsField && tagsList ) {
		var tagNames = Array.prototype.map.call( tagsList.options, function ( o ) { return o.value; } );
		tagsField.addEventListener( 'input', function () {
			var value  = tagsField.value;
			var cut    = value.lastIndexOf( ',' );
			// What is typed up to the tag being written, spaces after the comma
			// included, so each suggestion starts with exactly the typed text.
			var prefix = cut === -1 ? '' : value.slice( 0, cut + 1 ) + value.slice( cut + 1 ).match( /^\s*/ )[ 0 ];
			var typed  = value.slice( 0, cut === -1 ? 0 : cut ).split( ',' ).map( function ( t ) {
				return t.trim().toLowerCase();
			} );
			while ( tagsList.firstChild ) {
				tagsList.removeChild( tagsList.firstChild );
			}
			tagNames.forEach( function ( name ) {
				if ( typed.indexOf( name.toLowerCase() ) !== -1 ) {
					return;
				}
				var option   = document.createElement( 'option' );
				option.value = prefix + name;
				tagsList.appendChild( option );
			} );
		} );
	}
} )();
