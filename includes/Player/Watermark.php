<?php
/**
 * Watermark configuration — provides server-side config for the canvas overlay.
 *
 * Free: display_name + IP. Pro hooks mediashield_watermark_config to add
 * email, timestamp, custom text, font size, badge toggle.
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
	 * Get the watermark configuration array for the current user.
	 *
	 * @return array Watermark config for JS.
	 */
	public static function get_config(): array {
		$user = wp_get_current_user();

		return array(
			'enabled'       => Settings::get( 'ms_enabled' ),
			'text'          => $user->ID ? $user->display_name : __( 'Guest', 'mediashield' ),
			'ip'            => self::get_client_ip(),
			'opacity'       => Settings::get( 'ms_watermark_opacity' ),
			'color'         => Settings::get( 'ms_watermark_color' ),
			'swap_interval' => Settings::get( 'ms_watermark_swap_interval' ),
			'show_badge'    => Settings::get( 'ms_show_badge' ),
		);
	}

	/**
	 * Get client IP for watermark display.
	 *
	 * @return string
	 */
	private static function get_client_ip(): string {
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
			if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return $ip;
			}
		}
		return '';
	}
}
