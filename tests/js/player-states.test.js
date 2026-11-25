/**
 * Behaviour tests for assets/js/player-wrapper.js (Basecamp 10545260568, 10545260660).
 *
 * The script runs in jsdom with a fake REST answer; each test presses the
 * real buttons the member sees. Run: npx wp-scripts test-unit-js tests/js
 *
 * @jest-environment jsdom
 */
const fs = require( 'fs' );
const path = require( 'path' );

// MS_PLAYER_JS points the suite at another copy (the old code, to see it fail).
const SCRIPT = fs.readFileSync( process.env.MS_PLAYER_JS || path.join( __dirname, '../../assets/js/player-wrapper.js' ), 'utf8' );
// MS_TRACKER_JS likewise for the tracker.
const TRACKER = fs.readFileSync( process.env.MS_TRACKER_JS || path.join( __dirname, '../../assets/js/tracker.js' ), 'utf8' );

let played;
let reports;

function boot( { markup, session, loggedIn = true, tracker = false, heartbeat = {} } ) {
	document.body.innerHTML = markup;
	reports = [];
	window.mediashieldConfig = {
		restUrl: '/wp-json/mediashield/v1/',
		nonce: 'n',
		isLoggedIn: loggedIn ? '1' : '',
		requireLogin: '1',
		loginUrl: '/wp-login.php',
		player: { resume: '1', speedControl: '' },
		messages: {},
	};
	window.mediashieldRequest = { networkMessage: 'Network', message: () => 'Network', readJSON: ( r ) => r.json() };
	navigator.sendBeacon = jest.fn( ( url, blob ) => {
		reports.push( { url, type: blob.type } );
		return true;
	} );
	global.fetch = jest.fn( ( url ) => {
		const body = /heartbeat/.test( url ) ? heartbeat : session.body;
		const res = { ok: true, status: /heartbeat/.test( url ) ? 200 : session.status || 200, json: () => Promise.resolve( body ) };
		res.clone = () => res;
		return Promise.resolve( res );
	} );
	window.mediashieldConfig.interval = 30000;
	// eslint-disable-next-line no-eval
	window.eval( SCRIPT );
	if ( tracker ) {
		// eslint-disable-next-line no-eval
		window.eval( TRACKER );
	}
}

const flush = () => new Promise( ( r ) => jest.requireActual( 'timers' ).setImmediate( r ) );

const nativePlayer = ( extra = '' ) => `
	<div class="ms-protected-player" data-video-id="7" data-platform="self" data-protection-level="standard" ${ extra }>
		<div class="ms-player-target" data-source-url="https://example.org/lesson.mp4" data-stream-url="" data-duration="300"></div>
	</div>`;

beforeAll( () => {
	// jsdom has no media playback: a minimal clock and play state.
	Object.defineProperty( window.HTMLMediaElement.prototype, 'currentTime', {
		configurable: true,
		get() { return this._t || 0; },
		set( v ) { this._t = v; },
	} );
	Object.defineProperty( window.HTMLMediaElement.prototype, 'paused', {
		configurable: true,
		get() { return ! this._playing; },
	} );
	window.HTMLMediaElement.prototype.play = function () {
		this._playing = true;
		played.push( this.currentTime );
		this.dispatchEvent( new Event( 'play' ) );
		return Promise.resolve();
	};
	window.HTMLMediaElement.prototype.pause = function () {
		this._playing = false;
		this.dispatchEvent( new Event( 'pause' ) );
	};
	window.HTMLMediaElement.prototype.load = function () {};
} );

beforeEach( () => {
	played = [];
	jest.useFakeTimers();
} );

afterEach( () => {
	jest.useRealTimers();
} );

async function readyNative() {
	document.querySelector( 'video' ).dispatchEvent( new Event( 'loadedmetadata' ) );
	await flush();
	await flush();
}

describe( 'Resume (Basecamp 10545260660)', () => {
	test( 'the chip stays until the member picks', async () => {
		boot( { markup: nativePlayer(), session: { body: { session_token: 't', resume_position: 252 } } } );
		await readyNative();

		const chip = document.querySelector( '.ms-resume-chip' );
		expect( chip ).not.toBeNull();
		expect( chip.textContent ).toContain( 'Resume from 4:12' );
		expect( chip.textContent ).toContain( 'Start over' );

		jest.advanceTimersByTime( 60000 );
		expect( document.querySelector( '.ms-resume-chip' ) ).not.toBeNull();
	} );

	test( 'the big Play resumes at the saved position', async () => {
		boot( { markup: nativePlayer(), session: { body: { session_token: 't', resume_position: 252 } } } );
		await readyNative();

		const big = document.querySelector( '.ms-bigplay' );
		expect( big.getAttribute( 'aria-label' ) ).toBe( 'Resume from 4:12' );
		big.click();

		expect( played ).toEqual( [ 252 ] );
		expect( document.querySelector( '.ms-resume-chip' ) ).toBeNull();
		expect( big.getAttribute( 'aria-label' ) ).toBe( 'Play video' );
	} );

	test( 'Start over plays from the beginning', async () => {
		boot( { markup: nativePlayer(), session: { body: { session_token: 't', resume_position: 252 } } } );
		await readyNative();

		document.querySelector( '.ms-resume-chip__over' ).click();

		expect( played ).toEqual( [ 0 ] );
		expect( document.querySelector( '.ms-resume-chip' ) ).toBeNull();
	} );
} );

