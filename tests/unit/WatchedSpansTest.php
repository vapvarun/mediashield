<?php
/**
 * Regression test for Basecamp 10530930729 (1.4.3, QA bounce).
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\Access\SessionManager;
use MediaShield\Access\WatchedSpans;
use WP_UnitTestCase;

/**
 * "Watched" is time spent watching: skipping ahead earns nothing and a rewatch counts once.
 */
class WatchedSpansTest extends WP_UnitTestCase {

	/** @var int */
	private $user;

	/** @var int */
	private $video;

	public function set_up(): void {
		parent::set_up();
		$this->user  = self::factory()->user->create();
		$this->video = self::factory()->post->create( array( 'post_type' => 'mediashield_video', 'post_status' => 'publish' ) );
		update_post_meta( $this->video, '_ms_duration', 300 );
	}

	public function tear_down(): void {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}ms_watch_sessions" ); // phpcs:ignore WordPress.DB
		parent::tear_down();
	}

	/**
	 * Pretend the last report was $seconds ago.
	 */
	private function elapsed( int $seconds ): void {
		$all                    = (array) get_user_meta( $this->user, WatchedSpans::META_KEY, true );
		$all[ $this->video ]['t'] = time() - $seconds;
		update_user_meta( $this->user, WatchedSpans::META_KEY, $all );
	}

	/**
	 * Fails on f9275e6: watched_percent() was the furthest point, so playing
	 * 2 s, dragging to 4:08 and playing 20 s read as 89%.
	 */
	public function test_skipping_ahead_earns_nothing(): void {
		global $wpdb;
		$session = SessionManager::start( $this->video, $this->user, '203.0.113.5', 'UA' );
		$this->elapsed( 30 );
		SessionManager::heartbeat( $session['session_token'], 268, 300, true, true, array( array( 0, 2 ), array( 248, 268 ) ) );

		$this->assertSame( 7, SessionManager::watched_percent( $this->video, $this->user ), '22 s of 300 s' );
		$this->assertSame( 22, (int) $wpdb->get_var( $wpdb->prepare( "SELECT total_seconds FROM {$wpdb->prefix}ms_watch_sessions WHERE id = %d", $session['session_id'] ) ) ); // phpcs:ignore WordPress.DB
	}

	public function test_rewatch_counts_once_and_sittings_add_up(): void {
		WatchedSpans::start( $this->user, $this->video );
		$this->elapsed( 70 );
		WatchedSpans::record( $this->user, $this->video, array( array( 0, 60 ) ), 300 );
		$this->elapsed( 70 );
		WatchedSpans::record( $this->user, $this->video, array( array( 0, 60 ) ), 300 );
		$this->assertSame( 60.0, WatchedSpans::seconds( $this->user, $this->video ), 'The first minute twice is one minute.' );

		// A second sitting: 60-255 over a few reports.
		foreach ( array( array( 60, 120 ), array( 120, 180 ), array( 180, 240 ), array( 240, 255 ) ) as $span ) {
			$this->elapsed( 61 );
			WatchedSpans::record( $this->user, $this->video, array( $span ), 300 );
		}
		$this->assertSame( 85, WatchedSpans::percent( $this->user, $this->video ) );
	}

	public function test_a_report_longer_than_the_time_elapsed_is_refused(): void {
		WatchedSpans::start( $this->user, $this->video );
		$this->elapsed( 10 );
		$this->assertSame( 0.0, WatchedSpans::record( $this->user, $this->video, array( array( 0, 290 ) ), 300 ) );
		$this->assertSame( 0, WatchedSpans::percent( $this->user, $this->video ) );
	}
}
