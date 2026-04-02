/**
 * MediaShield Admin -- Milestones Page
 *
 * Premium milestone tracking with colored achievement rings,
 * user avatars, and paginated table.
 *
 * @package MediaShield
 */

import { useState, useEffect } from '@wordpress/element';
import { Button, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

const config = window.mediashieldAdmin || {};
const PER_PAGE = 20;

function getInitials( name ) {
	if ( ! name ) return '?';
	const parts = name.trim().split( /\s+/ );
	if ( parts.length >= 2 ) {
		return ( parts[ 0 ][ 0 ] + parts[ parts.length - 1 ][ 0 ] ).toUpperCase();
	}
	return name.substring( 0, 2 ).toUpperCase();
}

function timeAgo( dateStr ) {
	if ( ! dateStr ) return '\u2014';
	const date = new Date( dateStr );
	const now = new Date();
	const diffMs = now - date;
	const diffMin = Math.floor( diffMs / 60000 );
	const diffHr = Math.floor( diffMin / 60 );
	const diffDay = Math.floor( diffHr / 24 );

	if ( diffMin < 1 ) return __( 'Just now', 'mediashield' );
	if ( diffMin < 60 ) return `${ diffMin }m ago`;
	if ( diffHr < 24 ) return `${ diffHr }h ago`;
	if ( diffDay < 7 ) return `${ diffDay }d ago`;
	return date.toLocaleDateString( undefined, { month: 'short', day: 'numeric', year: 'numeric' } );
}

const MilestoneBadge = ( { pct } ) => {
	const tier = pct >= 100 ? '100' : pct >= 75 ? '75' : pct >= 50 ? '50' : '25';

	return (
		<span className="mediashield-milestone-badge">
			<span className={ `mediashield-milestone-badge__ring mediashield-milestone-badge__ring--${ tier }` }>
				{ pct }
			</span>
			<span>{ `${ pct }%` }</span>
		</span>
	);
};

const Milestones = () => {
	const [ milestones, setMilestones ] = useState( [] );
	const [ page, setPage ] = useState( 1 );
	const [ totalPages, setTotalPages ] = useState( 1 );
	const [ total, setTotal ] = useState( 0 );
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
					setTotalPages( parseInt( res.headers.get( 'X-WP-TotalPages' ), 10 ) || 1 );
					setTotal( parseInt( res.headers.get( 'X-WP-Total' ), 10 ) || 0 );
				}
			} )
			.catch( ( err ) => {
				if ( ! cancelled ) {
					setError( err.message || __( 'Failed to load milestones.', 'mediashield' ) );
				}
			} )
			.finally( () => {
				if ( ! cancelled ) setLoading( false );
			} );

		return () => {
			cancelled = true;
		};
	}, [ page ] );

	return (
		<div className="mediashield-page mediashield-milestones">
			<header className="mediashield-page__header">
				<h1>
					{ __( 'Milestones', 'mediashield' ) }
					{ ! loading && total > 0 && (
						<span className="mediashield-page__header-subtitle">
							{ total } { __( 'achievements', 'mediashield' ) }
						</span>
					) }
				</h1>
			</header>

			{ loading && (
				<div className="mediashield-loader">
					<Spinner />
					<span className="mediashield-loader__text">
						{ __( 'Loading milestones...', 'mediashield' ) }
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
								<th>{ __( 'Student', 'mediashield' ) }</th>
								<th>{ __( 'Video', 'mediashield' ) }</th>
								<th>{ __( 'Milestone', 'mediashield' ) }</th>
								<th>{ __( 'Reached', 'mediashield' ) }</th>
							</tr>
						</thead>
						<tbody>
							{ milestones.length === 0 && (
								<tr>
									<td colSpan="4" className="mediashield-table__empty">
										<span className="mediashield-table__empty-icon dashicons dashicons-flag" />
										{ __( 'No milestones recorded yet. They appear as students watch videos.', 'mediashield' ) }
									</td>
								</tr>
							) }
							{ milestones.map( ( m, idx ) => (
								<tr key={ m.id ?? idx }>
									<td>
										<div className="mediashield-user">
											<div className="mediashield-user__avatar">
												{ getInitials( m.user_name ) }
											</div>
											<div className="mediashield-user__info">
												<span className="mediashield-user__name" style={ { cursor: 'default' } }>
													{ m.user_name || `User #${ m.user_id }` }
												</span>
											</div>
										</div>
									</td>
									<td><strong>{ m.video_title || `Video #${ m.video_id }` }</strong></td>
									<td>
										<MilestoneBadge pct={ m.milestone_pct } />
									</td>
									<td style={ { color: 'var(--ms-color-text-secondary)', fontSize: '12px' } }>
										{ timeAgo( m.reached_at ) }
									</td>
								</tr>
							) ) }
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

			{ ! config.isProActive && (
				<div className="ms-upsell-inline">
					<span className="dashicons dashicons-megaphone" />
					<span>
						{ __( 'With', 'mediashield' ) } <strong>{ __( 'Pro', 'mediashield' ) }</strong>{ __( ', trigger actions on milestones: tag users, send emails, fire webhooks to CRMs.', 'mediashield' ) }
					</span>
					<a href="https://wbcomdesigns.com/downloads/mediashield-pro/" target="_blank" rel="noopener noreferrer">{ __( 'Learn more', 'mediashield' ) } &rarr;</a>
				</div>
			) }
		</div>
	);
};

export default Milestones;
