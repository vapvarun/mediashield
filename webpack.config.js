/**
 * MediaShield webpack configuration.
 *
 * Extends @wordpress/scripts default config with custom entry points.
 */
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const path = require( 'path' );

module.exports = {
	...defaultConfig,
	entry: {
		// Gutenberg video block.
		'blocks/video/index': path.resolve( __dirname, 'src/blocks/video/index.js' ),
		'blocks/video/view': path.resolve( __dirname, 'src/blocks/video/view.js' ),
		// Gutenberg playlist block.
		'blocks/playlist/index': path.resolve( __dirname, 'src/blocks/playlist/index.js' ),
		'blocks/playlist/view': path.resolve( __dirname, 'src/blocks/playlist/view.js' ),
		// Gutenberg my-videos block (dynamic — render.php). Without an
		// index.js entry that calls registerBlockType client-side,
		// Gutenberg shows "block support missing" in the editor.
		'blocks/my-videos/index': path.resolve( __dirname, 'src/blocks/my-videos/index.js' ),
		// Admin SPA.
		'admin/index': path.resolve( __dirname, 'src/admin/index.js' ),
	},
	optimization: {
		...defaultConfig.optimization,
		splitChunks: {
			...defaultConfig.optimization.splitChunks,
			cacheGroups: {
				...defaultConfig.optimization.splitChunks.cacheGroups,
				// Chart.js (the Dashboard chart) in its own named chunk, loaded
				// only with the Dashboard; listed in .gate-check-allow (P2).
				chartjs: {
					test: /[\\/]node_modules[\\/](chart\.js|@kurkle)[\\/]/,
					name: 'chartjs',
					chunks: 'async',
					enforce: true,
				},
			},
		},
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'build' ),
		// Admin screens load on demand (React.lazy in src/admin/App.js). The
		// content hash in the name means a browser never reuses a chunk from
		// an older version (the entry script itself is versioned by its
		// asset hash). Release gate P2: the entry stays under 40 KB gzipped.
		chunkFilename: 'admin/chunks/[name].[contenthash:8].js',
	},
};
