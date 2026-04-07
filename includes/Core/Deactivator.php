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
		wp_clear_scheduled_hook( 'ms_cleanup_inactive_sessions' );
		wp_clear_scheduled_hook( 'ms_archive_old_sessions' );

		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( 'ms_cleanup_inactive_sessions' );
			as_unschedule_all_actions( 'ms_archive_old_sessions' );
		}

		flush_rewrite_rules();
	}
}
