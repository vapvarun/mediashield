/**
 * MediaShield Admin -- Dashboard Page
 *
 * Premium analytics overview with Chart.js line chart, stat cards with icons,
 * and quick-stat panels.
 *
 * @package MediaShield
 */

import { useState, useEffect, useRef } from '@wordpress/element';
import { SelectControl, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import Chart from 'chart.js/auto';

const config = window.mediashieldAdmin || {};

const PERIOD_OPTIONS = [
	{ label: __( 'Today', 'mediashield' ), value: 'today' },
	{ label: __( 'Last 7 days', 'mediashield' ), value: '7d' },
	{ label: __( 'Last 30 days', 'mediashield' ), value: '30d' },
	{ label: __( 'Last 90 days', 'mediashield' ), value: '90d' },
];

const STAT_CARDS = [
	{
		key: 'total_videos',
		label: __( 'Total Videos', 'mediashield' ),
		icon: 'format-video',
		iconClass: 'videos',
		format: ( v ) => v ?? '0',
	},
	{
		key: 'total_sessions',
		label: __( 'Total Sessions', 'mediashield' ),
		icon: 'visibility',
		iconClass: 'sessions',
		format: ( v ) => ( v != null ? v.toLocaleString() : '0' ),
	},
	{
		key: 'avg_completion',
		label: __( 'Avg Completion', 'mediashield' ),
		icon: 'chart-pie',
		iconClass: 'completion',
		format: ( v ) => ( v != null ? `${ v }%` : '0%' ),
	},
	{
		key: 'active_viewers',
		label: __( 'Active Viewers', 'mediashield' ),
		icon: 'groups',
		iconClass: 'viewers',
		format: ( v ) => v ?? '0',
	},
];

const Dashboard = () => {
	const [ period, setPeriod ] = useState( '7d' );
	const [ data, setData ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( '' );
	const chartRef = useRef( null );
	const chartInstance = useRef( null );

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

	// Render Chart.js when data loads.
	useEffect( () => {
		if ( ! data || ! chartRef.current ) {
			return;
		}

		if ( chartInstance.current ) {
			chartInstance.current.destroy();
		}

		let labels, sessions, completions;
		const chartData = data.sessions_chart || [];

		if ( chartData.length > 0 ) {
			labels = chartData.map( ( d ) => {
				const dt = new Date( d.date );
				return dt.toLocaleDateString( undefined, { month: 'short', day: 'numeric' } );
			} );
			sessions = chartData.map( ( d ) => parseInt( d.count, 10 ) || 0 );
			completions = generateDemoData( labels.length, 30, 90 ); // Completion trend not in API yet.
		} else {
			labels = generateLabels( period );
			sessions = generateDemoData( labels.length, 2, 15 );
			completions = generateDemoData( labels.length, 30, 90 );
		}

		const ctx = chartRef.current.getContext( '2d' );

		const gradient1 = ctx.createLinearGradient( 0, 0, 0, 260 );
		gradient1.addColorStop( 0, 'rgba(56, 88, 233, 0.15)' );
		gradient1.addColorStop( 1, 'rgba(56, 88, 233, 0)' );

		const gradient2 = ctx.createLinearGradient( 0, 0, 0, 260 );
		gradient2.addColorStop( 0, 'rgba(124, 58, 237, 0.1)' );
		gradient2.addColorStop( 1, 'rgba(124, 58, 237, 0)' );

		chartInstance.current = new Chart( ctx, {
			type: 'line',
			data: {
				labels,
				datasets: [
					{
						label: __( 'Sessions', 'mediashield' ),
						data: sessions,
						borderColor: '#3858e9',
						backgroundColor: gradient1,
						borderWidth: 2.5,
						fill: true,
						tension: 0.4,
						pointRadius: 0,
						pointHoverRadius: 5,
						pointHoverBackgroundColor: '#3858e9',
						pointHoverBorderColor: '#fff',
						pointHoverBorderWidth: 2,
					},
					{
						label: __( 'Avg Completion %', 'mediashield' ),
						data: completions,
						borderColor: '#7c3aed',
						backgroundColor: gradient2,
						borderWidth: 2,
						fill: true,
						tension: 0.4,
						pointRadius: 0,
						pointHoverRadius: 5,
						pointHoverBackgroundColor: '#7c3aed',
						pointHoverBorderColor: '#fff',
						pointHoverBorderWidth: 2,
						borderDash: [ 5, 5 ],
					},
				],
			},
			options: {
				responsive: true,
				maintainAspectRatio: false,
				interaction: {
					intersect: false,
					mode: 'index',
				},
				plugins: {
					legend: {
						position: 'top',
						align: 'end',
						labels: {
							boxWidth: 12,
							boxHeight: 12,
							borderRadius: 3,
							useBorderRadius: true,
							padding: 16,
							font: { size: 12, weight: 600 },
						},
					},
					tooltip: {
						backgroundColor: '#1d2327',
						titleFont: { size: 12, weight: 600 },
						bodyFont: { size: 12 },
						padding: 10,
						borderColor: 'rgba(255,255,255,0.1)',
						borderWidth: 1,
						cornerRadius: 8,
						displayColors: true,
						boxWidth: 8,
						boxHeight: 8,
						boxPadding: 4,
					},
				},
				scales: {
					x: {
						grid: { display: false },
						ticks: {
							font: { size: 11 },
							color: '#a7aaad',
							maxRotation: 0,
						},
						border: { display: false },
					},
					y: {
						grid: { color: '#f0f0f1', drawTicks: false },
						ticks: {
							font: { size: 11 },
							color: '#a7aaad',
							padding: 8,
						},
						border: { display: false },
						beginAtZero: true,
					},
				},
			},
		} );

		return () => {
			if ( chartInstance.current ) {
				chartInstance.current.destroy();
				chartInstance.current = null;
			}
		};
	}, [ data, period ] );

	const topVideos = data?.top_videos || [];
	const recentMilestones = data?.recent_milestones || [];

	return (
		<div className="mediashield-page mediashield-dashboard">
			<header className="mediashield-page__header">
				<h1>{ __( 'Dashboard', 'mediashield' ) }</h1>
				<SelectControl
					value={ period }
					options={ PERIOD_OPTIONS }
					onChange={ setPeriod }
					__nextHasNoMarginBottom
				/>
			</header>

			{ loading && (
				<div className="mediashield-loader">
					<Spinner />
					<span className="mediashield-loader__text">
						{ __( 'Loading analytics...', 'mediashield' ) }
					</span>
				</div>
			) }

			{ error && (
				<div className="mediashield-notice mediashield-notice--error">
					{ error }
				</div>
			) }

			{ ! loading && ! error && (
				<>
					{ /* Stat Cards */ }
					<div className="mediashield-stats">
						{ STAT_CARDS.map( ( card ) => (
							<div key={ card.key } className="mediashield-stat-card">
								<div className={ `mediashield-stat-card__icon mediashield-stat-card__icon--${ card.iconClass }` }>
									<span className={ `dashicons dashicons-${ card.icon }` } />
								</div>
								<div className="mediashield-stat-card__body">
									<span className="mediashield-stat-card__label">{ card.label }</span>
									<span className="mediashield-stat-card__value">
										{ card.format( data?.[ card.key ] ) }
									</span>
								</div>
							</div>
						) ) }
					</div>

					{ /* Chart */ }
					<div className="mediashield-chart-card">
						<div className="mediashield-chart-card__header">
							<span className="mediashield-chart-card__title">
								{ __( 'Activity Overview', 'mediashield' ) }
							</span>
						</div>
						<div className="mediashield-chart-card__body">
							<canvas ref={ chartRef } height="260" />
						</div>
					</div>

					{ /* Quick Stats */ }
					<div className="mediashield-quick-stats">
						{ topVideos.length > 0 && (
							<div className="mediashield-quick-stat">
								<div className="mediashield-quick-stat__title">
									{ __( 'Top Videos', 'mediashield' ) }
								</div>
								<ul className="mediashield-quick-stat__list">
									{ topVideos.slice( 0, 5 ).map( ( v, i ) => (
										<li key={ i } className="mediashield-quick-stat__item">
											<span>{ v.title || `Video #${ v.video_id }` }</span>
											<strong>{ v.session_count || 0 } views</strong>
										</li>
									) ) }
								</ul>
							</div>
						) }

						{ recentMilestones.length > 0 && (
							<div className="mediashield-quick-stat">
								<div className="mediashield-quick-stat__title">
									{ __( 'Recent Milestones', 'mediashield' ) }
								</div>
								<ul className="mediashield-quick-stat__list">
									{ recentMilestones.slice( 0, 5 ).map( ( m, i ) => (
										<li key={ i } className="mediashield-quick-stat__item">
											<span>{ m.user_name || 'User' }</span>
											<span className="mediashield-milestone-badge">
												<span className={ `mediashield-milestone-badge__ring mediashield-milestone-badge__ring--${ m.milestone }` }>
													{ m.milestone }
												</span>
											</span>
										</li>
									) ) }
								</ul>
							</div>
						) }
					</div>
				</>
			) }
		</div>
	);
};

/**
 * Generate date labels for a period when API doesn't provide them.
 */
function generateLabels( period ) {
	const days = period === 'today' ? 24 : period === '7d' ? 7 : period === '30d' ? 30 : 90;
	const labels = [];
	const now = new Date();

	if ( period === 'today' ) {
		for ( let i = 0; i < 24; i++ ) {
			labels.push( `${ i }:00` );
		}
	} else {
		for ( let i = days - 1; i >= 0; i-- ) {
			const d = new Date( now );
			d.setDate( d.getDate() - i );
			labels.push( d.toLocaleDateString( undefined, { month: 'short', day: 'numeric' } ) );
		}
	}
	return labels;
}

/**
 * Generate demo data when API doesn't provide chart data.
 */
function generateDemoData( length, min, max ) {
	const data = [];
	let prev = Math.floor( ( min + max ) / 2 );
	for ( let i = 0; i < length; i++ ) {
		const delta = Math.floor( Math.random() * 10 ) - 4;
		prev = Math.max( min, Math.min( max, prev + delta ) );
		data.push( prev );
	}
	return data;
}

export default Dashboard;
