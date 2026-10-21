<?php
/**
 * Watermark configuration — provides server-side config for the canvas overlay.
 *
 * Free: display_name + IP, supplied per viewer by /session/start. Pro hooks
 * mediashield_watermark_config to add email, timestamp, custom text, font size,
 * badge toggle.
 *
 * @package MediaShield\Player
 */

namespace MediaShield\Player;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use MediaShield\Core\Settings;

/**
 * Class Watermark
 *
 * Watermark configuration for the canvas overlay.
 *
 * @since 1.0.0
 */
class Watermark {

	/**
	 * Get the site-wide watermark configuration for the page.
	 *
	 * Nothing here may name a viewer. This array is printed into the page HTML,
	 * and a page cache would serve the first visitor's name and IP to everyone
	 * after. The viewer's own text and IP come from /session/start.
	 *
	 * @return array Watermark config for JS.
	 */
	public static function get_config(): array {
		return array(
			'enabled'       => Settings::get( 'ms_enabled' ),
			'opacity'       => Settings::get( 'ms_watermark_opacity' ),
			'color'         => Settings::get( 'ms_watermark_color' ),
			'swap_interval' => Settings::get( 'ms_watermark_swap_interval' ),
			'show_badge'    => Settings::get( 'ms_show_badge' ),
		);
	}
}
