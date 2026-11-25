<?php
/**
 * Basecamp 10541776164, 10537102216 (1.4.4): a lesson's watch rule on a
 * video at None or Basic.
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\Access\SessionManager;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Measuring a required watch is not protection: None and Basic record it,
 * with no stream limit and no watermark.
 */
class WatchRuleLowLevelsTest extends WP_UnitTestCase {

	/** @var int */
	private $member;

	public function set_up(): void {
		parent::set_up();
		$this->member = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		// Explicit: an earlier test's migrator run can commit a stored value.
		update_option( 'ms_lms_require_watch_pct', 0 );
	}

	public function tear_down(): void {
		delete_option( 'ms_lms_require_watch_pct' );
		delete_option( 'ms_max_concurrent_streams' );
		remove_filter( 'mediashield_measure_watch', '__return_true' );
		parent::tear_down();
	}

	private function video( string $level ): int {
		$id = self::factory()->post->create( array( 'post_type' => 'mediashield_video', 'post_status' => 'publish' ) );
		update_post_meta( $id, '_ms_platform', 'self' );
		update_post_meta( $id, '_ms_source_url', 'https://example.org/lesson.mp4' );
		update_post_meta( $id, '_ms_protection_level', $level );
		return $id;
	}

	private static function played( array $session ): void {
		global $wpdb;
		$wpdb->update( $wpdb->prefix . 'ms_watch_sessions', array( 'max_position' => 12, 'total_seconds' => 12 ), array( 'id' => (int) $session['session_id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	/**
	 * The share rises for a Basic video in a lesson that asks 2%, and the
	 * viewing carries no watermark. Fails on 445482c (watermark enabled).
	 */
	public function test_basic_video_share_rises_without_a_watermark(): void {
		update_option( 'ms_lms_require_watch_pct', 2 );
		$video = $this->video( 'basic' );
		wp_set_current_user( $this->member );

		$start = new WP_REST_Request( 'POST', '/mediashield/v1/session/start' );
		$start->set_body_params( array( 'video_id' => $video ) );
		$data = rest_do_request( $start )->get_data();

		$this->assertNotEmpty( $data['session_token'] );
		$this->assertFalse( (bool) $data['watermark_config']['enabled'], 'Basic shows no watermark.' );

		$beat = new WP_REST_Request( 'POST', '/mediashield/v1/session/heartbeat' );
		$beat->set_header( 'Content-Type', 'application/json' );
		$beat->set_body( wp_json_encode( array( 'token' => $data['session_token'], 'position' => 2.5, 'duration' => 100, 'playing' => true, 'spans' => array( array( 0, 2.5 ) ) ) ) );
		$answer = rest_do_request( $beat )->get_data();

		$this->assertGreaterThanOrEqual( 2, $answer['watched_pct'] );
		$this->assertTrue( SessionManager::watch_requirement( $video, $this->member )['met'] );
	}

	/**
	 * None and Basic never had a stream limit, and measuring a lesson does not
	 * add one; their viewings do not use up a stream for Standard either.
	 * Fails on 445482c.
	 */
	public function test_stream_limit_does_not_apply_to_none_or_basic(): void {
		update_option( 'ms_max_concurrent_streams', 1 );
		$standard = $this->video( 'standard' );
		$basic    = $this->video( 'basic' );
		$none     = $this->video( 'none' );

		// The laptop is playing a Standard video.
		self::played( SessionManager::start( $standard, $this->member, '203.0.113.9', 'Laptop' ) );
		// The phone measures a Basic lesson: not refused.
		$this->assertIsArray( SessionManager::start( $basic, $this->member, '203.0.113.10', 'Phone' ) );

		// A None lesson playing on a tablet does not stop the laptop's Standard video.
		$member2 = self::factory()->user->create();
		self::played( SessionManager::start( $none, $member2, '203.0.113.20', 'Tablet' ) );
		$this->assertIsArray( SessionManager::start( $standard, $member2, '203.0.113.21', 'Laptop' ) );

		// A second Standard device is still refused.
		$this->assertFalse( SessionManager::start( $standard, $member2, '203.0.113.22', 'Phone' ) );
	}

	/**
	 * The player is told the site rule applies, for a member only. Fails on 445482c.
	 */
	public function test_player_is_marked_when_the_site_rule_applies(): void {
		$video = $this->video( 'none' );
		wp_set_current_user( $this->member );
		$this->assertStringNotContainsString( 'data-watch-track', \MediaShield\Player\Renderer::render( $video ), 'No rule: nothing.' );

		update_option( 'ms_lms_require_watch_pct', 2 );
		// Where an LMS says the site rule applies (Free review #8).
		add_filter( 'mediashield_measure_watch', '__return_true' );
		$this->assertStringContainsString( 'data-watch-track="1"', \MediaShield\Player\Renderer::render( $video ) );

		wp_set_current_user( 0 );
		update_option( 'ms_require_login', 0 );
		$this->assertStringNotContainsString( 'data-watch-track', \MediaShield\Player\Renderer::render( $video ), 'A guest is unchanged.' );
	}
}
