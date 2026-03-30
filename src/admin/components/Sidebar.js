/**
 * MediaShield Admin – Sidebar Navigation
 *
 * @package MediaShield
 */

import { NavigableMenu } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Sidebar component.
 *
 * @param {Object}   props             Component props.
 * @param {Array}    props.routes      Route definitions.
 * @param {string}   props.currentHash Current location hash.
 * @return {JSX.Element} Sidebar element.
 */
const Sidebar = ( { routes, currentHash } ) => {
	return (
		<nav className="mediashield-sidebar" aria-label={ __( 'Admin navigation', 'mediashield' ) }>
			<div className="mediashield-sidebar__brand">
				<strong>{ __( 'MediaShield', 'mediashield' ) }</strong>
			</div>
			<NavigableMenu orientation="vertical" className="mediashield-sidebar__menu">
				{ routes.map( ( route ) => {
					const isActive = currentHash === route.hash;
					return (
						<a
							key={ route.hash }
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
					);
				} ) }
			</NavigableMenu>
		</nav>
	);
};

export default Sidebar;
