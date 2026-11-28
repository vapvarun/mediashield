/**
 * MediaShield Video Block — Editor Component (React).
 *
 * Two ways in:
 *   1. "Choose from library" — opens VideoPickerModal.
 *   2. Paste a link — the server says whether the link is already a video in
 *      the library (GET /videos/match); if so the block offers "Use this
 *      video" first, so a second copy is never made by accident
 *      (Basecamp 10545260523). A new link is added to the library.
 *
 * Once chosen, the block and its sidebar name the video (title, platform,
 * length) from GET /videos/{id}/summary. Protection is the video's own
 * setting: the sidebar shows it and links to the video, with no second
 * control here.
 *
 * Pro extension: <Slot name="mediashield-video-access-controls" /> in sidebar.
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	Placeholder,
	Button,
	TextControl,
	Notice,
	PanelBody,
	Spinner,
	Slot,
	ExternalLink,
} from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { decodeEntities } from '@wordpress/html-entities';
import VideoPickerModal from '../../admin/components/VideoPickerModal';
import { detectPlatform, extractVideoId } from '../../admin/utils/platform';
import { formatDuration } from '../../admin/utils/time';
import { Video } from 'lucide-react';

/**
 * "YouTube · 12:04 · Standard": what a video is, in one line.
 *
 * @param {Object}  summary        From /videos/{id}/summary or /videos/match.
 * @param {boolean} withProtection Add the protection level.
 * @return {string} The line.
 */
function describe( summary, withProtection ) {
	const parts = [ summary.platform_label ];
	if ( summary.duration > 0 ) {
		parts.push( formatDuration( summary.duration ) );
	}
	if ( withProtection ) {
		parts.push( summary.protection_label );
	}
	return parts.filter( Boolean ).join( ' · ' );
}

/**
 * A failed request, in the calm words every MediaShield screen uses.
 *
 * @param {Object} err apiFetch rejection.
 * @return {string} Message.
 */
function failure( err ) {
	return window.mediashieldRequest ? window.mediashieldRequest.message( err ) : __( 'Something went wrong. Please try again.', 'mediashield' );
}

