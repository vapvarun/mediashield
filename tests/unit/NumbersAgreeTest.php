<?php
/**
 * Dashboard, Analytics and My Videos give the same completion for the same member.
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Basecamp 10545260603: averages were taken over page loads and sessions,
 * so one member read 47% on the Dashboard, 47% in Viewers and 100% / 50%
 * in My Videos, and an open page that never played was an "active viewer".
 */
class NumbersAgreeTest extends WP_UnitTestCase {

	public function tear_down(): void {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}ms_watch_sessions" ); // phpcs:ignore WordPress.DB
		parent::tear_down();
	}

	/**
	 * Insert one session.
	 */
	private function session( int $video, int $user, float $pct, bool $active = false ): void {
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
				'max_position'   => $pct,
				'total_seconds'  => (int) $pct,
				'completion_pct' => $pct,
				'is_active'      => $active ? 1 : 0,
			)
		);
	}

	private function get( string $route, int $as ): array {
		wp_set_current_user( $as );
		return (array) rest_do_request( new WP_REST_Request( 'GET', $route ) )->get_data();
	}

	public function test_one_member_reads_the_same_everywhere(): void {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}ms_watch_sessions" ); // phpcs:ignore WordPress.DB
		$member = self::factory()->user->create( array( 'display_name' => 'Aisha Khan' ) );
		$admin  = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$a      = self::factory()->post->create( array( 'post_type' => 'mediashield_video', 'post_status' => 'publish' ) );
		$b      = self::factory()->post->create( array( 'post_type' => 'mediashield_video', 'post_status' => 'publish' ) );
		update_post_meta( $a, '_ms_source_url', 'https://vimeo.com/76979871' );
		update_post_meta( $b, '_ms_source_url', 'https://vimeo.com/76979872' );

		// Video A: watched to 40%, then again to the end. Video B: half.
		$this->session( $a, $member, 40 );
		$this->session( $a, $member, 100 );
		$this->session( $b, $member, 50 );
		// A page left open that never played is not a viewer.
		$this->session( $b, $member, 0, true );

		$mine = array_column( $this->get( '/mediashield/v1/analytics/my-videos', $member ), 'completion_pct', 'video_id' );
		$this->assertEquals( array( $a => 100, $b => 50 ), $mine );

		$users = $this->get( '/mediashield/v1/analytics/users', $admin );
		$row   = current( array_filter( $users, static fn( $r ) => (int) $r['user_id'] === $member ) );
		$this->assertEquals( 75, $row['avg_completion'], 'Viewers: the average of the member\'s best on each video, as My Videos shows.' );

		$overview = $this->get( '/mediashield/v1/analytics/overview', $admin );
		$this->assertEquals( 75, $overview['avg_completion'], 'Dashboard: the same rule.' );
		$this->assertSame( 0, $overview['active_viewers'], 'A page that never played is not an active viewer.' );
		$top = array_column( $overview['top_videos'], 'avg_completion', 'video_id' );
		$this->assertEquals( 100, $top[ $a ] );

		$stats = $this->get( '/mediashield/v1/videos/' . $a . '/stats', $admin );
		$this->assertEquals( 100, $stats['avg_completion'] );
		$this->assertSame( 2, $stats['total_sessions'] );
		$this->assertSame( 1, $stats['unique_viewers'] );
	}
}
