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
				'id'             => 0,
				// The % of this video a lesson asks the member to watch,
				// passed by the LMS that places the player (Learnomy 2.4.1+).
				// Only a hint for the player's early report; completion is
				// still decided on the server from the watched share.
				'watch_required' => 0,
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

		// A filtered link is for this render only: Renderer reads it through
		// get_post_meta(), so it is answered there and never stored. Writing
		// it to the video and back on every view also counted as a new
		// source and forgot the lengths players measured.
		$override = null;
		if ( ! empty( $filtered_source_url ) && $video_id > 0 ) {
			$override = static function ( $value, $object_id, $meta_key ) use ( $video_id, $filtered_source_url ) {
				if ( (int) $object_id !== $video_id || '_ms_source_url' !== $meta_key ) {
					return $value;
				}
				return array( (string) $filtered_source_url );
			};
			add_filter( 'get_post_metadata', $override, 10, 3 );
		}

		// Renderer validates the video CPT + source and enqueues frontend assets
		// only when output will be produced — calling enqueue() here would leak
		// JS/CSS for invalid or non-existent IDs.
		$required = min( 100, absint( $atts['watch_required'] ) );
		try {
			$html = Renderer::render( $video_id, $required > 0 ? sprintf( ' data-watch-required="%d"', $required ) : '' );
		} finally {
			if ( $override ) {
				remove_filter( 'get_post_metadata', $override, 10 );
			}
		}

		return $html;
	}
}
