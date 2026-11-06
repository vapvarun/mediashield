<?php
/**
 * Plugin Name: MediaShield
 * Plugin URI:  https://wbcomdesigns.com/mediashield
 * Description: Video protection for WordPress — dynamic watermarking, multi-platform support, engagement analytics, and milestone automation.
 * Version:     1.4.2
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
define( 'MEDIASHIELD_VERSION', '1.4.2' );
define( 'MEDIASHIELD_DB_VERSION', 8 );
define( 'MEDIASHIELD_FILE', __FILE__ );
define( 'MEDIASHIELD_PATH', plugin_dir_path( __FILE__ ) );
define( 'MEDIASHIELD_URL', plugin_dir_url( __FILE__ ) );
// Licence and update host for MediaShield and MediaShield Pro; the SDK falls back to https://wbcomdesigns.com.
define( 'MEDIASHIELD_LICENCE_HOST', 'https://updates.wbcomdesigns.com' );

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

/**
 * One licence-server request (activate, deactivate or check) for MediaShield and MediaShield Pro. Goes through
 * the bundled SDK, so it uses the same host as update checks and falls back to the old host when the new one
 * cannot be reached. Returns the decoded reply, or false when no host answered.
 *
 * @param array $params EDD Software Licensing parameters (edd_action, license, item_id).
 * @return object|false
 */
function mediashield_licence_request( array $params ) {
	if ( ! class_exists( '\\EasyDigitalDownloads\\Updater\\Requests\\API' ) ) {
		return false;
	}
	// Name the fallback here as well: on an early request (plugin activation) the SDK registry has not run yet.
	if ( method_exists( '\EasyDigitalDownloads\Updater\Requests\API', 'set_fallback' ) ) {
		\EasyDigitalDownloads\Updater\Requests\API::set_fallback( MEDIASHIELD_LICENCE_HOST, 'https://wbcomdesigns.com' );
	}
	return ( new \EasyDigitalDownloads\Updater\Requests\API( MEDIASHIELD_LICENCE_HOST ) )->make_request( $params );
}

// EDD Software Licensing SDK — free plugin auto-updates with preset key.
add_action(
	'edd_sl_sdk_registry',
	function ( $registry ) {
		$registry->register(
			array(
				'id'           => 'mediashield',
				'url'          => MEDIASHIELD_LICENCE_HOST,
				'fallback_url' => 'https://wbcomdesigns.com',
				'item_id'      => 1661218,
				'version'      => MEDIASHIELD_VERSION,
				'file'         => MEDIASHIELD_FILE,
				'license'      => 'mediasheild7c2a9e5d1f8b4c6a3e0d9b2f7c1a8e12',
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

// Package Signatures (libs/package-signatures, vapvarun/package-signatures): checks that each MediaShield and
// MediaShield Pro update package carries the release signature before WordPress installs it. Warn mode: the
// result is logged (option package_signatures_log) and the update installs either way. Free registers Pro as
// well, because WordPress loads mediashield-pro/ before mediashield/ and Pro could not call this itself.
if ( file_exists( MEDIASHIELD_PATH . 'libs/package-signatures/package-signatures.php' ) ) {
	require_once MEDIASHIELD_PATH . 'libs/package-signatures/package-signatures.php';
	$mediashield_signed_products = array(
		'mediashield'     => plugin_basename( MEDIASHIELD_FILE ),
		'mediashield-pro' => 'mediashield-pro/mediashield-pro.php',
	);
	foreach ( $mediashield_signed_products as $mediashield_signed_product => $mediashield_signed_plugin ) {
		package_signatures_register(
			array(
				'product'  => $mediashield_signed_product,
				'plugin'   => $mediashield_signed_plugin,
				'hosts'    => array( 'wbcomdesigns.com', 'updates.wbcomdesigns.com' ),
				'keys'     => array( 'wbcom-2026-1' => 'kr+2P1WqRbENpZw0BXZmwFnA2tMLi6MlHbBa6jB13U4=' ),
				'mode'     => 'warn',
				'fallback' => 'https://wbcomdesigns.com/package-signatures/{product}/{version}/',
			)
		);
	}
	unset( $mediashield_signed_products, $mediashield_signed_product, $mediashield_signed_plugin );
}

// WP-CLI commands (autoloaded from includes/CLI, so they ship in the release zip).
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	\WP_CLI::add_command( 'mediashield scale', \MediaShield\CLI\ScaleCommand::class );
	\WP_CLI::add_command( 'mediashield repair', \MediaShield\CLI\RepairCommand::class );
}

/*
 * Auto-activate the preset license key on first load so downloads work.
 *
 * Three guards keep a slow or refusing license server from being hit on every
 * admin request (one install sent 933 activations in 23 hours):
 *   1. The activated flag is final. A `valid` reply, or any definitive refusal
 *      (invalid, disabled, expired, no activations left), ends the attempts.
 *   2. A 12-hour lock is set on every attempt, so a timeout or an unreadable
 *      reply is retried twice a day at most, not once a request.
 *   3. admin_init also fires for admin-ajax (Heartbeat), cron and REST, where
 *      no one benefits from activating, so those requests skip it.
 */
add_action(
	'admin_init',
	function () {
		if ( wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}

		// Owner opt-out: define MEDIASHIELD_NO_LICENSE_PHONE_HOME to disable the
		// store call entirely (air-gapped or privacy-strict installs, and tests).
		if ( defined( 'MEDIASHIELD_NO_LICENSE_PHONE_HOME' ) && MEDIASHIELD_NO_LICENSE_PHONE_HOME ) {
			return;
		}

		$preset_key      = 'mediasheild7c2a9e5d1f8b4c6a3e0d9b2f7c1a8e12';
		$option          = 'mediashield_license_key';
		$status_option   = 'mediashield_license';
		$activated       = 'mediashield_preset_activated';
		$lock            = 'mediashield_preset_activate_lock';

		if ( get_option( $activated ) || get_site_transient( $lock ) ) {
			return;
		}

		set_site_transient( $lock, 1, 12 * HOUR_IN_SECONDS );

		update_option( $option, $preset_key, false );

		// Through the shared helper: the new host first, the old one if it cannot be
		// reached. False means no host answered (or the SDK is not loaded yet): the
		// lock above retries it later.
		$body = mediashield_licence_request(
			array(
				'edd_action' => 'activate_license',
				'license'    => $preset_key,
				'item_id'    => 1661218,
			)
		);
		if ( ! is_object( $body ) ) {
			return;
		}

		$license = $body->license ?? '';

		// No verdict in the reply (an error page, a 403 from a firewall): try
		// again after the lock.
		if ( ! is_string( $license ) || '' === $license ) {
			return;
		}

		update_option( $activated, 1, false );

		if ( 'valid' !== $license ) {
			return;
		}

		// Sync the SDK's expected status-object option so the "Manage License"
		// modal shows the valid customer + expiry immediately, instead of
		// waiting for the SDK's daily get_version cron to re-check. Stored as
		// the reply object, which is what the SDK writes when the user
		// activates manually through its own overlay.
		update_option( $status_option, $body, false );
	}
);
