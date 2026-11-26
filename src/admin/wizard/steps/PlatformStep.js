/**
 * Wizard Step 2: where the owner hosts videos.
 *
 * Every tile is a real checkbox (Basecamp 10545260470). The picks shape step
 * 3 (which links it asks for, and the upload option); they are not saved,
 * because no setting depends on them: any supported link works later.
 * Bunny Stream needs Pro, so in Free it is a Pro tile with one link.
 */
import { __ } from '@wordpress/i18n';
import Icon from '../../components/Icon';
import '../../components/choices.css';

const config = window.mediashieldAdmin || {};

/**
 * The hosts a wizard run can pick. Labels are the platform names used on
 * every screen.
 *
 * @return {Array<{key: string, label: string, desc: string}>} Hosts.
 */
export const wizardHosts = () => [
	{ key: 'youtube', label: __( 'YouTube', 'mediashield' ), desc: __( 'Paste a link. No account needed.', 'mediashield' ) },
	{ key: 'vimeo', label: __( 'Vimeo', 'mediashield' ), desc: __( 'Paste a link. No account needed.', 'mediashield' ) },
	{ key: 'wistia', label: __( 'Wistia', 'mediashield' ), desc: __( 'Paste a link. No account needed.', 'mediashield' ) },
	{ key: 'self', label: __( 'Upload to this site', 'mediashield' ), desc: __( 'Files stay private and play through a signed link.', 'mediashield' ) },
	...( config.isProActive
		? [ { key: 'bunny', label: __( 'Bunny Stream', 'mediashield' ), desc: __( 'Connect your library under MediaShield > Platforms.', 'mediashield' ) } ]
		: [] ),
];

export default function PlatformStep( { hosts = [], setHosts = () => {} } ) {
	const toggle = ( key ) => setHosts( hosts.includes( key ) ? hosts.filter( ( k ) => k !== key ) : [ ...hosts, key ] );

	return (
		<div className="ms-wizard__step">
			<div className="ms-wizard__step-header">
				<Icon name="networking" className="ms-wizard__step-icon" />
				<div>
					<h2>{ __( 'Where are your videos?', 'mediashield' ) }</h2>
					<p>{ __( 'Pick every place you host videos. The next step asks only for those.', 'mediashield' ) }</p>
				</div>
			</div>

			<fieldset className="ms-choices ms-choices--tiles">
				<legend className="screen-reader-text">{ __( 'Where you host videos', 'mediashield' ) }</legend>
				{ wizardHosts().map( ( h ) => (
					<label key={ h.key } className="ms-choice">
						<input type="checkbox" checked={ hosts.includes( h.key ) } onChange={ () => toggle( h.key ) } />
						<span className="ms-choice__text">
							<span className="ms-choice__label">{ h.label }</span>
							<span className="ms-choice__desc">{ h.desc }</span>
						</span>
					</label>
				) ) }
			</fieldset>

			{ ! config.isProActive && (
				<div className="ms-choice ms-choice--pro">
					<span className="ms-choice__text">
						<span className="ms-choice__label">
							{ __( 'Bunny Stream', 'mediashield' ) }
							<span className="ms-choice__badge">{ __( 'Pro', 'mediashield' ) }</span>
						</span>
						<span className="ms-choice__desc">{ __( 'Browse and import your Bunny library, with adaptive streaming.', 'mediashield' ) }</span>
					</span>
					<a className="ms-choice__link" href="https://wbcomdesigns.com/downloads/mediashield-pro/" target="_blank" rel="noopener noreferrer">
						{ __( 'See MediaShield Pro', 'mediashield' ) }
					</a>
				</div>
			) }

			<p className="ms-wizard__hint">
				{ __( 'Only videos you add to MediaShield are protected. Embeds pasted straight into a post are left alone.', 'mediashield' ) }
			</p>
		</div>
	);
}
