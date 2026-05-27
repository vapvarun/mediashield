/**
 * Playlist Items Modal — manage videos in a playlist from the admin SPA.
 *
 * Wraps the REST endpoints `/mediashield/v1/playlists/{id}/items` (GET/POST/
 * DELETE) and `/playlists/{id}/items/reorder` (PUT). Until this shipped, the
 * only way to populate a playlist was via direct REST calls — see Basecamp
 * card 9829352473 ("Core feature non-functional"). This restores the missing
 * admin path so site owners don't need an HTTP client to use playlists.
 *
 * The modal renders inline below the playlist row instead of as an overlay,
 * matching how WP's edit-screen "items" panels work — feels closer to a
 * Gutenberg block inspector than a Photoshop dialog.
 *
 * @package MediaShield
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import { Button, Spinner, Modal } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { decodeEntities } from '@wordpress/html-entities';
import { useDispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import apiFetch from '@wordpress/api-fetch';
import Icon from './Icon';
import VideoPickerModal from './VideoPickerModal';

const config = window.mediashieldAdmin || {};

/**
 * Format integer seconds as "M:SS" or "H:MM:SS".
 */
function formatDuration( seconds ) {
	seconds = Math.max( 0, Math.floor( seconds ) );
	const h = Math.floor( seconds / 3600 );
	const m = Math.floor( ( seconds % 3600 ) / 60 );
	const s = seconds % 60;
	const pad = ( n ) => ( n < 10 ? `0${ n }` : `${ n }` );
	if ( h > 0 ) {
		return `${ h }:${ pad( m ) }:${ pad( s ) }`;
	}
	return `${ m }:${ pad( s ) }`;
}

