<?php
/**
 * Deactivation handler.
 *
 * @package MediaShield\Core
 */

namespace MediaShield\Core;

class Deactivator {

	/**
	 * Run on plugin deactivation.
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();
	}
}
