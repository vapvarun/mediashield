<?php
/**
 * [mediashield_playlist] shortcode handler.
 *
 * Usage: [mediashield_playlist id=42]
 * Delegates to {@see \MediaShield\Player\PlaylistRenderer} so the shortcode
 * and the mediashield/playlist Gutenberg block produce identical HTML.
 *
 * @package MediaShield\Block
 */

namespace MediaShield\Block;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use MediaShield\Player\PlaylistRenderer;

class PlaylistShortcode {

	/**
	 * Register hooks.
	 */
	public static function register(): void {
		add_shortcode( 'mediashield_playlist', array( __CLASS__, 'render' ) );
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
			'mediashield_playlist'
		);

		return PlaylistRenderer::render( (int) $atts['id'] );
	}
}
