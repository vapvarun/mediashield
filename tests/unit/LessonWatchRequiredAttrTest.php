<?php
/**
 * Regression test for the 1.4.4 per-lesson watch rule (Learnomy 2.4.1).
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\Block\Shortcode;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Learnomy 2.4.1 sets "watch N% to complete" on each lesson and places the
 * player as [mediashield id="X" watch_required="N"]. The player reports the
 * moment the member reaches N, so Complete unlocks at once instead of up to
 * one heartbeat later. With no site-wide rule the session still tells a
 * member their watched share, or the player could not tell when N is reached.
 */
class LessonWatchRequiredAttrTest extends WP_UnitTestCase {

	/** @var int */
	private $video;

	public function set_up(): void {
		parent::set_up();
		delete_option( 'ms_lms_require_watch_pct' );
		update_option( 'ms_require_login', false );
		$this->video = self::factory()->post->create(
			array(
				'post_type'   => 'mediashield_video',
				'post_status' => 'publish',
				'meta_input'  => array(
					'_ms_platform'   => 'youtube',
					'_ms_source_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
					'_ms_platform_video_id' => 'dQw4w9WgXcQ',
				),
			)
		);
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
	}

	public function test_lesson_percent_reaches_the_player(): void {
		$with    = Shortcode::render( array( 'id' => $this->video, 'watch_required' => '80' ) );
		$without = Shortcode::render( array( 'id' => $this->video ) );
		$capped  = Shortcode::render( array( 'id' => $this->video, 'watch_required' => '250' ) );

		$this->assertStringContainsString( 'data-watch-required="80"', $with );
		$this->assertStringNotContainsString( 'data-watch-required', $without );
		$this->assertStringContainsString( 'data-watch-required="100"', $capped );
	}

	public function test_member_gets_their_share_without_a_site_rule(): void {
		$request = new WP_REST_Request( 'POST', '/mediashield/v1/session/start' );
		$request->set_body_params( array( 'video_id' => $this->video ) );
		$data = (array) rest_do_request( $request )->get_data();

		$this->assertIsArray( $data['watch'] ?? null, 'a member always gets their watched share' );
		$this->assertSame( 0, (int) $data['watch']['watched_pct'] );
		$this->assertSame( 0, (int) $data['watch']['required_pct'], 'no site-wide rule: the lesson\'s own % comes from the player' );
	}
}
