/**
 * MediaShield Admin -- Students Page
 *
 * User engagement tracking with avatar initials, progress bars,
 * and drill-down detail view.
 *
 * @package MediaShield
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import { Button, TextControl, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

const config = window.mediashieldAdmin || {};

/**
 * Get initials from a name string.
 */
function getInitials( name ) {
	if ( ! name ) return '?';
	const parts = name.trim().split( /\s+/ );
	if ( parts.length >= 2 ) {
		return ( parts[ 0 ][ 0 ] + parts[ parts.length - 1 ][ 0 ] ).toUpperCase();
	}
	return name.substring( 0, 2 ).toUpperCase();
}

/**
 * Progress bar component.
 */
const ProgressBar = ( { value } ) => {
	const pct = Math.min( 100, Math.max( 0, value || 0 ) );
	const isComplete = pct >= 100;

	return (
		<div className="mediashield-progress">
			<div className="mediashield-progress__bar">
				<div
					className={ `mediashield-progress__fill${ isComplete ? ' mediashield-progress__fill--complete' : '' }` }
					style={ { width: `${ pct }%` } }
				/>
			</div>
			<span className="mediashield-progress__label">{ `${ Math.round( pct ) }%` }</span>
		</div>
	);
};

/**
 * Relative time display.
 */
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
	return date.toLocaleDateString( undefined, { month: 'short', day: 'numeric' } );
}

/**
 * Single-student drill-down view.
 */
