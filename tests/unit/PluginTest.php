<?php
/**
 * Smoke test for MediaShield plugin.
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use WP_UnitTestCase;

/**
 * Basic plugin load test.
 */
class PluginTest extends WP_UnitTestCase {

	/**
	 * The plugin file should be loaded.
	 */
	public function test_plugin_loaded(): void {
		$this->assertTrue( defined( 'MEDIASHIELD_VERSION' ), 'MEDIASHIELD_VERSION should be defined' );
	}

	/**
	 * All expected constants should be defined.
	 */
	public function test_constants_defined(): void {
		$this->assertTrue( defined( 'MEDIASHIELD_VERSION' ) );
		$this->assertTrue( defined( 'MEDIASHIELD_DB_VERSION' ) );
		$this->assertTrue( defined( 'MEDIASHIELD_FILE' ) );
		$this->assertTrue( defined( 'MEDIASHIELD_PATH' ) );
		$this->assertTrue( defined( 'MEDIASHIELD_URL' ) );
	}

	/**
	 * Plugin version should be a valid semver string.
	 */
	public function test_version_format(): void {
		$this->assertMatchesRegularExpression( '/^\d+\.\d+\.\d+$/', MEDIASHIELD_VERSION );
	}
}
