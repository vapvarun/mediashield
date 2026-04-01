/**
 * MediaShield Admin -- Videos Page
 *
 * Premium list table with platform indicators, protection badges,
 * and action buttons.
 *
 * @package MediaShield
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import { Button, Modal, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { decodeEntities } from '@wordpress/html-entities';

const config = window.mediashieldAdmin || {};
const PER_PAGE = 20;

const PLATFORM_LABELS = {
	youtube: 'YouTube',
	vimeo: 'Vimeo',
	bunny: 'Bunny Stream',
	wistia: 'Wistia',
	self: 'Self-hosted',
	iframe: 'Custom',
};

/**
 * Build an embeddable preview URL from platform + video ID.
 *
 * @param {string} platform    Platform slug.
 * @param {string} videoId     Platform video ID.
 * @param {string} sourceUrl   Source/embed URL.
 * @return {string|null} Embed URL or null.
 */
const getPreviewUrl = ( platform, videoId, sourceUrl ) => {
	if ( platform === 'youtube' && videoId ) {
		return `https://www.youtube.com/embed/${ videoId }?autoplay=1`;
	}
	if ( platform === 'vimeo' && videoId ) {
		return `https://player.vimeo.com/video/${ videoId }?autoplay=1`;
	}
	if ( platform === 'wistia' && videoId ) {
		return `https://fast.wistia.net/embed/iframe/${ videoId }?autoplay=true`;
	}
	if ( platform === 'bunny' && sourceUrl ) {
		// For Bunny, use the iframe embed URL format.
		if ( sourceUrl.includes( 'iframe.mediadelivery.net' ) ) {
			return sourceUrl;
		}
		// HLS URL — wrap in a basic player isn't possible via iframe, use source URL.
		return sourceUrl;
	}
	if ( sourceUrl ) {
		return sourceUrl;
	}
	return null;
};

/**
 * Preview lightbox modal component.
 */
const PreviewModal = ( { video, onClose } ) => {
	const platform = video.meta?._ms_platform || 'self';
	const videoId = video.meta?._ms_platform_video_id || '';
	const sourceUrl = video.meta?._ms_source_url || '';
	const previewUrl = getPreviewUrl( platform, videoId, sourceUrl );
	const isIframeable = [ 'youtube', 'vimeo', 'wistia' ].includes( platform ) ||
		( platform === 'bunny' && previewUrl?.includes( 'iframe.mediadelivery.net' ) );

	return (
		<Modal
			title={ decodeEntities( video.title?.rendered || __( 'Preview', 'mediashield' ) ) }
			onRequestClose={ onClose }
			className="ms-preview-modal"
			isFullScreen={ false }
		>
			<div className="ms-preview-modal__body">
				{ ! previewUrl && (
					<p>{ __( 'No preview available for this video.', 'mediashield' ) }</p>
				) }
				{ previewUrl && isIframeable && (
					<div className="ms-preview-modal__player">
						<iframe
							src={ previewUrl }
							title={ decodeEntities( video.title?.rendered || '' ) }
							frameBorder="0"
							allow="autoplay; fullscreen; picture-in-picture; encrypted-media"
							allowFullScreen
							style={ { width: '100%', height: '100%', position: 'absolute', top: 0, left: 0 } }
						/>
					</div>
				) }
				{ previewUrl && ! isIframeable && (
					<div className="ms-preview-modal__player">
						<video
							src={ previewUrl }
							controls
							autoPlay
							style={ { width: '100%', height: '100%', position: 'absolute', top: 0, left: 0, objectFit: 'contain', background: '#000' } }
						/>
					</div>
				) }
				<div className="ms-preview-modal__info">
					<span><strong>{ __( 'Platform:', 'mediashield' ) }</strong> { PLATFORM_LABELS[ platform ] || platform }</span>
					{ video.meta?._ms_duration > 0 && (
						<span><strong>{ __( 'Duration:', 'mediashield' ) }</strong> { Math.floor( video.meta._ms_duration / 60 ) }:{ String( video.meta._ms_duration % 60 ).padStart( 2, '0' ) }</span>
					) }
				</div>
			</div>
		</Modal>
	);
};

const ProtectionBadge = ( { level } ) => {
	const map = {
		none: __( 'None', 'mediashield' ),
		basic: __( 'Basic', 'mediashield' ),
		standard: __( 'Standard', 'mediashield' ),
		strict: __( 'Strict', 'mediashield' ),
	};

	return (
		<span className={ `mediashield-badge mediashield-badge--${ level || 'none' }` }>
			{ map[ level ] || level || map.none }
		</span>
	);
};

const PlatformLabel = ( { platform } ) => {
	const p = platform || 'self';
	return (
		<span className={ `mediashield-platform mediashield-platform--${ p }` }>
			<span className="mediashield-platform__dot" />
			{ PLATFORM_LABELS[ p ] || p }
		</span>
	);
};

