/**
 * MediaShield Admin – Videos Page
 *
 * List table of mediashield_video CPT posts with pagination.
 *
 * @package MediaShield
 */

import { useState, useEffect } from '@wordpress/element';
import { Button, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { decodeEntities } from '@wordpress/html-entities';

const config = window.mediashieldAdmin || {};
const PER_PAGE = 20;

/**
 * Badge for protection level.
 *
 * @param {Object} props       Component props.
 * @param {string} props.level Protection level slug.
 * @return {JSX.Element} Badge element.
 */
const ProtectionBadge = ( { level } ) => {
	const map = {
		none: __( 'None', 'mediashield' ),
		basic: __( 'Basic', 'mediashield' ),
		standard: __( 'Standard', 'mediashield' ),
		strict: __( 'Strict', 'mediashield' ),
	};

	return (
		<span className={ `mediashield-badge mediashield-badge--${ level || 'none' }` }>
			{ map[ level ] || level || map.none }
		</span>
	);
};

const Videos = () => {
	const [ videos, setVideos ] = useState( [] );
	const [ page, setPage ] = useState( 1 );
	const [ totalPages, setTotalPages ] = useState( 1 );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( '' );

	useEffect( () => {
		let cancelled = false;
		setLoading( true );
		setError( '' );

		apiFetch( {
			path: `/wp/v2/mediashield-videos?per_page=${ PER_PAGE }&page=${ page }&_locale=user`,
			parse: false,
		} )
			.then( async ( res ) => {
				const json = await res.json();
				if ( ! cancelled ) {
					setVideos( json );
					setTotalPages(
						parseInt( res.headers.get( 'X-WP-TotalPages' ), 10 ) || 1
					);
				}
			} )
			.catch( ( err ) => {
				if ( ! cancelled ) {
					setError( err.message || __( 'Failed to load videos.', 'mediashield' ) );
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
	}, [ page ] );

	return (
		<div className="mediashield-page mediashield-videos">
			<header className="mediashield-page__header">
				<h1>{ __( 'Videos', 'mediashield' ) }</h1>
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
				<>
					<table className="mediashield-table">
						<thead>
							<tr>
								<th>{ __( 'Title', 'mediashield' ) }</th>
								<th>{ __( 'Platform', 'mediashield' ) }</th>
								<th>{ __( 'Protection', 'mediashield' ) }</th>
								<th>{ __( 'Date', 'mediashield' ) }</th>
								<th>{ __( 'Actions', 'mediashield' ) }</th>
							</tr>
						</thead>
						<tbody>
							{ videos.length === 0 && (
								<tr>
									<td colSpan="5">
										{ __( 'No videos found.', 'mediashield' ) }
									</td>
								</tr>
							) }
							{ videos.map( ( video ) => (
								<tr key={ video.id }>
									<td>
										{ decodeEntities( video.title?.rendered || '' ) }
									</td>
									<td>{ video.meta?.platform || '—' }</td>
									<td>
										<ProtectionBadge level={ video.meta?.protection_level } />
									</td>
									<td>
										{ video.date
											? new Date( video.date ).toLocaleDateString()
											: '—' }
									</td>
									<td className="mediashield-table__actions">
										<a
											href={ `${ config.adminUrl }post.php?post=${ video.id }&action=edit` }
											className="mediashield-link"
										>
											{ __( 'Edit', 'mediashield' ) }
										</a>
										{ video.link && (
											<a
												href={ video.link }
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

					<div className="mediashield-pagination">
						<Button
							variant="secondary"
							disabled={ page <= 1 }
							onClick={ () => setPage( ( p ) => Math.max( 1, p - 1 ) ) }
						>
							{ __( 'Previous', 'mediashield' ) }
						</Button>
						<span className="mediashield-pagination__info">
							{ `${ page } / ${ totalPages }` }
						</span>
						<Button
							variant="secondary"
							disabled={ page >= totalPages }
							onClick={ () => setPage( ( p ) => p + 1 ) }
						>
							{ __( 'Next', 'mediashield' ) }
						</Button>
					</div>
				</>
			) }
		</div>
	);
};

export default Videos;
