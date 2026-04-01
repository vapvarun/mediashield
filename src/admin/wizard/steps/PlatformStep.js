/**
 * Wizard Step 3: Platform Connection (optional).
 *
 * Free version shows a teaser for platform connections.
 * Pro users can enter API keys here.
 */
import { __ } from '@wordpress/i18n';

const config = window.mediashieldAdmin || {};

export default function PlatformStep( { initialData } ) { // eslint-disable-line no-unused-vars
	const platforms = [
		{ name: 'YouTube', icon: 'dashicons-youtube' },
		{ name: 'Vimeo', icon: 'dashicons-video-alt' },
		{ name: 'Bunny Stream', icon: 'dashicons-cloud' },
		{ name: 'Wistia', icon: 'dashicons-format-video' },
	];

	return (
		<div className="mediashield-wizard__step">
			<h2>{ __( 'Connect a Platform', 'mediashield' ) }</h2>
			<p>{ __( 'MediaShield works with all major video platforms. You can always set this up later in Settings.', 'mediashield' ) }</p>

			<div style={ { display: 'grid', gridTemplateColumns: '1fr 1fr', gap: '12px', marginTop: '16px' } }>
				{ platforms.map( ( p ) => (
					<div key={ p.name } style={ {
						border: '1px solid #ddd',
						borderRadius: '8px',
						padding: '16px',
						textAlign: 'center',
						background: '#f9f9f9',
					} }>
						<span className={ `dashicons ${ p.icon }` } style={ { fontSize: '32px', width: '32px', height: '32px', color: '#2271b1' } } />
						<p style={ { margin: '8px 0 0', fontWeight: 600 } }>{ p.name }</p>
					</div>
				) ) }
			</div>

			{ ! config.isProActive && (
				<p style={ { marginTop: '16px', color: '#666', fontSize: '13px' } }>
					{ __( 'Direct platform uploads (Bunny, Vimeo, YouTube, Wistia) are available with MediaShield Pro.', 'mediashield' ) }
				</p>
			) }

			<p style={ { marginTop: '8px', color: '#999', fontSize: '12px' } }>
				{ __( 'You can skip this step. Videos from all platforms are automatically protected when embedded on your site.', 'mediashield' ) }
			</p>
		</div>
	);
}
