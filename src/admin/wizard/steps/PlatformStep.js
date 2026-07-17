/**
 * Wizard Step 2: Platform Connection (optional).
 *
 * Free version shows a teaser for platform connections.
 * Pro users can enter API keys here.
 */
import { __ } from '@wordpress/i18n';
import Icon from '../../components/Icon';

const config = window.mediashieldAdmin || {};

export default function PlatformStep( { initialData } ) { // eslint-disable-line no-unused-vars
	const platforms = [
		{ name: 'YouTube', icon: 'youtube', desc: __( 'Protect YouTube embeds', 'mediashield' ) },
		{ name: 'Vimeo', icon: 'video-alt', desc: __( 'Protect Vimeo embeds', 'mediashield' ) },
		{ name: 'Bunny Stream', icon: 'cloud', desc: __( 'Direct upload & DRM', 'mediashield' ) },
		{ name: 'Wistia', icon: 'format-video', desc: __( 'Wistia integration', 'mediashield' ) },
	];

	return (
		<div className="ms-wizard__step">
			<div className="ms-wizard__step-header">
				<Icon name="networking" className="ms-wizard__step-icon" />
				<div>
					<h2>{ __( 'Connect a Platform', 'mediashield' ) }</h2>
					<p>{ __( 'MediaShield works with all major video platforms. You can always set this up later.', 'mediashield' ) }</p>
				</div>
			</div>

			<div className="ms-wizard__platform-grid">
				{ platforms.map( ( p ) => (
					<div key={ p.name } className="ms-wizard__platform-card">
						<Icon name={ p.icon } className="ms-wizard__platform-icon" />
						<strong>{ p.name }</strong>
						<span className="ms-wizard__platform-desc">{ p.desc }</span>
					</div>
				) ) }
			</div>

			{ ! config.isProActive && (
				<div className="ms-wizard__pro-notice">
					<Icon name="lock" />
					<span>{ __( 'Direct platform uploads (Bunny, Vimeo, YouTube, Wistia) are available with MediaShield Pro.', 'mediashield' ) }</span>
				</div>
			) }

			<p className="ms-wizard__hint">
				{ __( 'You can skip this step. Only videos you add to MediaShield are protected — embeds pasted straight into a post are left alone.', 'mediashield' ) }
			</p>
		</div>
	);
}
