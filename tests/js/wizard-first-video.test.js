/**
 * The setup wizard's First Video step (MSUX-01): Save & Continue adds a
 * pasted link, a refused link keeps the step open, and the step is ticked
 * only when a video exists. Renders the real Wizard and presses its buttons.
 *
 * @jest-environment jsdom
 */
import { createRoot } from 'react-dom/client';
import { act } from 'react';
import apiFetch from '@wordpress/api-fetch';

// The @wordpress packages are WordPress's own scripts (externals), not
// installed here: React stands in for element, and plain stand-ins for the
// components behave like them (a primary Button carries is-primary).
jest.mock( '@wordpress/api-fetch', () => jest.fn(), { virtual: true } );
jest.mock( '@wordpress/element', () => require( 'react' ), { virtual: true } );
jest.mock( '@wordpress/i18n', () => ( {
	__: ( s ) => s,
	_n: ( s, p, n ) => ( 1 === n ? s : p ),
	sprintf: ( f, ...a ) => a.reduce( ( out, v ) => out.replace( /%(\d\$)?[sd]/, v ), f ),
} ), { virtual: true } );
jest.mock( '@wordpress/data', () => ( { useDispatch: () => ( { createErrorNotice: () => {} } ), useSelect: () => ( {} ) } ), { virtual: true } );
jest.mock( '@wordpress/notices', () => ( { store: {} } ), { virtual: true } );
jest.mock( '@wordpress/compose', () => ( { useInstanceId: () => 'id1' } ), { virtual: true } );
jest.mock( '@wordpress/hooks', () => ( { applyFilters: ( n, v ) => v, addFilter: () => {} } ), { virtual: true } );
jest.mock( '@wordpress/html-entities', () => ( { decodeEntities: ( s ) => s } ), { virtual: true } );
jest.mock( '@wordpress/components', () => {
	const React = require( 'react' );
	const Button = ( { variant, className = '', onClick, disabled, children } ) =>
		React.createElement( 'button', { className: `components-button ${ 'primary' === variant ? 'is-primary' : '' } ${ className }`, onClick, disabled }, children );
	const TextControl = ( { value, onChange, type = 'text' } ) =>
		React.createElement( 'input', { type, value, onChange: ( e ) => onChange( e.target.value ) } );
	const Stub = ( { children } ) => React.createElement( 'div', null, children );
	return new Proxy( { Button, TextControl }, { get: ( t, name ) => ( name in t ? t[ name ] : ( '__esModule' === name ? true : Stub ) ) } );
}, { virtual: true } );

// The admin config is read when the modules load.
window.mediashieldAdmin = {
	restUrl: '/r/',
	nonce: 'n',
	adminUrl: '/wp-admin/',
	defaultProtection: 'standard',
	protectionLevels: { standard: { label: 'Standard', summary: '' } },
	unplayableMessage: 'REFUSED',
	wizardLinkHint: 'Paste a link.',
};

// MS_WIZARD_JS points the suite at another copy (the old code, to see it fail).
const Wizard = require( process.env.MS_WIZARD_JS || '../../src/admin/wizard/Wizard' ).default;

global.IS_REACT_ACT_ENVIRONMENT = true;

let root;
let host;
let created;

beforeEach( async () => {
	created = [];
	window.mediashieldRequest = { message: ( err, fallback ) => fallback };
	apiFetch.mockReset();
	apiFetch.mockImplementation( ( opts ) => {
		if ( '/wp/v2/mediashield-videos' === opts.path && 'POST' === opts.method ) {
			created.push( opts.data );
			return Promise.resolve( { id: 42, title: { raw: 'Me at the zoo' }, meta: { _ms_platform: 'youtube' } } );
		}
		return Promise.resolve( {} );
	} );
	host = document.createElement( 'div' );
	document.body.appendChild( host );
	root = createRoot( host );
	await act( async () => root.render( <Wizard /> ) );
	// Steps 1 and 2 to the First Video step.
	await next();
	await next();
} );

afterEach( () => {
	act( () => root.unmount() );
	host.remove();
} );

async function next() {
	await act( async () => {
		host.querySelector( '.ms-wizard__btn--next' ).click();
	} );
}

async function type( value ) {
	const input = host.querySelector( 'input[type="url"]' );
	const setter = Object.getOwnPropertyDescriptor( HTMLInputElement.prototype, 'value' ).set;
	await act( async () => {
		setter.call( input, value );
		input.dispatchEvent( new Event( 'input', { bubbles: true } ) );
	} );
}

const stepTicked = ( n ) => host.querySelectorAll( '.ms-wizard__stepper-item' )[ n ].classList.contains( 'is-complete' );
const heading = () => host.querySelector( '.ms-wizard__step h2' ).textContent;

test( 'Save & Continue adds a pasted link, then ticks the step', async () => {
	expect( heading() ).toBe( 'Protect your first video' );
	await type( 'https://www.youtube.com/watch?v=jNQXAC9IVRw' );
	await next();

	expect( created ).toHaveLength( 1 );
	expect( created[ 0 ].meta._ms_source_url ).toBe( 'https://www.youtube.com/watch?v=jNQXAC9IVRw' );
	expect( heading() ).not.toBe( 'Protect your first video' );
	expect( stepTicked( 2 ) ).toBe( true );
} );

test( 'a refused link keeps the step open with the reason', async () => {
	await type( 'https://example.com/about-us' );
	await next();

	expect( created ).toHaveLength( 0 );
	expect( heading() ).toBe( 'Protect your first video' );
	expect( host.querySelector( '.ms-wizard__error' ).textContent ).toBe( 'REFUSED' );
} );

test( 'moving on with an empty field adds nothing and ticks nothing', async () => {
	await next();

	expect( created ).toHaveLength( 0 );
	expect( heading() ).not.toBe( 'Protect your first video' );
	expect( stepTicked( 2 ) ).toBe( false );
} );

test( 'one primary button on the step', () => {
	expect( host.querySelectorAll( '.ms-wizard__card .is-primary' ) ).toHaveLength( 0 );
	expect( host.querySelectorAll( '.ms-wizard__actions .is-primary' ) ).toHaveLength( 1 );
} );
