<?php
/**
 * Front-end UX walk on Reign (1.4.4): F25 a video that cannot play here,
 * F26 the playlist start and locked items, F28 My Videos states, F29 the
 * page context a guest grant can be scoped to.
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\Access\AccessControl;
use MediaShield\Player\PlaylistRenderer;
use MediaShield\Player\Renderer;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * What a member, a guest and the owner see.
 */
class FrontendWalkTest extends WP_UnitTestCase {

	private const UNAVAILABLE = 'This video is unavailable.';

	/** @var int */
	private $member;

	/** @var int */
	private $admin;

	public function set_up(): void {
		parent::set_up();
		$this->member = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->admin  = self::factory()->user->create( array( 'role' => 'administrator' ) );
		update_option( 'ms_require_login', 1 );
	}

	public function tear_down(): void {
		remove_all_filters( 'mediashield_guest_can_watch' );
		remove_all_filters( 'mediashield_page_context' );
		AccessControl::context_from_request( null, array(), new WP_REST_Request( 'GET', '/mediashield/v1/reset' ) );
		parent::tear_down();
	}

	private function video( string $status = 'publish', string $role = '' ): int {
		$id = self::factory()->post->create( array( 'post_type' => 'mediashield_video', 'post_status' => $status, 'post_title' => 'Beach walk' ) );
		update_post_meta( $id, '_ms_platform', 'self' );
		update_post_meta( $id, '_ms_source_url', 'https://example.org/beach.mp4' );
		if ( $role ) {
			update_post_meta( $id, '_ms_access_role', $role );
		}
		return $id;
	}

	/**
	 * F25: a trashed video shows viewers the calm unavailable state, and the
	 * owner that it is in the trash with a Restore link. Fails before 1.4.4
	 * (viewers got nothing, the owner "not published yet").
	 */
	public function test_a_trashed_video_is_unavailable_with_restore_for_the_owner(): void {
		$video = $this->video();
		wp_trash_post( $video );

		foreach ( array( 0, $this->member ) as $viewer ) {
			wp_set_current_user( $viewer );
			$html = Renderer::render( $video );
			$this->assertStringContainsString( self::UNAVAILABLE, $html, "Viewer $viewer" );
			$this->assertStringNotContainsString( 'trash', $html );
		}

		wp_set_current_user( $this->admin );
		$html = Renderer::render( $video );
		$this->assertStringContainsString( self::UNAVAILABLE, $html );
		$this->assertStringContainsString( 'This video is in the trash.', $html );
		$this->assertStringContainsString( 'action=untrash', $html );
		$this->assertStringContainsString( 'Restore it', $html );
	}

	/**
	 * F25: every other status a viewer cannot play, and a missing id.
	 */
	public function test_drafts_private_and_missing_videos_are_unavailable(): void {
		$cases = array(
			'draft'   => 'This video is not published yet.',
			'pending' => 'This video is not published yet.',
			'private' => 'This video is private.',
		);
		foreach ( $cases as $status => $cause ) {
			$video = $this->video( $status );
			wp_set_current_user( $this->member );
			$html = Renderer::render( $video );
			$this->assertStringContainsString( self::UNAVAILABLE, $html, $status );
			$this->assertStringNotContainsString( $cause, $html, $status );
			wp_set_current_user( $this->admin );
			$this->assertStringContainsString( $cause, Renderer::render( $video ), $status );
		}

		wp_set_current_user( 0 );
		$this->assertStringContainsString( self::UNAVAILABLE, Renderer::render( 999999 ) );
	}

