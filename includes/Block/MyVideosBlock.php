<?php
/**
 * Register the mediashield/my-videos Gutenberg block and shortcode.
 *
 * @package MediaShield\Block
 */

namespace MediaShield\Block;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class MyVideosBlock
 *
 * Registers the mediashield/my-videos Gutenberg block and shortcode.
 *
 * @since 1.0.0
 */
class MyVideosBlock {

	/**
	 * Register hooks.
	 */
	public static function register(): void {
		add_action( 'init', array( __CLASS__, 'register_block' ) );
		add_shortcode( 'mediashield_my_videos', array( __CLASS__, 'shortcode_render' ) );
	}

	/**
	 * Register the block type from block.json.
	 */
	public static function register_block(): void {
		// Register from build/ directory (compiled assets).
		$build_dir = MEDIASHIELD_PATH . 'build/blocks/my-videos';

		// Fall back to src/ if build doesn't exist (dev mode).
		if ( ! file_exists( $build_dir . '/block.json' ) ) {
			$build_dir = MEDIASHIELD_PATH . 'src/blocks/my-videos';
		}

		if ( file_exists( $build_dir . '/block.json' ) ) {
			register_block_type( $build_dir );
		}
	}

	/**
	 * Shortcode callback — renders the same output as the block.
	 *
	 * @param array|string $atts Shortcode attributes (unused).
	 * @return string Rendered HTML.
	 */
	public static function shortcode_render( $atts = array() ): string {
		$render_file = MEDIASHIELD_PATH . 'src/blocks/my-videos/render.php';

		if ( ! file_exists( $render_file ) ) {
			return '';
		}

		ob_start();
		include $render_file;
		return ob_get_clean();
	}
}
