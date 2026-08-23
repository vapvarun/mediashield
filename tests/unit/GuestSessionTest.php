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

		$first  = SessionManager::start( $this->video_id, $user, '203.0.113.9', 'UA' );
		$second = SessionManager::start( $other_video, $user, '203.0.113.9', 'UA' );

		$this->assertIsArray( $first );
		$this->assertFalse( $second, 'A second concurrent stream for one user must be refused.' );
	}
}
