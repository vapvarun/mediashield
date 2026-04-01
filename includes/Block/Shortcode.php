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
				'id' => 0,
			),
			$atts,
			'mediashield'
		);

		return Renderer::render( (int) $atts['id'] );
	}
}
