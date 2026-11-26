/**
 * Behaviour tests for assets/js/ad-breaks.js (Basecamp 10235688440): the
 * ad-break timeline shows the ad's own progress while an ad plays.
 *
 * @jest-environment jsdom
 */
const fs = require( 'fs' );
const path = require( 'path' );

// MS_ADS_JS points the suite at another copy (the old code, to see it fail).
const SCRIPT = fs.readFileSync( process.env.MS_ADS_JS || path.join( __dirname, '../../assets/js/ad-breaks.js' ), 'utf8' );

const flush = () => new Promise( ( r ) => jest.requireActual( 'timers' ).setImmediate( r ) );

beforeAll( () => {
	Object.defineProperty( window.HTMLMediaElement.prototype, 'currentTime', {
		configurable: true,
		get() { return this._t || 0; },
		set( v ) { this._t = v; },
	} );
	Object.defineProperty( window.HTMLMediaElement.prototype, 'duration', {
		configurable: true,
		get() { return this._d === undefined ? NaN : this._d; },
	} );
	window.HTMLMediaElement.prototype.play = function () {
		return Promise.resolve();
	};
	window.HTMLMediaElement.prototype.pause = function () {};
	// eslint-disable-next-line no-eval
	window.eval( SCRIPT );
} );

beforeEach( () => jest.useFakeTimers() );
afterEach( () => {
	jest.clearAllTimers();
	jest.useRealTimers();
} );

function lesson( breaks ) {
	document.body.innerHTML = `<div><div class="ms-protected-player" data-ad-breaks='${ JSON.stringify( breaks ) }'>
		<div class="ms-player-target" data-duration="600"></div></div></div>`;
	window.mediashieldConfig = { messages: {} };
	window.mediashieldAds = undefined;
	const el = document.querySelector( '.ms-protected-player' );
	const adapter = {
		_video: {},
		pos: 300,
		getPosition() { return this.pos; },
		getDuration() { return 600; },
		pause: jest.fn(),
		play: jest.fn(),
		seekTo: jest.fn(),
		onEnded() {},
	};
	window.dispatchEvent( new CustomEvent( 'mediashield:player-ready', { detail: { el, adapter } } ) );
	return { el, adapter };
}

const bar = () => document.querySelector( '.ms-ad-timeline__progress' ).style.width;

test( 'during a video ad the bar follows the ad, then the lesson again', async () => {
	lesson( [ { type: 'pre', adId: 0, label: 'Sponsor', html: '<video src="ad.mp4"></video>' } ] );
	await flush();
	const ad = document.querySelector( '.ms-ad-overlay video' );
	ad._d = 20;
	ad.currentTime = 5;
	ad.dispatchEvent( new Event( 'timeupdate' ) );

	expect( bar() ).toBe( '25%' );
	expect( document.querySelector( '.ms-ad-timeline' ).classList.contains( 'is-ad' ) ).toBe( true );
	expect( document.querySelector( '.ms-ad-timeline__caption' ).textContent ).toBe( 'Ad' );

	ad.currentTime = 15;
	jest.advanceTimersByTime( 1000 );
	expect( bar() ).toBe( '75%' );

	ad.dispatchEvent( new Event( 'ended' ) );
	expect( bar() ).toBe( '50%' ); // The lesson at 300 of 600 s.
	expect( document.querySelector( '.ms-ad-timeline' ).classList.contains( 'is-ad' ) ).toBe( false );
	expect( document.querySelector( '.ms-ad-timeline__caption' ).textContent ).toBe( 'Ad breaks' );
} );

test( 'an image ad counts its display time on the bar', async () => {
	lesson( [ { type: 'pre', adId: 0, html: '<img src="ad.png" alt="">' } ] );
	await flush();
	jest.advanceTimersByTime( 3000 );

	expect( parseFloat( bar() ) ).toBeCloseTo( 25, 0 ); // 3 of the fixed 12 s.
} );
