/**
 * MediaShield Admin – Milestones Page
 *
 * Recent milestones table with pagination.
 *
 * @package MediaShield
 */

import { useState, useEffect } from '@wordpress/element';
import { Button, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

const config = window.mediashieldAdmin || {};
const PER_PAGE = 20;

const Milestones = () => {
	const [ milestones, setMilestones ] = useState( [] );
	const [ page, setPage ] = useState( 1 );
	const [ totalPages, setTotalPages ] = useState( 1 );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( '' );

	useEffect( () => {
		let cancelled = false;
		setLoading( true );
		setError( '' );

		apiFetch( {
			url: `${ config.restUrl }analytics/milestones?page=${ page }&per_page=${ PER_PAGE }`,
			headers: { 'X-WP-Nonce': config.nonce },
			parse: false,
		} )
			.then( async ( res ) => {
				const json = await res.json();
				if ( ! cancelled ) {
					setMilestones( json );
					setTotalPages(
						parseInt( res.headers.get( 'X-WP-TotalPages' ), 10 ) || 1
					);
				}
			} )
			.catch( ( err ) => {
				if ( ! cancelled ) {
					setError( err.message || __( 'Failed to load milestones.', 'mediashield' ) );
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
		<div className="mediashield-page mediashield-milestones">
			<header className="mediashield-page__header">
				<h1>{ __( 'Milestones', 'mediashield' ) }</h1>
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
								<th>{ __( 'User', 'mediashield' ) }</th>
								<th>{ __( 'Video', 'mediashield' ) }</th>
								<th>{ __( 'Milestone %', 'mediashield' ) }</th>
								<th>{ __( 'Reached At', 'mediashield' ) }</th>
							</tr>
						</thead>
						<tbody>
							{ milestones.length === 0 && (
								<tr>
									<td colSpan="4">
										{ __( 'No milestones recorded yet.', 'mediashield' ) }
									</td>
								</tr>
							) }
							{ milestones.map( ( m, idx ) => (
								<tr key={ m.id ?? idx }>
									<td>{ m.user_name || m.user_id }</td>
									<td>{ m.video_title || m.video_id }</td>
									<td>{ `${ m.milestone }%` }</td>
									<td>
										{ m.reached_at
											? new Date( m.reached_at ).toLocaleString()
											: '—' }
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

export default Milestones;