describe( 'Player states (Basecamp 10545260568)', () => {
	test( 'logged out: message, who it is for, Log in, focus left alone', () => {
		boot( { markup: nativePlayer(), session: { body: {} }, loggedIn: false } );

		const overlay = document.querySelector( '.ms-login-overlay' );
		expect( overlay.textContent ).toContain( 'It is for members of this site.' );
		expect( overlay.querySelector( 'a.ms-login-button' ).getAttribute( 'href' ) ).toBe( '/wp-login.php' );
		expect( document.activeElement ).toBe( document.body );
	} );

	test( 'logged in without access: the owner\'s membership link', async () => {
		boot( {
			markup: nativePlayer( 'data-access-denied="1"' ),
			session: { status: 403, body: { code: 'access_denied', message: 'This video is for Gold members', data: { access_url: 'https://example.org/join/', access_label: 'See membership options' } } },
		} );
		await flush();
		await flush();

		const overlay = document.querySelector( '.ms-error-overlay' );
		expect( overlay.textContent ).toContain( 'This video is for Gold members' );
		const action = overlay.querySelector( 'a.ms-error-action' );
		expect( action.textContent ).toBe( 'See membership options' );
		expect( action.getAttribute( 'href' ) ).toBe( 'https://example.org/join/' );
	} );

	test( 'logged in without access and no membership page: a plain next step, no button', async () => {
		boot( {
			markup: nativePlayer( 'data-access-denied="1"' ),
			session: { status: 403, body: { code: 'access_denied', message: 'You do not have access to this video', data: { status: 403 } } },
		} );
		await flush();
		await flush();

		const overlay = document.querySelector( '.ms-error-overlay' );
		expect( overlay.querySelector( '.ms-error-sub' ).textContent ).toBe( 'Ask the site owner for access.' );
		expect( overlay.querySelector( 'a, button' ) ).toBeNull();
	} );

	function youtubePlayer( extra = '' ) {
		return `
			<div class="ms-protected-player" data-video-id="9" data-platform="youtube" data-protection-level="none" ${ extra }>
				<div class="ms-player-target" data-platform-video-id="aaaaaaaaaaa"></div>
			</div>`;
	}

	function fakeYouTube( errorCode ) {
		window.YT = {
			PlayerState: { PLAYING: 1, ENDED: 0 },
			Player: function ( target, opts ) {
				this.destroy = () => {};
				setTimeout( () => opts.events.onError( { data: errorCode } ), 0 );
			},
		};
	}

	test( 'removed at the source: message, Try again, report; no cause for a viewer', async () => {
		fakeYouTube( 100 );
		boot( { markup: youtubePlayer(), session: { body: {} } } );
		jest.advanceTimersByTime( 1 );

		const overlay = document.querySelector( '.ms-error-overlay' );
		expect( overlay.textContent ).toContain( 'This video is unavailable.' );
		expect( overlay.textContent ).toContain( 'The site owner has been told. Please try again later.' );
		expect( overlay.querySelector( '.ms-error-retry' ).textContent ).toBe( 'Try again' );
		expect( overlay.querySelector( '.ms-error-admin' ) ).toBeNull();
		expect( reports ).toEqual( [ { url: '/wp-json/mediashield/v1/videos/9/unavailable?_wpnonce=n', type: 'application/json' } ] );

		// Try again starts the player afresh, and only one report is sent.
		overlay.querySelector( '.ms-error-retry' ).click();
		expect( document.querySelectorAll( '.ms-player-target' ) ).toHaveLength( 1 );
		jest.advanceTimersByTime( 1 );
		expect( document.querySelectorAll( '.ms-error-overlay' ) ).toHaveLength( 1 );
		expect( reports ).toHaveLength( 1 );
	} );

	test( 'an editor also sees the cause and Edit video', () => {
		fakeYouTube( 150 );
		boot( { markup: youtubePlayer( 'data-edit-url="/wp-admin/post.php?post=9&action=edit"' ), session: { body: {} } } );
		jest.advanceTimersByTime( 1 );

		const note = document.querySelector( '.ms-error-admin' );
		expect( note.textContent ).toContain( 'YouTube will not play this video here. It may be private, removed, or not allowed on other sites.' );
		expect( note.querySelector( 'a' ).getAttribute( 'href' ) ).toBe( '/wp-admin/post.php?post=9&action=edit' );
		expect( reports ).toHaveLength( 1 );
	} );
} );

describe( 'Live watched share for an LMS (mediashield:watched)', () => {
	test( 'fires on session start and after each heartbeat, from the player container', async () => {
		boot( {
			markup: nativePlayer( 'data-watch-required="70"' ),
			session: { body: { session_token: 't', resume_position: 0, watch: { watched_pct: 12, required_pct: 0 } } },
			heartbeat: { watched_pct: 20, required_pct: 0 },
			tracker: true,
		} );
		const seen = [];
		document.addEventListener( 'mediashield:watched', ( e ) => {
			seen.push( { target: e.target.matches( '.ms-protected-player' ), ...e.detail } );
		} );
		await readyNative();

		document.querySelector( '.ms-bigplay' ).click();
		jest.advanceTimersByTime( 30000 );
		await flush();
		await flush();
		await flush();

		expect( seen ).toEqual( [
			{ target: true, videoId: 7, watchedPct: 12, requiredPct: 70 },
			{ target: true, videoId: 7, watchedPct: 20, requiredPct: 70 },
		] );
	} );
} );
