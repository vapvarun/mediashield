<?php
/**
 * New Learnomy protected video lessons start at 80% on a new install.
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\Core\Settings;
use MediaShield\Integrations\Learnomy;
use WP_UnitTestCase;

/**
 * QA on Basecamp 10545260590: a new install answered 0, so new lessons
 * started on Manual and the owner had no control for it.
 */
class LearnomyNewLessonDefaultTest extends WP_UnitTestCase {

	/**
	 * Remove every MediaShield option and the move record.
	 */
	private function empty_site(): void {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE 'ms\\_%' OR option_name = 'learnomy_protected_video_rule_moved'" ); // phpcs:ignore WordPress.DB
		wp_cache_flush();
	}

	/**
	 * A new install: 80; MediaShield never writes Learnomy's move record.
	 */
	public function test_new_install_starts_new_lessons_at_80(): void {
		$this->empty_site();
		\MediaShield\Core\Activator::activate();

		$this->assertSame( 80, Settings::get( 'ms_learnomy_new_lesson_pct' ) );
		$this->assertSame( 80, Learnomy::default_watch_pct( 0 ) );
		$this->assertSame( 0, Settings::get( 'ms_lms_require_watch_pct' ), 'The site-wide gate stays off, so Learnomy owns the rule.' );
		$this->assertFalse( get_option( Learnomy::RULE_MOVED_OPTION ), 'Learnomy\'s record is Learnomy\'s to write.' );
	}

	/**
	 * An update starts from the stored Require X% watched, and leaves the move to Learnomy.
	 */
	public function test_update_keeps_the_stored_share_for_the_move(): void {
		$this->empty_site();
		update_option( 'ms_db_version', 13 );
		update_option( 'ms_lms_require_watch_pct', 70 );

		\MediaShield\Core\Migrator::run();

		$this->assertSame( 70, Settings::get( 'ms_learnomy_new_lesson_pct' ) );
		$this->assertSame( 70, Learnomy::default_watch_pct( 0 ), 'Learnomy moves the old share onto existing lessons.' );
		$this->assertFalse( get_option( Learnomy::RULE_MOVED_OPTION ), 'The move is Learnomy\'s to run.' );
	}

	/**
	 * The owner's own value is used, and 0 leaves Learnomy's default.
	 */
	public function test_owner_value_answers_the_filter(): void {
		update_option( 'ms_learnomy_new_lesson_pct', 65 );
		$this->assertSame( 65, Learnomy::default_watch_pct( 0 ) );
		update_option( 'ms_learnomy_new_lesson_pct', 0 );
		$this->assertSame( 50, Learnomy::default_watch_pct( 50 ) );
	}
}
