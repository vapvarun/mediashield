<?php
/**
 * Admin findings from the independent UX test (MSUX).
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Each test names the finding and fails on the code before its fix.
 */
class AdminUxRoundTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		\MediaShield\REST\VideoListController::register_hooks();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * MSUX-12: a watched YouTube video showed "-" for its length: the list
	 * read only the stored length, not the one members' players reported.
	 */
	public function test_list_shows_the_length_players_reported(): void {
		$id = self::factory()->post->create(
			array(
				'post_type'   => 'mediashield_video',
				'post_status' => 'publish',
				'meta_input'  => array(
					'_ms_platform'          => 'youtube',
					'_ms_platform_video_id' => 'jNQXAC9IVRw',
					'_ms_source_url'        => 'https://www.youtube.com/watch?v=jNQXAC9IVRw',
				),
			)
		);
		update_post_meta( $id, \MediaShield\Access\WatchedSpans::MEASURED, array( 7 => 19.2 ) );

		$request = new WP_REST_Request( 'GET', '/wp/v2/mediashield-videos' );
		$request->set_param( 'include', array( $id ) );
		$request->set_param( '_fields', 'id,ms_list' );
		$row = rest_do_request( $request )->get_data()[0];

		$this->assertSame( 19, $row['ms_list']['duration'] );
	}

	/**
	 * MSUX-27: the embed box led with a PHP template snippet and told owners
	 * to paste the shortcode "using the Classic Editor".
	 */
	public function test_embed_box_leads_with_the_block(): void {
		$id = self::factory()->post->create( array( 'post_type' => 'mediashield_video', 'post_status' => 'publish' ) );
		ob_start();
		\MediaShield\CPT\VideoPostType::render_embed_meta_box( get_post( $id ) );
		$html = (string) ob_get_clean();
		$this->assertStringNotContainsString( 'do_shortcode', $html );
		$this->assertStringNotContainsString( 'PHP Template', $html );
		$this->assertLessThan( strpos( $html, 'ms-shortcode-input' ), strpos( $html, 'MediaShield Video block' ), 'The block comes first.' );
	}

	/**
	 * MSUX-13: every Viewers row offered Stop playback, even for a member
	 * who watched yesterday. The list now says who is watching now.
	 */
	public function test_viewers_list_says_who_is_watching_now(): void {
		global $wpdb;
		$video = self::factory()->post->create( array( 'post_type' => 'mediashield_video', 'post_status' => 'publish' ) );
		$live  = self::factory()->user->create( array( 'display_name' => 'Live Member' ) );
		$idle  = self::factory()->user->create( array( 'display_name' => 'Idle Member' ) );
		$now   = current_time( 'mysql', true );
		$then  = gmdate( 'Y-m-d H:i:s', time() - DAY_IN_SECONDS );
		foreach ( array( array( $live, $now, 1 ), array( $idle, $then, 0 ) ) as list( $user, $at, $active ) ) {
			$wpdb->insert( $wpdb->prefix . 'ms_watch_sessions', array( 'video_id' => $video, 'user_id' => $user, 'session_token' => 'v' . $user, 'started_at' => $at, 'last_heartbeat' => $at, 'max_position' => 20, 'total_seconds' => 20, 'completion_pct' => 30, 'is_active' => $active ) ); // phpcs:ignore WordPress.DB
		}

		$request = new WP_REST_Request( 'GET', '/mediashield/v1/analytics/users' );
		$request->set_param( 'per_page', 20 );
		$rows = wp_list_pluck( rest_do_request( $request )->get_data(), 'watching_now', 'user_id' );
		$wpdb->query( "DELETE FROM {$wpdb->prefix}ms_watch_sessions WHERE session_token IN ( 'v{$live}', 'v{$idle}' )" ); // phpcs:ignore WordPress.DB

		$this->assertTrue( $rows[ $live ] ?? null );
		$this->assertFalse( $rows[ $idle ] ?? null );
	}

	/**
	 * MSUX-23: playlist items showed the slug (SELF, YOUTUBE); MSUX-17 relies
	 * on the status to leave drafts out of the block's count.
	 */
	public function test_playlist_items_name_the_platform_in_glossary_words(): void {
		global $wpdb;
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$playlist = self::factory()->post->create( array( 'post_type' => 'mediashield_playlist', 'post_status' => 'publish' ) );
		$yt       = self::factory()->post->create( array( 'post_type' => 'mediashield_video', 'post_status' => 'publish' ) );
		$link     = self::factory()->post->create( array( 'post_type' => 'mediashield_video', 'post_status' => 'draft' ) );
		update_post_meta( $yt, '_ms_platform', 'youtube' );
		update_post_meta( $link, '_ms_platform', 'self' );
		update_post_meta( $link, '_ms_source_url', 'https://example.com/clip.mp4' );
		foreach ( array( $yt, $link ) as $i => $video ) {
			$wpdb->insert( $wpdb->prefix . 'ms_playlist_items', array( 'playlist_id' => $playlist, 'video_id' => $video, 'sort_order' => $i ) ); // phpcs:ignore WordPress.DB
		}

		$items = rest_do_request( new WP_REST_Request( 'GET', "/mediashield/v1/playlists/{$playlist}/items" ) )->get_data();
		$wpdb->delete( $wpdb->prefix . 'ms_playlist_items', array( 'playlist_id' => $playlist ) ); // phpcs:ignore WordPress.DB

		$this->assertSame( array( 'YouTube', 'Video file link' ), wp_list_pluck( $items, 'platform_label' ) );
		$this->assertSame( array( 'publish', 'draft' ), wp_list_pluck( $items, 'status' ) );
		$this->assertSame( 'Uploaded file or file link', \MediaShield\Support\Platforms::label( 'self' ) );
	}
}
