<?php
/**
 * Activation handler.
 *
 * @package MediaShield\Core
 */

namespace MediaShield\Core;

use MediaShield\DB\Schema;

class Activator {

	/**
	 * Run on plugin activation.
	 */
	public static function activate(): void {
		// PHP version check.
		if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
			deactivate_plugins( plugin_basename( MEDIASHIELD_FILE ) );
			wp_die(
				esc_html__( 'MediaShield requires PHP 8.1 or higher.', 'mediashield' ),
				'Plugin Activation Error',
				array( 'back_link' => true )
			);
		}

		// WP version check.
		if ( version_compare( get_bloginfo( 'version' ), '6.5', '<' ) ) {
			deactivate_plugins( plugin_basename( MEDIASHIELD_FILE ) );
			wp_die(
				esc_html__( 'MediaShield requires WordPress 6.5 or higher.', 'mediashield' ),
				'Plugin Activation Error',
				array( 'back_link' => true )
			);
		}

		// Create / update tables.
		Schema::create_tables();
		update_option( 'ms_db_version', MEDIASHIELD_DB_VERSION );

		// Default options.
		$defaults = array(
			'ms_enabled'                 => true,
			'ms_default_protection'      => 'standard',
			'ms_require_login'           => true,
			'ms_watermark_opacity'       => 0.3,
			'ms_watermark_color'         => '#ffffff',
			'ms_watermark_swap_interval' => 20,
			'ms_allowed_domains'         => '',
			'ms_max_concurrent_streams'  => 2,
		);

		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( $key ) ) {
				add_option( $key, $value );
			}
		}

		// Grant upload capability to administrators.
		$admin_role = get_role( 'administrator' );
		if ( $admin_role ) {
			$admin_role->add_cap( 'upload_mediashield' );
		}

		// Flag for setup wizard redirect.
		if ( ! get_option( 'ms_wizard_completed' ) ) {
			set_transient( 'ms_activation_redirect', true, 30 );
		}

		// Flush rewrite rules for new CPTs.
		flush_rewrite_rules();
	}
}
