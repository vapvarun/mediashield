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

	/**
	 * Rows this test made. SessionManager::start() runs its own COMMIT, which
	 * ends the test transaction, so they are removed by id here.
	 *
	 * @var array<string, int[]>
	 */
	private array $made = array( 'videos' => array(), 'users' => array() );

	public function tear_down(): void {
		global $wpdb;
		foreach ( $this->made['videos'] as $id ) {
			$wpdb->delete( $wpdb->prefix . 'ms_watch_sessions', array( 'video_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( $wpdb->prefix . 'ms_milestones', array( 'video_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			wp_delete_post( $id, true );
		}
		foreach ( $this->made['users'] as $id ) {
			wp_delete_user( $id );
		}
		// After start()'s COMMIT the rollback no longer covers these rows: keep the clean-up.
		$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		parent::tear_down();
	}

	private function user( string $role = 'subscriber' ): int {
		$id                    = self::factory()->user->create( array( 'role' => $role ) );
		$this->made['users'][] = $id;
		return $id;
	}

	private function video(): int {
		return $this->made['videos'][] = self::factory()->post->create(
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
		$user = $this->user();
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
		$user = $this->user();
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
		$this->assertNull( Settings::sanitize( 'ms_watermark_swap_interval', '1.5' ), 'A whole-number setting refuses 1.5 instead of saving 1.' );
		$this->assertSame( 12, Settings::sanitize( 'ms_watermark_swap_interval', '12' ) );
	}

	public function test_names_and_titles_decode_numeric_entities(): void {
		$this->assertSame( "Lesson 3 \u{2013} Intro", \MediaShield\Support\Text::plain( 'Lesson 3 &#8211; Intro' ) );
		$this->assertSame( 'Tom & Jerry', \MediaShield\Support\Text::plain( 'Tom &amp; Jerry' ) );
		$this->assertSame( '<script>', \MediaShield\Support\Text::plain( '&lt;script&gt;' ), 'The result is text; callers escape it for output.' );
	}

	public function test_a_load_that_never_played_is_not_counted_as_a_view(): void {
		$admin = $this->user( 'administrator' );
		wp_set_current_user( $admin );
		// Other suites' sessions survive their own COMMITs, so compare with before.
		$before = (int) rest_do_request( new WP_REST_Request( 'GET', '/mediashield/v1/analytics/overview' ) )->get_data()['total_sessions'];
		$video  = $this->video();
		$users = array( $this->user(), $this->user(), $this->user() );
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
		$this->assertSame( $before + 1, (int) $overview['total_sessions'] );
	}
}
