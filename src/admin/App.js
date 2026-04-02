/**
 * MediaShield Admin SPA – App (hash-based router)
 *
 * @package MediaShield
 */

import { useState, useEffect, useCallback } from '@wordpress/element';
import { applyFilters } from '@wordpress/hooks';
import { SlotFillProvider } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import Sidebar from './components/Sidebar';
import Toast from './components/Toast';
import ErrorBoundary from './components/ErrorBoundary';
import Dashboard from './pages/Dashboard';
import Videos from './pages/Videos';
import Playlists from './pages/Playlists';
import Students from './pages/Students';
import Tags from './pages/Tags';
import Milestones from './pages/Milestones';
import Settings from './pages/Settings';

/**
 * Default route definitions.
 *
 * Pro add-ons can extend via:
 *   wp.hooks.addFilter( 'mediashield_admin_routes', 'mediashield-pro', fn );
 */
const defaultRoutes = [
	{
		hash: '#/dashboard',
		label: __( 'Dashboard', 'mediashield' ),
		icon: 'dashboard',
		component: Dashboard,
	},
	{
		hash: '#/videos',
		label: __( 'Videos', 'mediashield' ),
		icon: 'format-video',
		component: Videos,
	},
	{
		hash: '#/playlists',
		label: __( 'Playlists', 'mediashield' ),
		icon: 'playlist-audio',
		component: Playlists,
	},
	{
		hash: '#/viewers',
		label: __( 'Viewers', 'mediashield' ),
		icon: 'groups',
		component: Students,
	},
	{
		hash: '#/tags',
		label: __( 'Tags', 'mediashield' ),
		icon: 'tag',
		component: Tags,
	},
	{
		hash: '#/milestones',
		label: __( 'Milestones', 'mediashield' ),
		icon: 'flag',
		component: Milestones,
	},
	{
		hash: '#/settings',
		label: __( 'Settings', 'mediashield' ),
		icon: 'admin-generic',
		component: Settings,
	},
];

/**
 * Return the current hash or the default route hash.
 *
 * @return {string} Current location hash.
 */
function getCurrentHash() {
	return window.location.hash || '#/dashboard';
}

const App = () => {
	const [ currentHash, setCurrentHash ] = useState( getCurrentHash );

	const handleHashChange = useCallback( () => {
		setCurrentHash( getCurrentHash() );
	}, [] );

	useEffect( () => {
		window.addEventListener( 'hashchange', handleHashChange );
		return () => {
			window.removeEventListener( 'hashchange', handleHashChange );
		};
	}, [ handleHashChange ] );

	// Allow pro extensions to add / reorder routes.
	const routes = applyFilters( 'mediashield_admin_routes', defaultRoutes );

	const activeRoute = routes.find( ( r ) => r.hash === currentHash );
	const ActiveComponent = activeRoute ? activeRoute.component : Dashboard;

	return (
		<SlotFillProvider>
			<div className="mediashield-admin">
				<Sidebar routes={ routes } currentHash={ currentHash } />
				<main className="mediashield-admin__content">
					<Toast />
					<ErrorBoundary key={ currentHash }>
						<ActiveComponent />
					</ErrorBoundary>
				</main>
			</div>
		</SlotFillProvider>
	);
};

export default App;
