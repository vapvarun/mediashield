/**
 * MediaShield -- the one place a failed request becomes words.
 *
 * A dropped connection rejects fetch() with the browser's own sentence
 * ("Failed to fetch", "Load failed", "NetworkError when attempting to fetch
 * resource."), and an HTML error page (a 502, a PHP fatal) fails JSON parsing
 * with "Unexpected token '<' ... is not valid JSON". wp.apiFetch reports the
 * same two cases as fetch_error / invalid_json, and rejects with the raw
 * Response when called with parse: false. None of those is a sentence for an
 * owner or a viewer, so they all read as one calm network message; a real
 * server refusal (a WP_Error with its own message) keeps that message.
 *
 * Every MediaShield script, Free and Pro, admin and front end, shows request
 * failures through window.mediashieldRequest.message() and parses responses
 * with window.mediashieldRequest.readJSON(). bin/request-error-check.php
 * fails local CI on a script that does not.
 *
 * @package MediaShield
 */
( function () {
	'use strict';

	var __ = ( window.wp && window.wp.i18n && window.wp.i18n.__ ) || function ( s ) {
		return s;
	};

	var NETWORK_MESSAGE = __( 'The server could not be reached or did not answer properly. Check your connection and try again.', 'mediashield' );
	var BROWSER_WORDS = /failed to fetch|load failed|networkerror|network request failed|unexpected token|not valid json|json\.parse|unexpected end of json/i;

	function isUnreachable( err ) {
		if ( ! err ) {
			return true;
		}
		if ( 'TypeError' === err.name || 'SyntaxError' === err.name || 'AbortError' === err.name ) {
			return true;
		}
		if ( 'fetch_error' === err.code || 'invalid_json' === err.code || 'network' === err.code ) {
			return true;
		}
		// apiFetch with parse: false rejects with the Response itself.
		if ( 'number' === typeof err.status && 'function' === typeof err.json ) {
			return 0 === err.status || err.status >= 500;
		}
		return 'string' === typeof err.message && BROWSER_WORDS.test( err.message );
	}

	window.mediashieldRequest = {
		/** The network message itself, for a caller that builds its own error. */
		networkMessage: NETWORK_MESSAGE,

		/**
		 * The words for a failed request: the network message when the server
		 * could not be reached or did not answer in JSON, otherwise the
		 * server's own message, else the caller's fallback.
		 *
		 * @param {*}      err      What the request rejected with.
		 * @param {string} fallback Shown when a refusal carries no message.
		 * @return {string}
		 */
		message: function ( err, fallback ) {
			if ( isUnreachable( err ) ) {
				return NETWORK_MESSAGE;
			}
			return ( err && 'string' === typeof err.message && '' !== err.message ) ? err.message : ( fallback || NETWORK_MESSAGE );
		},

		/**
		 * response.json(), where an answer that is not JSON (an error page)
		 * rejects with the network message (code `network`), and a JSON error
		 * answer rejects with the server's message and code.
		 *
		 * @param {Response} response A fetch() response.
		 * @return {Promise<*>}
		 */
		readJSON: function ( response ) {
			return response.json().then( function ( body ) {
				if ( ! response.ok ) {
					var refusal = new Error( ( body && body.message ) || '' );
					refusal.code = ( body && body.code ) || 'http_' + response.status;
					refusal.data = body && body.data;
					throw refusal;
				}
				return body;
			}, function () {
				var failure = new Error( NETWORK_MESSAGE );
				failure.code = 'network';
				throw failure;
			} );
		},
	};
}() );
