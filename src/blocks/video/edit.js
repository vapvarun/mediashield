/**
 * MediaShield Video Block — Editor Component (React).
 *
 * Two insertion modes:
 *   1. "Choose from library" — opens VideoPickerModal
 *   2. "Paste URL" — auto-detects platform, creates CPT via REST
 *
 * Pro extension: <Slot name="mediashield-video-access-controls" /> in sidebar.
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	Placeholder,
	Button,
	TextControl,
	SelectControl,
	Spinner,
	Slot,
} from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import VideoPickerModal from '../../admin/components/VideoPickerModal';
import { detectPlatform, extractVideoId } from '../../admin/utils/platform';

export default function Edit( { attributes, setAttributes } ) {
	const { videoId, url } = attributes;
	const [ isModalOpen, setModalOpen ] = useState( false );
	const [ isCreating, setCreating ] = useState( false );
	const [ video, setVideo ] = useState( null );
	const [ pasteUrl, setPasteUrl ] = useState( '' );
	const blockProps = useBlockProps();

	// Fetch video data when videoId changes.
	useEffect( () => {
		if ( videoId > 0 ) {
			apiFetch( { path: `/wp/v2/mediashield-videos/${ videoId }` } )
				.then( ( data ) => setVideo( data ) )
				.catch( () => setVideo( null ) );
		}
	}, [ videoId ] );

	/**
	 * Handle URL paste — auto-create video CPT.
	 */
	const handleUrlPaste = async () => {
		if ( ! pasteUrl.trim() ) return;

		setCreating( true );
		const platform = detectPlatform( pasteUrl );
		const platformVideoId = extractVideoId( pasteUrl, platform );

		try {
			const newVideo = await apiFetch( {
				path: '/wp/v2/mediashield-videos',
				method: 'POST',
				data: {
					title: platformVideoId
						? `${ platform } - ${ platformVideoId }`
						: pasteUrl.substring( 0, 60 ),
					status: 'publish',
					meta: {
						_ms_platform: platform,
						_ms_platform_video_id: platformVideoId,
						_ms_source_url: pasteUrl,
						_ms_protection_level: 'standard',
					},
				},
			} );

			setAttributes( { videoId: newVideo.id, url: pasteUrl } );
			setVideo( newVideo );
		} catch ( err ) {
			// eslint-disable-next-line no-console
			console.error( 'MediaShield: Failed to create video', err );
		}

		setCreating( false );
	};

	/**
	 * Handle video selection from picker modal.
	 */
	const handleSelect = ( selectedVideo ) => {
		setAttributes( {
			videoId: selectedVideo.id,
			url: selectedVideo.meta?._ms_source_url || '',
		} );
		setVideo( selectedVideo );
		setModalOpen( false );
	};

	// -- Render --

	// No video selected — show placeholder.
	if ( ! videoId || ! video ) {
		return (
			<div { ...blockProps }>
				<Placeholder
					icon="video-alt3"
					label={ __( 'MediaShield Video', 'mediashield' ) }
					instructions={ __(
						'Choose a video from your library or paste a URL.',
						'mediashield'
					) }
				>
					<div style={ { display: 'flex', flexDirection: 'column', gap: '12px', width: '100%' } }>
						<Button variant="primary" onClick={ () => setModalOpen( true ) }>
							{ __( 'Choose from Library', 'mediashield' ) }
						</Button>

						<div style={ { display: 'flex', gap: '8px', alignItems: 'flex-end' } }>
							<TextControl
								label={ __( 'Or paste a video URL', 'mediashield' ) }
								value={ pasteUrl }
								onChange={ setPasteUrl }
								placeholder="https://youtube.com/watch?v=..."
								style={ { flex: 1 } }
							/>
							<Button
								variant="secondary"
								onClick={ handleUrlPaste }
								disabled={ isCreating || ! pasteUrl.trim() }
							>
								{ isCreating ? <Spinner /> : __( 'Embed', 'mediashield' ) }
							</Button>
						</div>
					</div>
				</Placeholder>

				{ isModalOpen && (
					<VideoPickerModal
						onSelect={ handleSelect }
						onClose={ () => setModalOpen( false ) }
					/>
				) }
			</div>
		);
	}

	// Video selected — show preview.
	const platform = video.meta?._ms_platform || 'unknown';
	const sourceUrl = video.meta?._ms_source_url || url;
	const platformVideoId = video.meta?._ms_platform_video_id || '';

	// Resolve a preview thumbnail:
	// 1. WP featured image when the editor has set one.
	// 2. Otherwise derive a free CDN URL for known platforms so the block has a
	//    real preview without an extra API call.
	const derivedThumbnail = ( () => {
		if ( platform === 'youtube' && platformVideoId ) {
			return `https://i.ytimg.com/vi/${ platformVideoId }/hqdefault.jpg`;
		}
		if ( platform === 'bunny' && platformVideoId ) {
			// Bunny thumbnail convention — first segment of the platformVideoId
			// is the library, second is the video GUID; bail if it doesn't match.
			const parts = platformVideoId.split( '/' );
			if ( parts.length === 2 ) {
				return `https://vz-${ parts[ 0 ] }.b-cdn.net/${ parts[ 1 ] }/thumbnail.jpg`;
			}
		}
		return '';
	} )();

	const thumbnailUrl = video.featured_media_src_url
		|| video._embedded?.[ 'wp:featuredmedia' ]?.[ 0 ]?.source_url
		|| derivedThumbnail;

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<div style={ { padding: '16px' } }>
					<h3>{ __( 'Video Settings', 'mediashield' ) }</h3>
					<p>
						<strong>{ __( 'Platform:', 'mediashield' ) }</strong>{ ' ' }
						{ platform }
					</p>
					<p>
						<strong>{ __( 'ID:', 'mediashield' ) }</strong>{ ' ' }
						{ videoId }
					</p>

					<SelectControl
						label={ __( 'Protection Level', 'mediashield' ) }
						value={ video.meta?._ms_protection_level || 'standard' }
						options={ [
							{ label: __( 'Standard (Watermark + Tracking)', 'mediashield' ), value: 'standard' },
							{ label: __( 'None (Free Preview)', 'mediashield' ), value: 'none' },
						] }
						onChange={ ( value ) => {
							apiFetch( {
								path: `/wp/v2/mediashield-videos/${ videoId }`,
								method: 'POST',
								data: { meta: { _ms_protection_level: value } },
							} );
						} }
					/>

					{ /* Pro extension point for access controls */ }
					<Slot name="mediashield-video-access-controls" />

					<Button
						variant="link"
						isDestructive
						onClick={ () => {
							setAttributes( { videoId: 0, url: '' } );
							setVideo( null );
						} }
						style={ { marginTop: '12px' } }
					>
						{ __( 'Remove Video', 'mediashield' ) }
					</Button>
				</div>
			</InspectorControls>

			<div className="ms-block-preview">
				{ thumbnailUrl ? (
					<div className="ms-block-thumbnail" style={ { position: 'relative' } }>
						<img
							src={ thumbnailUrl }
							alt={ video.title?.rendered || '' }
							style={ { width: '100%', display: 'block' } }
						/>
						<span className="ms-block-platform-badge">{ platform }</span>
					</div>
				) : (
					<div className="ms-block-embed-preview">
						{ platform === 'self' && sourceUrl ? (
							<video
								src={ sourceUrl }
								preload="metadata"
								controls
								style={ { width: '100%', display: 'block' } }
							/>
						) : platform === 'vimeo' && platformVideoId ? (
							<iframe
								src={ `https://player.vimeo.com/video/${ platformVideoId }` }
								title={ video.title?.rendered || 'Video' }
								style={ { width: '100%', aspectRatio: '16/9', border: 'none' } }
								allow="autoplay; fullscreen; picture-in-picture"
							/>
						) : sourceUrl && /^https?:\/\//.test( sourceUrl ) ? (
							<iframe
								src={ sourceUrl }
								title={ video.title?.rendered || 'Video' }
								style={ { width: '100%', aspectRatio: '16/9', border: 'none' } }
							/>
						) : (
							<div className="ms-block-placeholder-preview">
								<span className="dashicons dashicons-video-alt3" />
								<span>{ video.title?.rendered || __( 'Video', 'mediashield' ) }</span>
							</div>
						) }
					</div>
				) }
			</div>
		</div>
	);
}
