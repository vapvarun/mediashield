/**
 * MediaShield Admin SPA – Entry Point
 *
 * @package MediaShield
 */

import { createRoot } from '@wordpress/element';
import domReady from '@wordpress/dom-ready';
import App from './App';
import Wizard from './wizard/Wizard';
import PlaylistItemsPanel from './components/PlaylistItemsPanel';
import './admin.css';

domReady( () => {
	// Admin SPA mount.
	const adminRoot = document.getElementById( 'mediashield-admin-root' );
	if ( adminRoot ) {
		createRoot( adminRoot ).render( <App /> );
	}

	// Wizard mount (separate page, same JS bundle).
	const wizardRoot = document.getElementById( 'mediashield-wizard-root' );
	if ( wizardRoot ) {
		createRoot( wizardRoot ).render( <Wizard /> );
	}

	// Playlist Items meta box mount on the playlist CPT edit screen.
	// PlaylistPostType.php enqueues this bundle on that screen and emits
	// `<div id="mediashield-playlist-items-root" data-playlist-id="N">`.
	const itemsRoot = document.getElementById( 'mediashield-playlist-items-root' );
	if ( itemsRoot ) {
		const playlistId = parseInt( itemsRoot.getAttribute( 'data-playlist-id' ) || '0', 10 );
		const playlistTitle = itemsRoot.getAttribute( 'data-playlist-title' ) || '';
		if ( playlistId > 0 ) {
			createRoot( itemsRoot ).render(
				<PlaylistItemsPanel
					playlistId={ playlistId }
					playlistTitle={ playlistTitle }
				/>
			);
		}
	}
} );
