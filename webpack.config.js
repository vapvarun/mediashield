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
		'blocks/my-videos/view': path.resolve( __dirname, 'src/blocks/my-videos/view.js' ),
		// Admin SPA.
		'admin/index': path.resolve( __dirname, 'src/admin/index.js' ),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'build' ),
	},
};
