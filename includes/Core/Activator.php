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

		// Default options — plug & play experience.
		$defaults = array(
			// Core.
			'ms_enabled'                 => true,
			'ms_default_protection'      => 'standard',
			'ms_require_login'           => true,

			// Watermark.
			'ms_watermark_opacity'       => 0.5,
			'ms_watermark_color'         => '#ffffff',
			'ms_watermark_swap_interval' => 30,

			// Access.
			'ms_allowed_domains'         => '',
			'ms_max_concurrent_streams'  => 2,

			// Upload.
			'ms_max_upload_size'         => 500,
			'ms_custom_url_patterns'     => '',

			// Badge.
			'ms_show_badge'              => true,

			// Login & Access Messages.
			'ms_login_overlay_text'      => __( 'Please log in to watch this video', 'mediashield' ),
			'ms_login_button_text'       => __( 'Log In', 'mediashield' ),
			'ms_access_denied_text'      => __( 'You do not have access to this video', 'mediashield' ),

			// Player controls.
			'ms_player_speed_control'    => true,
			'ms_player_sticky'           => false,
			'ms_player_keyboard'         => true,
			'ms_player_resume'           => true,
			'ms_player_endscreen'        => false,
			'ms_player_endscreen_text'   => '',
			'ms_player_endscreen_url'    => '',
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
