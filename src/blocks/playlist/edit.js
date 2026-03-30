/**
 * MediaShield Playlist Block — Editor Component.
 *
 * Select a playlist CPT, preview video count + thumbnails.
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { Placeholder, Button, Spinner, SelectControl } from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import { decodeEntities } from '@wordpress/html-entities';
import apiFetch from '@wordpress/api-fetch';

export default function Edit( { attributes, setAttributes } ) {
	const { playlistId } = attributes;
	const [ playlists, setPlaylists ] = useState( [] );
	const [ playlist, setPlaylist ] = useState( null );
	const [ items, setItems ] = useState( [] );
	const [ loading, setLoading ] = useState( false );
	const blockProps = useBlockProps();

	// Fetch all playlists for the dropdown.
	useEffect( () => {
		apiFetch( { path: '/wp/v2/mediashield-playlists?per_page=100&status=publish' } )
			.then( setPlaylists )
			.catch( () => setPlaylists( [] ) );
	}, [] );

	// Fetch selected playlist + items when playlistId changes.
	useEffect( () => {
		if ( playlistId > 0 ) {
			setLoading( true );

			Promise.all( [
				apiFetch( { path: `/wp/v2/mediashield-playlists/${ playlistId }` } ),
				apiFetch( { path: `/mediashield/v1/playlists/${ playlistId }/items` } ),
			] )
				.then( ( [ pl, its ] ) => {
					setPlaylist( pl );
					setItems( its );
					setLoading( false );
				} )
				.catch( () => {
					setPlaylist( null );
					setItems( [] );
					setLoading( false );
				} );
		}
	}, [ playlistId ] );

	// No playlist selected.
	if ( ! playlistId ) {
		const options = [
			{ label: __( '-- Select a Playlist --', 'mediashield' ), value: 0 },
			...playlists.map( ( pl ) => ( {
				label: decodeEntities( pl.title?.rendered || '' ),
				value: pl.id,
			} ) ),
		];

		return (
			<div { ...blockProps }>
				<Placeholder
					icon="playlist-video"
					label={ __( 'MediaShield Playlist', 'mediashield' ) }
					instructions={ __( 'Select a playlist to embed.', 'mediashield' ) }
				>
					<SelectControl
						value={ playlistId }
						options={ options }
						onChange={ ( val ) => setAttributes( { playlistId: parseInt( val, 10 ) } ) }
					/>
				</Placeholder>
			</div>
		);
	}

	// Loading.
	if ( loading ) {
		return (
			<div { ...blockProps } style={ { textAlign: 'center', padding: '40px' } }>
				<Spinner />
			</div>
		);
	}

	// Playlist preview.
	const title = playlist ? decodeEntities( playlist.title?.rendered || '' ) : __( 'Playlist', 'mediashield' );

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<div style={ { padding: '16px' } }>
					<h3>{ __( 'Playlist Settings', 'mediashield' ) }</h3>
					<p>
						<strong>{ __( 'ID:', 'mediashield' ) }</strong> { playlistId }
					</p>
					<p>
						<strong>{ __( 'Videos:', 'mediashield' ) }</strong> { items.length }
					</p>
					<Button
						variant="link"
						isDestructive
						onClick={ () => {
							setAttributes( { playlistId: 0 } );
							setPlaylist( null );
							setItems( [] );
						} }
					>
						{ __( 'Change Playlist', 'mediashield' ) }
					</Button>
				</div>
			</InspectorControls>

			<div className="ms-playlist-preview">
				<div className="ms-playlist-header">
					<span className="dashicons dashicons-playlist-video" />
					<strong>{ title }</strong>
					<span className="ms-playlist-count">
						{ items.length } { __( 'videos', 'mediashield' ) }
					</span>
				</div>
				<div className="ms-playlist-items-preview">
					{ items.slice( 0, 5 ).map( ( item, idx ) => (
						<div key={ item.item_id } className="ms-playlist-item-preview">
							<span className="ms-playlist-item-num">{ idx + 1 }</span>
							{ item.thumbnail ? (
								<img src={ item.thumbnail } alt="" className="ms-playlist-item-thumb" />
							) : (
								<span className="dashicons dashicons-video-alt3 ms-playlist-item-thumb-placeholder" />
							) }
							<span className="ms-playlist-item-title">{ item.title }</span>
							<span className="ms-playlist-item-platform">{ item.platform }</span>
						</div>
					) ) }
					{ items.length > 5 && (
						<p className="ms-playlist-more">
							+{ items.length - 5 } { __( 'more videos', 'mediashield' ) }
						</p>
					) }
				</div>
			</div>
		</div>
	);
}
