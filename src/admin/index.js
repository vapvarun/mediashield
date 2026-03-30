/**
 * MediaShield Admin SPA – Entry Point
 *
 * @package MediaShield
 */

import { createRoot } from '@wordpress/element';
import App from './App';
import Wizard from './wizard/Wizard';
import './admin.css';

const config = window.mediashieldAdmin || {};

// Admin SPA mount.
const adminRoot = document.getElementById( 'mediashield-admin-root' );
if ( adminRoot ) {
	const root = createRoot( adminRoot );
	root.render( <App /> );
}

// Wizard mount (separate page, same JS bundle).
const wizardRoot = document.getElementById( 'mediashield-wizard-root' );
if ( wizardRoot ) {
	const root = createRoot( wizardRoot );
	root.render( <Wizard /> );
}
