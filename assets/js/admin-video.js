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

	var patterns = {
		youtube: [ /(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/ ],
		vimeo: [ /(?:vimeo\.com\/|player\.vimeo\.com\/video\/)(\d+)/ ],
		wistia: [ /(?:wistia\.com\/medias\/|fast\.wistia\.net\/embed\/iframe\/)([a-z0-9]+)/ ],
		bunny: [
			/(?:iframe\.mediadelivery\.net\/embed\/\d+\/)([a-f0-9-]+)/,
			/(?:b-cdn\.net\/)([a-f0-9-]+)/,
		],
	};

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
				btn.classList.add( 'copied' );
				var icon = btn.querySelector( '.dashicons' );
				if ( icon ) {
					icon.classList.replace( 'dashicons-clipboard', 'dashicons-yes' );
				}
				setTimeout( function () {
					btn.classList.remove( 'copied' );
					if ( icon ) {
						icon.classList.replace( 'dashicons-yes', 'dashicons-clipboard' );
					}
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
