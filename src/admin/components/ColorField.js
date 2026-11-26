/**
 * A colour setting as a small swatch button that opens the picker.
 *
 * Settings and the wizard both used an always-open ColorPicker, two large
 * panels on one screen (MSUX-29). The picker reports every drag to
 * onChange (for a live preview); onClose runs once when it closes, for a
 * caller that saves on commit.
 *
 * @package MediaShield
 */

import { Button, ColorPicker, Dropdown } from '@wordpress/components';

/**
 * @param {Object}   props
 * @param {string}   props.label       Field label.
 * @param {string}   props.value       Hex colour, or '' when none is set.
 * @param {string}   props.fallback    Colour the picker starts from while none is set.
 * @param {string}   [props.emptyLabel] Button text while none is set ("Your theme's colour"); the swatch is then drawn empty.
 * @param {Function} props.onChange    Called with each new hex while picking.
 * @param {Function} [props.onClose]   Called when the picker closes.
 * @param {*}        [props.children]  Extra controls after the swatch (e.g. a reset button).
 */
export default function ColorField( { label, value, fallback, emptyLabel, onChange, onClose, children } ) {
	const shown = value || fallback;
	return (
		<div className="mediashield-color-field">
			<span className="mediashield-color-field__label">{ label }</span>
			<div className="mediashield-color-field__row">
				<Dropdown
					popoverProps={ { placement: 'bottom-start' } }
					onClose={ onClose }
					renderToggle={ ( { isOpen, onToggle } ) => (
						<Button
							variant="secondary"
							className="mediashield-color-field__toggle"
							onClick={ onToggle }
							aria-expanded={ isOpen }
							aria-label={ `${ label }: ${ value || emptyLabel || shown }` }
							__next40pxDefaultSize
						>
							<span className={ 'mediashield-color-field__swatch' + ( value || ! emptyLabel ? '' : ' is-empty' ) } style={ value || ! emptyLabel ? { background: shown } : undefined } />
							{ value || emptyLabel || shown }
						</Button>
					) }
					renderContent={ () => (
						<ColorPicker color={ shown } onChange={ onChange } enableAlpha={ false } />
					) }
				/>
				{ children }
			</div>
		</div>
	);
}
