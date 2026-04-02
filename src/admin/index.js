/**
 * MediaShield Admin SPA – Entry Point
 *
 * @package MediaShield
 */

import { createRoot } from '@wordpress/element';
import domReady from '@wordpress/dom-ready';
import App from './App';
import Wizard from './wizard/Wizard';
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
} );
