<?php
/**
 * One protection model: four levels, the same words everywhere, Strict's extras belong to Strict.
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\Player\Protection;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Basecamp 10545260510.
 */
class ProtectionModelTest extends WP_UnitTestCase {

	/**
	 * Admin; the three old switches at their defaults (an untouched site).
	 */
	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		update_option( 'ms_hide_source', 1 );
		update_option( 'ms_detect_devtools', 1 );
		update_option( 'ms_pause_on_devtools', '' );
	}

	/**
	 * The four levels, each with a label and the words every screen shows.
	 */
	public function test_four_levels_with_one_set_of_words(): void {
		$levels = Protection::levels();
		$this->assertSame( array( 'none', 'basic', 'standard', 'strict' ), array_keys( $levels ) );
		foreach ( $levels as $level ) {
			$this->assertNotSame( '', $level['label'] );
			$this->assertNotSame( '', $level['summary'] );
			$this->assertStringNotContainsStringIgnoringCase( 'login', $level['summary'], 'Login is not part of a level.' );
		}
		update_option( 'ms_default_protection', 'strict' );
		$this->assertSame( 'Site default (Strict)', Protection::site_default_label() );
	}

	/**
	 * An untouched site: source hiding and devtools detection are Strict's only.
	 */
	public function test_untouched_site_leaves_the_extras_to_strict(): void {
		$this->assertSame( array(), Protection::legacy_switches() );
		$config = Protection::get_config();
		$this->assertFalse( $config['hide_source'] );
		$this->assertFalse( $config['detect_devtools'] );
		$this->assertFalse( $config['pause_on_devtools'] );
		$this->assertFalse( Protection::site_hides_source() );
	}

	/**
	 * A site that changed a switch keeps its old site-wide behaviour, and Settings says so.
	 */
	public function test_changed_switches_keep_working_until_a_level_is_picked(): void {
		update_option( 'ms_pause_on_devtools', 1 );

		$this->assertSame( array( 'ms_pause_on_devtools' ), Protection::legacy_switches() );
		$config = Protection::get_config();
		$this->assertTrue( $config['hide_source'] );
		$this->assertTrue( $config['detect_devtools'] );
		$this->assertTrue( $config['pause_on_devtools'] );

		$get = rest_do_request( new WP_REST_Request( 'GET', '/mediashield/v1/settings' ) )->get_data();
		$this->assertSame( array( 'ms_pause_on_devtools' ), $get['ms_legacy_switches'] );

		// Picking a level moves the site onto the levels.
		$put = new WP_REST_Request( 'PUT', '/mediashield/v1/settings' );
		$put->set_header( 'Content-Type', 'application/json' );
		$put->set_body( wp_json_encode( array( 'ms_default_protection' => 'standard' ) ) );
		$after = rest_do_request( $put )->get_data();

		$this->assertSame( array(), $after['ms_legacy_switches'] );
		$this->assertFalse( Protection::get_config()['pause_on_devtools'] );
	}

	/**
	 * Other settings saved alone do not end a site's own switches.
	 */
	public function test_other_saves_keep_the_switches(): void {
		update_option( 'ms_hide_source', '' );
		$put = new WP_REST_Request( 'PUT', '/mediashield/v1/settings' );
		$put->set_header( 'Content-Type', 'application/json' );
		$put->set_body( wp_json_encode( array( 'ms_require_login' => false ) ) );
		rest_do_request( $put );

		$this->assertSame( array( 'ms_hide_source' ), Protection::legacy_switches() );
	}
}
