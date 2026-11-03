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
	 * Shortcode callback: what does [mediashield_my_videos] print?
	 *
	 * Exactly what the block prints. It renders the registered block, so the
	 * shortcode shares the block's render.php from build/ (src/ is not in the
	 * release zip, which is why reading the file directly printed nothing),
	 * its wrapper class and its view script.
	 *
	 * @param array|string $atts Shortcode attributes (unused).
	 * @return string Rendered HTML.
	 */
	public static function shortcode_render( $atts = array() ): string {
		return render_block(
			array(
				'blockName'    => 'mediashield/my-videos',
				'attrs'        => array(),
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);
	}
}
