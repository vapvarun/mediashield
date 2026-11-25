<?php
/**
 * Learnomy 2.4.1 owns "watch the video to complete"; MediaShield reports.
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use WP_UnitTestCase;

/**
 * One place for the rule: the Learnomy lesson.
 *
 * Learnomy 2.4.1 asks `learnomy_lesson_watched_pct` for the share of a
 * protected video lesson's video a member watched, and
 * `learnomy_protected_video_default_watch_pct` for the starting % of a new
 * protected video lesson. MediaShield answers both and, once Learnomy owns the
 * rule (`\Learnomy\protected_video_measured()` exists), adds nothing to the
 * lesson's completion status. With Learnomy 2.4.0 the 1.4.3 rule stays.
 * Fails on 1.4.3: neither filter was answered.
 */
class LearnomyWatchedShareTest extends WP_UnitTestCase {

	/** @var int */
	private $video;

	/** @var int */
	private $member;

	public function set_up(): void {
		parent::set_up();
		$this->member = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->video  = self::factory()->post->create(
			array(
				'post_type'   => 'mediashield_video',
				'post_status' => 'publish',
				'meta_input'  => array( '_ms_duration' => 100 ),
			)
		);
		update_user_meta( $this->member, '_ms_watched_spans', array( $this->video => array( 's' => array( array( 0, 42 ) ) ) ) );
	}

	public function tear_down(): void {
		delete_option( 'ms_lms_require_watch_pct' );
		parent::tear_down();
	}

	private function lesson( string $type = 'mediashield', $video_url = null ): object {
		return (object) array(
			'id'           => 7,
			'course_id'    => 3,
			'content_type' => $type,
			'video_url'    => null === $video_url ? (string) $this->video : $video_url,
		);
	}

	private function share( object $lesson, int $user_id = 0 ) {
		return apply_filters( 'learnomy_lesson_watched_pct', null, $user_id ? $user_id : $this->member, $lesson );
	}

	public function test_protected_lesson_reports_the_watched_share(): void {
		$this->assertSame( 42, $this->share( $this->lesson() ) );
	}

	public function test_a_guest_has_watched_nothing(): void {
		$this->assertSame( 0, apply_filters( 'learnomy_lesson_watched_pct', null, 0, $this->lesson() ) );
	}

	public function test_other_lessons_get_no_answer(): void {
		// Another content type: another provider's question.
		$this->assertNull( $this->share( $this->lesson( 'video', 'https://example.org/clip.mp4' ) ) );
		// A protected lesson whose id is not a MediaShield video.
		$page = self::factory()->post->create();
		$this->assertNull( $this->share( $this->lesson( 'mediashield', (string) $page ) ) );
		$this->assertNull( $this->share( $this->lesson( 'mediashield', '' ) ) );
		$this->assertNull( $this->share( $this->lesson( 'mediashield', 'https://example.org/clip.mp4' ) ) );
	}

	public function test_default_share_for_new_lessons_is_the_setting(): void {
		$this->assertSame( 0, apply_filters( 'learnomy_protected_video_default_watch_pct', 0 ) );
		update_option( 'ms_lms_require_watch_pct', 80 );
		$this->assertSame( 80, apply_filters( 'learnomy_protected_video_default_watch_pct', 0 ) );
	}

	/**
	 * Learnomy 2.4.0 (no protected_video_measured()): the 1.4.3 rule stays.
	 */
	public function test_without_learnomy_owning_the_rule_the_old_rule_applies(): void {
		$this->assertFalse( function_exists( '\\Learnomy\\protected_video_measured' ) );
		update_option( 'ms_lms_require_watch_pct', 80 );

		$status = apply_filters( 'learnomy_lesson_completion_rules', $this->status(), $this->member, $this->lesson() );

		$this->assertSame( 'mediashield_watch', $status['completion_mode'] );
		$this->assertFalse( $status['all_met'] );
		$this->assertSame( 42, $status['requirements'][0]['current_pct'] );
	}

