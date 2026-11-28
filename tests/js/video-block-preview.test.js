/**
 * The Video block's editor preview keeps its 16:9 area when the thumbnail
 * fails to load (a 403 image rule, a 404): the placeholder takes its place
 * instead of the preview collapsing to the caption (Basecamp 10552183284).
 *
 * @jest-environment jsdom
 */
import { createRoot } from 'react-dom/client';
import { act } from 'react';
import apiFetch from '@wordpress/api-fetch';

jest.mock( '@wordpress/api-fetch', () => jest.fn(), { virtual: true } );
jest.mock( '@wordpress/element', () => require( 'react' ), { virtual: true } );
jest.mock( '@wordpress/i18n', () => ( {
	__: ( s ) => s,
	_n: ( s, p, n ) => ( 1 === n ? s : p ),
	sprintf: ( f, ...a ) => a.reduce( ( out, v ) => out.replace( /%(\d\$)?[sd]/, v ), f ),
} ), { virtual: true } );
jest.mock( '@wordpress/data', () => ( { useSelect: () => 0, useDispatch: () => ( {} ) } ), { virtual: true } );
jest.mock( '@wordpress/html-entities', () => ( { decodeEntities: ( s ) => s } ), { virtual: true } );
jest.mock( '@wordpress/block-editor', () => {
	const React = require( 'react' );
	return { useBlockProps: () => ( {} ), InspectorControls: () => null, __esModule: true, default: {} };
}, { virtual: true } );
jest.mock( '@wordpress/components', () => {
	const React = require( 'react' );
	const Stub = ( { children } ) => React.createElement( 'div', null, children );
	return new Proxy( {}, { get: ( t, name ) => ( '__esModule' === name ? true : Stub ) } );
}, { virtual: true } );
jest.mock( 'lucide-react', () => ( { Video: () => null } ), { virtual: true } );
jest.mock( '../../src/admin/components/VideoPickerModal', () => ( { __esModule: true, default: () => null } ) );

// MS_BLOCK_EDIT_JS points the suite at another copy (the old code, to see it fail).
const Edit = require( process.env.MS_BLOCK_EDIT_JS || '../../src/blocks/video/edit' ).default;

global.IS_REACT_ACT_ENVIRONMENT = true;

test( 'a thumbnail that fails to load is replaced by the 16:9 placeholder', async () => {
	apiFetch.mockResolvedValue( { title: 'Lesson 1', platform_label: 'Bunny', duration: 0, thumbnail: 'https://example.test/blocked.jpg', protection_label: 'Standard', used_in: [] } );
	const host = document.createElement( 'div' );
	document.body.appendChild( host );
	const root = createRoot( host );

	await act( async () => {
		root.render( <Edit attributes={ { videoId: 9694 } } setAttributes={ () => {} } /> );
	} );
	const poster = host.querySelector( '.ms-block-preview__poster' );
	expect( poster ).not.toBeNull();

	await act( async () => {
		poster.dispatchEvent( new Event( 'error' ) );
	} );

	expect( host.querySelector( '.ms-block-preview__poster' ) ).toBeNull();
	expect( host.querySelector( '.ms-block-preview .ms-block-placeholder-preview' ) ).not.toBeNull();
	expect( host.querySelector( '.ms-block-preview__caption' ).textContent ).toContain( 'Lesson 1' );

	act( () => root.unmount() );
} );
