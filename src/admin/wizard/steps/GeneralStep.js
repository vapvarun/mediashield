/**
 * Wizard Step 1: General Settings.
 *
 * The protection levels are the shared list (LevelPicker, the same words as
 * Settings and the video edit screen); login is its own setting, as under
 * Settings > Who can watch (Basecamp 10545260510).
 */
import { __ } from '@wordpress/i18n';
import { ToggleControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import Icon from '../../components/Icon';
import LevelPicker from '../../components/LevelPicker';

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
				<Icon name="admin-generic" className="ms-wizard__step-icon" />
				<div>
					<h2>{ __( 'General Settings', 'mediashield' ) }</h2>
					<p>{ __( 'How your videos are protected and who can watch them.', 'mediashield' ) }</p>
				</div>
			</div>

			<div className="ms-wizard__step-fields">
				<ToggleControl
					label={ __( 'Protect videos', 'mediashield' ) }
					help={ __( 'Off: every video plays with no MediaShield protection.', 'mediashield' ) }
					checked={ enabled }
					onChange={ ( val ) => {
						setEnabled( val );
						save( 'ms_enabled', val );
					} }
					__nextHasNoMarginBottom
				/>

				<LevelPicker
					legend={ __( 'Protection level for your videos', 'mediashield' ) }
					value={ protection }
					onChange={ ( val ) => {
						setProtection( val );
						save( 'ms_default_protection', val );
					} }
					note={ __( 'Each level includes the one above it. A video can set its own level.', 'mediashield' ) }
				/>

				<ToggleControl
					label={ __( 'Require login', 'mediashield' ) }
					help={ __( 'Only logged-in members can watch. Applies at every protection level.', 'mediashield' ) }
					checked={ requireLogin }
					onChange={ ( val ) => {
						setRequireLogin( val );
						save( 'ms_require_login', val );
					} }
					__nextHasNoMarginBottom
				/>
			</div>
		</div>
	);
}