export default function PlaylistItemsModal( { playlistId, playlistTitle, onClose } ) {
	const [ items, setItems ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ saving, setSaving ] = useState( false );
	const [ pickerOpen, setPickerOpen ] = useState( false );

	const { createSuccessNotice, createErrorNotice } = useDispatch( noticesStore );

	const fetchItems = useCallback( () => {
		setLoading( true );
		apiFetch( {
			url: `${ config.restUrl }playlists/${ playlistId }/items`,
			headers: { 'X-WP-Nonce': config.nonce },
		} )
			.then( setItems )
			.catch( ( err ) => createErrorNotice( err.message || __( 'Could not load playlist items.', 'mediashield' ), { type: 'snackbar' } ) )
			.finally( () => setLoading( false ) );
	}, [ playlistId, createErrorNotice ] );

	useEffect( fetchItems, [ fetchItems ] );

	const handleAdd = useCallback( ( video ) => {
		setPickerOpen( false );
		setSaving( true );
		apiFetch( {
			url: `${ config.restUrl }playlists/${ playlistId }/items`,
			method: 'POST',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
			data: { video_id: video.id },
		} )
			.then( () => {
				// POST returns only the bare item record (no title/thumb/etc.) —
				// re-fetch the list so the new row renders with full metadata.
				fetchItems();
				createSuccessNotice( __( 'Added.', 'mediashield' ), { type: 'snackbar' } );
			} )
			.catch( ( err ) => createErrorNotice( err.message || __( 'Add failed.', 'mediashield' ), { type: 'snackbar' } ) )
			.finally( () => setSaving( false ) );
	}, [ playlistId, fetchItems, createSuccessNotice, createErrorNotice ] );

	const handleRemove = useCallback( ( itemId ) => {
		// eslint-disable-next-line no-alert
		if ( ! window.confirm( __( 'Remove this video from the playlist?', 'mediashield' ) ) ) {
			return;
		}
		setSaving( true );
		apiFetch( {
			url: `${ config.restUrl }playlists/${ playlistId }/items/${ itemId }`,
			method: 'DELETE',
			headers: { 'X-WP-Nonce': config.nonce },
		} )
			.then( () => {
				setItems( ( prev ) => prev.filter( ( i ) => i.item_id !== itemId ) );
				createSuccessNotice( __( 'Removed.', 'mediashield' ), { type: 'snackbar' } );
			} )
			.catch( ( err ) => createErrorNotice( err.message || __( 'Remove failed.', 'mediashield' ), { type: 'snackbar' } ) )
			.finally( () => setSaving( false ) );
	}, [ playlistId, createSuccessNotice, createErrorNotice ] );

	const handleMove = useCallback( ( index, direction ) => {
		const target = index + direction;
		if ( target < 0 || target >= items.length ) return;

		const reordered = [ ...items ];
		[ reordered[ index ], reordered[ target ] ] = [ reordered[ target ], reordered[ index ] ];
		setItems( reordered );

		setSaving( true );
		apiFetch( {
			url: `${ config.restUrl }playlists/${ playlistId }/items/reorder`,
			method: 'PUT',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
			// PlaylistController expects `order: [{item_id, sort_order}]` — not a
			// flat list of IDs. See includes/REST/PlaylistController.php :register_routes.
			data: {
				order: reordered.map( ( i, idx ) => ( { item_id: i.item_id, sort_order: idx } ) ),
			},
		} )
			.catch( ( err ) => {
				createErrorNotice( err.message || __( 'Reorder failed.', 'mediashield' ), { type: 'snackbar' } );
				fetchItems(); // resync on failure so UI doesn't lie
			} )
			.finally( () => setSaving( false ) );
	}, [ items, playlistId, fetchItems, createErrorNotice ] );

	return (
		<Modal
			title={ /* translators: %s: playlist title */
				__( 'Manage items — %s', 'mediashield' ).replace( '%s', decodeEntities( playlistTitle || '' ) )
			}
			onRequestClose={ onClose }
			className="mediashield-playlist-items-modal"
			style={ { maxWidth: '720px', width: '100%' } }
		>
			{ loading ? (
				<div className="mediashield-loader">
					<Spinner />
					<span className="mediashield-loader__text">
						{ __( 'Loading items…', 'mediashield' ) }
					</span>
				</div>
			) : (
				<>
					{ items.length === 0 ? (
						<div className="ms-playlist-items__empty">
							<Icon name="playlist-audio" className="ms-playlist-items__empty-icon" />
							<p className="ms-playlist-items__empty-title">
								{ __( 'No videos yet', 'mediashield' ) }
							</p>
							<p className="ms-playlist-items__empty-body">
								{ __( 'Add videos from your library to build the playlist. They will play in this order.', 'mediashield' ) }
							</p>
						</div>
					) : (
						<ol className="ms-playlist-items__list">
							{ items.map( ( item, index ) => (
								<li key={ item.item_id } className="ms-playlist-items__row">
									<span className="ms-playlist-items__position">{ index + 1 }</span>
									<div className="ms-playlist-items__thumb">
										{ item.thumbnail ? (
											<img src={ item.thumbnail } alt="" />
										) : (
											<Icon name="video-alt3" />
										) }
									</div>
									<div className="ms-playlist-items__meta">
										<div className="ms-playlist-items__title">
											{ decodeEntities( item.title || `Video #${ item.video_id }` ) }
										</div>
										<div className="ms-playlist-items__sub">
											<span className={ `ms-playlist-items__platform ms-playlist-items__platform--${ item.platform || 'self' }` }>
												{ item.platform || 'self' }
											</span>
											{ item.duration > 0 && (
												<span className="ms-playlist-items__duration">
													{ formatDuration( item.duration ) }
												</span>
											) }
											{ item.status && item.status !== 'publish' && (
												<span className="ms-playlist-items__status">{ item.status }</span>
											) }
										</div>
									</div>
									<div className="ms-playlist-items__row-actions">
										<Button
											icon="arrow-up-alt2"
											label={ __( 'Move up', 'mediashield' ) }
											disabled={ saving || index === 0 }
											onClick={ () => handleMove( index, -1 ) }
											size="small"
										/>
										<Button
											icon="arrow-down-alt2"
											label={ __( 'Move down', 'mediashield' ) }
											disabled={ saving || index === items.length - 1 }
											onClick={ () => handleMove( index, 1 ) }
											size="small"
										/>
										<Button
											icon="no-alt"
											label={ __( 'Remove', 'mediashield' ) }
											disabled={ saving }
											onClick={ () => handleRemove( item.item_id ) }
											size="small"
											className="ms-playlist-items__remove"
										/>
									</div>
								</li>
							) ) }
						</ol>
					) }

					<div className="ms-playlist-items__actions">
						<Button
							variant="primary"
							icon="plus"
							onClick={ () => setPickerOpen( true ) }
							disabled={ saving }
							__next40pxDefaultSize
						>
							{ __( 'Add video', 'mediashield' ) }
						</Button>
						<Button
							variant="tertiary"
							onClick={ onClose }
							__next40pxDefaultSize
						>
							{ __( 'Done', 'mediashield' ) }
						</Button>
					</div>
				</>
			) }

			{ pickerOpen && (
				<VideoPickerModal
					onSelect={ handleAdd }
					onClose={ () => setPickerOpen( false ) }
				/>
			) }
		</Modal>
	);
}
