<?php
/**
 * Watermark configuration — provides server-side config for the canvas overlay.
 *
 * Free: the viewer's name, supplied per viewer by /session/start; the email
 * (ms_watermark_show_email) and IP address (ms_watermark_show_ip) only when
 * the owner turns them on. Pro
 * hooks mediashield_watermark_config to choose the fields, font size and badge.
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
			'edges_only'    => Settings::get( 'ms_watermark_edges_only' ),
			'show_badge'    => Settings::get( 'ms_show_badge' ),
		);
	}

	/**
	 * The words that name a viewer in Free's watermark: the name, and the email when shown.
	 *
	 * WordPress stores the display name HTML-escaped ("Tom &amp; Jerry") and the
	 * canvas paints text as is, so it is decoded (card 10497708826). A guest
	 * has neither, so reads "Guest". The IP is separate (`ip` in the config)
	 * and opt-in. Pro replaces this text from its Watermark Fields.
	 *
	 * @since 1.4.3
	 *
	 * @param \WP_User $user Viewer; a WP_User with ID 0 for a guest.
	 * @return string
	 */
	public static function viewer_text( \WP_User $user ): string {
		if ( ! $user->exists() ) {
			return __( 'Guest', 'mediashield' );
		}
		$email = Settings::get( 'ms_watermark_show_email' ) ? (string) $user->user_email : '';
		return implode( " \u{00B7} ", array_filter( array( \MediaShield\Support\Text::plain( $user->display_name ), $email ) ) );
	}
}
