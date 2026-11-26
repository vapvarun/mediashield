<?php
/**
 * 1.4.4: MediaShield's Learnomy integration says when a protected video
 * lesson completes by watching a video (VideoStatus::needs_length()).
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\CPT\VideoStatus;
use WP_UnitTestCase;

/**
 * In its own process: it stubs Learnomy's Lesson model.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class LearnomyWatchRuleLengthTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		if ( ! class_exists( '\\Learnomy\\Models\\Lesson' ) ) {
			// phpcs:ignore Squiz.PHP.Eval.Discouraged -- test stub of Learnomy's model.
			eval( 'namespace Learnomy\\Models; class Lesson { public static function for_mediashield_video( int $id ): array { return $GLOBALS["ms_test_lessons"][ $id ] ?? array(); } }' );
		}
	}

	public function tear_down(): void {
		unset( $GLOBALS['ms_test_lessons'] );
		delete_option( 'ms_lms_require_watch_pct' );
		parent::tear_down();
	}

	/**
	 * A protected video lesson with the site's % asks for the length; without a % it does not.
	 */
	public function test_a_learnomy_lesson_with_a_watch_rule_asks_for_the_length(): void {
		$video = self::factory()->post->create( array( 'post_type' => 'mediashield_video', 'post_status' => 'publish' ) );
		$GLOBALS['ms_test_lessons'][ $video ] = array(
			(object) array(
				'id'           => 7,
				'content_type' => 'mediashield',
				'video_url'    => (string) $video,
			),
		);

		update_option( 'ms_lms_require_watch_pct', 0 );
		$this->assertFalse( VideoStatus::needs_length( $video ) );

		update_option( 'ms_lms_require_watch_pct', 70 );
		$this->assertTrue( VideoStatus::needs_length( $video ) );

		$other = self::factory()->post->create( array( 'post_type' => 'mediashield_video', 'post_status' => 'publish' ) );
		$this->assertFalse( VideoStatus::needs_length( $other ), 'No lesson plays it.' );
	}
}
