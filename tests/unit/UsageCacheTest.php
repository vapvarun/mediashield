<?php
/**
 * My Videos and the old playlist address do not scan post content on every
 * view (ponytail #2).
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\CPT\VideoUsage;
use WP_UnitTestCase;

/**
 * Fails before: every My Videos render and every old playlist address ran
 * the post_content scan, and an LMS link did not spare it.
 */
class UsageCacheTest extends WP_UnitTestCase {

	/**
	 * Content scans seen by the query filter.
	 *
	 * @var int
	 */
	private $scans = 0;

	public function set_up(): void {
		parent::set_up();
		VideoUsage::register();
		VideoUsage::forget();
		add_filter(
			'query',
			function ( $sql ) {
				if ( false !== strpos( (string) $sql, 'post_content LIKE' ) && false !== strpos( (string) $sql, "'mediashield_playlist'" ) ) {
					++$this->scans;
				}
				return $sql;
			}
		);
	}

	public function tear_down(): void {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}ms_watch_sessions" ); // phpcs:ignore WordPress.DB
		remove_all_filters( 'mediashield_video_watch_url' );
		VideoUsage::flush();
		parent::tear_down();
	}

	/**
	 * A member with one played video shown on one page.
	 *
	 * @return array{0:int,1:int,2:int} Member, video, page.
	 */
	private function member_with_video(): array {
		global $wpdb;
		$member = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$video  = self::factory()->post->create( array( 'post_type' => 'mediashield_video', 'post_status' => 'publish', 'post_title' => 'Cached clip' ) );
		$page   = self::factory()->post->create( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_content' => '[mediashield id="' . $video . '"]' ) );
		$now    = current_time( 'mysql', true );
		$wpdb->insert( // phpcs:ignore WordPress.DB
			$wpdb->prefix . 'ms_watch_sessions',
			array(
				'video_id'       => $video,
				'user_id'        => $member,
				'session_token'  => 'usage-cache-' . $video,
				'started_at'     => $now,
				'last_heartbeat' => $now,
				'max_position'   => 40,
				'total_seconds'  => 40,
				'completion_pct' => 40,
				'is_active'      => 0,
			)
		);
		return array( $member, $video, $page );
	}

	/**
	 * A second view (a new request: this request's memory forgotten) reads
	 * the cache; an edit to a page with an embed brings the scan back once.
	 */
	public function test_second_my_videos_render_runs_no_scan(): void {
		list( $member, , $page ) = $this->member_with_video();
		wp_set_current_user( $member );

		$html = do_blocks( '<!-- wp:mediashield/my-videos /-->' );
		$this->assertStringContainsString( esc_url( get_permalink( $page ) ), $html );
		$this->assertSame( 1, $this->scans );

		VideoUsage::flush(); // Next request.
		$this->scans = 0;
		$html        = do_blocks( '<!-- wp:mediashield/my-videos /-->' );
		$this->assertStringContainsString( esc_url( get_permalink( $page ) ), $html );
		$this->assertSame( 0, $this->scans, 'The second view reads the cache.' );

		wp_update_post( array( 'ID' => $page, 'post_content' => 'No video here now.' ) );
		$this->scans = 0;
		$html        = do_blocks( '<!-- wp:mediashield/my-videos /-->' );
		$this->assertSame( 1, $this->scans, 'Editing the embed clears the answer.' );
		$this->assertStringNotContainsString( esc_url( get_permalink( $page ) ), $html );
	}

	/**
	 * An LMS that links the video spares the scan entirely.
	 */
	public function test_linked_video_is_not_scanned(): void {
		list( $member ) = $this->member_with_video();
		wp_set_current_user( $member );
		add_filter( 'mediashield_video_watch_url', fn() => 'https://example.org/lesson/1/' );

		$html = do_blocks( '<!-- wp:mediashield/my-videos /-->' );

		$this->assertStringContainsString( 'https://example.org/lesson/1/', $html );
		$this->assertSame( 0, $this->scans );
	}

	/**
	 * The old playlist address scans once, then reads the cache.
	 */
	public function test_old_playlist_address_is_cached(): void {
		$playlist = self::factory()->post->create( array( 'post_type' => 'mediashield_playlist', 'post_status' => 'publish' ) );
		$page     = self::factory()->post->create( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_content' => '[mediashield_playlist id="' . $playlist . '"]' ) );

		$this->assertSame( get_permalink( $page ), VideoUsage::page_for_playlist( $playlist ) );
		$this->assertSame( get_permalink( $page ), VideoUsage::page_for_playlist( $playlist ) );
		$this->assertSame( 1, $this->scans );
	}
}
