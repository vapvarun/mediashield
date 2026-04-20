<?php
/**
 * Anti-download protection measures.
 *
 * Adds right-click blocking, source hiding, download-prevention attributes,
 * and client-side DevTools detection to protected video players.
 *
 * @package MediaShield\Player
 */

namespace MediaShield\Player;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Protection
 *
 * Anti-download protection measures for video players.
 *
 * @since 1.0.0
 */
class Protection {

	/**
	 * Get the protection configuration for JS.
	 *
	 * Values come from wp_options with sensible defaults; the result is passed
	 * through `mediashield_protection_config` for filter-based overrides.
	 *
	 * @return array Protection config.
	 */
	public static function get_config(): array {
		$config = array(
			'block_right_click'  => (bool) get_option( 'ms_block_right_click', true ),
			'block_keyboard'     => (bool) get_option( 'ms_block_keyboard', true ),
			'hide_source'        => (bool) get_option( 'ms_hide_source', true ),
			'detect_devtools'    => (bool) get_option( 'ms_detect_devtools', true ),
			'pause_on_devtools'  => (bool) get_option( 'ms_pause_on_devtools', false ),
			'devtools_title'     => (string) get_option( 'ms_devtools_title', __( 'Developer Tools Detected', 'mediashield' ) ),
			'devtools_message'   => (string) get_option( 'ms_devtools_message', __( 'Please close developer tools to continue watching this video.', 'mediashield' ) ),
		);

		/**
		 * Filter the protection configuration sent to the frontend.
		 *
		 * @since 1.1.0
		 *
		 * @param array $config Protection config.
		 */
		return apply_filters( 'mediashield_protection_config', $config );
	}
}
