/**
 * MediaShield Admin SPA – Entry Point
 *
 * @package MediaShield
 */

import { createRoot, lazy, Suspense } from '@wordpress/element';
import domReady from '@wordpress/dom-ready';
import App from './App';
import Loading from './components/Loading';
// Every admin stylesheet is imported here, so all of them stay in index.css
// (and its RTL twin) while the screens' code loads on demand.
import './admin.css';
import './pages/videos.css';
import './pages/settings.css';
import './components/upsell.css';
import './components/choices.css';
import './components/VideoPickerModal.css';

// Pages other than the admin app: loaded only where they mount.
const Wizard = lazy( () => import( /* webpackChunkName: "wizard" */ './wizard/Wizard' ) );
const PlaylistItemsPanel = lazy( () => import( /* webpackChunkName: "playlist-items" */ './components/PlaylistItemsPanel' ) );

domReady( () => {
	// Admin SPA mount.
	const adminRoot = document.getElementById( 'mediashield-admin-root' );
	if ( adminRoot ) {
		createRoot( adminRoot ).render( <App /> );
	}

	// Wizard mount (separate page, same JS bundle).
	const wizardRoot = document.getElementById( 'mediashield-wizard-root' );
	if ( wizardRoot ) {
		createRoot( wizardRoot ).render(
			<Suspense fallback={ <Loading /> }>
				<Wizard />
			</Suspense>
		);
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
				<Suspense fallback={ <Loading /> }>
					<PlaylistItemsPanel
						playlistId={ playlistId }
						playlistTitle={ playlistTitle }
					/>
				</Suspense>
			);
		}
	}
} );
