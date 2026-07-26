<?php
/**
 * Activation handler.
 *
 * @package MediaShield\Core
 */

namespace MediaShield\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use MediaShield\DB\Schema;

/**
 * Class Activator
 *
 * Handles plugin activation tasks.
 *
 * @since 1.0.0
 */
class Activator {

	/**
	 * Run on plugin activation.
	 */
	public static function activate(): void {
		// Activation runs before `init`, so these requirement messages are kept
		// as plain strings: translating the `mediashield` text domain this early
		// trips WP 6.7's `_load_textdomain_just_in_time` notice. Same reason the
		// Settings schema avoids __() in its defaults (see Settings::schema()).
		// PHP version check.
		if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
			deactivate_plugins( plugin_basename( MEDIASHIELD_FILE ) );
			wp_die(
				'MediaShield requires PHP 8.1 or higher.',
				'Plugin Activation Error',
				array( 'back_link' => true )
			);
		}

		// WP version check.
		if ( version_compare( get_bloginfo( 'version' ), '6.5', '<' ) ) {
			deactivate_plugins( plugin_basename( MEDIASHIELD_FILE ) );
			wp_die(
				'MediaShield requires WordPress 6.5 or higher.',
				'Plugin Activation Error',
				array( 'back_link' => true )
			);
		}

		// Create / update tables.
		Schema::create_tables();
		update_option( 'ms_db_version', MEDIASHIELD_DB_VERSION );

		// Seed defaults for every option declared in the Settings schema.
		Settings::seed_defaults();

		// Grant upload capability to administrators.
		$admin_role = get_role( 'administrator' );
		if ( $admin_role ) {
			$admin_role->add_cap( 'upload_mediashield' );
		}

		// Track activation time for delayed Pro upsell notice.
		if ( false === get_option( 'ms_activated_at' ) ) {
			add_option( 'ms_activated_at', time() );
		}

		// Flag for setup wizard redirect.
		if ( ! get_option( 'ms_wizard_completed' ) ) {
			set_transient( 'ms_activation_redirect', true, 30 );
		}

		// Flush rewrite rules for new CPTs.
		flush_rewrite_rules();
	}
}
