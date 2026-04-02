<?php
/**
 * Anti-download protection measures.
 *
 * Adds right-click blocking, source hiding, and download prevention
 * attributes to protected video players.
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
	 * @return array Protection config.
	 */
	public static function get_config(): array {
		$config = array(
			'block_right_click' => true,
			'block_keyboard'    => true, // Ctrl+S / Cmd+S.
			'hide_source'       => true, // Move src to data-ms-src.
		);

		/**
		 * Filter the protection configuration sent to the frontend.
		 *
		 * @since 1.1.0
		 *
		 * @param array $config Protection config (block_right_click, block_keyboard, hide_source).
		 */
		return apply_filters( 'mediashield_protection_config', $config );
	}
}
