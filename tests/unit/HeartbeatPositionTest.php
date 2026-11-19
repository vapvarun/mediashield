<?php
/**
 * Regression test for Basecamp 10526150191 (1.4.3).
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\Access\SessionManager;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * A heartbeat cannot report a place before the start of the video.
 */
class HeartbeatPositionTest extends WP_UnitTestCase {

	/**
	 * Fails on 1.4.2: a negative position was accepted (the request went on to the token check).
	 */
	public function test_negative_position_is_refused(): void {
		wp_set_current_user( self::factory()->user->create() );
		foreach ( array( 'position', 'duration' ) as $field ) {
			$request = new WP_REST_Request( 'POST', '/mediashield/v1/session/heartbeat' );
			$request->set_param( 'token', 'x' );
			$request->set_param( 'position', 5 );
			$request->set_param( 'duration', 60 );
			$request->set_param( $field, -50 );
			$response = rest_do_request( $request );
			$this->assertSame( 400, $response->get_status(), $field );
			$this->assertSame( 'rest_invalid_param', $response->as_error()->get_error_code(), $field );
		}

		// The end route already declared minimum 0; it holds.
		$request = new WP_REST_Request( 'POST', '/mediashield/v1/session/end' );
		$request->set_param( 'token', 'x' );
		$request->set_param( 'position', -50 );
		$this->assertSame( 400, rest_do_request( $request )->get_status(), 'end' );
	}

	/**
	 * Fails on 1.4.2: the service stored a negative completion for a direct caller.
	 */
	public function test_service_clamps_a_negative_position(): void {
		global $wpdb;
		$user  = self::factory()->user->create();
		$video = self::factory()->post->create(
			array(
				'post_type'   => 'mediashield_video',
				'post_status' => 'publish',
			)
		);
		$session = SessionManager::start( $video, $user, '203.0.113.9', 'UA' );
		SessionManager::heartbeat( $session['session_token'], -50.0, 100.0 );

		$pct = (float) $wpdb->get_var( $wpdb->prepare( "SELECT completion_pct FROM {$wpdb->prefix}ms_watch_sessions WHERE user_id = %d AND video_id = %d", $user, $video ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$this->assertGreaterThanOrEqual( 0.0, $pct );
	}
}
