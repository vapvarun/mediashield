/**
 * PlaylistPlaybackOptions — autoplay / countdown / loop / shuffle for one
 * playlist. Shared by the Manage items modal (Playlists page) and the meta
 * box on the playlist edit screen, so both write the same four meta keys the
 * player has always read. Saves on change, like the item list beside it.
 *
 * @package MediaShield
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import { ToggleControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useDispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import apiFetch from '@wordpress/api-fetch';

const COUNTDOWN_MIN = 1;
const COUNTDOWN_MAX = 30;

export default function PlaylistPlaybackOptions( { playlistId } ) {
	const [ meta, setMeta ] = useState( null );
	const [ countdown, setCountdown ] = useState( '5' );
	const [ saving, setSaving ] = useState( false );

	const { createSuccessNotice, createErrorNotice } = useDispatch( noticesStore );

	useEffect( () => {
		let cancelled = false;
		apiFetch( { path: `/wp/v2/mediashield-playlists/${ playlistId }?context=edit&_fields=meta` } )
			.then( ( res ) => {
				if ( cancelled ) return;
				setMeta( res.meta || {} );
				setCountdown( String( res.meta?._ms_countdown || 5 ) );
			} )
			.catch( ( err ) => {
				if ( ! cancelled ) createErrorNotice( err.message || __( 'Could not load playback options.', 'mediashield' ), { type: 'snackbar' } );
			} );
		return () => {
			cancelled = true;
		};
	}, [ playlistId, createErrorNotice ] );

	const save = useCallback( ( patch ) => {
		const previous = meta;
		setMeta( { ...meta, ...patch } );
		setSaving( true );
		apiFetch( {
			path: `/wp/v2/mediashield-playlists/${ playlistId }`,
			method: 'POST',
			data: { meta: patch },
		} )
			.then( ( res ) => {
				setMeta( res.meta || {} );
				createSuccessNotice( __( 'Playback options saved.', 'mediashield' ), { type: 'snackbar' } );
			} )
			.catch( ( err ) => {
				setMeta( previous );
				createErrorNotice( err.message || __( 'Could not save playback options.', 'mediashield' ), { type: 'snackbar' } );
			} )
			.finally( () => setSaving( false ) );
	}, [ meta, playlistId, createSuccessNotice, createErrorNotice ] );

	const saveCountdown = () => {
		const seconds = Math.min( COUNTDOWN_MAX, Math.max( COUNTDOWN_MIN, parseInt( countdown, 10 ) || 5 ) );
		setCountdown( String( seconds ) );
		if ( seconds !== meta._ms_countdown ) {
			save( { _ms_countdown: seconds } );
		}
	};

	if ( ! meta ) {
		return null;
	}

	return (
		<fieldset className="ms-playlist-options" disabled={ saving }>
			<legend className="ms-playlist-options__legend">{ __( 'Playback', 'mediashield' ) }</legend>
			<ToggleControl
				label={ __( 'Play the next video automatically', 'mediashield' ) }
				checked={ !! meta._ms_autoplay }
				onChange={ ( value ) => save( { _ms_autoplay: value } ) }
				__nextHasNoMarginBottom
			/>
			{ !! meta._ms_autoplay && (
				<TextControl
					className="ms-playlist-options__countdown"
					label={ __( 'Seconds before the next video starts', 'mediashield' ) }
					type="number"
					min={ COUNTDOWN_MIN }
					max={ COUNTDOWN_MAX }
					value={ countdown }
					onChange={ setCountdown }
					onBlur={ saveCountdown }
					__next40pxDefaultSize
					__nextHasNoMarginBottom
				/>
			) }
			<ToggleControl
				label={ __( 'Start again from the first video after the last', 'mediashield' ) }
				checked={ !! meta._ms_loop }
				onChange={ ( value ) => save( { _ms_loop: value } ) }
				__nextHasNoMarginBottom
			/>
			<ToggleControl
				label={ __( 'Play in random order', 'mediashield' ) }
				checked={ !! meta._ms_shuffle }
				onChange={ ( value ) => save( { _ms_shuffle: value } ) }
				__nextHasNoMarginBottom
			/>
		</fieldset>
	);
}
