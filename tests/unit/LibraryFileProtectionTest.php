<?php
/**
 * Regression tests for Basecamp 10482094152 (1.4.2).
 *
 * A non-buyer could download a paid self-hosted video: anyone who could write
 * posts read its file address from /wp/v2/mediashield-videos, and a video
 * whose source was a Media Library file left that file public, listed in
 * /wp/v2/media and served directly. Every test here fails on 1.4.1.
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\REST\StreamController;
use MediaShield\Upload\LibraryFiles;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Video meta in REST, and Media Library files moved into the protected folder.
 */
class LibraryFileProtectionTest extends WP_UnitTestCase {

	/**
	 * Media Library item holding the video file.
	 *
	 * @var int
	 */
	private int $attachment = 0;

	/**
	 * Absolute path of that file before protection.
	 *
	 * @var string
	 */
	private string $path = '';

	/**
	 * Create a Media Library video file.
	 */
	public function set_up(): void {
		parent::set_up();
		// The suite resets registered meta between tests; register it as init does.
		\MediaShield\CPT\VideoPostType::register_meta();
		do_action( 'rest_api_init' );

		$uploads    = wp_upload_dir();
		$this->path = trailingslashit( $uploads['path'] ) . 'lesson-' . wp_generate_password( 6, false ) . '.mp4';
		file_put_contents( $this->path, str_repeat( 'x', 64 ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		$this->attachment = self::factory()->attachment->create_object(
			$this->path,
			0,
			array(
				'post_mime_type' => 'video/mp4',
				'post_status'    => 'inherit',
			)
		);
	}

	/**
	 * Remove files the test left behind.
	 */
	public function tear_down(): void {
		$file = get_attached_file( $this->attachment );
		foreach ( array( $this->path, $file ) as $f ) {
			if ( $f && is_file( $f ) ) {
				wp_delete_file( $f );
			}
		}
		parent::tear_down();
	}

	/**
	 * A self-hosted video playing the Media Library file, saved as an admin.
	 *
	 * @return int Video ID.
	 */
	private function video(): int {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		return self::factory()->post->create(
			array(
				'post_type'   => 'mediashield_video',
				'post_status' => 'publish',
				'meta_input'  => array(
					'_ms_platform'   => 'self',
					'_ms_source_url' => wp_get_attachment_url( $this->attachment ),
				),
			)
		);
	}

	/**
	 * A contributor can write posts but cannot edit this video, so gets no address.
	 */
	public function test_contributor_gets_no_file_address_from_rest(): void {
		$video = $this->video();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'contributor' ) ) );

		$request = new WP_REST_Request( 'GET', '/wp/v2/mediashield-videos/' . $video );
		$data    = rest_get_server()->response_to_data( rest_do_request( $request ), false );

		$this->assertArrayNotHasKey( '_ms_source_url', $data['meta'] );
		$this->assertArrayNotHasKey( '_ms_platform_video_id', $data['meta'] );
		$this->assertSame( 'self', $data['meta']['_ms_platform'] );
	}

	/**
	 * Saving the video moves the file into the protected folder; playback follows it.
	 */
	public function test_media_library_file_moves_and_still_plays(): void {
		$video = $this->video();

		$this->assertFileDoesNotExist( $this->path );
		$this->assertSame( 'private', get_post_field( 'post_status', $this->attachment ) );
		$this->assertStringContainsString( '/mediashield/', (string) get_attached_file( $this->attachment ) );
		$this->assertStringContainsString( 'mediashield/v1/stream/' . $video, (string) get_post_meta( $video, '_ms_source_url', true ) );
		$this->assertSame( get_attached_file( $this->attachment ), StreamController::resolve_file( $video ) );
	}

	/**
	 * A visitor no longer finds the file in /wp/v2/media.
	 */
	public function test_media_endpoint_hides_protected_file_from_visitors(): void {
		$this->video();
		wp_set_current_user( 0 );

		$list = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/media' ) )->get_data();
		$this->assertNotContains( $this->attachment, wp_list_pluck( $list, 'id' ) );
		$this->assertSame( 401, rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/media/' . $this->attachment ) )->get_status() );
	}

	/**
	 * A file embedded in a post stays where it is, so that post keeps working.
	 */
	public function test_file_used_elsewhere_is_left_in_place(): void {
		self::factory()->post->create(
			array(
				'post_status'  => 'publish',
				'post_content' => '[video src="' . wp_get_attachment_url( $this->attachment ) . '"]',
			)
		);
		$this->video();

		$this->assertFileExists( $this->path );
		$this->assertSame( 'inherit', get_post_field( 'post_status', $this->attachment ) );
	}

	/**
	 * Restoring, and deleting the video, put the file back; neither deletes it.
	 */
	public function test_restore_and_delete_put_the_file_back(): void {
		$video = $this->video();
		$this->assertTrue( LibraryFiles::restore( $video ) );
		$this->assertFileExists( $this->path );
		$this->assertSame( 'inherit', get_post_field( 'post_status', $this->attachment ) );

		delete_post_meta( $video, '_ms_library_file' );
		LibraryFiles::protect( $video );
		$this->assertFileDoesNotExist( $this->path );

		wp_delete_post( $video, true );
		$this->assertFileExists( $this->path );
		$this->assertSame( $this->path, get_attached_file( $this->attachment ) );
	}

	/**
	 * When the old name was taken since, restore picks a free name and overwrites nothing.
	 */
	public function test_restore_never_overwrites_a_newer_file(): void {
		$video = $this->video();
		file_put_contents( $this->path, 'newer upload' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		$this->assertTrue( LibraryFiles::restore( $video ) );
		$this->assertSame( 'newer upload', file_get_contents( $this->path ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$back = (string) get_attached_file( $this->attachment );
		$this->assertNotSame( $this->path, $back );
		$this->assertFileExists( $back );
		$this->assertSame( wp_get_attachment_url( $this->attachment ), get_post_meta( $video, '_ms_source_url', true ) );
		wp_delete_file( $back );
	}
}
