/**
 * MediaShield Admin SPA – App (hash-based router)
 *
 * @package MediaShield
 */

import { useState, useEffect, useCallback, lazy, Suspense } from '@wordpress/element';
import { applyFilters } from '@wordpress/hooks';
import { SlotFillProvider } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

import Sidebar from './components/Sidebar';
import Toast from './components/Toast';
import ErrorBoundary from './components/ErrorBoundary';
import Loading from './components/Loading';

// Each screen loads when it is opened, so the admin entry stays small
// (release gate P2). Their CSS is imported by index.js, so it stays in
// index.css and its RTL twin.
const Dashboard = lazy( () => import( /* webpackChunkName: "dashboard" */ './pages/Dashboard' ) );
const Videos = lazy( () => import( /* webpackChunkName: "videos" */ './pages/Videos' ) );
const Playlists = lazy( () => import( /* webpackChunkName: "playlists" */ './pages/Playlists' ) );
const Students = lazy( () => import( /* webpackChunkName: "viewers" */ './pages/Students' ) );
const Tags = lazy( () => import( /* webpackChunkName: "tags" */ './pages/Tags' ) );
const Milestones = lazy( () => import( /* webpackChunkName: "milestones" */ './pages/Milestones' ) );
const Settings = lazy( () => import( /* webpackChunkName: "settings" */ './pages/Settings' ) );

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
 * Map of legacy hash aliases → current route hash. Keeps older bookmarks,
 * support-doc URLs, and external links working when a route gets renamed.
 *
 * @type {Object<string,string>}
 */
const HASH_ALIASES = {
	'#/students': '#/viewers', // Sidebar label changed Students → Viewers
};

/**
 * Return the current hash, resolving any legacy aliases, falling back to
 * the default route when nothing is set.
 *
 * @return {string} Current location hash.
 */
function getCurrentHash() {
	const raw = window.location.hash || '#/dashboard';
	const resolved = HASH_ALIASES[ raw ] || raw;
	if ( resolved !== raw ) {
		// Rewrite the URL to the canonical hash so the active-state in the
		// sidebar matches and the user sees the right URL after reload.
		window.history.replaceState( null, '', resolved );
	}
	return resolved;
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

	// A route also answers its own sub-paths, so a page can deep-link inside
	// itself: #/settings/integrations opens Settings at that section. The
	// exact hash wins first.
	const activeRoute = routes.find( ( r ) => r.hash === currentHash ) ||
		routes.find( ( r ) => currentHash.startsWith( r.hash + '/' ) );
	const ActiveComponent = activeRoute ? activeRoute.component : Dashboard;
	// Moving between sub-paths of one route keeps the page mounted.
	const routeHash = activeRoute ? activeRoute.hash : currentHash;

	return (
		<SlotFillProvider>
			<div className="mediashield-admin">
				<Sidebar routes={ routes } currentHash={ routeHash } />
				<main className="mediashield-admin__content">
					<Toast />
					<ErrorBoundary key={ routeHash }>
						<Suspense fallback={ <Loading /> }>
							<ActiveComponent />
						</Suspense>
					</ErrorBoundary>
				</main>
			</div>
		</SlotFillProvider>
	);
};

export default App;
