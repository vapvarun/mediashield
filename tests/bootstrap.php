<?php
/**
 * PHPUnit bootstrap file for MediaShield.
 *
 * @package MediaShield
 */

// Composer autoloader.
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Point to polyfills.
define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills' );

// Load the WP test suite.
$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find $_tests_dir/includes/functions.php — have you run bin/install-wp-tests.sh?" . PHP_EOL;
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once $_tests_dir . '/includes/functions.php';

// Tests never call the live store: the preset licence activation is off, and both store hosts are refused
// before a request leaves the machine. Without this every CI run would activate the free key as
// http://example.org on the live store (the Jetonomy CI did, 2026-10-26).
define( 'MEDIASHIELD_NO_LICENSE_PHONE_HOME', true );
tests_add_filter(
	'pre_http_request',
	static function ( $pre, $args, $url ) {
		$host = (string) wp_parse_url( $url, PHP_URL_HOST );
		return in_array( $host, array( 'wbcomdesigns.com', 'updates.wbcomdesigns.com' ), true )
			? new WP_Error( 'mediashield_tests_no_store', 'Tests do not call the live store.' )
			: $pre;
	},
	1,
	3
);


/**
 * Manually load the plugin being tested.
 */
tests_add_filter(
	'muplugins_loaded',
	function () {
		require dirname( __DIR__ ) . '/mediashield.php';
	}
);

// Start up the WP testing environment.
require $_tests_dir . '/includes/bootstrap.php';
