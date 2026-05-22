<?php
/**
 * Plugin Name: MediaShield
 * Plugin URI:  https://wbcomdesigns.com/mediashield
 * Description: Video protection for WordPress — dynamic watermarking, multi-platform support, engagement analytics, and milestone automation.
 * Version:     1.0.0
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Author:      Wbcom Designs
 * Author URI:  https://wbcomdesigns.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: mediashield
 * Domain Path: /languages
 *
 * @package MediaShield
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'MEDIASHIELD_VERSION', '1.0.0' );
define( 'MEDIASHIELD_DB_VERSION', 3 );
define( 'MEDIASHIELD_FILE', __FILE__ );
define( 'MEDIASHIELD_PATH', plugin_dir_path( __FILE__ ) );
define( 'MEDIASHIELD_URL', plugin_dir_url( __FILE__ ) );

// Composer autoloader. Without it, none of the MediaShield\ classes can load —
// including the Activator referenced by the activation hook below. Bail early
// with a clear admin notice instead of fatally erroring on activation when the
// vendor directory is missing (e.g. an incomplete install or a bad build).
if ( ! file_exists( MEDIASHIELD_PATH . 'vendor/autoload.php' ) ) {
	add_action(
		'admin_notices',
		function () {
			echo '<div class="notice notice-error"><p>';
			echo esc_html__( 'MediaShield is missing its Composer dependencies (vendor/autoload.php). Please reinstall the plugin or run “composer install” in the plugin directory.', 'mediashield' );
			echo '</p></div>';
		}
	);
	return;
}

require_once MEDIASHIELD_PATH . 'vendor/autoload.php';

// Action Scheduler is a runtime dependency but registers its as_*() functions
// only when its entry file is explicitly required — Composer does NOT autoload
// it. Load it here, before plugins_loaded fires (Action Scheduler hooks that
// action at priority 0/1 to initialise). The bundled library carries its own
// version registry, so requiring it again from another plugin is harmless —
// the highest registered version wins. Because the free plugin is mandatory for
// Pro (Requires Plugins: mediashield), loading it once here makes the scheduler
// available to Pro's cron features too, with no duplicate copy bundled in Pro.
if ( file_exists( MEDIASHIELD_PATH . 'vendor/woocommerce/action-scheduler/action-scheduler.php' ) ) {
	require_once MEDIASHIELD_PATH . 'vendor/woocommerce/action-scheduler/action-scheduler.php';
}

// Activation / Deactivation hooks.
register_activation_hook( __FILE__, array( 'MediaShield\\Core\\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'MediaShield\\Core\\Deactivator', 'deactivate' ) );

// Load text domain for translations.
add_action(
	'init',
	function () {
		load_plugin_textdomain( 'mediashield', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}
);

// Bootstrap the plugin on plugins_loaded.
add_action(
	'plugins_loaded',
	function () {
		\MediaShield\Core\Migrator::run();
		\MediaShield\Core\Plugin::instance();
	}
);

// EDD Software Licensing SDK — free plugin auto-updates with preset key.
add_action(
	'edd_sl_sdk_registry',
	function ( $registry ) {
		$registry->register(
			array(
				'id'      => 'mediashield',
				'url'     => 'https://wbcomdesigns.com',
				'item_id' => 1661218,
				'version' => MEDIASHIELD_VERSION,
				'file'    => MEDIASHIELD_FILE,
				'license' => 'wbcomfreec7e2a9b45d8f1c3e6a0b9d2f7c4e8a11',
			)
		);
	}
);

if ( file_exists( MEDIASHIELD_PATH . 'vendor/easy-digital-downloads/edd-sl-sdk/edd-sl-sdk.php' ) ) {
	require_once MEDIASHIELD_PATH . 'vendor/easy-digital-downloads/edd-sl-sdk/edd-sl-sdk.php';
}

// WP-CLI commands.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	$ms_scale_command = MEDIASHIELD_PATH . 'src/CLI/ScaleCommand.php';
	if ( file_exists( $ms_scale_command ) ) {
		require_once $ms_scale_command;
		\WP_CLI::add_command( 'mediashield scale', \MediaShield\CLI\ScaleCommand::class );
	}
}

// Auto-activate the preset license key on first load so downloads work.
add_action(
	'admin_init',
	function () {
		$preset_key = 'wbcomfreec7e2a9b45d8f1c3e6a0b9d2f7c4e8a11';
		$option     = 'mediashield_license_key';
		$activated  = 'mediashield_preset_activated';

		if ( get_option( $activated ) ) {
			return;
		}

		update_option( $option, $preset_key, false );

		$response = wp_remote_post(
			'https://wbcomdesigns.com',
			array(
				'timeout' => 15,
				'body'    => array(
					'edd_action' => 'activate_license',
					'license'    => $preset_key,
					'item_id'    => 1661218,
					'url'        => home_url(),
				),
			)
		);

		if ( ! is_wp_error( $response ) ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( 'valid' === ( $body['license'] ?? '' ) ) {
				update_option( $activated, 1, false );
			}
		}
	}
);
