<?php
/**
 * Register the mediashield/video Gutenberg block.
 *
 * @package MediaShield\Block
 */

namespace MediaShield\Block;

class VideoBlock {

	/**
	 * Register hooks.
	 */
	public static function register(): void {
		add_action( 'init', array( __CLASS__, 'register_block' ) );
	}

	/**
	 * Register the block type from block.json.
	 */
	public static function register_block(): void {
		// Register from build/ directory (compiled assets).
		$build_dir = MEDIASHIELD_PATH . 'build/blocks/video';

		// Fall back to src/ if build doesn't exist (dev mode).
		if ( ! file_exists( $build_dir . '/block.json' ) ) {
			$build_dir = MEDIASHIELD_PATH . 'src/blocks/video';
		}

		if ( file_exists( $build_dir . '/block.json' ) ) {
			register_block_type( $build_dir );
		}
	}
}
