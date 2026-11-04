<?php
/**
 * Access and token rules found in the 1.4.0 security review.
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\Cron\Cleanup;
use MediaShield\Embed\EmbedLink;
use MediaShield\Integrations\Learnomy;
use MediaShield\Player\PlaylistRenderer;
use MediaShield\REST\StreamController;
use MediaShield\Player\Renderer;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Stream address, playlist password and embed token spelling.
 */
class ReleaseSecurityTest extends WP_UnitTestCase {

	/**
	 * A video on a platform that supplies its own stream address (Bunny direct play).
	 *
	 * @return int
	 */
	private function bunny_video(): int {
		$video = self::factory()->post->create(
			array(
				'post_type'   => 'mediashield_video',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $video, '_ms_platform', 'bunny' );
		update_post_meta( $video, '_ms_platform_video_id', 'abc-guid' );
		return $video;
	}

	/**
	 * Fails before the fix: the signed CDN address was printed for a logged-out visitor.
	 */
	public function test_cdn_stream_address_is_withheld_from_someone_who_may_not_watch(): void {
		update_option( 'ms_require_login', true );
		$video = $this->bunny_video();
		add_filter(
			'mediashield_video_stream_url',
			static fn() => 'https://cdn.example.test/secret.m3u8?token=abc'
		);

		wp_set_current_user( 0 );
		$this->assertStringNotContainsString( 'secret.m3u8', Renderer::render( $video ) );

		wp_set_current_user( self::factory()->user->create() );
		$this->assertStringContainsString( 'secret.m3u8', Renderer::render( $video ) );
	}

	/**
	 * A self-hosted video with an explicit CDN playlist is not behind /stream either.
	 * Fails when the gate is keyed on the platform instead of on the address.
	 */
	public function test_explicit_cdn_address_on_a_self_hosted_video_is_withheld_too(): void {
		update_option( 'ms_require_login', true );
		$video = self::factory()->post->create(
			array(
				'post_type'   => 'mediashield_video',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $video, '_ms_platform', 'self' );
		update_post_meta( $video, '_ms_stream_url', 'https://cdn.example.test/lesson.m3u8' );
		update_post_meta( $video, '_ms_platform_video_id', 'lesson.mp4' );

		wp_set_current_user( 0 );
		$this->assertStringNotContainsString( 'lesson.m3u8', Renderer::render( $video ) );

		wp_set_current_user( self::factory()->user->create() );
		$this->assertStringContainsString( 'lesson.m3u8', Renderer::render( $video ) );
	}

	/**
	 * Fails before the fix: a playlist password did not stop the player route.
	 */
	public function test_player_route_respects_a_playlist_password(): void {
		global $wpdb;

		$playlist = self::factory()->post->create(
			array(
				'post_type'     => 'mediashield_playlist',
				'post_status'   => 'publish',
				'post_password' => 'letmein',
			)
		);
		$video    = $this->bunny_video();
		$wpdb->insert(
			"{$wpdb->prefix}ms_playlist_items",
			array(
				'playlist_id' => $playlist,
				'video_id'    => $video,
				'sort_order'  => 1,
			)
		);

		wp_set_current_user( 0 );
		$status = rest_do_request( new WP_REST_Request( 'GET', "/mediashield/v1/playlists/{$playlist}/player/{$video}" ) )->get_status();

		$this->assertSame( 404, $status );
	}

	/**
	 * Fails before the fix: "<id>x" cast to the same id and verified.
	 */
	public function test_embed_token_has_one_spelling(): void {
		$token = EmbedLink::token( 7, 3 );
		$this->assertIsArray( EmbedLink::verify( $token ) );

		$parts    = explode( '.', $token );
		$parts[2] = $parts[2] . 'x';
		$this->assertNull( EmbedLink::verify( implode( '.', $parts ) ) );

		$parts    = explode( '.', $token );
		$parts[0] = '+' . $parts[0];
		$this->assertNull( EmbedLink::verify( implode( '.', $parts ) ) );
	}

	/**
	 * A self-hosted video whose protected file is on disk.
	 *
	 * @param string $file Stored file name.
	 * @param bool   $own  Whether the upload recorded the claim (a 1.4.0 upload does).
	 * @return int
	 */
	private function uploaded_video( string $file, bool $own ): int {
		$dir = trailingslashit( wp_upload_dir()['basedir'] ) . 'mediashield/';
		wp_mkdir_p( $dir );
		file_put_contents( $dir . $file, 'x' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		$video = self::factory()->post->create(
			array(
				'post_type'   => 'mediashield_video',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $video, '_ms_platform', 'self' );
		update_post_meta( $video, '_ms_platform_video_id', $file );
		if ( $own ) {
			update_post_meta( $video, '_ms_owned_file', $file );
		}
		return $video;
	}

	/**
	 * Fails before the fix: naming another video's file in your own streamed it.
	 */
	public function test_a_video_cannot_stream_a_file_another_video_uploaded(): void {
		$file     = 'owned-' . wp_generate_password( 8, false ) . '.mp4';
		$owner    = $this->uploaded_video( $file, true );
		$borrower = $this->uploaded_video( $file, false );
		update_post_meta( $borrower, '_ms_owned_file', '' );

		$this->assertNotSame( '', StreamController::resolve_file( $owner ) );
		$this->assertSame( '', StreamController::resolve_file( $borrower ) );
	}

	/**
	 * Fails before the fix: pasting the protected file URL as the source URL had the same effect.
	 */
	public function test_a_pasted_url_into_the_protected_folder_is_refused(): void {
		$file  = 'pasted-' . wp_generate_password( 8, false ) . '.mp4';
		$owner = $this->uploaded_video( $file, true );
		$other = self::factory()->post->create(
			array(
				'post_type'   => 'mediashield_video',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $other, '_ms_platform', 'self' );
		update_post_meta( $other, '_ms_source_url', trailingslashit( wp_upload_dir()['baseurl'] ) . 'mediashield/' . $file );

		$this->assertNotSame( '', StreamController::resolve_file( $owner ) );
		$this->assertSame( '', StreamController::resolve_file( $other ) );
	}

	/**
	 * Upgrade: videos uploaded before 1.4.0 keep playing until the backfill has run,
	 * then the oldest video keeps the file and a later one naming it is refused.
	 */
	public function test_upgrade_backfill_keeps_existing_lessons_playing_then_assigns_owners(): void {
		$file   = 'legacy-' . wp_generate_password( 8, false ) . '.mp4';
		$first  = $this->uploaded_video( $file, false );
		$second = $this->uploaded_video( $file, false );
		update_option( 'ms_file_claims_pending', max( $first, $second ) );

		$this->assertNotSame( '', StreamController::resolve_file( $first ) );
		$this->assertNotSame( '', StreamController::resolve_file( $second ) );

		Cleanup::claim_uploaded_files();

		$this->assertNotSame( '', StreamController::resolve_file( $first ) );
		$this->assertSame( '', StreamController::resolve_file( $second ) );

		Cleanup::claim_uploaded_files();
		$this->assertFalse( get_option( 'ms_file_claims_pending' ) );
	}

	/**
	 * Fails before the fix: deleting a video that only names a file deleted the file.
	 */
	public function test_deleting_a_video_does_not_delete_a_file_it_does_not_own(): void {
		$file     = 'keep-' . wp_generate_password( 8, false ) . '.mp4';
		$owner    = $this->uploaded_video( $file, true );
		$borrower = $this->uploaded_video( $file, false );
		$path     = trailingslashit( wp_upload_dir()['basedir'] ) . 'mediashield/' . $file;

		wp_delete_post( $borrower, true );
		$this->assertFileExists( $path );

		wp_delete_post( $owner, true );
		$this->assertFileDoesNotExist( $path );
	}

	/**
	 * Fails before the fix: during the backfill a new video, or an old one that
	 * was re-pointed, played another video's file.
	 */
	public function test_the_upgrade_window_does_not_let_a_new_or_edited_video_borrow_a_file(): void {
		$file  = 'window-' . wp_generate_password( 8, false ) . '.mp4';
		$owner = $this->uploaded_video( $file, false );
		$old   = $this->uploaded_video( 'x-' . $file, false );
		update_option( 'ms_file_claims_pending', $old );

		$new = $this->uploaded_video( $file, false );
		$this->assertSame( '', StreamController::resolve_file( $new ), 'A video created after the update gets no grace.' );

		update_post_meta( $old, '_ms_platform_video_id', $file );
		$this->assertSame( '', StreamController::resolve_file( $old ), 'Re-pointing an unclaimed video forfeits its claim.' );

		Cleanup::claim_uploaded_files();
		$this->assertNotSame( '', StreamController::resolve_file( $owner ) );
	}

	/**
	 * Fails before the fix: the backfill was queued on plugins_loaded, before
	 * Action Scheduler is ready, so it was dropped and the flag never cleared.
	 */
	public function test_pending_backfill_is_queued_after_plugins_load_and_requeued_if_lost(): void {
		update_option( 'ms_file_claims_pending', 5 );
		wp_clear_scheduled_hook( 'ms_claim_uploaded_files' );

		Cleanup::ensure_claim_job();

		$queued = function_exists( 'as_next_scheduled_action' )
			? false !== as_next_scheduled_action( 'ms_claim_uploaded_files' )
			: false !== wp_next_scheduled( 'ms_claim_uploaded_files' );
		$this->assertTrue( $queued );

		delete_option( 'ms_file_claims_pending' );
	}
	/**
	 * Fails before the fix: any logged-in subscriber read a playlist's items, each
	 * with the video's source address, even for a password-protected playlist.
	 */
	public function test_playlist_items_need_editing_rights(): void {
		$video    = self::factory()->post->create( array( 'post_type' => 'mediashield_video', 'post_status' => 'publish' ) );
		update_post_meta( $video, '_ms_platform', 'self' );
		update_post_meta( $video, '_ms_source_url', 'https://example.test/wp-content/uploads/2026/11/lesson.mp4' );
		$playlist = self::factory()->post->create(
			array(
				'post_type'     => 'mediashield_playlist',
				'post_status'   => 'publish',
				'post_password' => 'partners',
			)
		);

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$add = new WP_REST_Request( 'POST', "/mediashield/v1/playlists/{$playlist}/items" );
		$add->set_param( 'video_id', $video );
		$this->assertSame( 201, rest_do_request( $add )->get_status() );

		$read = new WP_REST_Request( 'GET', "/mediashield/v1/playlists/{$playlist}/items" );
		$this->assertSame( 200, rest_do_request( $read )->get_status(), 'Editors still manage items.' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$response = rest_do_request( $read );
		$this->assertSame( 403, $response->get_status() );
		$this->assertStringNotContainsString( 'lesson.mp4', wp_json_encode( $response->get_data() ) );
	}
	/**
	 * Fails before the fix: a visitor under Require Login got the YouTube id and
	 * source URL of a video they may not watch, from the player and the playlist route.
	 */
	public function test_player_prints_no_address_for_a_viewer_who_may_not_watch(): void {
		update_option( 'ms_require_login', true );
		$video = self::factory()->post->create( array( 'post_type' => 'mediashield_video', 'post_status' => 'publish' ) );
		update_post_meta( $video, '_ms_platform', 'youtube' );
		update_post_meta( $video, '_ms_platform_video_id', 'aqz-KE-bpKQ' );
		update_post_meta( $video, '_ms_source_url', 'https://www.youtube.com/watch?v=aqz-KE-bpKQ' );

		wp_set_current_user( 0 );
		$html = Renderer::render( $video );
		$this->assertStringNotContainsString( 'aqz-KE-bpKQ', $html );
		$this->assertStringContainsString( 'data-access-denied="1"', $html );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$html = Renderer::render( $video );
		$this->assertStringContainsString( 'aqz-KE-bpKQ', $html, 'A member who may watch still gets the player.' );
		$this->assertStringNotContainsString( 'data-access-denied', $html );
	}

	/**
	 * Fails before the fix: a drip-locked Learnomy lesson still carried a working embed link.
	 */
	public function test_locked_learnomy_lesson_gets_no_embed_link(): void {
		$video = self::factory()->post->create( array( 'post_type' => 'mediashield_video', 'post_status' => 'publish' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$lesson = (object) array( 'content_type' => 'mediashield', 'video_url' => (string) $video );

		$open = Learnomy::add_embed_to_payload( array( 'locked' => false ), $lesson );
		$this->assertArrayHasKey( 'mediashield', $open, 'An open lesson keeps its link.' );

		$locked = Learnomy::add_embed_to_payload( array( 'locked' => true ), $lesson );
		$this->assertArrayNotHasKey( 'mediashield', $locked );
	}

	/**
	 * Fails before the fix: the playlist block or shortcode showed a protected playlist's videos.
	 */
	public function test_playlist_block_asks_for_the_password(): void {
		$video    = self::factory()->post->create( array( 'post_type' => 'mediashield_video', 'post_status' => 'publish', 'post_title' => 'Partner briefing one' ) );
		$playlist = self::factory()->post->create(
			array(
				'post_type'     => 'mediashield_playlist',
				'post_status'   => 'publish',
				'post_password' => 'partners',
			)
		);
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$add = new WP_REST_Request( 'POST', "/mediashield/v1/playlists/{$playlist}/items" );
		$add->set_param( 'video_id', $video );
		rest_do_request( $add );

		wp_set_current_user( 0 );
		$html = PlaylistRenderer::render( $playlist );
		$this->assertStringContainsString( 'post_password', $html );
		$this->assertStringNotContainsString( 'Partner briefing one', $html );
	}
}
