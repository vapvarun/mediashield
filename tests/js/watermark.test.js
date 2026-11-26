/**
 * Behaviour tests for assets/js/watermark.js (front-end walk F27): the
 * watermark reads on bright footage, at the owner's opacity and colour.
 *
 * @jest-environment jsdom
 */
const fs = require( 'fs' );
const path = require( 'path' );

// MS_WATERMARK_JS points the suite at another copy (the old code, to see it fail).
const SCRIPT = fs.readFileSync( process.env.MS_WATERMARK_JS || path.join( __dirname, '../../assets/js/watermark.js' ), 'utf8' );

let contexts;

beforeAll( () => {
	// jsdom has no 2D canvas: a context that records what is drawn.
	window.HTMLCanvasElement.prototype.getContext = function () {
		if ( ! this._ctx ) {
			const calls = [];
			this._ctx = {
				calls,
				canvas: this,
				fillStyle: '#000000',
				strokeStyle: '#000000',
				globalAlpha: 1,
				measureText: ( t ) => ( { width: t.length * 6 } ),
				clearRect() {},
				fillText( t ) { calls.push( [ 'fill', t, this.fillStyle, this.globalAlpha ] ); },
				strokeText( t ) { calls.push( [ 'stroke', t, this.strokeStyle, this.globalAlpha ] ); },
				drawImage( img ) { calls.push( [ 'image', img._ctx, this.globalAlpha ] ); },
			};
			contexts.push( this._ctx );
		}
		return this._ctx;
	};
	window.HTMLElement.prototype.getBoundingClientRect = function () {
		return { top: 0, left: 0, right: 800, bottom: 450, width: 800, height: 450 };
	};
	window.mediashieldConfig = {};
	// eslint-disable-next-line no-eval
	window.eval( SCRIPT );
} );

beforeEach( () => {
	contexts = [];
	jest.useFakeTimers();
} );

afterEach( () => {
	jest.clearAllTimers();
	jest.useRealTimers();
} );

function draw( watermarkConfig ) {
	document.body.innerHTML = '<div class="ms-protected-player" data-platform="self"><canvas class="ms-watermark-canvas"></canvas></div>';
	const el = document.querySelector( '.ms-protected-player' );
	window.dispatchEvent( new CustomEvent( 'mediashield:player-ready', { detail: { el, watermarkConfig } } ) );
	jest.advanceTimersByTime( 1 );
	return contexts;
}

test( 'the default white name gets a dark outline, laid on at the owner\'s opacity', () => {
	const [ main ] = draw( { enabled: true, text: 'Sofia Reyes', color: '#ffffff', opacity: 0.5 } );
	const drawn = contexts.flatMap( ( c ) => c.calls );

	const stroke = drawn.find( ( c ) => 'stroke' === c[ 0 ] );
	const fill = drawn.find( ( c ) => 'fill' === c[ 0 ] );
	expect( stroke && stroke[ 1 ] ).toBe( 'Sofia Reyes' );
	expect( stroke[ 2 ] ).toMatch( /^rgba\(0, 0, 0/ );
	expect( fill[ 2 ] ).toBe( '#ffffff' );
	// The text itself is solid on the buffer; the owner's 50% applies once.
	expect( fill[ 3 ] ).toBe( 1 );
	expect( main.calls.find( ( c ) => 'image' === c[ 0 ] )[ 2 ] ).toBe( 0.5 );
} );

test( 'a dark colour the owner saved keeps its colour and gets a light outline', () => {
	draw( { enabled: true, text: 'Sofia Reyes', color: '#1a1a1a', opacity: 0.8 } );
	const drawn = contexts.flatMap( ( c ) => c.calls );

	expect( drawn.find( ( c ) => 'fill' === c[ 0 ] )[ 2 ] ).toBe( '#1a1a1a' );
	expect( drawn.find( ( c ) => 'stroke' === c[ 0 ] )[ 2 ] ).toMatch( /^rgba\(255, 255, 255/ );
} );
