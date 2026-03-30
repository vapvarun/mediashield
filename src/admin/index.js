/**
 * MediaShield Admin SPA – Entry Point
 *
 * @package MediaShield
 */

import { createRoot } from '@wordpress/element';
import App from './App';
import './admin.css';

const container = document.getElementById( 'mediashield-admin-root' );

if ( container ) {
	const root = createRoot( container );
	root.render( <App /> );
}
