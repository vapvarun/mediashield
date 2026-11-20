<?php
/**
 * Regression test for Basecamp 10530930901 (1.4.3).
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use WP_REST_Request;
use WP_UnitTestCase;

/**
 * The dashboard overview: every day of the period, one milestone row per
 * viewer and video, the Protection card and the first-run steps.
 */
class DashboardOverviewTest extends WP_UnitTestCase {

	private function overview( string $period = '7d' ): array {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$request = new WP_REST_Request( 'GET', '/mediashield/v1/analytics/overview' );
		$request->set_param( 'period', $period );
		return (array) rest_do_request( $request )->get_data();
	}

	private function video( string $level = '' ): int {
		$id = self::factory()->post->create( array( 'post_type' => 'mediashield_video', 'post_status' => 'publish' ) );
		if ( '' !== $level ) {
			update_post_meta( $id, '_ms_protection_level', $level );
		}
		return $id;
	}

	public function tear_down(): void {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}ms_watch_sessions" ); // phpcs:ignore WordPress.DB
		$wpdb->query( "DELETE FROM {$wpdb->prefix}ms_milestones" ); // phpcs:ignore WordPress.DB
		parent::tear_down();
	}

	/**
	 * Fails on 0f2d9f1: only the one day with sessions came back, so the
	 * "Last 7 days" line chart had a single point and looked empty; Aisha
	 * finishing one video was four milestone rows, 100% above 25%.
	 */
	public function test_single_day_and_one_row_per_viewer_and_video(): void {
		global $wpdb;
		// session/start commits outside the test transaction; start clean.
		$wpdb->query( "DELETE FROM {$wpdb->prefix}ms_watch_sessions" ); // phpcs:ignore WordPress.DB
		$video = $this->video();
		$user  = self::factory()->user->create( array( 'display_name' => 'Aisha Khan' ) );
		$now   = current_time( 'mysql', true );
		foreach ( array( 1, 2, 3, 4 ) as $i ) {
			$wpdb->insert( $wpdb->prefix . 'ms_watch_sessions', array( 'video_id' => $video, 'user_id' => $user, 'session_token' => "t{$i}", 'started_at' => $now, 'last_heartbeat' => $now, 'max_position' => 10, 'total_seconds' => 10, 'is_active' => 0 ) ); // phpcs:ignore WordPress.DB
		}
		foreach ( array( 25, 50, 75, 100 ) as $pct ) {
			$wpdb->insert( $wpdb->prefix . 'ms_milestones', array( 'video_id' => $video, 'user_id' => $user, 'milestone_pct' => $pct, 'reached_at' => $now, 'session_id' => 1 ) ); // phpcs:ignore WordPress.DB
		}

		$data  = $this->overview( '7d' );
		$chart = $data['sessions_chart'];

		$this->assertGreaterThanOrEqual( 7, count( $chart ), 'Every day of the week, quiet days at 0.' );
		$this->assertSame( 4, array_sum( array_column( $chart, 'count' ) ) );
		$this->assertSame( ( new \DateTimeImmutable( 'now', wp_timezone() ) )->format( 'Y-m-d' ), end( $chart )['date'] );

		$this->assertCount( 1, $data['recent_milestones'] );
		$this->assertSame( 100, $data['recent_milestones'][0]['milestone_pct'] );
		$this->assertSame( 'Aisha Khan', $data['recent_milestones'][0]['user_name'] );
	}

	/**
	 * Fails on 0f2d9f1: no protection or first-run data in the overview.
	 */
	public function test_protection_card_and_first_run(): void {
		global $wpdb;
		// session/start commits outside the test transaction; start clean.
		$wpdb->query( "DELETE FROM {$wpdb->prefix}ms_watch_sessions" ); // phpcs:ignore WordPress.DB
		update_option( 'ms_default_protection', 'standard' );

		$fresh = $this->overview();
		$this->assertSame( array( 'has_video' => false, 'has_protected' => false, 'has_view' => false ), $fresh['first_run'] );

		$this->video();          // Site default: Standard.
		$this->video( 'strict' );
		$this->video( 'none' );

		$data = $this->overview();
		$this->assertSame( 2, $data['protection']['protected'] );
		$this->assertSame( 3, $data['protection']['total'] );
		$this->assertTrue( $data['first_run']['has_video'] );
		$this->assertTrue( $data['first_run']['has_protected'] );
		$this->assertFalse( $data['first_run']['has_view'] );
		$this->assertArrayHasKey( 'alerts', $data['protection'] );
	}
}
