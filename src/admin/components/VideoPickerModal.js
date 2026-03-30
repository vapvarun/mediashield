/**
 * Video Picker Modal — Shared component for selecting a video from the library.
 *
 * Fetches from /wp/v2/mediashield-videos. Searchable by title.
 * Used in both the Gutenberg block and admin SPA.
 *
 * @package MediaShield
 */
import { __ } from '@wordpress/i18n';
import { Modal, TextControl, Button, Spinner } from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import { decodeEntities } from '@wordpress/html-entities';
import apiFetch from '@wordpress/api-fetch';

export default function VideoPickerModal( { onSelect, onClose } ) {
	const [ videos, setVideos ] = useState( [] );
	const [ search, setSearch ] = useState( '' );
	const [ loading, setLoading ] = useState( true );

	useEffect( () => {
		setLoading( true );

		const params = new URLSearchParams( {
			per_page: '20',
			status: 'publish',
			orderby: 'date',
			order: 'desc',
		} );

		if ( search.trim() ) {
			params.set( 'search', search.trim() );
		}

		apiFetch( { path: `/wp/v2/mediashield-videos?${ params }` } )
			.then( ( data ) => {
				setVideos( data );
				setLoading( false );
			} )
			.catch( () => {
				setVideos( [] );
				setLoading( false );
			} );
	}, [ search ] );

	return (
		<Modal
			title={ __( 'Choose a Video', 'mediashield' ) }
			onRequestClose={ onClose }
			style={ { maxWidth: '600px', width: '100%' } }
		>
			<TextControl
				label={ __( 'Search videos', 'mediashield' ) }
				value={ search }
				onChange={ setSearch }
				placeholder={ __( 'Type to search...', 'mediashield' ) }
			/>

			{ loading && (
				<div style={ { textAlign: 'center', padding: '20px' } }>
					<Spinner />
				</div>
			) }

			{ ! loading && videos.length === 0 && (
				<p style={ { textAlign: 'center', color: '#999', padding: '20px' } }>
					{ __( 'No videos found.', 'mediashield' ) }
				</p>
			) }

			{ ! loading && videos.length > 0 && (
				<div style={ { maxHeight: '400px', overflowY: 'auto' } }>
					{ videos.map( ( video ) => {
						const platform = video.meta?._ms_platform || 'unknown';
						const title = decodeEntities( video.title?.rendered || '' ) || __( 'Untitled', 'mediashield' );
						return (
							<div
								key={ video.id }
								style={ {
									display: 'flex',
									alignItems: 'center',
									padding: '10px',
									borderBottom: '1px solid #eee',
									cursor: 'pointer',
								} }
								onClick={ () => onSelect( video ) }
								onKeyDown={ ( e ) => {
									if ( e.key === 'Enter' ) onSelect( video );
								} }
								role="button"
								tabIndex={ 0 }
							>
								<span
									className="dashicons dashicons-video-alt3"
									style={ { marginRight: '12px', color: '#2271b1' } }
								/>
								<div style={ { flex: 1 } }>
									<strong>{ title }</strong>
									<br />
									<small style={ { color: '#999' } }>
										{ platform } &middot; #{ video.id }
									</small>
								</div>
								<Button variant="secondary" isSmall>
									{ __( 'Select', 'mediashield' ) }
								</Button>
							</div>
						);
					} ) }
				</div>
			) }
		</Modal>
	);
}
