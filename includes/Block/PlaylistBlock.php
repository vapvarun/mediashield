<?php
/**
 * Register the mediashield/playlist Gutenberg block.
 *
 * @package MediaShield\Block
 */

namespace MediaShield\Block;

class PlaylistBlock {

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
		$build_dir = MEDIASHIELD_PATH . 'build/blocks/playlist';

		if ( ! file_exists( $build_dir . '/block.json' ) ) {
			$build_dir = MEDIASHIELD_PATH . 'src/blocks/playlist';
		}

		if ( file_exists( $build_dir . '/block.json' ) ) {
			register_block_type( $build_dir );
		}
	}
}
