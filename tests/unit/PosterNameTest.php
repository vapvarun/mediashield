<?php
/**
 * Regression tests for Basecamp 10499285942 (1.4.2).
 *
 * Posters sideloaded before 1.4.2 were named after the platform video ID
 * (mediashield-youtube-<id>.jpg, <id>-thumbnail.jpg), so a guest read an
 * unlisted video's ID from the playlist sidebar, /wp/v2/media, ?_embed and
 * the attachment page. The 1.4.2 upgrade renames those posters; every test
 * here fails on Free c2c2185.
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\Core\Migrator;
use MediaShield\CPT\Thumbnail;
use MediaShield\Player\PlaylistRenderer;
use WP_UnitTestCase;

/**
 * Poster file names, the upgrade rename and the playlist sidebar.
 */
class PosterNameTest extends WP_UnitTestCase {

	private const YT_ID = 'aqz-KE-bpKQ';

	/**
	 * Published YouTube video.
	 *
	 * @var int
	 */
	private int $video = 0;

	/**
	 * Create the video.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->video = self::factory()->post->create(
			array(
				'post_type'   => 'mediashield_video',
				'post_status' => 'publish',
				'post_title'  => 'Lesson one',
			)
		);
		update_post_meta( $this->video, '_ms_platform', 'youtube' );
		update_post_meta( $this->video, '_ms_platform_video_id', self::YT_ID );
		update_post_meta( $this->video, '_ms_source_url', 'https://www.youtube.com/watch?v=' . self::YT_ID );
	}

	/**
	 * Remove the files the tests uploaded.
	 */
	public function tear_down(): void {
		foreach ( get_children( array( 'post_parent' => $this->video, 'post_type' => 'attachment', 'fields' => 'ids' ) ) as $id ) {
			wp_delete_attachment( (int) $id, true );
		}
		delete_option( Thumbnail::RENAME_PENDING );
		update_option( 'ms_require_login', false );
		parent::tear_down();
	}

	/**
	 * Upload a real image as this video's poster, under the given name and title.
	 *
	 * @param string $name  File name.
	 * @param string $title Attachment title.
	 * @return int Attachment ID.
	 */
	private function poster( string $name, string $title ): int {
		$tmp = trailingslashit( get_temp_dir() ) . $name;
		copy( DIR_TESTDATA . '/images/canola.jpg', $tmp );
		$id = self::factory()->attachment->create_upload_object( $tmp, $this->video );
		@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink
		wp_update_post(
			array(
				'ID'         => $id,
				'post_title' => $title,
				'post_name'  => sanitize_title( $title ),
			)
		);
		update_post_meta( $id, '_wp_attachment_image_alt', $title );
		set_post_thumbnail( $this->video, $id );

		// Intermediate sizes as core names them, written by hand so the test
		// does not depend on GD or Imagick being installed.
		$file = get_attached_file( $id );
		$stem = pathinfo( $file, PATHINFO_FILENAME );
		$meta = wp_get_attachment_metadata( $id );
		$meta = is_array( $meta ) ? $meta : array();
		foreach ( array( 'thumbnail' => '150x150', 'medium' => '300x225' ) as $size => $dims ) {
			$sized = $stem . '-' . $dims . '.jpg';
			copy( $file, dirname( $file ) . '/' . $sized );
			$meta['sizes'][ $size ] = array(
				'file'      => $sized,
				'width'     => (int) $dims,
				'height'    => (int) substr( $dims, 4 ),
				'mime-type' => 'image/jpeg',
			);
		}
		$meta['file'] = _wp_relative_upload_path( $file );
		wp_update_attachment_metadata( $id, $meta );
		return (int) $id;
	}

	/**
	 * Everything a guest can read about an attachment, as one string.
	 *
	 * @param int $id Attachment ID.
	 * @return string
	 */
	private function public_view( int $id ): string {
		$post = get_post( $id );
		$meta = wp_get_attachment_metadata( $id );
		$out  = array( get_attached_file( $id ), wp_get_attachment_url( $id ), $post->post_title, $post->post_name, $post->guid, $post->post_excerpt, $post->post_content, get_post_meta( $id, '_wp_attachment_image_alt', true ), $meta['file'] ?? '' );
		foreach ( $meta['sizes'] ?? array() as $size ) {
			$out[] = $size['file'];
		}
		return implode( ' ', $out );
	}

	public function test_new_poster_names_carry_no_platform_id(): void {
		foreach ( array( array( 'youtube', self::YT_ID ), array( 'bunny', '18f15635-49a5-4f24-b1a5-e41e308e3173' ), array( 'vimeo', '76979871' ) ) as $case ) {
			$name = Thumbnail::poster_filename( $case[0], $case[1], $this->video, 'jpg' );
			$this->assertMatchesRegularExpression( '/^mediashield-poster-[a-f0-9]{12}\.jpg$/', $name );
			$this->assertStringNotContainsStringIgnoringCase( $case[1], $name );
		}
	}

