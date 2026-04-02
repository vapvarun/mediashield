<?php
/**
 * [mediashield] shortcode handler.
 *
 * Usage: [mediashield id=123]
 * Delegates rendering to Player\Renderer for API-compatible player output.
 *
 * @package MediaShield\Block
 */

namespace MediaShield\Block;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use MediaShield\Core\Assets;
use MediaShield\Player\Renderer;

/**
 * Class Shortcode
 *
 * Handles the [mediashield] shortcode rendering.
 *
 * @since 1.0.0
 */
class Shortcode {

	/**
	 * Register hooks.
	 */
	public static function register(): void {
		add_shortcode( 'mediashield', array( __CLASS__, 'render' ) );
	}

	/**
	 * Render the shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string HTML output.
	 */
	public static function render( $atts ): string {
		$atts = shortcode_atts(
			array(
				'id' => 0,
			),
			$atts,
			'mediashield'
		);

		$video_id = (int) $atts['id'];

		/**
		 * Filter the video source URL before rendering.
		 *
		 * @since 1.1.0
		 *
		 * @param string $source_url The source URL (empty string to use default from meta).
		 * @param int    $video_id   Video CPT post ID.
		 * @param array  $atts       Shortcode attributes.
		 */
		$filtered_source_url = apply_filters( 'mediashield_shortcode_source_url', '', $video_id, $atts );

		// If a filter provided a custom source URL, temporarily set the post meta
		// so the Renderer picks it up. Restore after rendering.
		$restore_source_url = false;
		if ( ! empty( $filtered_source_url ) && $video_id > 0 ) {
			$original_source_url = get_post_meta( $video_id, '_ms_source_url', true );
			update_post_meta( $video_id, '_ms_source_url', $filtered_source_url );
			$restore_source_url = true;
		}

		Assets::enqueue();

		$html = Renderer::render( $video_id );

		// Restore original source URL if we temporarily overrode it.
		if ( $restore_source_url ) {
			update_post_meta( $video_id, '_ms_source_url', $original_source_url );
		}

		return $html;
	}
}
