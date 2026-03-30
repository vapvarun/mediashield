/**
 * MediaShield Admin – Students Page
 *
 * User watch history with drill-down.
 *
 * @package MediaShield
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import { Button, TextControl, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

const config = window.mediashieldAdmin || {};

/**
 * Single-student drill-down view.
 *
 * @param {Object}   props          Component props.
 * @param {number}   props.userId   WP user ID.
 * @param {Function} props.onBack   Callback to return to list.
 * @return {JSX.Element} Detail view.
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
				if ( ! cancelled ) {
					setDetail( res );
				}
			} )
			.catch( ( err ) => {
				if ( ! cancelled ) {
					setError( err.message || __( 'Failed to load student details.', 'mediashield' ) );
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
	}, [ userId ] );

	return (
		<div className="mediashield-student-detail">
			<Button variant="tertiary" onClick={ onBack }>
				{ __( 'Back to list', 'mediashield' ) }
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
					<h2>{ detail.name || __( 'Unknown User', 'mediashield' ) }</h2>
					<p>{ detail.email }</p>

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
									<td colSpan="3">
										{ __( 'No watch history.', 'mediashield' ) }
									</td>
								</tr>
							) }
							{ ( detail.videos || [] ).map( ( v, idx ) => (
								<tr key={ v.video_id ?? idx }>
									<td>{ v.video_title || v.video_id }</td>
									<td>{ `${ v.progress ?? 0 }%` }</td>
									<td>
										{ v.last_watched
											? new Date( v.last_watched ).toLocaleString()
											: '—' }
									</td>
								</tr>
							) ) }
						</tbody>
					</table>
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

			<div className="mediashield-students__search">
				<TextControl
					label={ __( 'Search by name or email', 'mediashield' ) }
					value={ search }
					onChange={ setSearch }
					__nextHasNoMarginBottom
				/>
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
							<th>{ __( 'Email', 'mediashield' ) }</th>
							<th>{ __( 'Videos Watched', 'mediashield' ) }</th>
							<th>{ __( 'Avg Completion', 'mediashield' ) }</th>
							<th>{ __( 'Last Active', 'mediashield' ) }</th>
						</tr>
					</thead>
					<tbody>
						{ users.length === 0 && (
							<tr>
								<td colSpan="5">
									{ __( 'No students found.', 'mediashield' ) }
								</td>
							</tr>
						) }
						{ users.map( ( user ) => (
							<tr key={ user.id }>
								<td>
									<Button
										variant="link"
										onClick={ () => setSelectedUser( user.id ) }
									>
										{ user.name || __( 'Unknown', 'mediashield' ) }
									</Button>
								</td>
								<td>{ user.email || '—' }</td>
								<td>{ user.videos_watched ?? 0 }</td>
								<td>{ user.avg_completion != null ? `${ user.avg_completion }%` : '—' }</td>
								<td>
									{ user.last_active
										? new Date( user.last_active ).toLocaleString()
										: '—' }
								</td>
							</tr>
						) ) }
					</tbody>
				</table>
			) }
		</div>
	);
};

export default Students;
