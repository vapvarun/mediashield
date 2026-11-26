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
import { useInstanceId } from '@wordpress/compose';
import Icon from '../../components/Icon';
import LevelPicker from '../../components/LevelPicker';

export default function GeneralStep( { onSave, initialData } ) {
	const [ enabled, setEnabled ] = useState( initialData?.ms_enabled ?? true );
	const [ requireLogin, setRequireLogin ] = useState( initialData?.ms_require_login ?? true );
	const [ protection, setProtection ] = useState( initialData?.ms_default_protection || 'standard' );
	const whoId = useInstanceId( GeneralStep, 'ms-who' );
	// Asked plainly, so a public trailer is not walled by surprise (MSUX-06).
	// The site's current setting is preselected; nothing changes until the
	// owner picks.
	const whoChoices = [
		{
			value: false,
			label: __( 'Anyone who opens the page', 'mediashield' ),
			desc: __( 'Visitors and members. Good for trailers and public videos.', 'mediashield' ),
		},
		{
			value: true,
			label: __( 'Logged-in members only', 'mediashield' ),
			desc: __( 'Visitors see a login prompt instead of the video.', 'mediashield' ),
		},
	];

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
					help={ __( 'When off, every video plays with no MediaShield protection.', 'mediashield' ) }
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

				<fieldset className="ms-choices ms-wizard__who">
					<legend className="ms-choices__legend">{ __( 'Who can watch?', 'mediashield' ) }</legend>
					{ whoChoices.map( ( choice ) => (
						<label key={ String( choice.value ) } className="ms-choice">
							<input
								type="radio"
								name={ whoId }
								value={ choice.value ? 'members' : 'anyone' }
								checked={ requireLogin === choice.value }
								onChange={ () => {
									setRequireLogin( choice.value );
									save( 'ms_require_login', choice.value );
								} }
							/>
							<span className="ms-choice__text">
								<span className="ms-choice__label">{ choice.label }</span>
								<span className="ms-choice__desc">{ choice.desc }</span>
							</span>
						</label>
					) ) }
					<p className="ms-choices__note">{ __( 'Applies at every protection level. Change it later under Settings > Who can watch; a video can also be limited to one role.', 'mediashield' ) }</p>
				</fieldset>
			</div>
		</div>
	);
}
