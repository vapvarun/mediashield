/**
 * Behaviour tests for src/blocks/playlist/view.js (front-end walk F26, F29).
 *
 * @jest-environment jsdom
 */
const fs = require( 'fs' );
const path = require( 'path' );

// MS_PLAYLIST_JS points the suite at another copy (the old code, to see it fail).
const SCRIPT = fs.readFileSync( process.env.MS_PLAYLIST_JS || path.join( __dirname, '../../src/blocks/playlist/view.js' ), 'utf8' );

let observed;

function boot() {
	document.body.innerHTML = `
		<div class="ms-playlist-player" data-playlist-id="5" data-autoplay="1" data-countdown="1">
			<div class="ms-playlist-main"><div class="ms-protected-player" data-video-id="12" data-ms-context="post:33"></div></div>
			<div class="ms-playlist-items">
				<div class="ms-playlist-item is-locked" data-video-id="11"></div>
				<div class="ms-playlist-item is-active" data-video-id="12"><video class="ms-playlist-item-thumb" data-frame-src="https://example.org/stream/12?ms_token=t"></video></div>
				<div class="ms-playlist-item is-locked" data-video-id="13"></div>
				<div class="ms-playlist-item" data-video-id="14"><video class="ms-playlist-item-thumb" data-frame-src="https://example.org/stream/14?ms_token=t"></video></div>
			</div>
		</div>`;
	window.mediashieldConfig = { restUrl: '/wp-json/mediashield/v1/', nonce: 'n' };
	window.mediashieldRequest = { readJSON: ( r ) => r.json() };
	global.fetch = jest.fn( () => Promise.resolve( { json: () => Promise.resolve( { html: '<div class="ms-protected-player" data-video-id="14"></div>' } ) } ) );
	observed = [];
	window.IntersectionObserver = function ( cb ) {
		this.observe = ( el ) => observed.push( [ el, cb, this ] );
		this.unobserve = () => {};
	};
	// eslint-disable-next-line no-eval
	window.eval( SCRIPT );
}

beforeEach( () => jest.useFakeTimers() );
afterEach( () => {
	jest.clearAllTimers();
	jest.useRealTimers();
} );

test( 'autoplay goes from the playable start to the next playable item, past a locked one, naming the page', () => {
	boot();
	document.querySelector( '.ms-playlist-player' ).dispatchEvent( new CustomEvent( 'mediashield:ended' ) );
	jest.advanceTimersByTime( 1000 );

	expect( global.fetch ).toHaveBeenCalledTimes( 1 );
	expect( global.fetch.mock.calls[ 0 ][ 0 ] ).toBe( '/wp-json/mediashield/v1/playlists/5/player/14?ms_context=post%3A33' );
} );

test( 'a video file row loads its frame only when it comes into view', () => {
	boot();
	const [ el, cb, io ] = observed[ 0 ];
	expect( el.getAttribute( 'src' ) ).toBeNull();

	cb( [ { isIntersecting: true, target: el } ], io );

	expect( el.getAttribute( 'src' ) ).toBe( 'https://example.org/stream/12?ms_token=t#t=0.5' );
	expect( el.preload ).toBe( 'metadata' );
} );
