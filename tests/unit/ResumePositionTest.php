<?php
/**
 * Regression test for Basecamp 10531602227 (1.4.3).
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\Access\SessionManager;
use WP_UnitTestCase;

/**
 * A visit without pressing play does not lose the place to resume from.
 */
class ResumePositionTest extends WP_UnitTestCase {

	public function tear_down(): void {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}ms_watch_sessions" ); // phpcs:ignore WordPress.DB
		parent::tear_down();
	}

	/**
	 * Owner decision on 10531602227: resume at the exact stop point, not the
	 * furthest point. Fails on 9da6331, which offered 480 (the furthest).
	 */
	public function test_resume_offers_the_exact_stop_after_a_rewind(): void {
		update_option( 'ms_player_resume', 1 );
		$user  = self::factory()->user->create();
		$video = self::factory()->post->create( array( 'post_type' => 'mediashield_video', 'post_status' => 'publish' ) );
		update_post_meta( $video, '_ms_duration', 600 );

		// Watch to 8:00, rewind to 3:00, leave.
		$token = SessionManager::start( $video, $user, '203.0.113.5', 'UA' )['session_token'];
		$this->assertTrue( SessionManager::heartbeat( $token, 480.0, 600.0, true, true, array() ) );
		SessionManager::end( $token, 180.0, 600.0, array() );

		// Come back: Resume from 3:00. A visit without play reports 0 and keeps it.
		$again = SessionManager::start( $video, $user, '203.0.113.5', 'UA' );
		$this->assertSame( 180.0, (float) $again['resume_position'] );
		SessionManager::heartbeat( $again['session_token'], 0.0, 600.0, false, true, array() );
		SessionManager::end( $again['session_token'], 0.0, 600.0, array() );
		$this->assertSame( 180.0, (float) SessionManager::start( $video, $user, '203.0.113.5', 'UA' )['resume_position'] );

		// Stored in the existing user-meta row, exported, and erased with it.
		$this->assertSame( 180.0, \MediaShield\Access\WatchedSpans::stop( $user, $video ) );
		$export = \MediaShield\Privacy\PrivacyExporter::export( get_userdata( $user )->user_email, 1 );
		$this->assertStringContainsString( '180', wp_json_encode( $export ) );
		\MediaShield\Privacy\PrivacyEraser::erase( get_userdata( $user )->user_email, 1 );
		$this->assertNull( \MediaShield\Access\WatchedSpans::stop( $user, $video ) );
	}

	/**
	 * Near the end the stop is kept as reported (the player offers nothing
	 * from 95%); a stop is never past the video's length.
	 */
	public function test_a_stop_near_the_end_is_kept_within_the_length(): void {
		$user  = self::factory()->user->create();
		$video = self::factory()->post->create( array( 'post_type' => 'mediashield_video', 'post_status' => 'publish' ) );
		\MediaShield\Access\WatchedSpans::record( $user, $video, array(), 600.0, 9999.0 );
		$this->assertSame( 600.0, \MediaShield\Access\WatchedSpans::stop( $user, $video ) );
	}

	/**
	 * Fails on 6744d4a: the newest session (a page view at 0) was read, so the
	 * second visit offered nothing.
	 */
	public function test_visits_without_play_keep_the_last_played_place(): void {
		global $wpdb;
		update_option( 'ms_player_resume', 1 );
		$user  = self::factory()->user->create();
		$video = self::factory()->post->create( array( 'post_type' => 'mediashield_video', 'post_status' => 'publish' ) );
		$table = $wpdb->prefix . 'ms_watch_sessions';
		$old   = gmdate( 'Y-m-d H:i:s', time() - HOUR_IN_SECONDS );
		$wpdb->insert( $table, array( 'video_id' => $video, 'user_id' => $user, 'session_token' => 'watched', 'started_at' => $old, 'last_heartbeat' => $old, 'max_position' => 47, 'total_seconds' => 30, 'is_active' => 0 ) ); // phpcs:ignore WordPress.DB

		foreach ( array( 1, 2, 3 ) as $visit ) {
			$session = SessionManager::start( $video, $user, '203.0.113.5', 'UA' );
			$this->assertSame( 47.0, (float) $session['resume_position'], "visit {$visit}" );
		}

		$never = self::factory()->post->create( array( 'post_type' => 'mediashield_video', 'post_status' => 'publish' ) );
		$this->assertSame( 0.0, (float) SessionManager::start( $never, $user, '203.0.113.5', 'UA' )['resume_position'] );
	}
}
