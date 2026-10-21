<?php
/**
 * The heartbeat limit belongs to a session, not to the viewer.
 *
 * It was keyed on the user id, so a member with three players on one page
 * (each beats about twice a minute) went over the 4 a minute limit and lost
 * progress beats with 429s.
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\Access\SessionManager;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Per-session heartbeat limit.
 */
class HeartbeatRateLimitTest extends WP_UnitTestCase {

	/**
	 * Send one heartbeat and return the HTTP status.
	 *
	 * @param string $token Session token.
	 * @return int
	 */
	private function beat( string $token ): int {
		$request = new WP_REST_Request( 'POST', '/mediashield/v1/session/heartbeat' );
		$request->set_body_params(
			array(
				'token'    => $token,
				'position' => 5,
				'duration' => 100,
				'playing'  => true,
				'focused'  => true,
			)
		);

		return rest_do_request( $request )->get_status();
	}

	/**
	 * Fails on the per-user key: the fifth beat across two players is refused.
	 */
	public function test_two_players_do_not_share_a_heartbeat_budget(): void {
		$user = self::factory()->user->create();
		wp_set_current_user( $user );
		update_option( 'ms_max_concurrent_streams', 5 );

		$tokens = array();
		foreach ( array( '203.0.113.1', '203.0.113.2' ) as $ip ) {
			$video    = self::factory()->post->create(
				array(
					'post_type'   => 'mediashield_video',
					'post_status' => 'publish',
				)
			);
			$session  = SessionManager::start( $video, $user, $ip, 'UA' );
			$tokens[] = $session['session_token'];
		}

		for ( $i = 0; $i < 3; $i++ ) {
			$this->assertNotSame( 429, $this->beat( $tokens[0] ), "Player one beat {$i}." );
			$this->assertNotSame( 429, $this->beat( $tokens[1] ), "Player two beat {$i}." );
		}
	}

	/**
	 * A single session is still capped, so the limit has not been removed.
	 */
	public function test_one_session_is_still_limited(): void {
		$user = self::factory()->user->create();
		wp_set_current_user( $user );

		$video   = self::factory()->post->create(
			array(
				'post_type'   => 'mediashield_video',
				'post_status' => 'publish',
			)
		);
		$session = SessionManager::start( $video, $user, '203.0.113.5', 'UA' );

		for ( $i = 0; $i < 4; $i++ ) {
			$this->assertNotSame( 429, $this->beat( $session['session_token'] ) );
		}
		$this->assertSame( 429, $this->beat( $session['session_token'] ) );
	}
}
