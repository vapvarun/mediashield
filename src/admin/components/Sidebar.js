/**
 * MediaShield Admin -- Sidebar Navigation
 *
 * @package MediaShield
 */

import { __ } from '@wordpress/i18n';

const config = window.mediashieldAdmin || {};

const Sidebar = ( { routes, currentHash } ) => {
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
				{ routes.map( ( route ) => {
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
			</ul>
		</nav>
	);
};

export default Sidebar;