const Videos = () => {
	const [ videos, setVideos ] = useState( [] );
	const [ total, setTotal ] = useState( 0 );
	const [ page, setPage ] = useState( 1 );
	const [ totalPages, setTotalPages ] = useState( 1 );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( '' );
	const [ previewVideo, setPreviewVideo ] = useState( null );

	useEffect( () => {
		let cancelled = false;
		setLoading( true );
		setError( '' );

		apiFetch( {
			path: `/wp/v2/mediashield-videos?per_page=${ PER_PAGE }&page=${ page }&_locale=user`,
			parse: false,
		} )
			.then( async ( res ) => {
				const json = await res.json();
				if ( ! cancelled ) {
					setVideos( json );
					const tp = parseInt( res.headers.get( 'X-WP-TotalPages' ), 10 ) || 1;
					const tt = parseInt( res.headers.get( 'X-WP-Total' ), 10 ) || 0;
					setTotalPages( tp );
					setTotal( tt );
				}
			} )
			.catch( ( err ) => {
				if ( ! cancelled ) {
					setError( err.message || __( 'Failed to load videos.', 'mediashield' ) );
				}
			} )
			.finally( () => {
				if ( ! cancelled ) {
					setLoading( false );
				}
			} );

		return () => {
			cancelled = true;
		};
	}, [ page ] );

	return (
		<div className="mediashield-page mediashield-videos">
			<header className="mediashield-page__header">
				<h1>
					{ __( 'Videos', 'mediashield' ) }
					{ ! loading && (
						<span className="mediashield-page__header-subtitle">
							{ total } { total === 1 ? __( 'video', 'mediashield' ) : __( 'videos', 'mediashield' ) }
						</span>
					) }
				</h1>
				<a
					href={ `${ config.adminUrl }post-new.php?post_type=mediashield_video` }
					className="components-button is-primary"
				>
					{ __( 'Add New Video', 'mediashield' ) }
				</a>
			</header>

			{ loading && (
				<div className="mediashield-loader">
					<Spinner />
					<span className="mediashield-loader__text">
						{ __( 'Loading videos...', 'mediashield' ) }
					</span>
				</div>
			) }

			{ error && (
				<div className="mediashield-notice mediashield-notice--error">
					{ error }
				</div>
			) }

			{ ! loading && ! error && (
				<div className="mediashield-table-card">
					<table className="mediashield-table">
						<thead>
							<tr>
								<th>{ __( 'Title', 'mediashield' ) }</th>
								<th>{ __( 'Platform', 'mediashield' ) }</th>
								<th>{ __( 'Protection', 'mediashield' ) }</th>
								<th>{ __( 'Date', 'mediashield' ) }</th>
								<th>{ __( 'Actions', 'mediashield' ) }</th>
							</tr>
						</thead>
						<tbody>
							{ videos.length === 0 && (
								<tr>
									<td colSpan="5" className="mediashield-table__empty">
										<span className="mediashield-table__empty-icon dashicons dashicons-format-video" />
										{ __( 'No videos yet. Create your first protected video.', 'mediashield' ) }
									</td>
								</tr>
							) }
							{ videos.map( ( video ) => (
								<tr key={ video.id }>
									<td>
										<strong>
											{ decodeEntities( video.title?.rendered || '' ) }
										</strong>
									</td>
									<td>
										<PlatformLabel platform={ video.meta?._ms_platform } />
									</td>
									<td>
										<ProtectionBadge level={ video.meta?._ms_protection_level } />
									</td>
									<td style={ { color: 'var(--ms-color-text-secondary)', fontSize: '12px' } }>
										{ video.date
											? new Date( video.date ).toLocaleDateString( undefined, {
												year: 'numeric',
												month: 'short',
												day: 'numeric',
											} )
											: '\u2014' }
									</td>
									<td className="mediashield-table__actions">
										<a
											href={ `${ config.adminUrl }post.php?post=${ video.id }&action=edit` }
											className="mediashield-action-btn mediashield-action-btn--edit"
										>
											{ __( 'Edit', 'mediashield' ) }
										</a>
										<button
										type="button"
										className="mediashield-action-btn mediashield-action-btn--view"
										onClick={ () => setPreviewVideo( video ) }
									>
										{ __( 'Preview', 'mediashield' ) }
									</button>
									</td>
								</tr>
							) ) }
						</tbody>
					</table>

					{ totalPages > 1 && (
						<div className="mediashield-pagination">
							<span className="mediashield-pagination__info">
								{ `${ __( 'Page', 'mediashield' ) } ${ page } ${ __( 'of', 'mediashield' ) } ${ totalPages }` }
							</span>
							<div className="mediashield-pagination__buttons">
								<Button
									variant="secondary"
									size="small"
									disabled={ page <= 1 }
									onClick={ () => setPage( ( p ) => Math.max( 1, p - 1 ) ) }
								>
									{ __( 'Previous', 'mediashield' ) }
								</Button>
								<Button
									variant="secondary"
									size="small"
									disabled={ page >= totalPages }
									onClick={ () => setPage( ( p ) => p + 1 ) }
								>
									{ __( 'Next', 'mediashield' ) }
								</Button>
							</div>
						</div>
					) }
				</div>
			) }

			{ /* Preview Lightbox */ }
			{ previewVideo && (
				<PreviewModal
					video={ previewVideo }
					onClose={ () => setPreviewVideo( null ) }
				/>
			) }
		</div>
	);
};

export default Videos;
