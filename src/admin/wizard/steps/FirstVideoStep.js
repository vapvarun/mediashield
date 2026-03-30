/**
 * Wizard Step 4: Protect Your First Video.
 *
 * Paste a URL to auto-detect platform and create a video CPT post.
 */
import { __ } from '@wordpress/i18n';
import { TextControl, Button, Spinner } from '@wordpress/components';
import { useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

export default function FirstVideoStep() {
	const [ url, setUrl ] = useState( '' );
	const [ creating, setCreating ] = useState( false );
	const [ created, setCreated ] = useState( null );

	const detectPlatform = ( videoUrl ) => {
		if ( /youtube\.com|youtu\.be/.test( videoUrl ) ) return 'youtube';
		if ( /vimeo\.com/.test( videoUrl ) ) return 'vimeo';
		if ( /iframe\.mediadelivery\.net|bunny/.test( videoUrl ) ) return 'bunny';
		if ( /wistia/.test( videoUrl ) ) return 'wistia';
		if ( /\.(mp4|webm|mov)(\?|$)/i.test( videoUrl ) ) return 'self';
		return 'iframe';
	};

	const handleCreate = async () => {
		if ( ! url.trim() ) return;

		setCreating( true );
		const platform = detectPlatform( url );

		try {
			const video = await apiFetch( {
				path: '/wp/v2/mediashield-videos',
				method: 'POST',
				data: {
					title: url.substring( 0, 60 ),
					status: 'publish',
					meta: {
						_ms_platform: platform,
						_ms_source_url: url,
						_ms_protection_level: 'standard',
					},
				},
			} );

			setCreated( { id: video.id, platform } );
		} catch ( err ) {
			// eslint-disable-next-line no-console
			console.error( 'Failed to create video', err );
		}

		setCreating( false );
	};

	return (
		<div className="mediashield-wizard__step">
			<h2>{ __( 'Protect Your First Video', 'mediashield' ) }</h2>
			<p>{ __( 'Paste a video URL to see protection in action. You can skip this and add videos later.', 'mediashield' ) }</p>

			{ ! created ? (
				<div style={ { display: 'flex', gap: '8px', alignItems: 'flex-end' } }>
					<TextControl
						label={ __( 'Video URL', 'mediashield' ) }
						value={ url }
						onChange={ setUrl }
						placeholder="https://youtube.com/watch?v=..."
						style={ { flex: 1 } }
					/>
					<Button
						variant="primary"
						onClick={ handleCreate }
						disabled={ creating || ! url.trim() }
					>
						{ creating ? <Spinner /> : __( 'Protect', 'mediashield' ) }
					</Button>
				</div>
			) : (
				<div style={ {
					background: '#f0f9f0',
					border: '1px solid #46b450',
					borderRadius: '8px',
					padding: '16px',
					marginTop: '16px',
				} }>
					<p style={ { margin: 0, fontWeight: 600, color: '#2e7d32' } }>
						{ __( 'Video protected!', 'mediashield' ) }
					</p>
					<p style={ { margin: '4px 0 0', fontSize: '13px', color: '#555' } }>
						{ __( 'Platform:', 'mediashield' ) } { created.platform } &middot;
						{ __( 'ID:', 'mediashield' ) } #{ created.id }
					</p>
				</div>
			) }
		</div>
	);
}
