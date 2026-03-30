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
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'MEDIASHIELD_VERSION', '1.0.0' );
define( 'MEDIASHIELD_DB_VERSION', 1 );
define( 'MEDIASHIELD_FILE', __FILE__ );
define( 'MEDIASHIELD_PATH', plugin_dir_path( __FILE__ ) );
define( 'MEDIASHIELD_URL', plugin_dir_url( __FILE__ ) );

// Composer autoloader.
if ( file_exists( MEDIASHIELD_PATH . 'vendor/autoload.php' ) ) {
	require_once MEDIASHIELD_PATH . 'vendor/autoload.php';
}

// Activation / Deactivation hooks.
register_activation_hook( __FILE__, array( 'MediaShield\\Core\\Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'MediaShield\\Core\\Deactivator', 'deactivate' ) );

// Bootstrap the plugin on plugins_loaded.
add_action( 'plugins_loaded', function () {
	\MediaShield\Core\Migrator::run();
	\MediaShield\Core\Plugin::instance();
} );