	public function test_sideloaded_poster_carries_no_id_in_file_title_or_slug(): void {
		$serve = static function ( $pre, $args ) {
			copy( DIR_TESTDATA . '/images/canola.jpg', $args['filename'] );
			return array(
				'headers'  => array( 'content-type' => 'image/jpeg' ),
				'body'     => '',
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'cookies'  => array(),
				'filename' => $args['filename'],
			);
		};
		add_filter( 'pre_http_request', $serve, 10, 2 );
		$id = Thumbnail::sideload_poster( 'https://img.youtube.com/vi/' . self::YT_ID . '/maxresdefault.jpg', $this->video, 'youtube', self::YT_ID, 'youtube - ' . self::YT_ID );
		remove_filter( 'pre_http_request', $serve, 10 );

		$this->assertIsInt( $id );
		$this->assertSame( $id, (int) get_post_thumbnail_id( $this->video ) );
		$this->assertStringNotContainsStringIgnoringCase( self::YT_ID, $this->public_view( $id ) );
		$this->assertStringNotContainsStringIgnoringCase( sanitize_title( self::YT_ID ), $this->public_view( $id ) );
	}

	public function test_upgrade_renames_an_old_id_named_poster_and_its_sizes(): void {
		$id      = $this->poster( 'mediashield-youtube-' . self::YT_ID . '.jpg', 'youtube - ' . self::YT_ID );
		$old     = get_attached_file( $id );
		$sizes   = wp_get_attachment_metadata( $id )['sizes'];
		$this->assertNotEmpty( $sizes, 'The fixture has intermediate sizes.' );
		$this->assertStringContainsStringIgnoringCase( self::YT_ID, $this->public_view( $id ), 'The fixture carries the ID before the upgrade.' );

		// The 1.4.2 upgrade from DB v9 queues the job; one pass renames.
		update_option( 'ms_db_version', 9 );
		Migrator::run();
		$this->assertNotFalse( get_option( Thumbnail::RENAME_PENDING, false ) );
		Thumbnail::rename_batch();

		clean_post_cache( $id );
		$view = $this->public_view( $id );
		$this->assertStringNotContainsStringIgnoringCase( self::YT_ID, $view );
		$this->assertStringNotContainsStringIgnoringCase( sanitize_title( self::YT_ID ), $view );
		$this->assertFileExists( get_attached_file( $id ) );
		$this->assertFileDoesNotExist( $old );
		$dir = dirname( get_attached_file( $id ) );
		foreach ( wp_get_attachment_metadata( $id )['sizes'] as $size ) {
			$this->assertFileExists( $dir . '/' . $size['file'] );
		}
		foreach ( $sizes as $size ) {
			$this->assertFileDoesNotExist( dirname( $old ) . '/' . $size['file'] );
		}
		$this->assertSame( 'Lesson one', get_post( $id )->post_title );
		$this->assertSame( wp_get_attachment_url( $id ), get_post( $id )->guid );

		// The next pass finds nothing left and finishes the job.
		Thumbnail::rename_batch();
		$this->assertFalse( get_option( Thumbnail::RENAME_PENDING, false ) );
	}

	public function test_a_second_run_changes_nothing(): void {
		$id = $this->poster( self::YT_ID . '-thumbnail.jpg', 'Lesson one' );
		$this->assertSame( 1, Thumbnail::rename_posters( $this->video ) );
		clean_post_cache( $id );
		$after = $this->public_view( $id );

		$this->assertSame( 0, Thumbnail::rename_posters( $this->video ) );
		clean_post_cache( $id );
		$this->assertSame( $after, $this->public_view( $id ) );
	}

	public function test_an_owner_poster_without_the_id_is_untouched(): void {
		$id     = $this->poster( 'my-lesson-cover.jpg', 'Lesson cover' );
		$before = $this->public_view( $id );

		$this->assertSame( 0, Thumbnail::rename_posters( $this->video ) );
		clean_post_cache( $id );
		$this->assertSame( $before, $this->public_view( $id ) );
	}

	public function test_playlist_sidebar_gives_a_guest_no_id_from_an_old_poster(): void {
		global $wpdb;
		$this->poster( 'mediashield-youtube-' . self::YT_ID . '.jpg', 'Lesson one' );
		$playlist = self::factory()->post->create(
			array(
				'post_type'   => 'mediashield_playlist',
				'post_status' => 'publish',
			)
		);
		$wpdb->insert( $wpdb->prefix . 'ms_playlist_items', array( 'playlist_id' => $playlist, 'video_id' => $this->video, 'sort_order' => 0 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		update_option( 'ms_require_login', true );
		update_option( 'ms_hide_source', true );
		wp_set_current_user( 0 );

		$html = PlaylistRenderer::render( $playlist );
		$this->assertStringContainsString( 'ms-playlist-sidebar', $html );
		$this->assertStringNotContainsStringIgnoringCase( self::YT_ID, $html );

		// A member who may watch keeps the poster in the sidebar.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->assertStringContainsString( 'ms-playlist-item-thumb"', PlaylistRenderer::render( $playlist ) );
	}
}