const StudentDetail = ( { userId, onBack } ) => {
	const [ detail, setDetail ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( '' );

	useEffect( () => {
		let cancelled = false;
		setLoading( true );
		setError( '' );

		apiFetch( {
			url: `${ config.restUrl }analytics/users/${ userId }`,
			headers: { 'X-WP-Nonce': config.nonce },
		} )
			.then( ( res ) => {
				if ( ! cancelled ) setDetail( res );
			} )
			.catch( ( err ) => {
				if ( ! cancelled ) setError( err.message || __( 'Failed to load details.', 'mediashield' ) );
			} )
			.finally( () => {
				if ( ! cancelled ) setLoading( false );
			} );

		return () => {
			cancelled = true;
		};
	}, [ userId ] );

	return (
		<div className="mediashield-student-detail">
			<Button
				variant="tertiary"
				className="mediashield-back-btn"
				onClick={ onBack }
			>
				<span className="dashicons dashicons-arrow-left-alt2" />
				{ __( 'Back to Students', 'mediashield' ) }
			</Button>

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

			{ ! loading && ! error && detail && (
				<>
					<div className="mediashield-student-detail__header">
						<div className="mediashield-student-detail__avatar">
							{ getInitials( detail.name ) }
						</div>
						<div className="mediashield-student-detail__info">
							<h2>{ detail.name || __( 'Unknown User', 'mediashield' ) }</h2>
							<p>{ detail.email }</p>
						</div>
					</div>

					<div className="mediashield-table-card">
						<div className="mediashield-table-card__header">
							<span className="mediashield-table-card__title">
								{ __( 'Watch History', 'mediashield' ) }
							</span>
							<span className="mediashield-table-card__count">
								{ ( detail.videos || [] ).length } { __( 'videos', 'mediashield' ) }
							</span>
						</div>
						<table className="mediashield-table">
							<thead>
								<tr>
									<th>{ __( 'Video', 'mediashield' ) }</th>
									<th>{ __( 'Progress', 'mediashield' ) }</th>
									<th>{ __( 'Last Watched', 'mediashield' ) }</th>
								</tr>
							</thead>
							<tbody>
								{ ( detail.videos || [] ).length === 0 && (
									<tr>
										<td colSpan="3" className="mediashield-table__empty">
											<span className="mediashield-table__empty-icon dashicons dashicons-video-alt3" />
											{ __( 'No watch history recorded.', 'mediashield' ) }
										</td>
									</tr>
								) }
								{ ( detail.videos || [] ).map( ( v, idx ) => (
									<tr key={ v.video_id ?? idx }>
										<td><strong>{ v.video_title || `Video #${ v.video_id }` }</strong></td>
										<td><ProgressBar value={ v.progress } /></td>
										<td style={ { color: 'var(--ms-color-text-secondary)', fontSize: '12px' } }>
											{ v.last_watched
												? new Date( v.last_watched ).toLocaleString()
												: '\u2014' }
										</td>
									</tr>
								) ) }
							</tbody>
						</table>
					</div>
				</>
			) }
		</div>
	);
};

const Students = () => {
	const [ users, setUsers ] = useState( [] );
	const [ search, setSearch ] = useState( '' );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( '' );
	const [ selectedUser, setSelectedUser ] = useState( null );

	const fetchUsers = useCallback( () => {
		setLoading( true );
		setError( '' );

		const qs = search ? `?search=${ encodeURIComponent( search ) }` : '';

		apiFetch( {
			url: `${ config.restUrl }analytics/users${ qs }`,
			headers: { 'X-WP-Nonce': config.nonce },
		} )
			.then( ( res ) => setUsers( res ) )
			.catch( ( err ) =>
				setError( err.message || __( 'Failed to load students.', 'mediashield' ) )
			)
			.finally( () => setLoading( false ) );
	}, [ search ] );

	useEffect( () => {
		const timer = setTimeout( fetchUsers, 400 );
		return () => clearTimeout( timer );
	}, [ fetchUsers ] );

	if ( selectedUser ) {
		return (
			<div className="mediashield-page mediashield-students">
				<StudentDetail
					userId={ selectedUser }
					onBack={ () => setSelectedUser( null ) }
				/>
			</div>
		);
	}

	return (
		<div className="mediashield-page mediashield-students">
			<header className="mediashield-page__header">
				<h1>{ __( 'Students', 'mediashield' ) }</h1>
			</header>

			<div className="mediashield-search-bar">
				<TextControl
					placeholder={ __( 'Search by name or email...', 'mediashield' ) }
					value={ search }
					onChange={ setSearch }
					__nextHasNoMarginBottom
				/>
			</div>

			{ loading && (
				<div className="mediashield-loader">
					<Spinner />
					<span className="mediashield-loader__text">
						{ __( 'Loading students...', 'mediashield' ) }
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
								<th>{ __( 'Videos Watched', 'mediashield' ) }</th>
								<th>{ __( 'Avg Completion', 'mediashield' ) }</th>
								<th>{ __( 'Last Active', 'mediashield' ) }</th>
							</tr>
						</thead>
						<tbody>
							{ users.length === 0 && (
								<tr>
									<td colSpan="4" className="mediashield-table__empty">
										<span className="mediashield-table__empty-icon dashicons dashicons-groups" />
										{ search
											? __( 'No students match your search.', 'mediashield' )
											: __( 'No student activity recorded yet.', 'mediashield' ) }
									</td>
								</tr>
							) }
							{ users.map( ( user ) => (
								<tr key={ user.id }>
									<td>
										<div className="mediashield-user">
											<div className="mediashield-user__avatar">
												{ getInitials( user.name ) }
											</div>
											<div className="mediashield-user__info">
												<button
													className="mediashield-user__name"
													onClick={ () => setSelectedUser( user.id ) }
													type="button"
												>
													{ user.name || __( 'Unknown', 'mediashield' ) }
												</button>
												<div className="mediashield-user__email">
													{ user.email || '' }
												</div>
											</div>
										</div>
									</td>
									<td>
										<strong>{ user.videos_watched ?? 0 }</strong>
									</td>
									<td>
										<ProgressBar value={ user.avg_completion } />
									</td>
									<td style={ { color: 'var(--ms-color-text-secondary)', fontSize: '12px' } }>
										{ timeAgo( user.last_active ) }
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

export default Students;
