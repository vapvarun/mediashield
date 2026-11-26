/**
 * The four protection levels as one radio list, in the shared words
 * (utils/protection.js). Used by Settings and the setup wizard.
 *
 * @package MediaShield
 */
import { __ } from '@wordpress/i18n';
import { useInstanceId } from '@wordpress/compose';
import { protectionLevels } from '../utils/protection';
import './choices.css';

/**
 * @param {Object}   props
 * @param {string}   props.value    Selected level slug.
 * @param {Function} props.onChange Called with the new slug.
 * @param {string}   props.legend   Group label.
 * @param {string}   props.note     Line under the list.
 * @return {JSX.Element} Radio group.
 */
export default function LevelPicker( { value, onChange, legend, note } ) {
	const id = useInstanceId( LevelPicker, 'ms-level' );
	const levels = protectionLevels();

	return (
		<fieldset className="ms-choices">
			<legend className="ms-choices__legend">{ legend || __( 'Protection level', 'mediashield' ) }</legend>
			{ Object.keys( levels ).map( ( slug ) => (
				<label key={ slug } className="ms-choice">
					<input
						type="radio"
						name={ id }
						value={ slug }
						checked={ value === slug }
						onChange={ () => onChange( slug ) }
					/>
					<span className="ms-choice__text">
						<span className="ms-choice__label">{ levels[ slug ].label }</span>
						<span className="ms-choice__desc">{ levels[ slug ].adds }</span>
					</span>
				</label>
			) ) }
			{ note && <p className="ms-choices__note">{ note }</p> }
		</fieldset>
	);
}
