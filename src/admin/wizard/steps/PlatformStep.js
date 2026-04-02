/**
 * Wizard Step 2: Platform Connection (optional).
 *
 * Free version shows a teaser for platform connections.
 * Pro users can enter API keys here.
 */
import { __ } from '@wordpress/i18n';

const config = window.mediashieldAdmin || {};

export default function PlatformStep( { initialData } ) { // eslint-disable-line no-unused-vars
	const platforms = [
		{ name: 'YouTube', icon: 'dashicons-youtube', desc: __( 'Protect YouTube embeds', 'mediashield' ) },
		{ name: 'Vimeo', icon: 'dashicons-video-alt', desc: __( 'Protect Vimeo embeds', 'mediashield' ) },
		{ name: 'Bunny Stream', icon: 'dashicons-cloud', desc: __( 'Direct upload & DRM', 'mediashield' ) },
		{ name: 'Wistia', icon: 'dashicons-format-video', desc: __( 'Wistia integration', 'mediashield' ) },
	];

	return (
		<div className="ms-wizard__step">
			<div className="ms-wizard__step-header">
				<span className="ms-wizard__step-icon dashicons dashicons-networking" />
				<div>
					<h2>{ __( 'Connect a Platform', 'mediashield' ) }</h2>
					<p>{ __( 'MediaShield works with all major video platforms. You can always set this up later.', 'mediashield' ) }</p>
				</div>
			</div>

			<div className="ms-wizard__platform-grid">
				{ platforms.map( ( p ) => (
					<div key={ p.name } className="ms-wizard__platform-card">
						<span className={ `ms-wizard__platform-icon dashicons ${ p.icon }` } />
						<strong>{ p.name }</strong>
						<span className="ms-wizard__platform-desc">{ p.desc }</span>
					</div>
				) ) }
			</div>

			{ ! config.isProActive && (
				<div className="ms-wizard__pro-notice">
					<span className="dashicons dashicons-lock" />
					<span>{ __( 'Direct platform uploads (Bunny, Vimeo, YouTube, Wistia) are available with MediaShield Pro.', 'mediashield' ) }</span>
				</div>
			) }

			<p className="ms-wizard__hint">
				{ __( 'You can skip this step. Videos from all platforms are automatically protected when embedded on your site.', 'mediashield' ) }
			</p>
		</div>
	);
}
