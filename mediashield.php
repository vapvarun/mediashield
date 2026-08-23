<?php
/**
 * Plugin Name: MediaShield
 * Plugin URI:  https://wbcomdesigns.com/mediashield
 * Description: Video protection for WordPress — dynamic watermarking, multi-platform support, engagement analytics, and milestone automation.
 * Version:     1.2.0
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
define( 'MEDIASHIELD_VERSION', '1.2.0' );
define( 'MEDIASHIELD_DB_VERSION', 5 );
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
				'license' => 'mediasheild7c2a9e5d1f8b4c6a3e0d9b2f7c1a8e12',
			)
		);
	}
);

// Load the vendored EDD SL SDK only when the package is COMPLETE. A partial
// build or extract that keeps the entry file but drops libs/edd-sl-sdk/src
// fatals inside the SDK the moment it touches a src class — the SDK's own
// bootstrap calls EasyDigitalDownloads\Updater\Utilities\Path immediately, so
// the site white-screens on activation. That is exactly the reported failure
// (BC#10111573949: "Class 'EasyDigitalDownloads\Updater\Utilities\Path' not
// found"). Guarding on the entry file alone is not enough; check the source is
// present too and degrade to "updates disabled" with a soft admin notice.
// Licensing only authorises update downloads — it never gates features, so
// video protection keeps working either way. Matches the BuddyNext pattern.
if ( file_exists( MEDIASHIELD_PATH . 'libs/edd-sl-sdk/edd-sl-sdk.php' )
	&& file_exists( MEDIASHIELD_PATH . 'libs/edd-sl-sdk/src/Versions.php' ) ) {
	require_once MEDIASHIELD_PATH . 'libs/edd-sl-sdk/edd-sl-sdk.php';
} elseif ( is_admin() ) {
	add_action(
		'admin_notices',
		static function () {
			echo '<div class="notice notice-warning"><p>'
				. esc_html__( 'MediaShield: the bundled licensing and update SDK is incomplete, so automatic updates are turned off. Reinstall the plugin from a complete package to restore them. Every other feature works normally.', 'mediashield' )
				. '</p></div>';
		}
	);
}

// WP-CLI commands.
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	$ms_scale_command = MEDIASHIELD_PATH . 'src/CLI/ScaleCommand.php';
	if ( file_exists( $ms_scale_command ) ) {
		require_once $ms_scale_command;
		\WP_CLI::add_command( 'mediashield scale', \MediaShield\CLI\ScaleCommand::class );
	}

	$ms_repair_command = MEDIASHIELD_PATH . 'src/CLI/RepairCommand.php';
	if ( file_exists( $ms_repair_command ) ) {
		require_once $ms_repair_command;
		\WP_CLI::add_command( 'mediashield repair', \MediaShield\CLI\RepairCommand::class );
	}
}

// Auto-activate the preset license key on first load so downloads work.
add_action(
	'admin_init',
	function () {
		$preset_key      = 'mediasheild7c2a9e5d1f8b4c6a3e0d9b2f7c1a8e12';
		$option          = 'mediashield_license_key';
		$status_option   = 'mediashield_license';
		$activated       = 'mediashield_preset_activated';

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

		if ( is_wp_error( $response ) ) {
			return;
		}

		$raw = wp_remote_retrieve_body( $response );
		$body = json_decode( $raw, true );

		if ( 'valid' !== ( $body['license'] ?? '' ) ) {
			return;
		}

		update_option( $activated, 1, false );

		// Sync the SDK's expected status-object option so the "Manage License"
		// modal shows the valid customer + expiry immediately, instead of
		// waiting for the SDK's daily get_version cron to re-check. Stored as
		// an object (stdClass) to match what the SDK writes when the user
		// activates manually through its own overlay.
		update_option( $status_option, json_decode( $raw ), false );
	}
);
