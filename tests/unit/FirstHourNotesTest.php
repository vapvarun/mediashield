<?php
/**
 * Basecamp 10499286227 items 6-8: the end beacon keeps the place reached,
 * a number setting refuses text, and a load that never played is not a view.
 * Each test fails on Free be8d9c6.
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\Access\SessionManager;
use MediaShield\Core\Settings;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * End position, numeric settings, played sessions.
 */
class FirstHourNotesTest extends WP_UnitTestCase {

	private function video(): int {
		return self::factory()->post->create(
			array(
				'post_type'   => 'mediashield_video',
				'post_status' => 'publish',
			)
		);
	}

	private function session_row( string $token ): object {
		global $wpdb;
		$parsed = SessionManager::validate_token( $token );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}ms_watch_sessions WHERE id = %d", $parsed['session_id'] ) );
	}

	public function test_leaving_before_the_first_heartbeat_keeps_the_place_reached(): void {
		$user = self::factory()->user->create();
		wp_set_current_user( $user );
		$token = SessionManager::start( $this->video(), $user, '203.0.113.9', 'UA' )['session_token'];

		$request = new WP_REST_Request( 'POST', '/mediashield/v1/session/end' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( wp_json_encode( array( 'token' => $token, 'position' => 22, 'duration' => 100 ) ) );
		$this->assertSame( 200, rest_do_request( $request )->get_status() );

		$row = $this->session_row( $token );
		$this->assertSame( 22.0, (float) $row->max_position );
		$this->assertSame( 0, (int) $row->is_active );
	}

	public function test_an_end_with_only_the_token_still_works(): void {
		$user = self::factory()->user->create();
		wp_set_current_user( $user );
		$token = SessionManager::start( $this->video(), $user, '203.0.113.9', 'UA' )['session_token'];

		$request = new WP_REST_Request( 'POST', '/mediashield/v1/session/end' );
		$request->set_body_params( array( 'token' => $token ) );
		$this->assertSame( 200, rest_do_request( $request )->get_status() );
		$this->assertSame( 0.0, (float) $this->session_row( $token )->max_position );
	}

	public function test_a_number_setting_refuses_text(): void {
		$this->assertNull( Settings::sanitize( 'ms_watermark_opacity', 'abc' ) );
		$this->assertNull( Settings::sanitize( 'ms_watermark_swap_interval', 'soon' ) );
		$this->assertSame( 0.3, Settings::sanitize( 'ms_watermark_opacity', '0.3' ) );
		$this->assertSame( 1.0, Settings::sanitize( 'ms_watermark_opacity', 7 ) );
	}

	public function test_a_load_that_never_played_is_not_counted_as_a_view(): void {
		global $wpdb;
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$video = $this->video();
		$users = self::factory()->user->create_many( 3 );
		foreach ( $users as $i => $user ) {
			$token = SessionManager::start( $video, $user, '203.0.113.' . ( 20 + $i ), 'UA' )['session_token'];
			if ( 0 === $i ) {
				SessionManager::heartbeat( $token, 50, 100 );
			}
		}

		wp_set_current_user( $admin );
		$stats = rest_do_request( new WP_REST_Request( 'GET', '/mediashield/v1/videos/' . $video . '/stats' ) )->get_data();
		$this->assertSame( 1, (int) $stats['total_sessions'], 'Two loads that never played are not views.' );
		$this->assertSame( 50.0, (float) $stats['avg_completion'] );

		$overview = rest_do_request( new WP_REST_Request( 'GET', '/mediashield/v1/analytics/overview' ) )->get_data();
		$this->assertSame( 1, (int) $overview['total_sessions'] );
		$this->assertSame( (float) $overview['avg_completion'], (float) $overview['top_videos'][0]['avg_completion'], 'The Dashboard average and Top Videos agree.' );
	}
}
