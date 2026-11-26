<?php
/**
 * Regression test for the 1.4.4 standalone deep check.
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Every player opens a session on page load, before anything plays. The
 * Viewers list, a viewer's detail and the member's own My Videos counted
 * those as watching: a member who scrolled past five videos and played one
 * showed five videos watched. Only sessions that played count
 * (SessionManager::played_sql()), as on the dashboard. Fails on 1.4.3.
 */
class PlayedSessionsOnlyTest extends WP_UnitTestCase {

	public function tear_down(): void {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}ms_watch_sessions" ); // phpcs:ignore WordPress.DB
		parent::tear_down();
	}

	private function session( int $video, int $user, float $position, int $seconds ): void {
		global $wpdb;
		$now = current_time( 'mysql', true );
		$wpdb->insert( // phpcs:ignore WordPress.DB
			$wpdb->prefix . 'ms_watch_sessions',
			array(
				'video_id'       => $video,
				'user_id'        => $user,
				'session_token'  => wp_generate_password( 12, false ),
				'started_at'     => $now,
				'last_heartbeat' => $now,
				'max_position'   => $position,
				'total_seconds'  => $seconds,
				'completion_pct' => $position > 0 ? 50 : 0,
				'is_active'      => 0,
			)
		);
	}

	private function get( string $route, int $as ): array {
		wp_set_current_user( $as );
		return (array) rest_do_request( new WP_REST_Request( 'GET', $route ) )->get_data();
	}

	public function test_page_loads_without_play_are_not_watching(): void {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}ms_watch_sessions" ); // phpcs:ignore WordPress.DB
		$member = self::factory()->user->create( array( 'display_name' => 'Sam Viewer' ) );
		$admin  = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$played = self::factory()->post->create( array( 'post_type' => 'mediashield_video', 'post_status' => 'publish' ) );
		$this->session( $played, $member, 30, 30 );
		foreach ( range( 1, 4 ) as $i ) {
			$scrolled = self::factory()->post->create( array( 'post_type' => 'mediashield_video', 'post_status' => 'publish' ) );
			$this->session( $scrolled, $member, 0, 0 ); // Loaded on the page, never played.
		}

		$users = $this->get( '/mediashield/v1/analytics/users', $admin );
		$row   = current( array_filter( (array) ( $users['items'] ?? $users ), static fn( $r ) => (int) ( $r['user_id'] ?? 0 ) === $member ) );
		$this->assertNotFalse( $row, 'the member who played is listed' );
		$this->assertSame( 1, (int) $row['videos_watched'], 'one video played, four only loaded' );

		$mine = $this->get( '/mediashield/v1/analytics/my-videos', $member );
		$this->assertCount( 1, (array) ( $mine['items'] ?? $mine ), 'My Videos lists only what was played' );
	}
}
