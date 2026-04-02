/**
 * MediaShield Admin -- Error Boundary
 *
 * Catches unhandled React errors and displays a fallback UI
 * instead of a blank white screen.
 *
 * @package MediaShield
 */

import { Component } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

class ErrorBoundary extends Component {
	constructor( props ) {
		super( props );
		this.state = { hasError: false, error: null };
	}

	static getDerivedStateFromError( error ) {
		return { hasError: true, error };
	}

	render() {
		if ( this.state.hasError ) {
			return (
				<div style={ { padding: '40px', textAlign: 'center' } }>
					<span
						className="dashicons dashicons-warning"
						style={ { fontSize: 48, color: '#d63638', display: 'block', marginBottom: 16 } }
					/>
					<h2>{ __( 'Something went wrong', 'mediashield' ) }</h2>
					<p style={ { color: '#757575' } }>
						{ __( 'An error occurred while loading this page.', 'mediashield' ) }
					</p>
					<button
						className="components-button is-primary"
						onClick={ () => {
							this.setState( { hasError: false, error: null } );
							window.location.hash = '#/dashboard';
						} }
					>
						{ __( 'Go to Dashboard', 'mediashield' ) }
					</button>
				</div>
			);
		}
		return this.props.children;
	}
}

export default ErrorBoundary;
