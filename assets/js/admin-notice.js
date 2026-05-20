/**
 * MediaShield — dismiss the Pro upsell admin notice.
 *
 * Extracted from an inline <script> in Admin\Menu so the markup carries no
 * inline scripts (ux-foundation BLOCK gate F2). Uses event delegation so it
 * works regardless of when the notice is injected; reads the nonce from the
 * notice's data-nonce attribute and posts to the global ajaxurl.
 */
( function () {
	'use strict';

	document.addEventListener( 'click', function ( e ) {
		var dismiss = e.target.closest( '.ms-pro-notice .notice-dismiss' );
		if ( ! dismiss ) {
			return;
		}

		var notice = dismiss.closest( '.ms-pro-notice' );
		if ( ! notice || ! window.ajaxurl ) {
			return;
		}

		var body = new URLSearchParams();
		body.append( 'action', 'ms_dismiss_pro_notice' );
		body.append( 'nonce', notice.getAttribute( 'data-nonce' ) || '' );

		fetch( window.ajaxurl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString(),
		} );
	} );
} )();