	/**
	 * Learnomy 2.4.1: the lesson's own rule decides, MediaShield adds nothing.
	 *
	 * Separate process: the stub function cannot be undefined afterwards.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_when_learnomy_owns_the_rule_completion_is_left_alone(): void {
		$this->stub_learnomy_241();
		update_option( 'ms_lms_require_watch_pct', 80 );
		update_option( 'learnomy_protected_video_rule_moved', array( 'pct' => 80, 'moved' => 3, 'at' => '2026-11-25 05:00:00' ) );

		$this->assertTrue( \MediaShield\Integrations\Learnomy::owns_watch_rule() );

		$this->assertSame( $this->status(), apply_filters( 'learnomy_lesson_completion_rules', $this->status(), $this->member, $this->lesson() ) );
		// The share is still reported, for Learnomy's own rule.
		$this->assertSame( 42, $this->share( $this->lesson() ) );
	}

	/**
	 * Learnomy 2.4.1 still moving the site-wide % onto its lessons: the old
	 * rule keeps applying, and Settings keeps its control, until it is done.
	 *
	 * Fails before the fix: the function alone handed the rule to Learnomy,
	 * so the lessons had no watch gate while the move ran.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_while_learnomy_is_moving_the_rule_the_old_rule_applies(): void {
		$this->stub_learnomy_241();
		update_option( 'ms_lms_require_watch_pct', 80 );
		delete_option( 'learnomy_protected_video_rule_moved' );

		$this->assertFalse( \MediaShield\Integrations\Learnomy::owns_watch_rule() );
		$status = apply_filters( 'learnomy_lesson_completion_rules', $this->status(), $this->member, $this->lesson() );
		$this->assertSame( 'mediashield_watch', $status['completion_mode'] );
		$this->assertSame( 80, $status['requirements'][0]['min_pct'] );

		// Settings reads the same answer, so it keeps the site-wide control.
		$this->assertFalse( $this->settings_flag() );

		// The move finishes: Learnomy owns the rule and Settings says so.
		update_option( 'learnomy_protected_video_rule_moved', array( 'pct' => 80, 'moved' => 3, 'at' => '2026-11-25 05:00:00' ) );
		$this->assertTrue( \MediaShield\Integrations\Learnomy::owns_watch_rule() );
		$this->assertSame( $this->status(), apply_filters( 'learnomy_lesson_completion_rules', $this->status(), $this->member, $this->lesson() ) );
		$this->assertTrue( $this->settings_flag() );
	}

	/**
	 * Learnomy 2.4.1 with no site-wide %: nothing to move, so it owns the rule at once.
	 *
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_with_no_site_wide_rule_learnomy_owns_it_at_once(): void {
		$this->stub_learnomy_241();
		delete_option( 'ms_lms_require_watch_pct' );
		delete_option( 'learnomy_protected_video_rule_moved' );

		$this->assertTrue( \MediaShield\Integrations\Learnomy::owns_watch_rule() );
		$this->assertTrue( $this->settings_flag() );
	}

	/**
	 * Define Learnomy 2.4.1's protected_video_measured(). Call only in a separate process.
	 */
	private function stub_learnomy_241(): void {
		eval( 'namespace Learnomy; function protected_video_measured(): bool { return true; }' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged -- test stub of Learnomy 2.4.1's function.
	}

	/**
	 * The learnomyOwnsWatchRule flag the admin Settings screen gets.
	 *
	 * @return bool
	 */
	private function settings_flag(): bool {
		$GLOBALS['wp_scripts'] = null; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- fresh script registry per read.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		\MediaShield\Admin\Menu::enqueue_admin_assets( 'toplevel_page_mediashield' );
		$data = (string) wp_scripts()->get_data( 'mediashield-admin', 'data' );
		$this->assertMatchesRegularExpression( '/var mediashieldAdmin = (\{.*\});/s', $data );
		preg_match( '/var mediashieldAdmin = (\{.*\});/s', $data, $m );
		return (bool) json_decode( $m[1], true )['learnomyOwnsWatchRule'];
	}

	private function status(): array {
		return array(
			'lesson_id'       => 7,
			'completion_mode' => 'manual',
			'enforcement'     => 'strict',
			'requirements'    => array(),
			'all_met'         => true,
			'is_completed'    => false,
		);
	}
}
