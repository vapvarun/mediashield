/**
 * Wizard Step 1: General Settings.
 */
import { __ } from '@wordpress/i18n';
import { ToggleControl, SelectControl } from '@wordpress/components';
import { useState } from '@wordpress/element';

export default function GeneralStep( { onSave, initialData } ) {
	const [ enabled, setEnabled ] = useState( initialData?.ms_enabled ?? true );
	const [ requireLogin, setRequireLogin ] = useState( initialData?.ms_require_login ?? true );
	const [ protection, setProtection ] = useState( initialData?.ms_default_protection || 'standard' );

	const save = ( key, value ) => {
		onSave( { [ key ]: value } );
	};

	return (
		<div className="ms-wizard__step">
			<div className="ms-wizard__step-header">
				<span className="ms-wizard__step-icon dashicons dashicons-admin-generic" />
				<div>
					<h2>{ __( 'General Settings', 'mediashield' ) }</h2>
					<p>{ __( 'Configure the basic protection settings for your videos.', 'mediashield' ) }</p>
				</div>
			</div>

			<div className="ms-wizard__step-fields">
				<ToggleControl
					label={ __( 'Enable video protection', 'mediashield' ) }
					checked={ enabled }
					onChange={ ( val ) => { setEnabled( val ); save( 'ms_enabled', val ); } }
				/>

				<ToggleControl
					label={ __( 'Require login to watch', 'mediashield' ) }
					help={ __( 'Visitors must be logged in to view protected videos.', 'mediashield' ) }
					checked={ requireLogin }
					onChange={ ( val ) => { setRequireLogin( val ); save( 'ms_require_login', val ); } }
				/>

				<SelectControl
					label={ __( 'Default protection level', 'mediashield' ) }
					value={ protection }
					options={ [
						{ label: __( 'Standard (Watermark + Tracking)', 'mediashield' ), value: 'standard' },
						{ label: __( 'None (No protection)', 'mediashield' ), value: 'none' },
					] }
					onChange={ ( val ) => { setProtection( val ); save( 'ms_default_protection', val ); } }
				/>
			</div>
		</div>
	);
}
