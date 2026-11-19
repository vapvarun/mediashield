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

use MediaShield\Player\PlayerWrapper;
use MediaShield\Player\Renderer;
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

	/**
	 * A viewer who may watch never receives the stored file name, on either render path.
	 *
	 * Fails on 1.4.2 before the QA fix: data-platform-video-id carried the
	 * obscured name, a permanent download link on nginx.
	 */
	public function test_stored_file_name_never_reaches_the_page(): void {
		update_option( 'ms_require_login', false );
		$video = $this->video();
		$name  = (string) get_post_meta( $video, '_ms_platform_video_id', true );
		$this->assertNotSame( '', $name );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$html = Renderer::render( $video );
		$this->assertMatchesRegularExpression( '#data-stream-url="[^"]*stream(/|%2F)' . $video . '[^"]*ms_token=#', $html, 'Still plays through the signed stream route.' );
		$this->assertStringNotContainsString( $name, $html );
		$this->assertStringNotContainsString( '/uploads/mediashield/', $html );

		// Another video naming that file by its protected-folder URL cannot
		// stream it (no claim), and must not print the URL either, on the
		// auto-wrap path that finds a pasted <video> by its source.
		$raw   = trailingslashit( wp_upload_dir()['baseurl'] ) . 'mediashield/' . $name;
		$other = self::factory()->post->create(
			array(
				'post_type'   => 'mediashield_video',
				'post_status' => 'publish',
				'meta_input'  => array(
					'_ms_platform'   => 'self',
					'_ms_source_url' => $raw,
				),
			)
		);
		$this->assertStringNotContainsString( $name, Renderer::render( $other ) );
		$wrapped = PlayerWrapper::process_buffer( '<p>x</p><video src="' . esc_url( $raw ) . '" controls></video>' );
		$this->assertStringContainsString( 'data-video-id="' . $other . '"', $wrapped );
		$this->assertStringNotContainsString( 'data-source-url="' . esc_url( $raw ), $wrapped );
	}

	/**
	 * YouTube keeps its id: the player needs it.
	 */
	public function test_youtube_id_is_still_printed_for_an_allowed_viewer(): void {
		update_option( 'ms_require_login', false );
		$video = self::factory()->post->create( array( 'post_type' => 'mediashield_video', 'post_status' => 'publish' ) );
		update_post_meta( $video, '_ms_platform', 'youtube' );
		update_post_meta( $video, '_ms_platform_video_id', 'aqz-KE-bpKQ' );
		update_post_meta( $video, '_ms_source_url', 'https://www.youtube.com/watch?v=aqz-KE-bpKQ' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->assertStringContainsString( 'data-platform-video-id="aqz-KE-bpKQ"', Renderer::render( $video ) );
	}

	/**
	 * Saving an already moved video keeps it in the owner notice's moved list.
	 */
	public function test_resaving_a_moved_video_keeps_it_in_the_notice(): void {
		$video = $this->video();
		wp_update_post(
			array(
				'ID'         => $video,
				'post_title' => 'Edited',
			)
		);
		$this->assertSame( 'moved', get_post_meta( $video, '_ms_library_file', true ) );
		$this->assertContains( $video, get_option( 'ms_library_files_report' )['moved'] );
	}

	/**
	 * A moved video pointed at a different Media Library file protects the new file.
	 *
	 * Fails on ba1029c: a moved video was skipped on every later save, so the
	 * new file stayed public (fail-open).
	 *
	 * @dataProvider repoint_shapes
	 *
	 * @param bool $field_cleared Whether the edit screen cleared the hidden file field (it does when a URL is pasted).
	 */
	public function test_moved_video_repointed_protects_the_new_file( bool $field_cleared ): void {
		$video    = $this->video();
		$old_name = (string) get_post_meta( $video, '_ms_platform_video_id', true );

		$second = trailingslashit( wp_upload_dir()['path'] ) . 'second-' . wp_generate_password( 6, false ) . '.mp4';
		file_put_contents( $second, str_repeat( 'y', 64 ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$att2 = self::factory()->attachment->create_object(
			$second,
			0,
			array(
				'post_mime_type' => 'video/mp4',
				'post_status'    => 'inherit',
			)
		);

		// What the edit screen saves: the new address, and the hidden file
		// field either cleared by its script or left as it was.
		update_post_meta( $video, '_ms_source_url', wp_get_attachment_url( $att2 ) );
		if ( $field_cleared ) {
			update_post_meta( $video, '_ms_platform_video_id', '' );
		}
		wp_update_post(
			array(
				'ID'         => $video,
				'post_title' => 'Re-pointed',
			)
		);

		$this->assertFileDoesNotExist( $second, 'The new file is moved into the protected folder.' );
		$this->assertSame( 'private', get_post_field( 'post_status', $att2 ) );
		$this->assertNotSame( $old_name, get_post_meta( $video, '_ms_platform_video_id', true ) );
		$this->assertSame( get_attached_file( $att2 ), StreamController::resolve_file( $video ) );

		// The old file is no longer played by the video, so it is back in the Media Library.
		$this->assertFileExists( $this->path );
		$this->assertSame( 'inherit', get_post_field( 'post_status', $this->attachment ) );
		wp_delete_file( (string) get_attached_file( $att2 ) );
	}

	/**
	 * Both shapes the edit screen can save.
	 *
	 * @return array<string, array{bool}>
	 */
	public static function repoint_shapes(): array {
		return array(
			'file field cleared' => array( true ),
			'file field kept'    => array( false ),
		);
	}

	/**
	 * A restored video saved again unchanged stays public: the owner chose that.
	 */
	public function test_restored_video_resaved_unchanged_stays_restored(): void {
		$video = $this->video();
		LibraryFiles::restore( $video );
		wp_update_post(
			array(
				'ID'         => $video,
				'post_title' => 'Still restored',
			)
		);

		$this->assertSame( 'restored', get_post_meta( $video, '_ms_library_file', true ) );
		$this->assertFileExists( $this->path );
		$this->assertSame( 'inherit', get_post_field( 'post_status', $this->attachment ) );
		// A different file than the one restored is protected.
		$other = trailingslashit( wp_upload_dir()['path'] ) . 'other-' . wp_generate_password( 6, false ) . '.mp4';
		file_put_contents( $other, str_repeat( 'z', 64 ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$att3 = self::factory()->attachment->create_object(
			$other,
			0,
			array(
				'post_mime_type' => 'video/mp4',
				'post_status'    => 'inherit',
			)
		);
		update_post_meta( $video, '_ms_source_url', wp_get_attachment_url( $att3 ) );
		wp_update_post(
			array(
				'ID'         => $video,
				'post_title' => 'Other file',
			)
		);
		$this->assertSame( 'moved', get_post_meta( $video, '_ms_library_file', true ) );
		$this->assertFileDoesNotExist( $other );
		$this->assertFileExists( $this->path, 'The restored file stays public.' );
		wp_delete_file( (string) get_attached_file( $att3 ) );
	}

	/**
	 * The file as Elementor stores it: JSON with escaped slashes in _elementor_data.
	 */
	public function test_file_in_elementor_data_is_left_in_place(): void {
		$page = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$data = array(
			array(
				'elType'   => 'widget',
				'settings' => array(
					'hosted_url' => array(
						'url' => wp_get_attachment_url( $this->attachment ),
					),
				),
			),
		);
		update_post_meta( $page, '_elementor_data', wp_slash( wp_json_encode( $data ) ) );
		$this->assertStringContainsString( '\\/', (string) get_post_meta( $page, '_elementor_data', true ), 'Fixture: slashes are escaped as Elementor stores them.' );

		$this->video();

		$this->assertFileExists( $this->path );
		$this->assertSame( 'inherit', get_post_field( 'post_status', $this->attachment ) );
	}

	/**
	 * A block that names the Media Library item by id only.
	 */
	public function test_file_named_by_id_in_a_block_is_left_in_place(): void {
		self::factory()->post->create(
			array(
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:video {"id":' . $this->attachment . '} --><figure class="wp-block-video"></figure><!-- /wp:video -->',
			)
		);
		$this->video();

		$this->assertFileExists( $this->path );
	}

	/**
	 * Meta known to hold a Media Library id: a featured image, an ACF field, the
	 * core Video widget, an ACF options-page field.
	 *
	 * @dataProvider id_references
	 *
	 * @param string $kind Which reference.
	 */
	public function test_file_named_by_id_in_meta_or_settings_is_left_in_place( string $kind ): void {
		$post = self::factory()->post->create();
		switch ( $kind ) {
			case 'thumbnail':
				update_post_meta( $post, '_thumbnail_id', $this->attachment );
				break;
			case 'acf':
				update_post_meta( $post, 'lesson_video', $this->attachment );
				update_post_meta( $post, '_lesson_video', 'field_6512ab34cd' );
				break;
			case 'acf_gallery':
				update_post_meta( $post, 'clips', array( (string) $this->attachment ) );
				update_post_meta( $post, '_clips', 'field_6512ab34ce' );
				break;
			case 'widget':
				update_option( 'widget_media_video', array( 2 => array( 'attachment_id' => $this->attachment ) ) );
				break;
			case 'acf_option':
				update_option( 'options_intro_video', $this->attachment );
				update_option( '_options_intro_video', 'field_6512ab34cf' );
				break;
		}
		$this->video();

		$this->assertFileExists( $this->path, $kind );
	}

	/**
	 * Id references the "used elsewhere" check reads.
	 *
	 * @return array<string, array{0: string}>
	 */
	public function id_references(): array {
		return array(
			'featured image' => array( 'thumbnail' ),
			'ACF file field' => array( 'acf' ),
			'ACF gallery'    => array( 'acf_gallery' ),
			'Video widget'   => array( 'widget' ),
			'ACF options'    => array( 'acf_option' ),
		);
	}

	/**
	 * A number that only looks like the id (no ACF field behind it) is not a use.
	 */
	public function test_unrelated_meta_with_the_same_number_does_not_keep_the_file(): void {
		update_post_meta( self::factory()->post->create(), 'view_count', $this->attachment );
		$this->video();

		$this->assertFileDoesNotExist( $this->path );
	}

	/**
	 * Saving a video does not move a Media Library file the saver may not edit.
	 */
	public function test_saver_who_cannot_edit_the_file_does_not_move_it(): void {
		$owner = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_update_post(
			array(
				'ID'          => $this->attachment,
				'post_author' => $owner,
			)
		);
		$author = self::factory()->user->create( array( 'role' => 'author' ) );
		wp_set_current_user( $author );
		$video = self::factory()->post->create(
			array(
				'post_type'   => 'mediashield_video',
				'post_status' => 'publish',
				'post_author' => $author,
				'meta_input'  => array(
					'_ms_platform'   => 'self',
					'_ms_source_url' => wp_get_attachment_url( $this->attachment ),
				),
			)
		);

		$this->assertFileExists( $this->path );
		$this->assertSame( 'inherit', get_post_field( 'post_status', $this->attachment ) );

		// The owner saving it protects it.
		wp_set_current_user( $owner );
		wp_update_post(
			array(
				'ID'         => $video,
				'post_title' => 'Saved by the owner',
			)
		);
		$this->assertFileDoesNotExist( $this->path );
		$this->assertSame( 'private', get_post_field( 'post_status', $this->attachment ) );
	}

	/**
	 * A reference scan that fails leaves the file where it is.
	 */
	public function test_failed_reference_scan_leaves_the_file_in_place(): void {
		global $wpdb;
		$break = static function ( string $sql ) use ( $wpdb ): string {
			return 0 === strpos( ltrim( $sql ), 'SELECT m.post_id' ) ? str_replace( $wpdb->postmeta . ' m ', $wpdb->postmeta . '_missing m ', $sql ) : $sql;
		};
		add_filter( 'query', $break );
		$suppress = $wpdb->suppress_errors( true );
		$this->video();
		$wpdb->suppress_errors( $suppress );
		remove_filter( 'query', $break );

		$this->assertFileExists( $this->path );
	}

	/**
	 * A file at uploads/<folder>/<name> with no Media Library item, as another plugin stores it.
	 *
	 * @param string $relative Path below uploads.
	 * @return string Absolute path.
	 */
	private function plugin_file( string $relative ): string {
		$path = trailingslashit( wp_upload_dir()['basedir'] ) . $relative;
		wp_mkdir_p( dirname( $path ) );
		file_put_contents( $path, str_repeat( 'w', 64 ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		return $path;
	}

	/**
	 * A self-hosted video playing a file by URL, saved as an admin.
	 *
	 * @param string $relative Path below uploads.
	 * @return int Video ID.
	 */
	private function video_for( string $relative ): int {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		return self::factory()->post->create(
			array(
				'post_type'   => 'mediashield_video',
				'post_status' => 'publish',
				'meta_input'  => array(
					'_ms_platform'   => 'self',
					'_ms_source_url' => trailingslashit( wp_upload_dir()['baseurl'] ) . $relative,
				),
			)
		);
	}

	/**
	 * Only a file with a Media Library item moves. Another plugin's file stays and is reported.
	 *
	 * Fails on 1.4.2 (Basecamp 10517640742): a WPMediaVerse upload at
	 * uploads/wpmediaverse/ was moved into the protected folder, breaking the
	 * WPMediaVerse item that still pointed at it.
	 */
	public function test_file_without_media_library_item_stays_and_is_reported(): void {
		$relative = 'wpmediaverse/x-' . wp_generate_password( 6, false ) . '.mp4';
		$path     = $this->plugin_file( $relative );
		$video    = $this->video_for( $relative );

		$this->assertFileExists( $path, 'Another plugin\'s file stays where it is.' );
		$this->assertSame( '', (string) get_post_meta( $video, '_ms_platform_video_id', true ) );
		$this->assertSame( 'not_in_library', get_post_meta( $video, '_ms_library_file', true ) );
		$report = get_option( 'ms_library_files_report' );
		$this->assertArrayHasKey( $video, $report['left'], 'Listed in the owner notice.' );
		$this->assertNotContains( $video, $report['moved'] );

		// The notice names it and says why.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		ob_start();
		LibraryFiles::render_notice();
		$this->assertStringContainsString( 'not in your Media Library', (string) ob_get_clean() );

		// An attachment-backed file in uploads/YYYY/MM still moves.
		$this->assertMatchesRegularExpression( '#/\d{4}/\d{2}/[^/]+$#', $this->path, 'Fixture: the Media Library file sits in YYYY/MM.' );
		$this->video();
		$this->assertFileDoesNotExist( $this->path );
		$this->assertSame( 'private', get_post_field( 'post_status', $this->attachment ) );
		wp_delete_file( $path );
	}

	/**
	 * What 1.4.2 left behind when it moved a file with no Media Library item.
	 *
	 * @param string $relative Path below uploads the file came from.
	 * @return array{0: int, 1: string} Video ID and the original absolute path.
	 */
	private function moved_by_142( string $relative ): array {
		$path  = $this->plugin_file( $relative );
		$video = self::factory()->post->create(
			array(
				'post_type'   => 'mediashield_video',
				'post_status' => 'draft',
			)
		);
		$url   = trailingslashit( wp_upload_dir()['baseurl'] ) . $relative;
		$name  = ( new \MediaShield\Upload\Drivers\SelfHosted() )->adopt( $path );
		$this->assertNotSame( '', $name, 'Fixture: the file moved.' );
		update_post_meta( $video, '_ms_platform', 'self' );
		update_post_meta(
			$video,
			'_ms_library_restore',
			array(
				'url'        => $url,
				'file'       => $relative,
				'attachment' => 0,
				'name'       => $name,
			)
		);
		update_post_meta( $video, '_ms_platform_video_id', $name );
		update_post_meta( $video, '_ms_owned_file', $name );
		update_post_meta( $video, '_ms_source_url', rest_url( 'mediashield/v1/stream/' . $video ) );
		update_post_meta( $video, '_ms_library_file', 'moved' );
		return array( $video, $path );
	}

	/**
	 * Run the upgrade from 1.4.2 and its background job to the end.
	 */
	private function upgrade_from_142(): void {
		update_option( 'ms_db_version', 10 );
		\MediaShield\Core\Migrator::run();
		for ( $i = 0; $i < 10 && false !== get_option( 'ms_library_files_repair_pending', false ); $i++ ) {
			do_action( 'ms_repair_library_files' );
		}
	}

	/**
	 * The 1.4.3 repair moves back what 1.4.2 took from other plugins, and nothing else.
	 *
	 * Fails on 1.4.2 (Basecamp 10517640742): nothing gives the WPMediaVerse
	 * file back.
	 */
	public function test_upgrade_moves_back_files_taken_from_other_plugins(): void {
		$tag = wp_generate_password( 6, false );
		list( $theirs, $theirs_path ) = $this->moved_by_142( 'wpmediaverse/x-' . $tag . '.mp4' );
		list( $dated, $dated_path )   = $this->moved_by_142( '2026/01/loose-' . $tag . '.mp4' );
		$ours                         = $this->video();
		$this->assertFileDoesNotExist( $theirs_path );
		$this->assertFileDoesNotExist( $this->path );

		$this->upgrade_from_142();

		$this->assertFileExists( $theirs_path, 'The other plugin\'s file is back where it was.' );
		$this->assertSame( trailingslashit( wp_upload_dir()['baseurl'] ) . 'wpmediaverse/x-' . $tag . '.mp4', get_post_meta( $theirs, '_ms_source_url', true ) );
		$this->assertSame( '', (string) get_post_meta( $theirs, '_ms_platform_video_id', true ) );
		$this->assertArrayHasKey( $theirs, get_option( 'ms_library_files_report' )['left'], 'Listed in the owner notice.' );

		$this->assertFileDoesNotExist( $this->path, 'A Media Library file stays protected.' );
		$this->assertSame( 'private', get_post_field( 'post_status', $this->attachment ) );
		$this->assertSame( 'moved', get_post_meta( $ours, '_ms_library_file', true ) );
		$this->assertFileDoesNotExist( $dated_path, 'A file in the YYYY/MM layout stays protected.' );
		$this->assertSame( 'moved', get_post_meta( $dated, '_ms_library_file', true ) );

		// A second run changes nothing.
		$before = array(
			get_post_meta( $theirs ),
			get_post_meta( $dated ),
			get_post_meta( $ours ),
			get_attached_file( $this->attachment ),
		);
		update_option( 'ms_library_files_repair_pending', 0, false );
		$this->upgrade_from_142();
		$this->assertSame(
			$before,
			array(
				get_post_meta( $theirs ),
				get_post_meta( $dated ),
				get_post_meta( $ours ),
				get_attached_file( $this->attachment ),
			)
		);
		$this->assertFileExists( $theirs_path );
		$this->assertFileDoesNotExist( $this->path );

		wp_delete_file( $theirs_path );
		wp_delete_file( trailingslashit( wp_upload_dir()['basedir'] ) . 'mediashield/' . get_post_meta( $dated, '_ms_owned_file', true ) );
	}
}
