/**
 * Video Picker Modal — choose a video from the library.
 *
 * Used by the Video block and the playlist screens. Search runs on the
 * server (/wp/v2/mediashield-videos?search=), 20 videos a page with
 * "Show more", so it works the same with 20 videos or 20,000.
 *
 * Each row says what the video is in owners' words, from the `ms_card`
 * field (VideoSummaryController::card()): "YouTube · 12:04 · Site default
 * (Standard)". Never a platform slug or an id.
 *
 * @package MediaShield
 */
import { __, sprintf } from '@wordpress/i18n';
import { Modal, TextControl, Button, Spinner, Notice } from '@wordpress/components';
import { useState, useEffect, useRef } from '@wordpress/element';
import { decodeEntities } from '@wordpress/html-entities';
import apiFetch from '@wordpress/api-fetch';
import { formatDuration } from '../utils/time';
import Icon from './Icon';
import './VideoPickerModal.css';

const PER_PAGE = 20;

/**
 * "YouTube · 12:04 · Site default (Standard)".
 *
 * @param {Object|null} card The video's ms_card.
 * @return {string} One line.
 */
function describe( card ) {
	if ( ! card ) return '';
	const parts = [ card.platform_label ];
	if ( card.duration > 0 ) parts.push( formatDuration( card.duration ) );
	parts.push( card.protection_label );
	return parts.filter( Boolean ).join( ' · ' );
}

export default function VideoPickerModal( { onSelect, onClose } ) {
	const [ videos, setVideos ] = useState( [] );
	const [ search, setSearch ] = useState( '' );
	const [ query, setQuery ] = useState( '' );
	const [ page, setPage ] = useState( 1 );
	const [ totalPages, setTotalPages ] = useState( 1 );
	const [ total, setTotal ] = useState( 0 );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( '' );
	const request = useRef( 0 );

	// Ask the server once typing pauses, not on every key.
	useEffect( () => {
		const timer = setTimeout( () => {
			setQuery( search.trim() );
			setPage( 1 );
		}, 300 );
		return () => clearTimeout( timer );
	}, [ search ] );

	useEffect( () => {
		const id = ++request.current;
		setLoading( true );
		setError( '' );

		const params = new URLSearchParams( {
			per_page: String( PER_PAGE ),
			page: String( page ),
			status: 'publish',
			// Only videos that can play: never a draft or a video with no source.
			ms_state: 'ready',
			orderby: query ? 'relevance' : 'date',
			order: 'desc',
			_fields: 'id,title,ms_card',
		} );
		if ( query ) {
			params.set( 'search', query );
		}

		apiFetch( { path: `/wp/v2/mediashield-videos?${ params }`, parse: false } )
			.then( ( res ) => window.mediashieldRequest.readJSON( res ).then( ( data ) => ( { res, data } ) ) )
			.then( ( { res, data } ) => {
				// A later search has started: this answer is stale.
				if ( id !== request.current ) return;
				setTotalPages( parseInt( res.headers.get( 'X-WP-TotalPages' ), 10 ) || 1 );
				setTotal( parseInt( res.headers.get( 'X-WP-Total' ), 10 ) || 0 );
				setVideos( ( prev ) => ( 1 === page ? data : [ ...prev, ...data ] ) );
			} )
			.catch( ( err ) => {
				if ( id !== request.current ) return;
				setError( window.mediashieldRequest.message( err, __( 'The videos could not be loaded. Please try again.', 'mediashield' ) ) );
			} )
			.finally( () => {
				if ( id === request.current ) setLoading( false );
			} );
	}, [ query, page ] );

	return (
		<Modal
			title={ __( 'Choose a Video', 'mediashield' ) }
			onRequestClose={ onClose }
			className="ms-video-picker"
		>
			<TextControl
				label={ __( 'Search videos', 'mediashield' ) }
				value={ search }
				onChange={ setSearch }
				placeholder={ __( 'Type part of a title…', 'mediashield' ) }
				__nextHasNoMarginBottom
				__next40pxDefaultSize
			/>

			{ error && <Notice status="error" isDismissible={ false }>{ error }</Notice> }

			{ ! loading && ! error && videos.length === 0 && (
				<p className="ms-video-picker__empty">
					{ query
						? __( 'No videos match that search.', 'mediashield' )
						: __( 'Your library has no published videos yet.', 'mediashield' ) }
				</p>
			) }

			{ videos.length > 0 && (
				<ul className="ms-video-picker__list" aria-busy={ loading }>
					{ videos.map( ( video ) => {
						const title = decodeEntities( video.title?.rendered || '' ) || __( '(no title)', 'mediashield' );
						const card = video.ms_card;
						return (
							<li key={ video.id }>
								<button type="button" className="ms-video-picker__row" onClick={ () => onSelect( video ) }>
									{ card?.thumbnail ? (
										<img className="ms-video-picker__thumb" src={ card.thumbnail } alt="" onError={ ( e ) => { e.currentTarget.hidden = true; } } loading="lazy" />
									) : (
										<span className="ms-video-picker__thumb ms-video-picker__thumb--empty" aria-hidden="true">
											<Icon name="video-alt3" />
										</span>
									) }
									<span className="ms-video-picker__text">
										<strong>{ title }</strong>
										<span>{ describe( card ) }</span>
									</span>
								</button>
							</li>
						);
					} ) }
				</ul>
			) }

			{ loading && (
				<div className="ms-video-picker__loading">
					<Spinner />
				</div>
			) }

			{ ! loading && page < totalPages && (
				<div className="ms-video-picker__more">
					<Button variant="secondary" onClick={ () => setPage( page + 1 ) } __next40pxDefaultSize>
						{ sprintf(
							/* translators: 1: videos shown, 2: videos found. */
							__( 'Show more (%1$d of %2$d)', 'mediashield' ),
							videos.length,
							total
						) }
					</Button>
				</div>
			) }
		</Modal>
	);
}
