<?php
/**
 * Regression test for the 1.4.4 standalone deep check.
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use WP_UnitTestCase;

/**
 * My Videos on a site with no LMS leads back to the video: the title links
 * the published page that shows it. In 1.4.3 the titles were plain text and
 * there was no Resume link, so a member could not find the video again.
 * A draft or password-protected page is never offered.
 */
class MyVideosWatchPageTest extends WP_UnitTestCase {

	public function tear_down(): void {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}ms_watch_sessions" ); // phpcs:ignore WordPress.DB
		parent::tear_down();
	}

	public function test_title_links_the_page_that_shows_the_video(): void {
		global $wpdb;
		$member = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$video  = self::factory()->post->create(
			array(
				'post_type'   => 'mediashield_video',
				'post_status' => 'publish',
				'post_title'  => 'Lighting basics',
			)
		);
		$draft  = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'draft',
				'post_content' => '[mediashield id="' . $video . '"]',
			)
		);
		$page   = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => '[mediashield id="' . $video . '"]',
			)
		);
		$now = current_time( 'mysql', true );
		$wpdb->insert( // phpcs:ignore WordPress.DB
			$wpdb->prefix . 'ms_watch_sessions',
			array(
				'video_id'       => $video,
				'user_id'        => $member,
				'session_token'  => 'my-videos-link',
				'started_at'     => $now,
				'last_heartbeat' => $now,
				'max_position'   => 40,
				'total_seconds'  => 40,
				'completion_pct' => 40,
				'is_active'      => 0,
			)
		);

		wp_set_current_user( $member );
		$html = do_blocks( '<!-- wp:mediashield/my-videos /-->' );

		$this->assertStringContainsString( 'Lighting basics', $html );
		$this->assertStringContainsString( 'href="' . esc_url( get_permalink( $page ) ) . '"', $html, 'the title links the published page with the video' );
		$this->assertStringNotContainsString( (string) get_permalink( $draft ), $html, 'a draft is never offered' );
	}
}
