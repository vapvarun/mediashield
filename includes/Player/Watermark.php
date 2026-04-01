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

		$config = array(
			'enabled'       => (bool) get_option( 'ms_enabled', true ),
			'text'          => $user->ID ? $user->display_name : __( 'Guest', 'mediashield' ),
			'ip'            => self::get_client_ip(),
			'opacity'       => (float) get_option( 'ms_watermark_opacity', 0.3 ),
			'color'         => get_option( 'ms_watermark_color', '#ffffff' ),
			'swap_interval' => (int) get_option( 'ms_watermark_swap_interval', 20 ),
			'show_badge'    => (bool) get_option( 'ms_show_badge', true ),
		);

		// Force badge visible if pro not active (free always shows badge).
		if ( ! defined( 'MEDIASHIELD_PRO_VERSION' ) ) {
			$config['show_badge'] = true;
		}

		return $config;
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
