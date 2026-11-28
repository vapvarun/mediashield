<?php
/**
 * Views count once per member per video (Basecamp 10551679560).
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Replays by one member do not add views; plays stay as total_sessions.
 */
class OneViewPerMemberTest extends WP_UnitTestCase {

	public function tear_down(): void {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}ms_watch_sessions" ); // phpcs:ignore WordPress.DB
		parent::tear_down();
	}

	private function play( int $video, int $user, string $started ): void {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB
			$wpdb->prefix . 'ms_watch_sessions',
			array(
				'video_id'       => $video,
				'user_id'        => $user,
				'session_token'  => wp_generate_password( 12, false ),
				'started_at'     => $started,
				'last_heartbeat' => $started,
				'max_position'   => 10,
				'total_seconds'  => 10,
				'completion_pct' => 20,
				'is_active'      => 0,
			)
		);
	}

	/**
	 * Fails on a06b681: the Views number was total_sessions (6), every play.
	 */
	public function test_a_member_replaying_counts_once(): void {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}ms_watch_sessions" ); // phpcs:ignore WordPress.DB
		$a     = self::factory()->user->create();
		$b     = self::factory()->user->create();
		$video = self::factory()->post->create( array( 'post_type' => 'mediashield_video', 'post_status' => 'publish' ) );
		$other = self::factory()->post->create( array( 'post_type' => 'mediashield_video', 'post_status' => 'publish' ) );
		$today = gmdate( 'Y-m-d H:i:s', time() - 60 );
		$ago   = gmdate( 'Y-m-d H:i:s', time() - 3 * DAY_IN_SECONDS );

		// Member A plays the video three times on two days, B once, A once more on another video.
		$this->play( $video, $a, $ago );
		$this->play( $video, $a, $today );
		$this->play( $video, $a, $today );
		$this->play( $video, $b, $today );
		$this->play( $other, $a, $today );
		// Two guest sessions stay two views (one guest per session).
		$this->play( $video, 0, $today );
		$this->play( $video, 0, $today );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$data = rest_do_request( new WP_REST_Request( 'GET', '/mediashield/v1/analytics/overview' ) )->get_data();

		$this->assertSame( 5, $data['total_views'] ?? null, 'A + B + 2 guests on the video, A on the other.' );
		$this->assertSame( 7, $data['total_sessions'], 'Plays are still every played session.' );
		$this->assertSame( 5, array_sum( array_map( static fn( $d ) => (int) $d['count'], $data['sessions_chart'] ) ), 'The chart adds up to Views.' );

		$top = array_column( $data['top_videos'], null, 'video_id' );
		$this->assertSame( 4, $top[ $video ]['view_count'] );
		$this->assertSame( 6, $top[ $video ]['session_count'] );
		$this->assertSame( 1, $top[ $other ]['view_count'] );
		$this->assertSame( $video, $data['top_videos'][0]['video_id'] );

		// Per-video stats use the same definition.
		$stats = rest_do_request( new WP_REST_Request( 'GET', '/mediashield/v1/videos/' . $video . '/stats' ) )->get_data();
		$this->assertSame( 4, $stats['unique_viewers'] );
	}
}
