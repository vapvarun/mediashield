/**
 * MediaShield Admin -- Videos Page
 *
 * Premium list table with platform indicators, protection badges,
 * and action buttons.
 *
 * @package MediaShield
 */

import { useState, useEffect, useMemo } from '@wordpress/element';
import { Button, Modal, Spinner, TextControl, SelectControl, CheckboxControl } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { applyFilters } from '@wordpress/hooks';
import { decodeEntities } from '@wordpress/html-entities';
import { useDispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import Icon from '../components/Icon';
import { formatDuration } from '../utils/time';
import { protectionLevels, siteDefaultLabel, protectionOptions } from '../utils/protection';
import './videos.css';
import '../components/upsell.css';

const config = window.mediashieldAdmin || {};
const PER_PAGE = 20;

const PLATFORM_LABELS = {
	youtube: 'YouTube',
	vimeo: 'Vimeo',
	bunny: 'Bunny Stream',
	wistia: 'Wistia',
	self: __( 'Video file', 'mediashield' ),
	iframe: __( 'Not a video link', 'mediashield' ),
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
	const levels = protectionLevels();
	// An empty level means the video follows the site default; say so,
	// instead of showing "None" for a video that is actually protected.
	if ( ! level ) {
		const def = window.mediashieldAdmin?.defaultProtection || 'standard';
		return <span className={ `mediashield-badge mediashield-badge--${ def }` }>{ siteDefaultLabel() }</span>;
	}
	return (
		<span className={ `mediashield-badge mediashield-badge--${ level }` }>
			{ levels[ level ]?.label || level }
		</span>
	);
};

/**
 * Where the video comes from: the platform, or for a file this site holds,
 * "Uploaded file" and its name instead of the signed stream address.
 *
 * @param {Object} props
 * @param {string} props.platform Platform slug.
 * @param {string} props.file     Uploaded file name, '' for a link.
 * @return {JSX.Element} Label.
 */
const PlatformLabel = ( { platform, file } ) => {
	const p = platform || 'self';
	return (
		<span className={ `mediashield-platform mediashield-platform--${ p }` }>
			<span className="mediashield-platform__dot" />
			{ platformName( p, file ) }
			{ file && <span className="ms-videos__file" title={ file }>{ file }</span> }
		</span>
	);
};

/**
 * The platform's name on every screen; a self-hosted video is an uploaded
 * file or a link to a file.
 *
 * @param {string} p    Platform slug.
 * @param {string} file Uploaded file name.
 * @return {string} Name.
 */
const platformName = ( p, file ) => {
	if ( 'self' === p ) {
		return file ? __( 'Uploaded file', 'mediashield' ) : __( 'Video file link', 'mediashield' );
	}
	return PLATFORM_LABELS[ p ] || p;
};

const STATUS_LABELS = {
	ready: __( 'Ready', 'mediashield' ),
	encoding: __( 'Encoding', 'mediashield' ),
	failed: __( 'Failed', 'mediashield' ),
	// The platform says the video is private, removed or not embeddable.
	unavailable: __( 'Unavailable at platform', 'mediashield' ),
	no_source: __( 'No source', 'mediashield' ),
	draft: __( 'Draft', 'mediashield' ),
};

const PROTECTION_OPTIONS = protectionOptions();

/**
 * The status badge, and for a video that cannot play, the next step.
 *
 * @param {Object} props
 * @param {string} props.status   ready, encoding, failed, unavailable, no_source or draft.
 * @param {string} props.platform Platform slug.
 * @param {string} props.editUrl  The video's edit screen.
 * @return {JSX.Element} Badge.
 */
const StatusBadge = ( { status, platform, editUrl } ) => {
	const s = STATUS_LABELS[ status ] ? status : 'ready';
	const tone = { ready: 'success', encoding: 'warn', failed: 'danger', unavailable: 'danger', no_source: 'danger', draft: 'muted' }[ s ];
	let label = STATUS_LABELS[ s ];
	if ( 'unavailable' === s && PLATFORM_LABELS[ platform ] && 'self' !== platform ) {
		/* translators: %s: platform name, e.g. YouTube. */
		label = sprintf( __( 'Unavailable at %s', 'mediashield' ), PLATFORM_LABELS[ platform ] );
	}
	return (
		<>
			<span className={ `mediashield-badge mediashield-badge--${ tone }` }>{ label }</span>
			{ 'unavailable' === s && (
				<span className="ms-videos__hint">
					<a href={ editUrl } title={ __( 'Removed or made private there.', 'mediashield' ) }>{ __( 'Replace link', 'mediashield' ) }</a>
				</span>
			) }
			{ 'no_source' === s && (
				<span className="ms-videos__hint"><a href={ editUrl }>{ __( 'Add a link or upload', 'mediashield' ) }</a></span>
			) }
		</>
	);
};

const UsedIn = ( { places } ) => {
	if ( ! places || ! places.length ) {
		return <span className="ms-videos__muted">{ __( 'Not used yet', 'mediashield' ) }</span>;
	}
	return (
		<ul className="ms-videos__used">
			{ places.map( ( place ) => (
				<li key={ place.id } title={ `${ decodeEntities( place.title ) } (${ place.type })` }>
					<a href={ place.url }>{ decodeEntities( place.title ) }</a>
					<span className="ms-videos__muted"> { place.type }</span>
				</li>
			) ) }
		</ul>
	);
};

/**
 * A video's thumbnail, or the video icon when there is none or the image
 * does not load (a YouTube video removed before MediaShield checked it).
 *
 * @param {Object} props     Props.
 * @param {string} props.src Image URL.
 * @return {JSX.Element} Thumbnail.
 */
const Thumb = ( { src } ) => {
	const [ failed, setFailed ] = useState( false );
	if ( ! src || failed ) {
		return <span className="ms-videos__thumb ms-videos__thumb--empty" aria-hidden="true"><Icon name="format-video" /></span>;
	}
	return <img className="ms-videos__thumb" src={ src } alt="" loading="lazy" onError={ () => setFailed( true ) } />;
};

/**
 * Videos list (Basecamp 10530930295): thumbnail, duration, status, where it
 * is used, platform and protection filters, bulk protection and Trash, and
 * pages of 20, so a library of hundreds stays workable.
 *
 * @return {JSX.Element} Page.
 */
const Videos = () => {
	const [ videos, setVideos ] = useState( [] );
	const [ total, setTotal ] = useState( 0 );
	const [ page, setPage ] = useState( 1 );
	const [ search, setSearch ] = useState( '' );
	const [ debouncedSearch, setDebouncedSearch ] = useState( '' );
	const [ platform, setPlatform ] = useState( '' );
	const [ protection, setProtection ] = useState( '' );
	const [ view, setView ] = useState( 'all' );
	// Status chip: '' (all), ready, attention or draft. #/videos/attention
	// (the Dashboard's link) opens on that chip.
	const [ state, setState ] = useState( () => {
		const m = window.location.hash.match( /^#\/videos\/(ready|attention|draft)/ );
		return m ? m[ 1 ] : '';
	} );
	const [ counts, setCounts ] = useState( null );
	const [ totalPages, setTotalPages ] = useState( 1 );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( '' );
	// Bumped by Retry or after a bulk action to run the list request again.
	const [ attempt, setAttempt ] = useState( 0 );
	const [ previewVideo, setPreviewVideo ] = useState( null );
	const [ selected, setSelected ] = useState( [] );
	const [ bulkAction, setBulkAction ] = useState( '' );
	const [ applying, setApplying ] = useState( false );

	const { createSuccessNotice, createErrorNotice } = useDispatch( noticesStore );

	// Pro adds "Import from Bunny" when a Bunny library is connected.
	const headerActions = useMemo( () => applyFilters( 'mediashield_videos_header_actions', [] ), [] );

	const copyShortcode = async ( videoId ) => {
		const shortcode = `[mediashield id=${ videoId }]`;
		try {
			// `navigator.clipboard` requires a secure context; fall back to a
			// hidden textarea + execCommand for plain-HTTP dev environments.
			if ( navigator.clipboard && window.isSecureContext ) {
				await navigator.clipboard.writeText( shortcode );
			} else {
				const ta = document.createElement( 'textarea' );
				ta.value = shortcode;
				ta.setAttribute( 'readonly', '' );
				ta.style.position = 'absolute';
				ta.style.left = '-9999px';
				document.body.appendChild( ta );
				ta.select();
				document.execCommand( 'copy' );
				document.body.removeChild( ta );
			}
			createSuccessNotice(
				sprintf(
					/* translators: %s: shortcode that was copied to clipboard */
					__( 'Copied %s to clipboard', 'mediashield' ),
					shortcode
				),
				{ type: 'snackbar' }
			);
		} catch ( err ) {
			createErrorNotice( __( 'Could not copy shortcode — please copy manually.', 'mediashield' ), { type: 'snackbar' } );
		}
	};

	// Debounce the search box so we don't hit the REST API on every keystroke,
	// and reset to page 1 whenever the term changes (a filtered result set has
	// its own page count).
	useEffect( () => {
		const timer = setTimeout( () => {
			setDebouncedSearch( search.trim() );
			setPage( 1 );
		}, 400 );
		return () => clearTimeout( timer );
	}, [ search ] );

	useEffect( () => {
		let cancelled = false;
		setLoading( true );
		setError( '' );
		setSelected( [] );

		// Search, filters and pages are all served by the core posts route;
		// totals come back in the X-WP-Total* headers.
		const params = new URLSearchParams( {
			per_page: String( PER_PAGE ),
			page: String( page ),
			context: 'edit',
			_locale: 'user',
		} );
		if ( debouncedSearch ) {
			params.set( 'search', debouncedSearch );
		}
		if ( platform ) {
			params.set( 'ms_platform', platform );
		}
		if ( protection ) {
			params.set( 'ms_protection', protection );
		}
		if ( state && 'trash' !== view ) {
			params.set( 'ms_state', state );
		}
		params.set( 'status', 'trash' === view ? 'trash' : 'publish,draft,pending,private,future' );

		apiFetch( {
			path: `/wp/v2/mediashield-videos?${ params.toString() }`,
			parse: false,
		} )
			.then( async ( res ) => {
				const json = await window.mediashieldRequest.readJSON( res );
				if ( ! cancelled ) {
					setVideos( json );
					setTotalPages( parseInt( res.headers.get( 'X-WP-TotalPages' ), 10 ) || 1 );
					setTotal( parseInt( res.headers.get( 'X-WP-Total' ), 10 ) || 0 );
				}
			} )
			.catch( ( err ) => {
				if ( ! cancelled ) {
					setError( window.mediashieldRequest.message( err, __( 'Failed to load videos.', 'mediashield' ) ) );
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
	}, [ page, debouncedSearch, platform, protection, view, state, attempt ] );

	// Chip counts: one COUNT(*) on the server (Basecamp 10545260458). Fetched
	// again after a bulk action or a retry, not per page.
	useEffect( () => {
		apiFetch( { path: '/mediashield/v1/videos/counts' } )
			.then( setCounts )
			.catch( () => setCounts( null ) );
	}, [ attempt ] );

	const pickState = ( next ) => {
		setState( next );
		setView( 'all' );
		setPage( 1 );
		window.history.replaceState( null, '', next ? `#/videos/${ next }` : '#/videos' );
	};
	const chips = [
		{ key: '', label: __( 'All', 'mediashield' ), count: counts?.all },
		{ key: 'ready', label: __( 'Ready', 'mediashield' ), count: counts?.ready },
		{ key: 'attention', label: __( 'Needs attention', 'mediashield' ), count: counts?.attention },
		{ key: 'draft', label: __( 'Draft', 'mediashield' ), count: counts?.draft },
	];

	const toggle = ( id ) => setSelected( ( prev ) => ( prev.includes( id ) ? prev.filter( ( x ) => x !== id ) : [ ...prev, id ] ) );
	const allSelected = videos.length > 0 && videos.every( ( v ) => selected.includes( v.id ) );

	const bulkOptions = 'trash' === view
		? [ { value: '', label: __( 'Bulk actions', 'mediashield' ) }, { value: 'restore', label: __( 'Restore', 'mediashield' ) } ]
		: [
			{ value: '', label: __( 'Bulk actions', 'mediashield' ) },
			...PROTECTION_OPTIONS.map( ( o ) => ( {
				value: `protection:${ o.value }`,
				label: sprintf( /* translators: %s: protection level, e.g. Standard. */ __( 'Set protection: %s', 'mediashield' ), o.label ),
			} ) ),
			{ value: 'trash', label: __( 'Move to Trash', 'mediashield' ) },
		];

	const applyBulk = () => {
		if ( ! bulkAction || ! selected.length ) {
			return;
		}
		const [ action, level ] = bulkAction.split( ':' );
		setApplying( true );
		apiFetch( {
			path: '/mediashield/v1/videos/bulk',
			method: 'POST',
			data: { ids: selected, action, ...( level ? { level } : {} ) },
		} )
			.then( ( res ) => {
				const done = ( res.done || [] ).length;
				const skipped = ( res.skipped || [] ).length;
				createSuccessNotice(
					skipped
						? sprintf(
							/* translators: 1: videos changed, 2: videos left as they were. */
							__( '%1$d updated. %2$d skipped: already changed, removed or not yours to edit.', 'mediashield' ),
							done,
							skipped
						)
						: sprintf( /* translators: %d: number of videos changed. */ __( '%d updated.', 'mediashield' ), done ),
					{ type: 'snackbar' }
				);
				setBulkAction( '' );
				setAttempt( ( n ) => n + 1 );
			} )
			.catch( ( err ) => createErrorNotice( window.mediashieldRequest.message( err, __( 'The bulk action could not be applied.', 'mediashield' ) ), { type: 'snackbar' } ) )
			.finally( () => setApplying( false ) );
	};

	const filtered = !! ( debouncedSearch || platform || protection || state );

	return (
		<div className="mediashield-page mediashield-videos">
			<header className="mediashield-page__header">
				<h1>
					{ 'trash' === view ? __( 'Videos in Trash', 'mediashield' ) : __( 'Videos', 'mediashield' ) }
					{ ! loading && ! error && (
						<span className="mediashield-page__header-subtitle">
							{ total } { total === 1 ? __( 'video', 'mediashield' ) : __( 'videos', 'mediashield' ) }
						</span>
					) }
				</h1>
				<div className="ms-videos__header-actions">
					{ headerActions.map( ( a ) => (
						<a key={ a.key } href={ a.href } className="components-button is-secondary">{ a.label }</a>
					) ) }
					<a
						href={ `${ config.adminUrl }post-new.php?post_type=mediashield_video` }
						className="components-button is-primary"
					>
						{ __( 'Add New Video', 'mediashield' ) }
					</a>
				</div>
			</header>

			<div className="ms-videos__toolbar">
				<div className="ms-videos__search">
					<TextControl
						label={ __( 'Search videos', 'mediashield' ) }
						hideLabelFromVision
						placeholder={ __( 'Search videos by title…', 'mediashield' ) }
						value={ search }
						onChange={ setSearch }
						__nextHasNoMarginBottom
						__next40pxDefaultSize
					/>
				</div>
				<SelectControl
					label={ __( 'Platform', 'mediashield' ) }
					hideLabelFromVision
					value={ platform }
					options={ [ { value: '', label: __( 'All platforms', 'mediashield' ) }, ...Object.keys( PLATFORM_LABELS ).filter( ( k ) => 'iframe' !== k ).map( ( k ) => ( { value: k, label: PLATFORM_LABELS[ k ] } ) ) ] }
					onChange={ ( v ) => {
						setPlatform( v );
						setPage( 1 );
					} }
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
				<SelectControl
					label={ __( 'Protection', 'mediashield' ) }
					hideLabelFromVision
					value={ protection }
					options={ [ { value: '', label: __( 'All protection levels', 'mediashield' ) }, ...PROTECTION_OPTIONS ] }
					onChange={ ( v ) => {
						setProtection( v );
						setPage( 1 );
					} }
					__nextHasNoMarginBottom
					__next40pxDefaultSize
				/>
			</div>

			{ 'trash' !== view && counts?.attention > 0 && 'attention' !== state && (
				<div className="ms-videos__attention" role="status">
					<strong>
						{ sprintf(
							/* translators: %d: number of videos that cannot play. */
							_n( '%d video needs attention.', '%d videos need attention.', counts.attention, 'mediashield' ),
							counts.attention
						) }
					</strong>{ ' ' }
					<span>{ _n( 'Members cannot play it until it is fixed.', 'Members cannot play them until they are fixed.', counts.attention, 'mediashield' ) }</span>{ ' ' }
					<Button variant="link" onClick={ () => pickState( 'attention' ) }>{ __( 'Show only these', 'mediashield' ) }</Button>
				</div>
			) }

			<div className="ms-videos__chips" role="group" aria-label={ __( 'Show videos by status', 'mediashield' ) }>
				{ chips.map( ( c ) => (
					<Button
						key={ c.key || 'all' }
						variant={ 'trash' !== view && state === c.key ? 'primary' : 'secondary' }
						aria-pressed={ 'trash' !== view && state === c.key }
						onClick={ () => pickState( c.key ) }
						__next40pxDefaultSize
					>
						{ c.label }{ undefined !== c.count && <span className="ms-videos__chip-count">{ c.count }</span> }
					</Button>
				) ) }
				<Button
					variant={ 'trash' === view ? 'primary' : 'tertiary' }
					aria-pressed={ 'trash' === view }
					onClick={ () => {
						setView( 'trash' );
						setPage( 1 );
					} }
					__next40pxDefaultSize
				>
					{ __( 'Trash', 'mediashield' ) }
				</Button>
			</div>

			{ loading && (
				<div className="mediashield-loader">
					<Spinner />
					<span className="mediashield-loader__text">
						{ __( 'Loading videos...', 'mediashield' ) }
					</span>
				</div>
			) }

			{ error && (
				<div className="mediashield-notice mediashield-notice--error" role="alert">
					{ error }{ ' ' }
					<Button variant="secondary" onClick={ () => setAttempt( ( n ) => n + 1 ) }>
						{ __( 'Try again', 'mediashield' ) }
					</Button>
				</div>
			) }

			{ ! loading && ! error && (
				<div className="mediashield-table-card">
					{ videos.length > 0 && (
						<div className="ms-videos__bulk">
							<CheckboxControl
								label={ allSelected ? __( 'Clear selection', 'mediashield' ) : __( 'Select all on this page', 'mediashield' ) }
								checked={ allSelected }
								onChange={ () => setSelected( allSelected ? [] : videos.map( ( v ) => v.id ) ) }
								__nextHasNoMarginBottom
							/>
							<SelectControl
								label={ __( 'Bulk action', 'mediashield' ) }
								hideLabelFromVision
								value={ bulkAction }
								options={ bulkOptions }
								onChange={ setBulkAction }
								__nextHasNoMarginBottom
								__next40pxDefaultSize
							/>
							<Button variant="secondary" onClick={ applyBulk } isBusy={ applying } disabled={ applying || ! bulkAction || ! selected.length }>
								{ selected.length
									? sprintf( /* translators: %d: number of selected videos. */ __( 'Apply to %d', 'mediashield' ), selected.length )
									: __( 'Apply', 'mediashield' ) }
							</Button>
						</div>
					) }
					<table className="mediashield-table ms-videos__table">
						<thead>
							<tr>
								<th className="ms-videos__col-check"><span className="screen-reader-text">{ __( 'Select', 'mediashield' ) }</span></th>
								<th className="ms-videos__col-title">{ __( 'Video', 'mediashield' ) }</th>
								<th className="ms-videos__col-platform">{ __( 'Source', 'mediashield' ) }</th>
								<th className="ms-videos__col-duration">{ __( 'Length', 'mediashield' ) }</th>
								<th className="ms-videos__col-protection">{ __( 'Protection', 'mediashield' ) }</th>
								<th className="ms-videos__col-used">{ __( 'Used in', 'mediashield' ) }</th>
								<th className="ms-videos__col-status">{ __( 'Status', 'mediashield' ) }</th>
								<th className="ms-videos__col-actions">{ __( 'Actions', 'mediashield' ) }</th>
							</tr>
						</thead>
						<tbody>
							{ videos.length === 0 && (
								<tr>
									<td colSpan="8" className="mediashield-table__empty">
										<Icon name="format-video" className="mediashield-table__empty-icon" />
										{ 'trash' === view
											? __( 'The trash is empty.', 'mediashield' )
											: ( filtered
												? __( 'No videos match these filters.', 'mediashield' )
												: __( 'No videos yet. Create your first protected video.', 'mediashield' ) ) }
									</td>
								</tr>
							) }
							{ videos.map( ( video ) => {
								const list = video.ms_list || {};
								const title = decodeEntities( video.title?.raw ?? video.title?.rendered ?? '' ) || __( '(no title)', 'mediashield' );
								const editUrl = `${ config.adminUrl }post.php?post=${ video.id }&action=edit`;
								const level = video.meta?._ms_protection_level;
								// One line under the title on a phone, where the other columns hide.
								const phoneMeta = [
									'no_source' === list.status && ! list.source ? null : ( list.source || platformName( video.meta?._ms_platform || 'self', list.source ) ),
									( list.duration ?? video.meta?._ms_duration ) > 0 ? formatDuration( list.duration ?? video.meta?._ms_duration ) : null,
									level ? protectionLevels()[ level ]?.label : siteDefaultLabel(),
								].filter( Boolean ).join( ' · ' );
								return (
									<tr key={ video.id } className={ selected.includes( video.id ) ? 'is-selected' : '' }>
										<td className="ms-videos__col-check" data-label={ __( 'Select', 'mediashield' ) }>
											<CheckboxControl
												label={ <span className="screen-reader-text">{ sprintf( /* translators: %s: video title. */ __( 'Select %s', 'mediashield' ), title ) }</span> }
												checked={ selected.includes( video.id ) }
												onChange={ () => toggle( video.id ) }
												__nextHasNoMarginBottom
											/>
										</td>
										<td className="ms-videos__col-title" data-label={ __( 'Video', 'mediashield' ) }>
											<div className="ms-videos__title">
												<Thumb src={ list.thumbnail } />
												<span className="ms-videos__title-text">
													<a className="ms-videos__title-link" href={ editUrl } title={ title }><strong>{ title }</strong></a>
													<span className="ms-videos__phone-meta">
														{ 'unavailable' === list.status && <a href={ editUrl } title={ __( 'Removed or made private there.', 'mediashield' ) }>{ __( 'Replace link', 'mediashield' ) }</a> }
														{ 'no_source' === list.status && <a href={ editUrl }>{ __( 'Add a link or upload', 'mediashield' ) }</a> }
														{ 'unavailable' !== list.status && 'no_source' !== list.status && phoneMeta }
													</span>
													{ list.needs_length && (
														<span className="ms-videos__needs-length">
															<a
																href={ `${ editUrl }#ms-duration` }
																aria-label={ __( "Add this video's length so the watch rule can't be skipped.", 'mediashield' ) }
																title={ __( "Add this video's length so the watch rule can't be skipped.", 'mediashield' ) }
															>
																<span className="ms-videos__chip">{ __( 'Length needed', 'mediashield' ) }</span>
															</a>
														</span>
													) }
												</span>
											</div>
										</td>
										<td data-label={ __( 'Source', 'mediashield' ) } className="ms-videos__col-platform">
											{ 'no_source' === list.status && ! list.source
												? <span className="ms-videos__muted" aria-label={ __( 'No source', 'mediashield' ) }>—</span>
												: <PlatformLabel platform={ video.meta?._ms_platform } file={ list.source } /> }
										</td>
										<td data-label={ __( 'Length', 'mediashield' ) } className="ms-videos__num ms-videos__col-duration">
											{ formatDuration( list.duration ?? video.meta?._ms_duration ) }
										</td>
										<td data-label={ __( 'Protection', 'mediashield' ) } className="ms-videos__col-protection">
											<ProtectionBadge level={ video.meta?._ms_protection_level } />
										</td>
										<td data-label={ __( 'Used in', 'mediashield' ) } className="ms-videos__col-used">
											<UsedIn places={ list.used_in } />
										</td>
										<td data-label={ __( 'Status', 'mediashield' ) } className="ms-videos__col-status">
											<StatusBadge status={ list.status } platform={ video.meta?._ms_platform } editUrl={ editUrl } />
										</td>
										<td data-label={ __( 'Actions', 'mediashield' ) } className="mediashield-table__actions ms-videos__col-actions">
											<a
												href={ editUrl }
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
											<button
												type="button"
												className="mediashield-action-btn mediashield-action-btn--copy"
												onClick={ () => copyShortcode( video.id ) }
												title={ __( 'Copy [mediashield id=…] shortcode', 'mediashield' ) }
												aria-label={ __( 'Copy shortcode to clipboard', 'mediashield' ) }
											>
												<Icon name="clipboard" className="mediashield-action-btn__icon" />
												<span className="ms-videos__copy-label">{ __( 'Shortcode', 'mediashield' ) }</span>
											</button>
										</td>
									</tr>
								);
							} ) }
						</tbody>
					</table>

					{ totalPages > 1 && (
						<nav className="mediashield-pagination" aria-label={ __( 'Videos pages', 'mediashield' ) }>
							<span className="mediashield-pagination__info">
								{ sprintf(
									/* translators: 1: current page, 2: number of pages. */
									__( 'Page %1$d of %2$d', 'mediashield' ),
									page,
									totalPages
								) }
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
						</nav>
					) }
				</div>
			) }

			{ /* One line, at the end, never a box above the list (Basecamp 10545260681). */ }
			{ ! config.isProActive && ! loading && 'trash' !== view && (
				<p className="ms-upsell-line">
					{ __( 'Bunny Stream import is in Pro.', 'mediashield' ) }{ ' ' }
					<a href="https://wbcomdesigns.com/downloads/mediashield-pro/" target="_blank" rel="noopener noreferrer">{ __( 'See MediaShield Pro', 'mediashield' ) }</a>
				</p>
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
