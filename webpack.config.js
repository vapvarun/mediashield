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
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'build' ),
	},
};
