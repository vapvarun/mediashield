<?php
/**
 * Learnomy card 10536470184: MediaShield reads Learnomy 2.4.1's own
 * Lesson::naming_mediashield_video(), which 2.4.1 has; 1.4.4 called
 * for_mediashield_video(), which it does not, so the "Length needed"
 * prompt never showed for a Learnomy lesson.
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\CPT\VideoStatus;
use WP_UnitTestCase;

/**
 * In its own process: it stubs Learnomy 2.4.1's Lesson model.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class LearnomyNamingLookupTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		if ( ! class_exists( '\\Learnomy\\Models\\Lesson' ) ) {
			// Learnomy 2.4.1's signature: every lesson naming the video, any status and type.
			// phpcs:ignore Squiz.PHP.Eval.Discouraged -- test stub of Learnomy's model.
			eval( 'namespace Learnomy\\Models; class Lesson { public static function naming_mediashield_video( int $id, string $needle = "" ): array { return $GLOBALS["ms_test_lessons"][ $id ] ?? array(); } }' );
		}
	}

	public function tear_down(): void {
		unset( $GLOBALS['ms_test_lessons'] );
		delete_option( 'ms_lms_require_watch_pct' );
		parent::tear_down();
	}

	private static function lesson( int $id, int $video, string $type, string $status ): object {
		return (object) array(
			'id'           => $id,
			'content_type' => $type,
			'video_url'    => (string) $video,
			'status'       => $status,
		);
	}

	/**
	 * A published protected video lesson with a watch rule asks for the
	 * length; draft lessons and other lesson types do not.
	 */
	public function test_a_published_protected_lesson_asks_for_the_length(): void {
		update_option( 'ms_lms_require_watch_pct', 70 );
		$video = self::factory()->post->create( array( 'post_type' => 'mediashield_video', 'post_status' => 'publish' ) );

		$GLOBALS['ms_test_lessons'][ $video ] = array(
			self::lesson( 1, $video, 'mediashield', 'draft' ),
			self::lesson( 2, $video, 'video', 'published' ),
		);
		$this->assertFalse( VideoStatus::needs_length( $video ), 'Only a draft and a plain Video lesson.' );

		$GLOBALS['ms_test_lessons'][ $video ][] = self::lesson( 3, $video, 'mediashield', 'published' );
		$this->assertTrue( VideoStatus::needs_length( $video ) );
	}

	/**
	 * Basecamp 10545260458: "Used in" names every lesson that plays the
	 * video, a draft too; such a place is never taken for a post (get_post( 0 )).
	 * Fails before 1.4.4's Learnomy provider ("Not used yet").
	 */
	public function test_used_in_names_the_learnomy_lesson(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$video = self::factory()->post->create( array( 'post_type' => 'mediashield_video', 'post_status' => 'publish' ) );
		$row   = self::lesson( 3, $video, 'mediashield', 'published' );
		$row->title = 'Week 1: Lighting';
		$GLOBALS['ms_test_lessons'][ $video ] = array( $row, self::lesson( 4, $video, 'mediashield', 'draft' ) );

		$places = \MediaShield\CPT\VideoUsage::for_video( $video );

		// The draft lesson too, as draft pages are listed (QA round 2).
		$this->assertSame( array( 'learnomy_lesson:3', 'learnomy_lesson:4' ), wp_list_pluck( $places, 'id' ) );
		$this->assertSame( 'learnomy_lesson:3', $places[0]['id'] );
		$this->assertSame( 'Week 1: Lighting', $places[0]['title'] );
		$this->assertStringContainsString( 'page=learnomy-lesson-editor&lesson_id=3', $places[0]['url'] );

		$GLOBALS['post'] = get_post( self::factory()->post->create() );
		$this->assertSame( '', \MediaShield\CPT\VideoUsage::page_for_viewer( $video, get_current_user_id() ), 'A lesson place is not a post.' );
	}
}
