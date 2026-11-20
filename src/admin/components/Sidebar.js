/**
 * MediaShield Admin -- Sidebar Navigation
 *
 * @package MediaShield
 */

import { __ } from '@wordpress/i18n';
import Icon from './Icon';

const config = window.mediashieldAdmin || {};

/**
 * The six top menu items and the screens under each (Basecamp 10530930846).
 *
 * Routes keep their own hashes, so every old link, bookmark and doc still
 * opens; only the menu groups them. A route this table does not name (a
 * third-party screen added through `mediashield_admin_routes`) joins the
 * group named in its `group` key, or Settings.
 */
const GROUPS = [
	{ key: 'dashboard', label: __( 'Dashboard', 'mediashield' ), icon: 'dashboard', hashes: [ '#/dashboard' ] },
	{ key: 'videos', label: __( 'Videos', 'mediashield' ), icon: 'format-video', hashes: [ '#/videos', '#/playlists', '#/tags', '#/upload-queue' ] },
	{ key: 'analytics', label: __( 'Analytics', 'mediashield' ), icon: 'chart-pie', hashes: [ '#/viewers', '#/heatmap', '#/realtime', '#/milestones', '#/export' ] },
	{ key: 'security', label: __( 'Security', 'mediashield' ), icon: 'warning', hashes: [ '#/alerts' ] },
	{ key: 'platforms', label: __( 'Platforms', 'mediashield' ), icon: 'cloud', hashes: [ '#/platforms', '#/bunny-browser', '#/youtube-browser', '#/vimeo-browser', '#/wistia-browser' ] },
	{ key: 'settings', label: __( 'Settings', 'mediashield' ), icon: 'admin-generic', hashes: [ '#/settings', '#/license', '#/milestone-config' ] },
];

/**
 * Pro screens shown locked, in their group, when Pro is not active.
 */
const LOCKED_PRO_ITEMS = [
	{ group: 'platforms', label: __( 'Platforms', 'mediashield' ), icon: 'cloud' },
	{ group: 'analytics', label: __( 'Heatmaps', 'mediashield' ), icon: 'chart-area' },
	{ group: 'analytics', label: __( 'Realtime', 'mediashield' ), icon: 'controls-play' },
	{ group: 'analytics', label: __( 'Export', 'mediashield' ), icon: 'download' },
	{ group: 'security', label: __( 'Alerts', 'mediashield' ), icon: 'warning' },
];

const UPSELL_URL = 'https://wbcomdesigns.com/downloads/mediashield-pro/';

/**
 * Which group a route belongs to; a route not listed in GROUPS sits under Settings.
 *
 * @param {Object} route Route definition.
 * @return {string} Group key.
 */
const groupOf = ( route ) => {
	const found = GROUPS.find( ( g ) => g.hashes.includes( route.hash ) );
	return found ? found.key : 'settings';
};

const LockedLink = ( { item, className } ) => (
	<a
		href={ UPSELL_URL }
		target="_blank"
		rel="noopener noreferrer"
		className={ className + ' mediashield-sidebar__item--locked' }
		title={ __( 'Upgrade to MediaShield Pro', 'mediashield' ) }
	>
		<Icon name={ item.icon } />
		<span className="mediashield-sidebar__label">{ item.label }</span>
		<span className="mediashield-sidebar__pro-badge">{ __( 'PRO', 'mediashield' ) }</span>
	</a>
);

const Sidebar = ( { routes, currentHash } ) => {
	const isProActive = !! config.isProActive;

	const groups = GROUPS.map( ( group ) => {
		// Every route of the group, the hidden ones too (platform browsers),
		// so the group stays highlighted on them.
		const all = routes.filter( ( r ) => groupOf( r ) === group.key );
		const children = all.filter( ( r ) => ! r.hidden );
		// The group's own screen first (Videos, Settings), then the others
		// in the table's order.
		children.sort( ( a, b ) => {
			const ia = group.hashes.indexOf( a.hash );
			const ib = group.hashes.indexOf( b.hash );
			return ( -1 === ia ? 99 : ia ) - ( -1 === ib ? 99 : ib );
		} );
		const locked = isProActive ? [] : LOCKED_PRO_ITEMS.filter( ( i ) => i.group === group.key );
		return {
			...group,
			children,
			locked,
			active: all.some( ( r ) => r.hash === currentHash ),
		};
	} ).filter( ( g ) => g.children.length || g.locked.length );

	return (
		<nav className="mediashield-sidebar" aria-label={ __( 'Admin navigation', 'mediashield' ) }>
			<div className="mediashield-sidebar__brand">
				<span className="ms-brand-icon">
					<Icon name="shield" />
				</span>
				<strong>{ __( 'MediaShield', 'mediashield' ) }</strong>
				<span className="mediashield-sidebar__version">
					{ config.version || 'v1.0' }
				</span>
			</div>
			<ul className="mediashield-sidebar__menu">
				{ groups.map( ( group ) => {
					const first = group.children[ 0 ];
					const showSub = group.active && ( group.children.length > 1 || ( group.children.length > 0 && group.locked.length > 0 ) );
					return (
						<li key={ group.key } className="mediashield-sidebar__group">
							{ first ? (
								<a
									href={ first.hash }
									className={ 'mediashield-sidebar__item' + ( group.active ? ( showSub ? ' is-open' : ' is-active' ) : '' ) }
									aria-current={ group.active && ! showSub ? 'page' : undefined }
									title={ group.label }
								>
									<Icon name={ group.icon } />
									<span className="mediashield-sidebar__label">{ group.label }</span>
								</a>
							) : (
								<LockedLink item={ { ...group.locked[ 0 ], label: group.label, icon: group.icon } } className="mediashield-sidebar__item" />
							) }
							{ showSub && (
								<ul className="mediashield-sidebar__sub" aria-label={ group.label }>
									{ group.children.map( ( route ) => {
										const isActive = currentHash === route.hash;
										return (
											<li key={ route.hash }>
												<a
													href={ route.hash }
													className={ 'mediashield-sidebar__item mediashield-sidebar__subitem' + ( isActive ? ' is-active' : '' ) }
													aria-current={ isActive ? 'page' : undefined }
													title={ route.label }
												>
													<Icon name={ route.icon } />
													<span className="mediashield-sidebar__label">{ route.label }</span>
												</a>
											</li>
										);
									} ) }
									{ group.locked.map( ( item ) => (
										<li key={ `locked-${ item.label }` }>
											<LockedLink item={ item } className="mediashield-sidebar__item mediashield-sidebar__subitem" />
										</li>
									) ) }
								</ul>
							) }
						</li>
					);
				} ) }
			</ul>
		</nav>
	);
};

export default Sidebar;
