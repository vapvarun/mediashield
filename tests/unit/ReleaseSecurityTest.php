<?php
/**
 * Access and token rules found in the 1.4.0 security review.
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\Cron\Cleanup;
use MediaShield\Embed\EmbedLink;
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
		update_option( 'ms_file_claims_pending', 1 );

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
}
