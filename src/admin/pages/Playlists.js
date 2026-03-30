/**
 * MediaShield Admin – Playlists Page
 *
 * @package MediaShield
 */

import { useState, useEffect } from '@wordpress/element';
import { Button, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { decodeEntities } from '@wordpress/html-entities';

const config = window.mediashieldAdmin || {};

const Playlists = () => {
	const [ playlists, setPlaylists ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( '' );

	useEffect( () => {
		let cancelled = false;
		setLoading( true );
		setError( '' );

		apiFetch( {
			url: `${ config.wpRestUrl }wp/v2/mediashield-playlists`,
			headers: { 'X-WP-Nonce': config.nonce },
		} )
			.then( ( res ) => {
				if ( ! cancelled ) {
					setPlaylists( res );
				}
			} )
			.catch( ( err ) => {
				if ( ! cancelled ) {
					setError( err.message || __( 'Failed to load playlists.', 'mediashield' ) );
				}
			} )
			.finally( () => {
				if ( ! cancelled ) {
					setLoading( false );
				}
			} );

		return () => {
			cancelled = true;
		};
	}, [] );

	return (
		<div className="mediashield-page mediashield-playlists">
			<header className="mediashield-page__header">
				<h1>{ __( 'Playlists', 'mediashield' ) }</h1>
			</header>

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
							<th>{ __( 'Title', 'mediashield' ) }</th>
							<th>{ __( 'Date', 'mediashield' ) }</th>
							<th>{ __( 'Actions', 'mediashield' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ playlists.length === 0 && (
							<tr>
								<td colSpan="3">
									{ __( 'No playlists found.', 'mediashield' ) }
								</td>
							</tr>
						) }
						{ playlists.map( ( playlist ) => (
							<tr key={ playlist.id }>
								<td>
									{ decodeEntities( playlist.title?.rendered || '' ) }
								</td>
								<td>
									{ playlist.date
										? new Date( playlist.date ).toLocaleDateString()
										: '—' }
								</td>
								<td className="mediashield-table__actions">
									<a
										href={ `${ config.wpRestUrl.replace( '/wp-json/', '' ) }/wp-admin/post.php?post=${ playlist.id }&action=edit` }
										className="mediashield-link"
									>
										{ __( 'Edit', 'mediashield' ) }
									</a>
									{ playlist.link && (
										<a
											href={ playlist.link }
											className="mediashield-link"
											target="_blank"
											rel="noopener noreferrer"
										>
											{ __( 'View', 'mediashield' ) }
										</a>
									) }
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			) }
		</div>
	);
};

export default Playlists;
