/**
 * MediaShield Admin – Tags Page
 *
 * Inline CRUD for video tags.
 *
 * @package MediaShield
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import { Button, TextControl, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useDispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import apiFetch from '@wordpress/api-fetch';

const config = window.mediashieldAdmin || {};

const Tags = () => {
	const [ tags, setTags ] = useState( [] );
	const [ newName, setNewName ] = useState( '' );
	const [ loading, setLoading ] = useState( true );
	const [ creating, setCreating ] = useState( false );
	const [ deletingId, setDeletingId ] = useState( null );
	const [ error, setError ] = useState( '' );

	const { createSuccessNotice, createErrorNotice } = useDispatch( noticesStore );

	const fetchTags = useCallback( () => {
		setLoading( true );
		setError( '' );

		apiFetch( {
			url: `${ config.restUrl }tags`,
			headers: { 'X-WP-Nonce': config.nonce },
		} )
			.then( ( res ) => setTags( res ) )
			.catch( ( err ) =>
				setError( err.message || __( 'Failed to load tags.', 'mediashield' ) )
			)
			.finally( () => setLoading( false ) );
	}, [] );

	useEffect( () => {
		fetchTags();
	}, [ fetchTags ] );

	const handleCreate = useCallback( () => {
		if ( ! newName.trim() ) {
			return;
		}
		setCreating( true );

		apiFetch( {
			url: `${ config.restUrl }tags`,
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': config.nonce,
			},
			data: { name: newName.trim() },
		} )
			.then( () => {
				setNewName( '' );
				createSuccessNotice( __( 'Tag created.', 'mediashield' ), { type: 'snackbar' } );
				fetchTags();
			} )
			.catch( ( err ) => {
				createErrorNotice(
					err.message || __( 'Could not create tag.', 'mediashield' ),
					{ type: 'snackbar' }
				);
			} )
			.finally( () => setCreating( false ) );
	}, [ newName, fetchTags, createSuccessNotice, createErrorNotice ] );

	const handleDelete = useCallback(
		( id ) => {
			setDeletingId( id );

			apiFetch( {
				url: `${ config.restUrl }tags/${ id }`,
				method: 'DELETE',
				headers: { 'X-WP-Nonce': config.nonce },
			} )
				.then( () => {
					createSuccessNotice( __( 'Tag deleted.', 'mediashield' ), { type: 'snackbar' } );
					fetchTags();
				} )
				.catch( ( err ) => {
					createErrorNotice(
						err.message || __( 'Could not delete tag.', 'mediashield' ),
						{ type: 'snackbar' }
					);
				} )
				.finally( () => setDeletingId( null ) );
		},
		[ fetchTags, createSuccessNotice, createErrorNotice ]
	);

	return (
		<div className="mediashield-page mediashield-tags">
			<header className="mediashield-page__header">
				<h1>{ __( 'Tags', 'mediashield' ) }</h1>
			</header>

			<div className="mediashield-tags__create">
				<TextControl
					label={ __( 'New tag name', 'mediashield' ) }
					value={ newName }
					onChange={ setNewName }
					__nextHasNoMarginBottom
				/>
				<Button
					variant="primary"
					isBusy={ creating }
					disabled={ creating || ! newName.trim() }
					onClick={ handleCreate }
				>
					{ __( 'Add Tag', 'mediashield' ) }
				</Button>
			</div>

			{ loading && (
				<div className="mediashield-loader">
					<Spinner />
				</div>
			) }

			{ error && (
				<div className="mediashield-notice mediashield-notice--error">
					{ error }
				</div>
			) }

			{ ! loading && ! error && (
				<table className="mediashield-table">
					<thead>
						<tr>
							<th>{ __( 'Name', 'mediashield' ) }</th>
							<th>{ __( 'Videos', 'mediashield' ) }</th>
							<th>{ __( 'Actions', 'mediashield' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ tags.length === 0 && (
							<tr>
								<td colSpan="3">
									{ __( 'No tags yet.', 'mediashield' ) }
								</td>
							</tr>
						) }
						{ tags.map( ( tag ) => (
							<tr key={ tag.id }>
								<td>{ tag.name }</td>
								<td>{ tag.video_count ?? 0 }</td>
								<td>
									<Button
										variant="tertiary"
										isDestructive
										isBusy={ deletingId === tag.id }
										disabled={ deletingId === tag.id }
										onClick={ () => handleDelete( tag.id ) }
									>
										{ __( 'Delete', 'mediashield' ) }
									</Button>
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			) }
		</div>
	);
};

export default Tags;
