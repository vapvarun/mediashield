<?php
/**
 * Regression tests for anonymous watch sessions.
 *
 * These collisions were all unreachable while guests were blocked outright.
 * Allowing guest playback (BC#10217643237) made every one of them live, so
 * they are pinned here rather than left to be rediscovered.
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\Access\SessionManager;
use WP_UnitTestCase;

/**
 * Guest session isolation and limits.
 */
class GuestSessionTest extends WP_UnitTestCase {

	/**
	 * Video under test.
	 *
	 * @var int
	 */
	private int $video_id = 0;

	/**
	 * Create a video fixture.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->video_id = self::factory()->post->create(
			array(
				'post_type'   => 'mediashield_video',
				'post_status' => 'publish',
			)
		);
	}

	/**
	 * Two guests are not the same viewer.
	 *
	 * The dedup lookup keyed on user_id, and every guest is user_id 0, so the
	 * second visitor was handed the first visitor's session token — and with it
	 * their resume position. A privacy leak, not just a counting error.
	 */
	public function test_two_guests_get_separate_sessions(): void {
		$first  = SessionManager::start( $this->video_id, 0, '203.0.113.1', 'UA-A' );
		$second = SessionManager::start( $this->video_id, 0, '203.0.113.2', 'UA-B' );

		$this->assertIsArray( $first );
		$this->assertIsArray( $second );
		$this->assertNotSame(
			$first['session_id'],
			$second['session_id'],
			'Each guest must get their own session row.'
		);
		$this->assertNotSame( $first['session_token'], $second['session_token'] );
	}

	/**
	 * A guest must never resume someone else's position.
	 */
	public function test_guest_session_never_resumes(): void {
		SessionManager::start( $this->video_id, 0, '203.0.113.1', 'UA-A' );
		$second = SessionManager::start( $this->video_id, 0, '203.0.113.2', 'UA-B' );

		$this->assertFalse( $second['is_resumed'] );
		$this->assertSame( 0.0, (float) $second['resume_position'] );
	}

	/**
	 * The concurrent-stream limit counted every guest on the site into one
	 * bucket, so at the default of 2 the third simultaneous visitor to a public
	 * video anywhere was refused. A limit exists to stop credential sharing;
	 * with no account to share there is nothing to enforce.
	 */
	public function test_guests_are_not_subject_to_the_concurrent_limit(): void {
		update_option( 'ms_max_concurrent_streams', 2 );

		$results = array();
		for ( $i = 0; $i < 5; $i++ ) {
			$results[] = SessionManager::start( $this->video_id, 0, '203.0.113.' . $i, 'UA' );
		}

		foreach ( $results as $index => $result ) {
			$this->assertIsArray( $result, "Guest {$index} should not be refused." );
		}
	}

	/**
	 * Logged-in users must still be limited — the guest carve-out is not a
	 * back door around concurrency for real accounts.
	 */
	public function test_logged_in_users_still_hit_the_concurrent_limit(): void {
		update_option( 'ms_max_concurrent_streams', 1 );
		$user = self::factory()->user->create();

		$other_video = self::factory()->post->create(
			array(
				'post_type'   => 'mediashield_video',
				'post_status' => 'publish',
			)
		);

		$first = SessionManager::start( $this->video_id, $user, '203.0.113.9', 'UA' );
		// Test change (1.4.4): only a viewing that played counts, so the first
		// device plays (players open a viewing at the first play).
		self::mark_played( $first );
		// A different device: the same address and browser counts as one.
		$second = SessionManager::start( $other_video, $user, '203.0.113.10', 'Other UA' );

		$this->assertIsArray( $first );
		$this->assertFalse( $second, 'A second concurrent stream for one user must be refused.' );
	}

	/**
	 * Fails on 1.4.3: a second device opening the SAME video was handed the
	 * first device's session, was never counted, and then let a third device
	 * in. The open session is reused only by the device that opened it.
	 */
	public function test_another_device_on_the_same_video_counts_toward_the_limit(): void {
		update_option( 'ms_max_concurrent_streams', 1 );
		$user = self::factory()->user->create();

		$first  = SessionManager::start( $this->video_id, $user, '203.0.113.9', 'UA' );
		$again  = SessionManager::start( $this->video_id, $user, '203.0.113.9', 'UA' );
		self::mark_played( $first ); // Test change (1.4.4): see above.
		$second = SessionManager::start( $this->video_id, $user, '203.0.113.10', 'Other UA' );

		$this->assertIsArray( $first );
		$this->assertIsArray( $again, 'the same device reopening the video keeps its session' );
		$this->assertSame( $first['session_id'], $again['session_id'] );
		$this->assertFalse( $second, 'another device on the same video is one more stream and is refused' );
	}

	/**
	 * Fails on 9b9f5eb: a viewing opened on another device that never played
	 * (a lesson page left open; a pre-1.4.4 player opened one on load) took
	 * the member's only stream, so they were refused with nothing playing.
	 */
	public function test_an_unplayed_viewing_on_another_device_does_not_block(): void {
		update_option( 'ms_max_concurrent_streams', 1 );
		$user = self::factory()->user->create();

		$idle = SessionManager::start( $this->video_id, $user, '203.0.113.9', 'Tablet' );
		// Opened a minute ago and never played.
		self::started_ago( $idle, 60 );
		$second = SessionManager::start( $this->video_id, $user, '203.0.113.10', 'Laptop' );

		$this->assertIsArray( $idle );
		$this->assertIsArray( $second, 'Nothing was playing on the tablet.' );

		// Once the laptop plays, a third device is refused as before.
		self::mark_played( $second );
		$this->assertFalse( SessionManager::start( $this->video_id, $user, '203.0.113.11', 'Phone' ) );
	}

	/**
	 * QA round 2: a second device pressing Play within seconds of the first,
	 * before the first had reported, was let in. A viewing counts from the
	 * moment it starts. Fails on 0028c0d.
	 */
	public function test_a_second_device_pressing_play_at_once_is_refused(): void {
		update_option( 'ms_max_concurrent_streams', 1 );
		$user = self::factory()->user->create();

		$first  = SessionManager::start( $this->video_id, $user, '203.0.113.9', 'Laptop' );
		$second = SessionManager::start( $this->video_id, $user, '203.0.113.10', 'Phone' );

		$this->assertIsArray( $first );
		$this->assertFalse( $second, 'The laptop started playing 0 s ago and has not reported yet.' );
	}

	/**
	 * Move a viewing's start back in time.
	 *
	 * @param array $session From SessionManager::start().
	 * @param int   $seconds How long ago.
	 */
	private static function started_ago( array $session, int $seconds ): void {
		global $wpdb;
		$wpdb->update( $wpdb->prefix . 'ms_watch_sessions', array( 'started_at' => gmdate( 'Y-m-d H:i:s', time() - $seconds ) ), array( 'id' => (int) $session['session_id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * Record that a viewing played, as its first heartbeat does.
	 *
	 * @param array $session From SessionManager::start().
	 */
	private static function mark_played( array $session ): void {
		global $wpdb;
		$wpdb->update( $wpdb->prefix . 'ms_watch_sessions', array( 'max_position' => 12, 'total_seconds' => 12 ), array( 'id' => (int) $session['session_id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}
}
