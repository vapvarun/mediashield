/**
 * MediaShield Admin -- Playlists Page
 *
 * Premium playlist management with table card, empty state, and action buttons.
 *
 * @package MediaShield
 */

import { useState, useEffect } from '@wordpress/element';
import { Spinner, Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { decodeEntities } from '@wordpress/html-entities';
import Icon from '../components/Icon';
import PlaylistItemsModal from '../components/PlaylistItemsModal';

const config = window.mediashieldAdmin || {};
const PER_PAGE = 20;

const Playlists = () => {
	const [ playlists, setPlaylists ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( '' );
	const [ itemsModal, setItemsModal ] = useState( null );
	// Bumped when the modal closes, so the Settings column shows what was just changed in it.
	const [ refresh, setRefresh ] = useState( 0 );
	const [ page, setPage ] = useState( 1 );
	const [ totalPages, setTotalPages ] = useState( 1 );
	const [ total, setTotal ] = useState( 0 );

	useEffect( () => {
		let cancelled = false;
		setLoading( true );
		setError( '' );

		apiFetch( {
			path: `/wp/v2/mediashield-playlists?per_page=${ PER_PAGE }&page=${ page }&_locale=user`,
			parse: false,
		} )
			.then( async ( res ) => {
				const json = await window.mediashieldRequest.readJSON( res );
				if ( ! cancelled ) {
					setPlaylists( json );
					setTotalPages( parseInt( res.headers.get( 'X-WP-TotalPages' ), 10 ) || 1 );
					setTotal( parseInt( res.headers.get( 'X-WP-Total' ), 10 ) || 0 );
				}
			} )
			.catch( ( err ) => {
				if ( ! cancelled ) setError( window.mediashieldRequest.message( err, __( 'Failed to load playlists.', 'mediashield' ) ) );
			} )
			.finally( () => {
				if ( ! cancelled ) setLoading( false );
			} );

		return () => {
			cancelled = true;
		};
	}, [ refresh, page ] );

	return (
		<div className="mediashield-page mediashield-playlists">
			<header className="mediashield-page__header">
				<h1>
					{ __( 'Playlists', 'mediashield' ) }
					{ ! loading && (
						<span className="mediashield-page__header-subtitle">
							{ total } { total === 1 ? __( 'playlist', 'mediashield' ) : __( 'playlists', 'mediashield' ) }
						</span>
					) }
				</h1>
				<a
					href={ `${ config.adminUrl }post-new.php?post_type=mediashield_playlist` }
					className="components-button is-primary"
				>
					{ __( 'Add New Playlist', 'mediashield' ) }
				</a>
			</header>

			{ loading && (
				<div className="mediashield-loader">
					<Spinner />
					<span className="mediashield-loader__text">
						{ __( 'Loading playlists...', 'mediashield' ) }
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
								<th>{ __( 'Title', 'mediashield' ) }</th>
								<th>{ __( 'Settings', 'mediashield' ) }</th>
								<th>{ __( 'Date', 'mediashield' ) }</th>
								<th>{ __( 'Actions', 'mediashield' ) }</th>
							</tr>
						</thead>
						<tbody>
							{ playlists.length === 0 && (
								<tr>
									<td colSpan="4" className="mediashield-table__empty">
										<Icon name="playlist-audio" className="mediashield-table__empty-icon" />
										{ __( 'No playlists yet. Create one to group your videos.', 'mediashield' ) }
									</td>
								</tr>
							) }
							{ playlists.map( ( playlist ) => {
								const meta = playlist.meta || {};
								const flags = [];
								if ( meta._ms_autoplay ) flags.push( __( 'Autoplay', 'mediashield' ) );
								if ( meta._ms_loop ) flags.push( __( 'Loop', 'mediashield' ) );
								if ( meta._ms_shuffle ) flags.push( __( 'Shuffle', 'mediashield' ) );

								return (
									<tr key={ playlist.id }>
										<td data-label={ __( 'Title', 'mediashield' ) }>
											<strong>
												{ decodeEntities( playlist.title?.rendered || '' ) }
											</strong>
										</td>
										<td data-label={ __( 'Settings', 'mediashield' ) } className="mediashield-table__badges">
											{ flags.length > 0 ? (
												flags.map( ( f ) => (
													<span
														key={ f }
														className="mediashield-badge mediashield-badge--standard"
													>
														{ f }
													</span>
												) )
											) : (
												<span style={ { color: 'var(--ms-color-text-tertiary)', fontSize: '12px' } }>
													{ __( 'Default', 'mediashield' ) }
												</span>
											) }
										</td>
										<td data-label={ __( 'Date', 'mediashield' ) } style={ { color: 'var(--ms-color-text-secondary)', fontSize: '12px' } }>
											{ playlist.date
												? new Date( playlist.date ).toLocaleDateString( undefined, {
													year: 'numeric',
													month: 'short',
													day: 'numeric',
												} )
												: '\u2014' }
										</td>
										<td data-label={ __( 'Actions', 'mediashield' ) } className="mediashield-table__actions">
											<Button
												variant="secondary"
												onClick={ () => setItemsModal( {
													id: playlist.id,
													title: playlist.title?.rendered || '',
												} ) }
												__next40pxDefaultSize
												className="mediashield-action-btn"
											>
												{ __( 'Manage items', 'mediashield' ) }
											</Button>
											<a
												href={ `${ config.adminUrl }post.php?post=${ playlist.id }&action=edit` }
												className="mediashield-action-btn mediashield-action-btn--edit"
											>
												{ __( 'Edit', 'mediashield' ) }
											</a>
										</td>
									</tr>
								);
							} ) }
						</tbody>
					</table>

					{ totalPages > 1 && (
						<div className="mediashield-pagination">
							<span className="mediashield-pagination__info">
								{ `${ __( 'Page', 'mediashield' ) } ${ page } ${ __( 'of', 'mediashield' ) } ${ totalPages }` }
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
						</div>
					) }
				</div>
			) }

			{ itemsModal && (
				<PlaylistItemsModal
					playlistId={ itemsModal.id }
					playlistTitle={ itemsModal.title }
					onClose={ () => {
						setItemsModal( null );
						setRefresh( ( n ) => n + 1 );
					} }
				/>
			) }
		</div>
	);
};

export default Playlists;
