<?php
/**
 * Videos used only through a playlist (Bunny end-to-end test, D7).
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\CPT\VideoUsage;
use WP_UnitTestCase;

/**
 * The Videos list's "Used in" names the playlist that holds a video.
 */
class VideoUsagePlaylistTest extends WP_UnitTestCase {

	public function tear_down(): void {
		VideoUsage::flush();
		parent::tear_down();
	}

	private function video(): int {
		return self::factory()->post->create(
			array(
				'post_type'   => 'mediashield_video',
				'post_status' => 'publish',
			)
		);
	}

	private function playlist( string $title, string $status, array $videos ): int {
		global $wpdb;
		$id = self::factory()->post->create(
			array(
				'post_type'   => 'mediashield_playlist',
				'post_status' => $status,
				'post_title'  => $title,
			)
		);
		foreach ( $videos as $order => $video ) {
			$wpdb->insert( $wpdb->prefix . 'ms_playlist_items', array( 'playlist_id' => $id, 'video_id' => $video, 'sort_order' => $order ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		}
		return $id;
	}

	/**
	 * Fails on HEAD: a video only in a playlist read "Not used yet".
	 */
	public function test_playlist_is_a_place_with_its_edit_link_for_owners(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$in_list  = $this->video();
		$also     = $this->video();
		$unused   = $this->video();
		$playlist = $this->playlist( 'Bunny Shorts Playlist', 'publish', array( $in_list, $also ) );
		$this->playlist( 'Old list', 'trash', array( $in_list ) );

		VideoUsage::prime( array( $in_list, $also, $unused ) );

		$places = VideoUsage::for_video( $in_list );
		$this->assertCount( 1, $places, 'A trashed playlist is not a place.' );
		$this->assertSame( $playlist, $places[0]['id'] );
		$this->assertSame( 'Bunny Shorts Playlist', $places[0]['title'] );
		$this->assertSame( 'Playlist', $places[0]['type'] );
		$this->assertSame( get_edit_post_link( $playlist, 'raw' ), $places[0]['url'] );
		$this->assertSame( $playlist, VideoUsage::for_video( $also )[0]['id'] );
		$this->assertSame( array(), VideoUsage::for_video( $unused ) );
	}

	/**
	 * My Videos never links a member to the playlist itself (it has no public
	 * page since bfa2b26): for a video used only through a playlist it links
	 * the first published page that shows that playlist, by block or shortcode.
	 *
	 * Fails before the fix: page_for_viewer() found no page and returned ''.
	 */
	public function test_member_link_is_the_page_holding_the_playlist(): void {
		$member   = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$by_block = $this->video();
		$by_code  = $this->video();
		$block_pl = $this->playlist( 'Block list', 'publish', array( $by_block ) );
		$code_pl  = $this->playlist( 'Shortcode list', 'publish', array( $by_code ) );

		// A draft holding the playlist is never offered.
		self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'draft',
				'post_content' => '<!-- wp:mediashield/playlist {"playlistId":' . $block_pl . '} /-->',
			)
		);
		$block_page = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:mediashield/playlist {"playlistId":' . $block_pl . '} /-->',
			)
		);
		$code_page = self::factory()->post->create(
			array(
				'post_type'    => 'post',
				'post_status'  => 'publish',
				'post_content' => 'Watch: [mediashield_playlist id="' . $code_pl . '"]',
			)
		);

		VideoUsage::prime( array( $by_block, $by_code ) );

		$this->assertSame( get_permalink( $block_page ), VideoUsage::page_for_viewer( $by_block, $member ) );
		$this->assertSame( get_permalink( $code_page ), VideoUsage::page_for_viewer( $by_code, $member ) );
		$this->assertStringNotContainsString( 'playlist', VideoUsage::page_for_viewer( $by_block, $member ) );

		// The owner's "Used in" still names the playlist, with its edit link.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		VideoUsage::flush();
		$places = VideoUsage::for_video( $by_block );
		$this->assertSame( array( $block_pl ), wp_list_pluck( $places, 'id' ) );
		$this->assertSame( get_edit_post_link( $block_pl, 'raw' ), $places[0]['url'] );
	}

	/**
	 * A video used only in a playlist that no page shows: no link at all.
	 */
	public function test_no_page_for_a_playlist_nobody_shows(): void {
		$member = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$video  = $this->video();
		$this->playlist( 'Unplaced', 'publish', array( $video ) );

		$this->assertSame( '', VideoUsage::page_for_viewer( $video, $member ) );
	}

	/**
	 * A page that embeds the video is listed too, before the playlist.
	 */
	public function test_pages_and_playlists_together(): void {
		$video    = $this->video();
		$page     = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => '[mediashield id="' . $video . '"]',
			)
		);
		$playlist = $this->playlist( 'Course clips', 'publish', array( $video ) );

		$ids = wp_list_pluck( VideoUsage::for_video( $video ), 'id' );

		$this->assertSame( array( $page, $playlist ), $ids );
	}
}
