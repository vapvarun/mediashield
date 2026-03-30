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
		// Gutenberg video block (editor).
		'blocks/video/index': path.resolve( __dirname, 'src/blocks/video/index.js' ),
		// Gutenberg video block (frontend view — Interactivity API).
		'blocks/video/view': path.resolve( __dirname, 'src/blocks/video/view.js' ),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'build' ),
	},
};
