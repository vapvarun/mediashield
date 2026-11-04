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

		// Create / update tables and run any pending upgrade steps. The plugin is
		// loaded after plugins_loaded on the activation request, so an update by
		// deactivate, replace, activate never reaches the bootstrap's Migrator
		// run; writing the version here instead skipped every step between.
		Migrator::run();

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

		// Register the CPTs before flushing: `init` has already run on the
		// activation request, so without this the saved rules have no
		// /playlist/ and playlist pages 404 until permalinks are saved.
		\MediaShield\CPT\VideoPostType::register_post_type();
		\MediaShield\CPT\PlaylistPostType::register_post_type();
		flush_rewrite_rules();
	}
}