	/**
	 * F26: the playlist opens on the first video this member may watch;
	 * the others are marked locked; a video file with no poster gets its
	 * own frame. Fails before 1.4.4.
	 */
	public function test_playlist_starts_on_a_playable_item_and_marks_locked_ones(): void {
		$locked   = $this->video( 'publish', 'administrator' );
		$playable = $this->video();
		$playlist = self::factory()->post->create( array( 'post_type' => 'mediashield_playlist', 'post_status' => 'publish' ) );
		global $wpdb;
		foreach ( array( $locked, $playable ) as $order => $video ) {
			$wpdb->insert( $wpdb->prefix . 'ms_playlist_items', array( 'playlist_id' => $playlist, 'video_id' => $video, 'sort_order' => $order ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		}
		wp_set_current_user( $this->member );

		$html = PlaylistRenderer::render( $playlist );

		$this->assertMatchesRegularExpression( '/class="ms-playlist-main">\s*<div[^>]*data-video-id="' . $playable . '"/', $html, 'The player is the playable item.' );
		$this->assertMatchesRegularExpression( '/ms-playlist-item is-locked"[^>]*data-video-id="' . $locked . '"/', $html );
		$this->assertMatchesRegularExpression( '/ms-playlist-item is-active"\s*aria-current="true"\s*data-video-id="' . $playable . '"/', $html );
		$this->assertStringContainsString( 'Locked', $html );
		$this->assertMatchesRegularExpression( '/<video class="ms-playlist-item-thumb" data-frame-src="[^"]+"/', $html, 'A frame for the playable file.' );
		$this->assertSame( 1, substr_count( $html, 'data-frame-src' ), 'None for the locked one.' );
	}

	/**
	 * F28: My Videos asks a guest to log in with a button, and an empty list
	 * shows one next step when the owner set a link. Fails before 1.4.4.
	 */
	public function test_my_videos_states_have_an_icon_and_one_next_step(): void {
		wp_set_current_user( 0 );
		$guest = do_shortcode( '[mediashield_my_videos]' );
		$this->assertStringContainsString( 'ms-my-videos__state-action', $guest );
		$this->assertStringContainsString( '>Log in</a>', $guest );
		$this->assertStringContainsString( '<svg', $guest );

		wp_set_current_user( $this->member );
		$empty = do_shortcode( '[mediashield_my_videos]' );
		$this->assertStringContainsString( 'You have not watched any videos yet.', $empty );
		$this->assertStringNotContainsString( 'Browse videos', $empty, 'No link set: no button.' );

		$linked = do_shortcode( '[mediashield_my_videos browse_url="https://example.org/courses/"]' );
		$this->assertStringContainsString( 'href="https://example.org/courses/">Browse videos</a>', $linked );
	}

	/**
	 * F29: a guest grant sees where the video is shown, on the page and in
	 * the player's REST request, and the stream address carries it. Fails
	 * before 1.4.4 (the filter had no context).
	 */
	public function test_guest_grant_is_told_where_the_video_is_shown(): void {
		$video  = $this->video();
		$lesson = self::factory()->post->create( array( 'post_title' => 'Free preview lesson' ) );
		$seen   = array();
		add_filter(
			'mediashield_guest_can_watch',
			static function ( $allowed, $video_id, $context = null ) use ( &$seen, $lesson ) {
				$seen[] = $context;
				return is_array( $context ) && 'post' === $context['type'] && $lesson === $context['id'];
			},
			10,
			3
		);
		wp_set_current_user( 0 );

		$this->go_to( get_permalink( $lesson ) );
		$this->assertTrue( AccessControl::can_watch( $video, 0 )['allowed'], 'On the preview page.' );
		$this->assertStringContainsString( 'data-ms-context="post:' . $lesson . '"', Renderer::render( $video ) );

		$other = self::factory()->post->create();
		$this->go_to( get_permalink( $other ) );
		$this->assertFalse( AccessControl::can_watch( $video, 0 )['allowed'], 'Anywhere else.' );

		$start = new WP_REST_Request( 'POST', '/mediashield/v1/session/start' );
		$start->set_body_params(
			array(
				'video_id'   => $video,
				'ms_context' => 'post:' . $lesson,
			)
		);
		$this->assertNotEmpty( rest_do_request( $start )->get_data()['session_token'] ?? '', 'The player names the page.' );
		$this->assertStringContainsString( 'ms_context=post%3A' . $lesson, \MediaShield\REST\StreamController::signed_url( $video, 0 ) );

		$start->set_param( 'ms_context', 'post:' . $other );
		$this->assertSame( 401, rest_do_request( $start )->get_status() );
	}
}