export default function Edit( { attributes, setAttributes } ) {
	const { videoId } = attributes;
	const [ isModalOpen, setModalOpen ] = useState( false );
	const [ busy, setBusy ] = useState( false );
	const [ summary, setSummary ] = useState( null );
	const [ loadFailed, setLoadFailed ] = useState( false );
	// Thumbnails that failed to load (a 403 from an image rule, a 404): the
	// placeholder takes their place, so the preview keeps its 16:9 area
	// instead of collapsing to the caption (Basecamp 10552183284).
	const [ brokenThumbs, setBrokenThumbs ] = useState( [] );
	const showsThumb = ( url ) => !! url && ! brokenThumbs.includes( url );
	const thumbFailed = ( url ) => () => setBrokenThumbs( ( list ) => ( list.includes( url ) ? list : [ ...list, url ] ) );
	const [ pasteUrl, setPasteUrl ] = useState( '' );
	const [ found, setFound ] = useState( null );
	const [ error, setError ] = useState( '' );
	const blockProps = useBlockProps();
	// The post being edited, left out of "used on N other pages". By name, so
	// the block also works where the post editor store is not loaded (widgets).
	const postId = useSelect( ( select ) => select( 'core/editor' )?.getCurrentPostId?.() || 0, [] );

	// What the chosen video is, and where else it is shown.
	useEffect( () => {
		if ( ! ( videoId > 0 ) ) {
			setSummary( null );
			return;
		}
		setLoadFailed( false );
		apiFetch( { path: `/mediashield/v1/videos/${ videoId }/summary?post_id=${ postId }` } )
			.then( setSummary )
			.catch( () => {
				setSummary( null );
				setLoadFailed( true );
			} );
	}, [ videoId, postId ] );

	const choose = ( id, url ) => {
		setAttributes( { videoId: id, url: url || '' } );
		setFound( null );
		setPasteUrl( '' );
		setError( '' );
	};

	/**
	 * Add the link to the library as a new video and place it.
	 *
	 * @param {string} link Pasted link.
	 */
	const addNew = async ( link ) => {
		const platform = detectPlatform( link );
		const platformVideoId = extractVideoId( link, platform );
		const created = await apiFetch( {
			path: '/wp/v2/mediashield-videos',
			method: 'POST',
			data: {
				// Never the platform ID: the server gives the video the platform's
				// title or "Video 123", and a slug to match.
				title: platformVideoId ? '' : link.substring( 0, 60 ),
				status: 'publish',
				meta: {
					_ms_platform: platform,
					_ms_platform_video_id: platformVideoId,
					// No protection level: the video follows the site default.
					_ms_source_url: link,
				},
			},
		} );
		choose( created.id, link );
	};

	/**
	 * A pasted link: use the library video it already is, or add it.
	 */
	const handlePaste = async () => {
		const link = pasteUrl.trim();
		if ( ! link ) return;
		setBusy( true );
		setError( '' );
		setFound( null );
		try {
			const res = await apiFetch( { path: `/mediashield/v1/videos/match?url=${ encodeURIComponent( link ) }` } );
			if ( res.match ) {
				setFound( res.match );
			} else {
				await addNew( link );
			}
		} catch ( err ) {
			setError( failure( err ) );
		}
		setBusy( false );
	};

	const addAnyway = async () => {
		setBusy( true );
		setError( '' );
		try {
			await addNew( pasteUrl.trim() );
		} catch ( err ) {
			setError( failure( err ) );
		}
		setBusy( false );
	};

	const handleSelect = ( selectedVideo ) => {
		choose( selectedVideo.id, selectedVideo.meta?._ms_source_url || '' );
		setModalOpen( false );
	};

	const picker = isModalOpen && (
		<VideoPickerModal
			onSelect={ handleSelect }
			onClose={ () => setModalOpen( false ) }
		/>
	);

	// -- No video yet: paste or pick. --
	if ( ! ( videoId > 0 ) ) {
		return (
			<div { ...blockProps }>
				<Placeholder
					icon="video-alt3"
					label={ __( 'MediaShield Video', 'mediashield' ) }
					instructions={ __( 'Paste a video link, or choose a video from your library.', 'mediashield' ) }
				>
					<div className="ms-block-paste">
						<div className="ms-block-paste__row">
							<TextControl
								label={ __( 'Video link', 'mediashield' ) }
								value={ pasteUrl }
								onChange={ ( value ) => {
									setPasteUrl( value );
									setFound( null );
									setError( '' );
								} }
								placeholder="https://www.youtube.com/watch?v=…"
								__nextHasNoMarginBottom
								__next40pxDefaultSize
							/>
							<Button
								variant="primary"
								onClick={ handlePaste }
								disabled={ busy || ! pasteUrl.trim() }
								isBusy={ busy }
								__next40pxDefaultSize
							>
								{ __( 'Add', 'mediashield' ) }
							</Button>
						</div>

						{ error && (
							<Notice status="error" isDismissible={ false }>{ error }</Notice>
						) }

						{ found && (
							<div className="ms-block-found" role="status">
								{ showsThumb( found.thumbnail ) ? (
									<img className="ms-block-found__thumb" src={ found.thumbnail } alt="" onError={ thumbFailed( found.thumbnail ) } />
								) : (
									<span className="ms-block-found__thumb" aria-hidden="true" />
								) }
								<div className="ms-block-found__text">
									<strong>{ __( 'This link is already in your library', 'mediashield' ) }</strong>
									<span>
										{ sprintf(
											/* translators: 1: video title, 2: platform, length and protection, e.g. "YouTube · 12:04 · Standard". */
											__( '"%1$s" · %2$s', 'mediashield' ),
											found.title,
											describe( found, true )
										) }
									</span>
								</div>
								<div className="ms-block-found__actions">
									<Button variant="primary" onClick={ () => choose( found.id, pasteUrl.trim() ) } __next40pxDefaultSize>
										{ __( 'Use this video', 'mediashield' ) }
									</Button>
									<Button variant="secondary" onClick={ addAnyway } disabled={ busy } __next40pxDefaultSize>
										{ __( 'Add as a new video', 'mediashield' ) }
									</Button>
								</div>
							</div>
						) }

						<Button variant="secondary" onClick={ () => setModalOpen( true ) } __next40pxDefaultSize>
							{ __( 'Choose from library', 'mediashield' ) }
						</Button>
					</div>
				</Placeholder>
				{ picker }
			</div>
		);
	}

	// -- A video is chosen. --
	const others = summary ? summary.used_in : [];
	let usedLabel = __( 'Not used on any other page yet.', 'mediashield' );
	if ( others.length ) {
		usedLabel = summary.used_in_more
			? sprintf(
				/* translators: %d: number of pages. */
				_n( 'Used on %d or more other page:', 'Used on %d or more other pages:', others.length, 'mediashield' ),
				others.length
			)
			: sprintf(
				/* translators: %d: number of pages. */
				_n( 'Used on %d other page:', 'Used on %d other pages:', others.length, 'mediashield' ),
				others.length
			);
	}

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Video', 'mediashield' ) }>
					{ ! summary && ! loadFailed && <Spinner /> }
					{ loadFailed && (
						<Notice status="warning" isDismissible={ false }>
							{ __( 'This video could not be found. It may have been deleted. Pick a different video.', 'mediashield' ) }
						</Notice>
					) }
					{ summary && (
						<div className="ms-block-sidebar">
							<p>
								<strong>{ summary.title }</strong>
								<br />
								<span className="ms-block-sidebar__muted">{ describe( summary, false ) }</span>
							</p>
							<p>
								{ sprintf(
									/* translators: %s: protection level, e.g. Standard. */
									__( 'Protection: %s', 'mediashield' ),
									summary.protection_label
								) }
								{ summary.edit_url && (
									<>
										<br />
										<a href={ summary.edit_url } target="_blank" rel="noreferrer">
											{ __( 'Change on the video', 'mediashield' ) }
										</a>
									</>
								) }
							</p>
							<p>{ usedLabel }</p>
							{ others.length > 0 && (
								<ul className="ms-block-sidebar__places">
									{ others.map( ( place ) => (
										<li key={ place.id }>
											<ExternalLink href={ place.url }>{ decodeEntities( place.title ) }</ExternalLink>
											<span className="ms-block-sidebar__muted"> · { place.type }</span>
										</li>
									) ) }
								</ul>
							) }
						</div>
					) }

					{ /* Pro extension point for access controls */ }
					<Slot name="mediashield-video-access-controls" />

					<div className="ms-block-sidebar__actions">
						<Button variant="secondary" onClick={ () => setModalOpen( true ) } __next40pxDefaultSize>
							{ __( 'Pick a different video', 'mediashield' ) }
						</Button>
						<Button variant="link" isDestructive onClick={ () => choose( 0, '' ) }>
							{ __( 'Remove video', 'mediashield' ) }
						</Button>
					</div>
				</PanelBody>
			</InspectorControls>

			<div className="ms-block-preview">
				{ summary && showsThumb( summary.thumbnail ) ? (
					<img className="ms-block-preview__poster" src={ summary.thumbnail } alt="" onError={ thumbFailed( summary.thumbnail ) } />
				) : (
					<div className="ms-block-placeholder-preview">
						<Video size={ 48 } strokeWidth={ 1.75 } className="ms-icon" aria-hidden="true" />
					</div>
				) }
				<div className="ms-block-preview__caption">
					{ summary ? (
						<>
							<strong>{ summary.title }</strong>
							<span>{ describe( summary, false ) }</span>
						</>
					) : (
						<span>{ loadFailed ? __( 'Video not found', 'mediashield' ) : __( 'Loading video…', 'mediashield' ) }</span>
					) }
				</div>
			</div>
			{ picker }
		</div>
	);
}
