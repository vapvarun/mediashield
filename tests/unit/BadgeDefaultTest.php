<?php
/**
 * Regression test for Basecamp 10530931001 (1.4.3).
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\Core\Settings;
use MediaShield\Player\Watermark;
use WP_UnitTestCase;

/**
 * The "Protected by MediaShield" badge is off on a new install and an
 * existing site keeps its choice.
 */
class BadgeDefaultTest extends WP_UnitTestCase {

	/**
	 * Fails on a24cccc: a new install seeded the badge on.
	 */
	public function test_new_install_has_no_badge(): void {
		delete_option( 'ms_show_badge' );
		Settings::seed_defaults();

		$this->assertFalse( (bool) get_option( 'ms_show_badge' ) );
		$this->assertFalse( (bool) Watermark::get_config()['show_badge'] );
	}

	public function test_existing_site_keeps_its_badge(): void {
		update_option( 'ms_show_badge', 1 );
		Settings::seed_defaults();

		$this->assertTrue( (bool) Watermark::get_config()['show_badge'] );
	}
}
