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
