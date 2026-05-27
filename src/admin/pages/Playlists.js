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

const Playlists = () => {
	const [ playlists, setPlaylists ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( '' );
	const [ itemsModal, setItemsModal ] = useState( null );

	useEffect( () => {
		let cancelled = false;
		setLoading( true );
		setError( '' );

		apiFetch( {
			path: '/wp/v2/mediashield-playlists?_locale=user',
		} )
			.then( ( res ) => {
				if ( ! cancelled ) setPlaylists( res );
			} )
			.catch( ( err ) => {
				if ( ! cancelled ) setError( err.message || __( 'Failed to load playlists.', 'mediashield' ) );
			} )
			.finally( () => {
				if ( ! cancelled ) setLoading( false );
			} );

		return () => {
			cancelled = true;
		};
	}, [] );

	return (
		<div className="mediashield-page mediashield-playlists">
			<header className="mediashield-page__header">
				<h1>
					{ __( 'Playlists', 'mediashield' ) }
					{ ! loading && (
						<span className="mediashield-page__header-subtitle">
							{ playlists.length } { playlists.length === 1 ? __( 'playlist', 'mediashield' ) : __( 'playlists', 'mediashield' ) }
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
										<td>
											<strong>
												{ decodeEntities( playlist.title?.rendered || '' ) }
											</strong>
										</td>
										<td>
											{ flags.length > 0 ? (
												flags.map( ( f ) => (
													<span
														key={ f }
														className="mediashield-badge mediashield-badge--standard"
														style={ { marginRight: 4 } }
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
										<td style={ { color: 'var(--ms-color-text-secondary)', fontSize: '12px' } }>
											{ playlist.date
												? new Date( playlist.date ).toLocaleDateString( undefined, {
													year: 'numeric',
													month: 'short',
													day: 'numeric',
												} )
												: '\u2014' }
										</td>
										<td className="mediashield-table__actions">
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
											{ playlist.link && (
												<a
													href={ playlist.link }
													className="mediashield-action-btn mediashield-action-btn--view"
													target="_blank"
													rel="noopener noreferrer"
												>
													{ __( 'View', 'mediashield' ) }
												</a>
											) }
										</td>
									</tr>
								);
							} ) }
						</tbody>
					</table>
				</div>
			) }

			{ itemsModal && (
				<PlaylistItemsModal
					playlistId={ itemsModal.id }
					playlistTitle={ itemsModal.title }
					onClose={ () => setItemsModal( null ) }
				/>
			) }
		</div>
	);
};

export default Playlists;
