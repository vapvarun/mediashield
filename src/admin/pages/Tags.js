/**
 * MediaShield Admin -- Tags Page
 *
 * Video tags: add, rename in place, delete. A tag's video count opens the
 * Videos list filtered to that tag. Member milestone tags are not here;
 * they show on each member's page under Viewers.
 *
 * @package MediaShield
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import { Button, TextControl, Spinner } from '@wordpress/components';
import { __, _n, sprintf } from '@wordpress/i18n';
import { useDispatch } from '@wordpress/data';
import { store as noticesStore } from '@wordpress/notices';
import apiFetch from '@wordpress/api-fetch';
import Icon from '../components/Icon';

const config = window.mediashieldAdmin || {};
const PER_PAGE = 50;

const Tags = () => {
	const [ tags, setTags ] = useState( [] );
	const [ newName, setNewName ] = useState( '' );
	const [ loading, setLoading ] = useState( true );
	const [ creating, setCreating ] = useState( false );
	const [ deletingId, setDeletingId ] = useState( null );
	const [ error, setError ] = useState( '' );
	const [ page, setPage ] = useState( 1 );
	const [ total, setTotal ] = useState( 0 );
	const [ totalPages, setTotalPages ] = useState( 1 );
	// Rename in place: { id, name } while a row is being edited.
	const [ editing, setEditing ] = useState( null );
	const [ saving, setSaving ] = useState( false );

	const { createSuccessNotice, createErrorNotice } = useDispatch( noticesStore );

	const fetchTags = useCallback( () => {
		setLoading( true );
		setError( '' );

		apiFetch( {
			url: `${ config.restUrl }tags?per_page=${ PER_PAGE }&page=${ page }`,
			headers: { 'X-WP-Nonce': config.nonce },
			parse: false,
		} )
			.then( async ( res ) => {
				setTags( await window.mediashieldRequest.readJSON( res ) );
				setTotal( parseInt( res.headers.get( 'X-WP-Total' ), 10 ) || 0 );
				setTotalPages( parseInt( res.headers.get( 'X-WP-TotalPages' ), 10 ) || 1 );
			} )
			.catch( ( err ) =>
				setError( window.mediashieldRequest.message( err, __( 'Failed to load tags.', 'mediashield' ) ) )
			)
			.finally( () => setLoading( false ) );
	}, [ page ] );

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
					window.mediashieldRequest.message( err, __( 'Could not create tag.', 'mediashield' ) ),
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
						window.mediashieldRequest.message( err, __( 'Could not delete tag.', 'mediashield' ) ),
						{ type: 'snackbar' }
					);
				} )
				.finally( () => setDeletingId( null ) );
		},
		[ fetchTags, createSuccessNotice, createErrorNotice ]
	);

	const saveRename = () => {
		const name = editing?.name.trim();
		if ( ! name ) return;
		setSaving( true );
		apiFetch( {
			url: `${ config.restUrl }tags/${ editing.id }`,
			method: 'PATCH',
			headers: { 'X-WP-Nonce': config.nonce },
			data: { name },
		} )
			.then( () => {
				setEditing( null );
				createSuccessNotice( __( 'Tag renamed.', 'mediashield' ), { type: 'snackbar' } );
				fetchTags();
			} )
			.catch( ( err ) =>
				createErrorNotice(
					window.mediashieldRequest.message( err, __( 'Could not rename the tag.', 'mediashield' ) ),
					{ type: 'snackbar' }
				)
			)
			.finally( () => setSaving( false ) );
	};

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
							{ sprintf( /* translators: %d: number of video tags. */ _n( '%d tag', '%d tags', total, 'mediashield' ), total ) }
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
										<Icon name="tag" className="mediashield-table__empty-icon" />
										{ __( 'No video tags yet. Add one above, or in the Tags box on a video, to organise your videos.', 'mediashield' ) }
									</td>
								</tr>
							) }
							{ tags.map( ( tag ) => (
								<tr key={ tag.id }>
									<td data-label={ __( 'Name', 'mediashield' ) }>
										{ editing?.id === tag.id ? (
											<div className="mediashield-tags__rename">
												<TextControl
													label={ __( 'Tag name', 'mediashield' ) }
													hideLabelFromVision
													value={ editing.name }
													onChange={ ( name ) => setEditing( { id: tag.id, name } ) }
													onKeyDown={ ( e ) => {
														if ( 'Enter' === e.key ) saveRename();
														if ( 'Escape' === e.key ) setEditing( null );
													} }
													autoFocus
													__nextHasNoMarginBottom
													__next40pxDefaultSize
												/>
												<Button variant="primary" isBusy={ saving } disabled={ saving || ! editing.name.trim() } onClick={ saveRename }>
													{ __( 'Save', 'mediashield' ) }
												</Button>
												<Button variant="tertiary" disabled={ saving } onClick={ () => setEditing( null ) }>
													{ __( 'Cancel', 'mediashield' ) }
												</Button>
											</div>
										) : (
											<strong>{ tag.name }</strong>
										) }
									</td>
									<td data-label={ __( 'Slug', 'mediashield' ) }>
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
									<td data-label={ __( 'Videos', 'mediashield' ) }>
										{ tag.video_count > 0 ? (
											<a
												className="mediashield-badge mediashield-badge--standard"
												href={ `#/videos/tag/${ tag.id }` }
												aria-label={ sprintf( /* translators: 1: number of videos, 2: tag name. */ _n( 'Show the %1$d video tagged %2$s', 'Show the %1$d videos tagged %2$s', tag.video_count, 'mediashield' ), tag.video_count, tag.name ) }
											>
												{ tag.video_count }
											</a>
										) : (
											<span className="mediashield-badge mediashield-badge--standard">0</span>
										) }
									</td>
									<td data-label={ __( 'Actions', 'mediashield' ) } className="mediashield-table__actions">
										<button
											type="button"
											className="mediashield-action-btn"
											disabled={ editing?.id === tag.id }
											onClick={ () => setEditing( { id: tag.id, name: tag.name } ) }
										>
											{ __( 'Rename', 'mediashield' ) }
										</button>
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
					{ totalPages > 1 && (
						<nav className="mediashield-pagination" aria-label={ __( 'Tag pages', 'mediashield' ) }>
							<span className="mediashield-pagination__info">
								{ sprintf( /* translators: 1: current page, 2: number of pages. */ __( 'Page %1$d of %2$d', 'mediashield' ), page, totalPages ) }
							</span>
							<div className="mediashield-pagination__buttons">
								<Button variant="secondary" size="small" disabled={ page <= 1 } onClick={ () => setPage( ( p ) => Math.max( 1, p - 1 ) ) }>
									{ __( 'Previous', 'mediashield' ) }
								</Button>
								<Button variant="secondary" size="small" disabled={ page >= totalPages } onClick={ () => setPage( ( p ) => p + 1 ) }>
									{ __( 'Next', 'mediashield' ) }
								</Button>
							</div>
						</nav>
					) }
				</div>
			) }
		</div>
	);
};

export default Tags;
