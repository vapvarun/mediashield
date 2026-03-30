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

class Protection {

	/**
	 * Get the protection configuration for JS.
	 *
	 * @return array Protection config.
	 */
	public static function get_config(): array {
		return array(
			'block_right_click' => true,
			'block_keyboard'    => true, // Ctrl+S / Cmd+S
			'hide_source'       => true, // Move src to data-ms-src
		);
	}
}
