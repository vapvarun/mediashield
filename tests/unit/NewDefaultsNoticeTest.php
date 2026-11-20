<?php
/**
 * Regression test for Basecamp 10530930695 / 10530931001 (1.4.3, owner decision).
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\Admin\NewDefaultsNotice;
use MediaShield\Core\Migrator;
use WP_UnitTestCase;

/**
 * A site updated from 1.4.2 is offered the new defaults once; nothing changes silently.
 */
class NewDefaultsNoticeTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		set_current_screen( 'toplevel_page_mediashield' );
	}

	public function tear_down(): void {
		delete_option( NewDefaultsNotice::DECIDED );
		update_option( 'ms_db_version', MEDIASHIELD_DB_VERSION );
		parent::tear_down();
	}

	/**
	 * Fails on 63c9ede: an updated site kept the stored badge and was never told.
	 */
	public function test_updated_site_is_offered_once_and_apply_changes_it(): void {
		update_option( 'ms_show_badge', 1 );
		delete_option( NewDefaultsNotice::DECIDED );
		update_option( 'ms_db_version', 10 ); // A 1.4.2 site.
		Migrator::run();

		$this->assertSame( 'pending', get_option( NewDefaultsNotice::DECIDED ) );
		$this->assertTrue( NewDefaultsNotice::should_show() );
		$this->assertTrue( (bool) get_option( 'ms_show_badge' ), 'Nothing changes before the owner chooses.' );

		NewDefaultsNotice::decide( 'apply' );
		$this->assertFalse( (bool) get_option( 'ms_show_badge' ) );
		$this->assertSame( 'apply', get_option( NewDefaultsNotice::DECIDED ) );
		$this->assertFalse( NewDefaultsNotice::should_show() );
	}

	public function test_keep_changes_nothing_and_never_returns(): void {
		update_option( 'ms_show_badge', 1 );
		update_option( NewDefaultsNotice::DECIDED, 'pending' );

		NewDefaultsNotice::decide( 'keep' );
		$this->assertTrue( (bool) get_option( 'ms_show_badge' ) );
		$this->assertFalse( NewDefaultsNotice::should_show() );

		update_option( 'ms_db_version', 11 );
		Migrator::run();
		$this->assertSame( 'keep', get_option( NewDefaultsNotice::DECIDED ), 'A later update does not ask again.' );
	}

	public function test_fresh_install_never_sees_it_and_not_now_snoozes(): void {
		delete_option( NewDefaultsNotice::DECIDED );
		update_option( 'ms_db_version', 0 );
		Migrator::run();
		update_option( 'ms_show_badge', 1 ); // The owner turns the badge on later.
		$this->assertFalse( NewDefaultsNotice::should_show() );

		update_option( NewDefaultsNotice::DECIDED, 'pending' );
		NewDefaultsNotice::decide( 'later' );
		$this->assertFalse( NewDefaultsNotice::should_show(), 'Snoozed for this admin.' );
		$this->assertSame( 'pending', get_option( NewDefaultsNotice::DECIDED ) );
	}
}
