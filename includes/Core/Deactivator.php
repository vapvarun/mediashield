<?php
/**
 * Deactivation handler.
 *
 * @package MediaShield\Core
 */

namespace MediaShield\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Deactivator
 *
 * Handles plugin deactivation tasks.
 *
 * @since 1.0.0
 */
class Deactivator {

	/**
	 * Run on plugin deactivation.
	 */
	public static function deactivate(): void {
		flush_rewrite_rules();
	}
}
