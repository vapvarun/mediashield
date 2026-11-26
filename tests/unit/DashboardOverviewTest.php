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
		// Test change (Basecamp 10545260458): a video with no source is not
		// Ready and is not counted as protected, so the fixture has a link.
		update_post_meta( $id, '_ms_source_url', 'https://vimeo.com/76979871' );
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
		// Labelled change (Basecamp 10478806397): two more first-run keys.
		$this->assertSame(
			array(
				'has_video'     => false,
				'has_protected' => false,
				'has_embed'     => false,
				'has_view'      => false,
				'watch_url'     => '',
			),
			$fresh['first_run']
		);

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

	/**
	 * Basecamp 10478806397: the first run walks the owner to a page that
	 * shows the video and to watching it as a member, from their own content
	 * (no sample data). Fails before: no has_embed / watch_url, and the
	 * Analytics screens had no first-run state in the admin config.
	 */
	public function test_first_run_reaches_the_page_and_the_first_viewing(): void {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}ms_watch_sessions" ); // phpcs:ignore WordPress.DB
		delete_option( \MediaShield\CPT\VideoUsage::INDEX_PENDING );
		\MediaShield\CPT\VideoUsage::register();

		$video = $this->video( 'standard' );
		$this->assertFalse( $this->overview()['first_run']['has_embed'] );

		$draft = self::factory()->post->create( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_content' => '[mediashield id="' . $video . '"]' ) );
		$this->assertFalse( $this->overview()['first_run']['has_embed'], 'A draft is not a page members can open.' );

		$page  = self::factory()->post->create( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_content' => '[mediashield id="' . $video . '"]' ) );
		$state = $this->overview()['first_run'];
		$this->assertTrue( $state['has_embed'] );
		$this->assertSame( get_permalink( $page ), $state['watch_url'], 'Step 4 links the page that shows the video.' );
		$this->assertFalse( $state['has_view'] );

		// The admin config carries the same state for the Analytics screens.
		$this->assertSame( $state, \MediaShield\REST\AnalyticsController::first_run_state() );

		$now = current_time( 'mysql', true );
		$wpdb->insert( $wpdb->prefix . 'ms_watch_sessions', array( 'video_id' => $video, 'user_id' => 1, 'session_token' => 'first-view', 'started_at' => $now, 'last_heartbeat' => $now, 'max_position' => 12, 'total_seconds' => 12, 'is_active' => 0 ) ); // phpcs:ignore WordPress.DB
		$this->assertTrue( $this->overview()['first_run']['has_view'], 'Real data: the first-run state is no longer shown.' );
		unset( $draft );
	}
}
