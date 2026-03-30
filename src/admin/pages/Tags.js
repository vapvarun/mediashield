/**
 * MediaShield Admin -- Tags Page
 *
 * Premium inline CRUD with card-wrapped form, video count badges,
 * and confirmation on delete.
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
		if ( ! newName.trim() ) return;
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

	const handleKeyDown = ( e ) => {
		if ( e.key === 'Enter' && newName.trim() && ! creating ) {
			handleCreate();
		}
	};

	return (
		<div className="mediashield-page mediashield-tags">
			<header className="mediashield-page__header">
				<h1>
					{ __( 'Tags', 'mediashield' ) }
					{ ! loading && (
						<span className="mediashield-page__header-subtitle">
							{ tags.length } { tags.length === 1 ? __( 'tag', 'mediashield' ) : __( 'tags', 'mediashield' ) }
						</span>
					) }
				</h1>
			</header>

			<div className="mediashield-tags__create">
				<TextControl
					placeholder={ __( 'Enter tag name...', 'mediashield' ) }
					value={ newName }
					onChange={ setNewName }
					onKeyDown={ handleKeyDown }
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
					<span className="mediashield-loader__text">
						{ __( 'Loading tags...', 'mediashield' ) }
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
								<th>{ __( 'Name', 'mediashield' ) }</th>
								<th>{ __( 'Slug', 'mediashield' ) }</th>
								<th>{ __( 'Videos', 'mediashield' ) }</th>
								<th>{ __( 'Actions', 'mediashield' ) }</th>
							</tr>
						</thead>
						<tbody>
							{ tags.length === 0 && (
								<tr>
									<td colSpan="4" className="mediashield-table__empty">
										<span className="mediashield-table__empty-icon dashicons dashicons-tag" />
										{ __( 'No tags yet. Create one above to organize your videos.', 'mediashield' ) }
									</td>
								</tr>
							) }
							{ tags.map( ( tag ) => (
								<tr key={ tag.id }>
									<td><strong>{ tag.name }</strong></td>
									<td>
										<code style={ {
											fontSize: '11px',
											background: 'var(--ms-color-bg)',
											padding: '2px 6px',
											borderRadius: '3px',
											color: 'var(--ms-color-text-secondary)',
										} }>
											{ tag.slug }
										</code>
									</td>
									<td>
										<span className="mediashield-badge mediashield-badge--standard">
											{ tag.video_count ?? 0 }
										</span>
									</td>
									<td>
										<button
											className="mediashield-action-btn mediashield-action-btn--delete"
											disabled={ deletingId === tag.id }
											onClick={ () => handleDelete( tag.id ) }
										>
											{ deletingId === tag.id
												? __( 'Deleting...', 'mediashield' )
												: __( 'Delete', 'mediashield' ) }
										</button>
									</td>
								</tr>
							) ) }
						</tbody>
					</table>
				</div>
			) }
		</div>
	);
};

export default Tags;
