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

	/**
	 * Made-up tokens share one bucket per caller: limited, and one transient.
	 * Keyed on the token text they each got a fresh bucket and an options row.
	 */
	public function test_a_flood_of_invalid_tokens_is_limited(): void {
		global $wpdb;

		wp_set_current_user( self::factory()->user->create() );
		$like   = $wpdb->esc_like( '_transient_ms_rate_' ) . '%';
		$before = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) );

		$statuses = array();
		for ( $i = 0; $i < 10; $i++ ) {
			$statuses[] = $this->beat( "{$i}|1|1|1700000000|" . str_repeat( 'a', 64 ) );
		}

		$after = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) );
		$this->assertSame( 429, $statuses[9] );
		$this->assertLessThanOrEqual( 1, $after - $before );
	}

	/**
	 * The config printed into the page names no viewer, so a page cache cannot
	 * hand one visitor's name or IP to the next.
	 */
	public function test_page_watermark_config_names_no_viewer(): void {
		wp_set_current_user( self::factory()->user->create( array( 'display_name' => 'Alice Member' ) ) );
		$_SERVER['REMOTE_ADDR'] = '203.0.113.9';

		$config = \MediaShield\Player\Watermark::get_config();

		$this->assertArrayNotHasKey( 'ip', $config );
		$this->assertArrayNotHasKey( 'text', $config );
	}

	/**
	 * The bucket is keyed on the token text, so a token must have exactly one
	 * valid spelling. The timestamp used to be cast before it was signed, so
	 * "<ts>x" validated as the same session and each spelling got a fresh bucket.
	 */
	public function test_a_respelled_token_is_not_valid(): void {
		$user    = self::factory()->user->create();
		$video   = self::factory()->post->create(
			array(
				'post_type'   => 'mediashield_video',
				'post_status' => 'publish',
			)
		);
		$session = SessionManager::start( $video, $user, '203.0.113.6', 'UA' );

		$this->assertIsArray( SessionManager::validate_token( $session['session_token'] ) );

		$parts    = explode( '|', $session['session_token'] );
		$parts[3] = $parts[3] . 'x';
		$this->assertFalse( SessionManager::validate_token( implode( '|', $parts ) ) );
	}
}
