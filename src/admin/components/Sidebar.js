/**
 * MediaShield Admin -- Sidebar Navigation
 *
 * @package MediaShield
 */

import { __ } from '@wordpress/i18n';

const config = window.mediashieldAdmin || {};

/**
 * Locked Pro menu items shown when Pro is not active.
 * These appear grayed-out with a PRO badge to indicate upgrade availability.
 */
const LOCKED_PRO_ITEMS = [
	{ label: __( 'Platforms', 'mediashield' ), icon: 'cloud' },
	{ label: __( 'DRM', 'mediashield' ), icon: 'lock' },
	{ label: __( 'Heatmaps', 'mediashield' ), icon: 'chart-area' },
	{ label: __( 'Alerts', 'mediashield' ), icon: 'warning' },
	{ label: __( 'Export', 'mediashield' ), icon: 'download' },
	{ label: __( 'Realtime', 'mediashield' ), icon: 'controls-play' },
];

const UPSELL_URL = 'https://wbcomdesigns.com/downloads/mediashield-pro/';

const Sidebar = ( { routes, currentHash } ) => {
	const isProActive = !! config.isProActive;

	// Separate Settings from other routes. Hide routes marked hidden (browser pages).
	const settingsRoute = routes.find( ( r ) => r.hash === '#/settings' );
	const otherRoutes = routes.filter( ( r ) => r.hash !== '#/settings' && ! r.hidden );

	return (
		<nav className="mediashield-sidebar" aria-label={ __( 'Admin navigation', 'mediashield' ) }>
			<div className="mediashield-sidebar__brand">
				<span className="ms-brand-icon">
					<span className="dashicons dashicons-shield" aria-hidden="true" />
				</span>
				<strong>{ __( 'MediaShield', 'mediashield' ) }</strong>
				<span className="mediashield-sidebar__version">
					{ config.version || 'v1.0' }
				</span>
			</div>
			<ul className="mediashield-sidebar__menu">
				{ otherRoutes.map( ( route ) => {
					const isActive = currentHash === route.hash;
					return (
						<li key={ route.hash } style={ { listStyle: 'none', margin: 0, padding: 0 } }>
							<a
								href={ route.hash }
								className={
									'mediashield-sidebar__item' +
									( isActive ? ' is-active' : '' )
								}
								aria-current={ isActive ? 'page' : undefined }
							>
								<span className={ `dashicons dashicons-${ route.icon }` } aria-hidden="true" />
								<span className="mediashield-sidebar__label">
									{ route.label }
								</span>
							</a>
						</li>
					);
				} ) }

				{ /* Locked Pro items -- only shown when Pro is NOT active */ }
				{ ! isProActive && LOCKED_PRO_ITEMS.map( ( item ) => (
					<li key={ `locked-${ item.icon }` } style={ { listStyle: 'none', margin: 0, padding: 0 } }>
						<a
							href={ UPSELL_URL }
							target="_blank"
							rel="noopener noreferrer"
							className="mediashield-sidebar__item mediashield-sidebar__item--locked"
							title={ __( 'Upgrade to MediaShield Pro', 'mediashield' ) }
						>
							<span className={ `dashicons dashicons-${ item.icon }` } aria-hidden="true" />
							<span className="mediashield-sidebar__label">
								{ item.label }
							</span>
							<span className="mediashield-sidebar__pro-badge">
								{ __( 'PRO', 'mediashield' ) }
							</span>
						</a>
					</li>
				) ) }

				{ /* Settings always last */ }
				{ settingsRoute && (
					<li style={ { listStyle: 'none', margin: 0, padding: 0 } }>
						<a
							href={ settingsRoute.hash }
							className={
								'mediashield-sidebar__item' +
								( currentHash === settingsRoute.hash ? ' is-active' : '' )
							}
							aria-current={ currentHash === settingsRoute.hash ? 'page' : undefined }
						>
							<span className={ `dashicons dashicons-${ settingsRoute.icon }` } aria-hidden="true" />
							<span className="mediashield-sidebar__label">
								{ settingsRoute.label }
							</span>
						</a>
					</li>
				) }
			</ul>
		</nav>
	);
};

export default Sidebar;
