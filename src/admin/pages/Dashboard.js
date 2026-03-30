/**
 * MediaShield Admin – Dashboard Page
 *
 * Overview cards with period selector. Chart.js integration deferred.
 *
 * @package MediaShield
 */

import { useState, useEffect } from '@wordpress/element';
import { SelectControl, Spinner, Card, CardBody, CardHeader } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

const config = window.mediashieldAdmin || {};

const PERIOD_OPTIONS = [
	{ label: __( 'Today', 'mediashield' ), value: 'today' },
	{ label: __( 'Last 7 days', 'mediashield' ), value: '7d' },
	{ label: __( 'Last 30 days', 'mediashield' ), value: '30d' },
];

const Dashboard = () => {
	const [ period, setPeriod ] = useState( '7d' );
	const [ data, setData ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( '' );

	useEffect( () => {
		let cancelled = false;
		setLoading( true );
		setError( '' );

		apiFetch( {
			url: `${ config.restUrl }analytics/overview?period=${ period }`,
			headers: { 'X-WP-Nonce': config.nonce },
		} )
			.then( ( res ) => {
				if ( ! cancelled ) {
					setData( res );
				}
			} )
			.catch( ( err ) => {
				if ( ! cancelled ) {
					setError( err.message || __( 'Failed to load analytics.', 'mediashield' ) );
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
	}, [ period ] );

	const cards = [
		{
			key: 'total_videos',
			title: __( 'Total Videos', 'mediashield' ),
			value: data?.total_videos ?? '—',
		},
		{
			key: 'total_sessions',
			title: __( 'Total Sessions', 'mediashield' ),
			value: data?.total_sessions ?? '—',
		},
		{
			key: 'avg_completion',
			title: __( 'Avg Completion', 'mediashield' ),
			value: data?.avg_completion != null ? `${ data.avg_completion }%` : '—',
		},
		{
			key: 'active_viewers',
			title: __( 'Active Viewers', 'mediashield' ),
			value: data?.active_viewers ?? '—',
		},
	];

	return (
		<div className="mediashield-page mediashield-dashboard">
			<header className="mediashield-page__header">
				<h1>{ __( 'Dashboard', 'mediashield' ) }</h1>
				<SelectControl
					label={ __( 'Period', 'mediashield' ) }
					value={ period }
					options={ PERIOD_OPTIONS }
					onChange={ setPeriod }
					__nextHasNoMarginBottom
				/>
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
				<div className="mediashield-cards">
					{ cards.map( ( card ) => (
						<Card key={ card.key } className="mediashield-card">
							<CardHeader>
								<span className="mediashield-card__title">{ card.title }</span>
							</CardHeader>
							<CardBody>
								<span className="mediashield-card__value">{ card.value }</span>
							</CardBody>
						</Card>
					) ) }
				</div>
			) }

			{ /* Chart.js integration placeholder */ }
			{ ! loading && ! error && (
				<Card className="mediashield-card mediashield-card--chart">
					<CardHeader>
						<span className="mediashield-card__title">
							{ __( 'Activity Chart', 'mediashield' ) }
						</span>
					</CardHeader>
					<CardBody>
						<p className="mediashield-placeholder">
							{ __( 'Chart visualisation will appear here (Chart.js integration pending).', 'mediashield' ) }
						</p>
					</CardBody>
				</Card>
			) }
		</div>
	);
};

export default Dashboard;
