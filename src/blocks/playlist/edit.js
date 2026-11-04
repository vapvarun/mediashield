/**
 * MediaShield Playlist Block — Editor Component.
 *
 * Select a playlist CPT, preview video count + thumbnails.
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { Placeholder, Spinner, SelectControl, PanelBody, ExternalLink } from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import { decodeEntities } from '@wordpress/html-entities';
import apiFetch from '@wordpress/api-fetch';
import { ListVideo, Video } from 'lucide-react';

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
		if ( playlistId <= 0 ) {
			setPlaylist( null );
			setItems( [] );
			return;
		}
		setLoading( true );
		Promise.all( [
			apiFetch( { path: `/wp/v2/mediashield-playlists/${ playlistId }?context=edit` } ),
			apiFetch( { path: `/mediashield/v1/playlists/${ playlistId }/items` } ),
		] )
			.then( ( [ pl, its ] ) => {
				setPlaylist( pl );
				setItems( Array.isArray( its ) ? its : [] );
			} )
			.catch( () => {
				setPlaylist( null );
				setItems( [] );
			} )
			.finally( () => setLoading( false ) );
	}, [ playlistId ] );

	const options = [
		{ label: __( '-- Select a Playlist --', 'mediashield' ), value: 0 },
		...playlists.map( ( pl ) => ( {
			label: decodeEntities( pl.title?.rendered || '' ),
			value: pl.id,
		} ) ),
	];
	const choose = ( val ) => setAttributes( { playlistId: parseInt( val, 10 ) || 0 } );

	// No playlist selected.
	if ( ! playlistId ) {
		return (
			<div { ...blockProps }>
				<Placeholder
					icon="playlist-video"
					label={ __( 'MediaShield Playlist', 'mediashield' ) }
					instructions={ __( 'Select a playlist to embed.', 'mediashield' ) }
				>
					<SelectControl
						label={ __( 'Playlist', 'mediashield' ) }
						hideLabelFromVision
						value={ playlistId }
						options={ options }
						onChange={ choose }
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</Placeholder>
			</div>
		);
	}

	const title = playlist ? decodeEntities( playlist.title?.rendered || '' ) : __( 'Playlist', 'mediashield' );
	const meta = playlist?.meta || {};
	const onOff = ( v ) => ( v ? __( 'On', 'mediashield' ) : __( 'Off', 'mediashield' ) );
	const countLabel = sprintf(
		/* translators: %d: number of videos in the playlist. */
		_n( '%d video', '%d videos', items.length, 'mediashield' ),
		items.length
	);

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Playlist', 'mediashield' ) } initialOpen>
					<SelectControl
						label={ __( 'Playlist', 'mediashield' ) }
						value={ playlistId }
						options={ options }
						onChange={ choose }
						help={ countLabel }
						__next40pxDefaultSize
						__nextHasNoMarginBottom
					/>
				</PanelBody>
				<PanelBody title={ __( 'Playback', 'mediashield' ) } initialOpen={ false }>
					{ /* Playback is a setting of the playlist itself, shared by this
					     block, the shortcode and the playlist page. */ }
					<dl className="ms-playlist-settings">
						<dt>{ __( 'Autoplay next video', 'mediashield' ) }</dt>
						<dd>{ onOff( meta._ms_autoplay ) }</dd>
						<dt>{ __( 'Countdown', 'mediashield' ) }</dt>
						<dd>
							{ sprintf(
								/* translators: %d: seconds before the next video starts. */
								_n( '%d second', '%d seconds', Number( meta._ms_countdown ) || 5, 'mediashield' ),
								Number( meta._ms_countdown ) || 5
							) }
						</dd>
						<dt>{ __( 'Loop', 'mediashield' ) }</dt>
						<dd>{ onOff( meta._ms_loop ) }</dd>
						<dt>{ __( 'Shuffle', 'mediashield' ) }</dt>
						<dd>{ onOff( meta._ms_shuffle ) }</dd>
					</dl>
					<ExternalLink href={ `post.php?post=${ playlistId }&action=edit` }>
						{ __( 'Change these in the playlist settings', 'mediashield' ) }
					</ExternalLink>
				</PanelBody>
			</InspectorControls>

			{ loading ? (
				<div className="ms-playlist-preview ms-playlist-preview--loading">
					<Spinner />
				</div>
			) : (
				<div className="ms-playlist-preview">
					<div className="ms-playlist-header">
						<ListVideo size={ 20 } strokeWidth={ 1.75 } className="ms-icon" />
						<strong className="ms-playlist-title">{ title }</strong>
						<span className="ms-playlist-count">{ countLabel }</span>
					</div>
					<ol className="ms-playlist-items-preview">
						{ items.slice( 0, 5 ).map( ( item, idx ) => (
							<li key={ item.item_id } className="ms-playlist-item-preview">
								<span className="ms-playlist-item-num">{ idx + 1 }</span>
								{ item.thumbnail ? (
									<img src={ item.thumbnail } alt="" className="ms-playlist-item-thumb" />
								) : (
									<Video size={ 24 } strokeWidth={ 1.75 } className="ms-icon ms-playlist-item-thumb-placeholder" />
								) }
								<span className="ms-playlist-item-title">{ decodeEntities( item.title || '' ) }</span>
								{ item.platform && (
									<span className="ms-playlist-item-platform">{ item.platform }</span>
								) }
							</li>
						) ) }
					</ol>
					{ items.length > 5 && (
						<p className="ms-playlist-more">
							{ sprintf(
								/* translators: %d: number of further videos not shown in the preview. */
								_n( '+%d more video', '+%d more videos', items.length - 5, 'mediashield' ),
								items.length - 5
							) }
						</p>
					) }
				</div>
			) }
		</div>
	);
}
