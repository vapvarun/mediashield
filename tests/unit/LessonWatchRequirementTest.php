<?php
/**
 * Regression test for Basecamp 10530930729 (1.4.3).
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use WP_UnitTestCase;

/**
 * "Require X% watched before Complete" gates a Learnomy protected video lesson.
 *
 * Learnomy decides completion from the status that its
 * `learnomy_lesson_completion_rules` filter returns, both for the button and
 * for POST /lessons/{id}/complete, so the filter's answer is the whole rule.
 */
class LessonWatchRequirementTest extends WP_UnitTestCase {

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
			)
		);
	}

	/**
	 * A Learnomy lesson row playing the test video.
	 */
	private function lesson( string $type = 'mediashield' ): object {
		return (object) array(
			'id'           => 7,
			'course_id'    => 3,
			'content_type' => $type,
			'video_url'    => (string) $this->video,
		);
	}

	/**
	 * Learnomy's status for a Manual lesson, before MediaShield answers.
	 */
	private function status( string $enforcement = 'strict', bool $completed = false ): array {
		return array(
			'lesson_id'       => 7,
			'completion_mode' => 'manual',
			'enforcement'     => $enforcement,
			'requirements'    => array(),
			'all_met'         => true,
			'is_completed'    => $completed,
		);
	}

	/**
	 * Give the member $pct of a 100-second video as watched time (WatchedSpans).
	 */
	private function watched( float $pct ): void {
		update_post_meta( $this->video, '_ms_duration', 100 );
		$all                 = (array) get_user_meta( $this->member, '_ms_watched_spans', true );
		$spans               = (array) ( $all[ $this->video ]['s'] ?? array() );
		$spans[]             = array( 0, $pct );
		$all[ $this->video ] = array( 's' => \MediaShield\Access\WatchedSpans::merge( $spans ) );
		update_user_meta( $this->member, '_ms_watched_spans', $all );
	}

	private function ask( array $status, string $type = 'mediashield' ): array {
		return (array) apply_filters( 'learnomy_lesson_completion_rules', $status, $this->member, $this->lesson( $type ) );
	}

	public function tear_down(): void {
		delete_option( 'ms_lms_require_watch_pct' );
		parent::tear_down();
	}

	/**
	 * Off by default: Learnomy's answer is untouched.
	 */
	public function test_off_by_default(): void {
		$this->assertSame( $this->status(), $this->ask( $this->status() ) );
	}

	/**
	 * Fails on b75cb14: nothing answered the filter, so Complete was allowed at 0:00.
	 */
	public function test_unwatched_lesson_cannot_complete(): void {
		update_option( 'ms_lms_require_watch_pct', 80 );
		$this->watched( 40 );

		$status = $this->ask( $this->status() );

		$this->assertFalse( $status['all_met'], 'mark_lesson_complete() must refuse' );
		$this->assertSame( 'mediashield_watch', $status['completion_mode'], 'Learnomy shows the strip and disables Complete for any non-manual mode' );
		$this->assertCount( 1, $status['requirements'] );
		$this->assertSame( 'Watch 80% to complete', $status['requirements'][0]['label'] );
		$this->assertSame( 40, $status['requirements'][0]['current_pct'] );
		$this->assertFalse( $status['requirements'][0]['met'] );
	}

	public function test_watched_share_allows_complete(): void {
		update_option( 'ms_lms_require_watch_pct', 80 );
		$this->watched( 30 );
		$this->watched( 85.4 ); // A second sitting overlapping the first: counted once.

		$status = $this->ask( $this->status() );

		$this->assertTrue( $status['all_met'] );
		$this->assertTrue( $status['requirements'][0]['met'] );
		$this->assertSame( 85, $status['requirements'][0]['current_pct'] );
	}

	/**
	 * Fails on 1.4.3 (Basecamp 10541776164, 10537102216): a video added by
	 * pasting a URL keeps Duration 0, the share was read only against that
	 * field, and it stayed 0% however much was played, so Complete never
	 * unlocked. The length the member's player reports is used instead.
	 */
	public function test_duration_zero_uses_the_length_the_player_reports(): void {
		update_option( 'ms_lms_require_watch_pct', 5 );
		delete_post_meta( $this->video, '_ms_duration' );

		// A sitting that started two minutes ago, as the heartbeat records it:
		// 20 seconds played of a 214-second video.
		$all                 = (array) get_user_meta( $this->member, '_ms_watched_spans', true );
		$all[ $this->video ] = array(
			's' => array(),
			't' => time() - 120,
		);
		update_user_meta( $this->member, '_ms_watched_spans', $all );
		$accepted = \MediaShield\Access\WatchedSpans::record( $this->member, $this->video, array( array( 0, 20 ) ), 214.0, 20.0 );

		$this->assertEqualsWithDelta( 20.0, $accepted, 0.01, 'the stretch is accepted' );
		$this->assertSame( 9, \MediaShield\Access\WatchedSpans::percent( $this->member, $this->video ), '20 s of 214 s is 9%' );
		$status = $this->ask( $this->status() );
		$this->assertTrue( $status['all_met'], 'Complete unlocks at 5%' );
		$this->assertSame( 9, $status['requirements'][0]['current_pct'] );

		// A later report of a shorter length never shrinks the share's base.
		\MediaShield\Access\WatchedSpans::record( $this->member, $this->video, array(), 30.0, 0.0 );
		$this->assertSame( 9, \MediaShield\Access\WatchedSpans::percent( $this->member, $this->video ) );
	}

	public function test_enforcement_modes_and_other_lessons_are_respected(): void {
		update_option( 'ms_lms_require_watch_pct', 80 );

		// Admins and the course instructor resolve to 'off'.
		$this->assertSame( $this->status( 'off' ), $this->ask( $this->status( 'off' ) ) );
		// Advisory shows the requirement but never blocks.
		$this->assertTrue( $this->ask( $this->status( 'advisory' ) )['all_met'] );
		// A completed lesson stays completed.
		$this->assertSame( $this->status( 'strict', true ), $this->ask( $this->status( 'strict', true ) ) );
		// Only protected video lessons.
		$this->assertSame( $this->status(), $this->ask( $this->status(), 'text' ) );
	}
}
