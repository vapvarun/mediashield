/**
 * Wizard Step 3: Protect Your First Video.
 *
 * Paste a URL to auto-detect platform and create a video CPT post.
 */
import { __ } from '@wordpress/i18n';
import { TextControl, Button, Spinner } from '@wordpress/components';
import { useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

export default function FirstVideoStep( { initialData } ) { // eslint-disable-line no-unused-vars
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
		<div className="ms-wizard__step">
			<div className="ms-wizard__step-header">
				<span className="ms-wizard__step-icon dashicons dashicons-video-alt3" />
				<div>
					<h2>{ __( 'Protect Your First Video', 'mediashield' ) }</h2>
					<p>{ __( 'Paste a video URL to see protection in action. You can skip this and add videos later.', 'mediashield' ) }</p>
				</div>
			</div>

			<div className="ms-wizard__step-fields">
				{ ! created ? (
					<div className="ms-wizard__url-input">
						<TextControl
							label={ __( 'Video URL', 'mediashield' ) }
							value={ url }
							onChange={ setUrl }
							placeholder="https://youtube.com/watch?v=..."
						/>
						<Button
							variant="primary"
							onClick={ handleCreate }
							disabled={ creating || ! url.trim() }
							className="ms-wizard__btn--protect"
						>
							{ creating ? <Spinner /> : (
								<>
									<span className="dashicons dashicons-shield" />
									{ __( 'Protect', 'mediashield' ) }
								</>
							) }
						</Button>
					</div>
				) : (
					<div className="ms-wizard__success-banner">
						<span className="dashicons dashicons-yes-alt" />
						<div>
							<strong>{ __( 'Video protected successfully!', 'mediashield' ) }</strong>
							<span>
								{ __( 'Platform:', 'mediashield' ) } { created.platform } &middot;
								{ __( 'ID:', 'mediashield' ) } #{ created.id }
							</span>
						</div>
					</div>
				) }
			</div>

			<p className="ms-wizard__hint">
				{ __( 'Supports YouTube, Vimeo, Bunny Stream, Wistia, and direct MP4/WebM URLs.', 'mediashield' ) }
			</p>
		</div>
	);
}
