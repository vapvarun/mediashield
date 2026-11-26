<?php
/**
 * mediashield_create_video's `replacing` (1.4.4): the reference about to
 * switch to the new video does not keep its file in place.
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use WP_UnitTestCase;

/**
 * Learnomy's "Protect this video" switches the lesson only after MediaShield
 * succeeds, so while it asks, the lesson still names the file.
 *
 * In its own process: the \\Learnomy\\table() stub would otherwise stay
 * defined for later tests, whose Learnomy lookups would then find no table.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class CreateVideoReplacingTest extends WP_UnitTestCase {

	/**
	 * A real video file (copied into the test container; MS_TEST_VIDEO points elsewhere).
	 */
	private const VIDEO_FILE = '/cache/fixtures/beach-male.mp4';

	/** @var int */
	private $attachment;

	/** @var string */
	private $url;

	public function set_up(): void {
		parent::set_up();
		global $wpdb;
		if ( ! function_exists( '\\Learnomy\\table' ) ) {
			eval( 'namespace Learnomy; function table( $name ) { global $wpdb; return $wpdb->prefix . "lrn_" . $name; }' ); // phpcs:ignore Squiz.PHP.Eval.Discouraged -- test stub of Learnomy's table().
		}
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- temporary test tables (WP_UnitTestCase makes CREATE TABLE temporary).
		$wpdb->query( "CREATE TABLE {$wpdb->prefix}lrn_lessons ( id bigint unsigned NOT NULL AUTO_INCREMENT, video_url varchar(500), content longtext, media_id bigint unsigned, PRIMARY KEY (id) )" );
		$wpdb->query( "CREATE TABLE {$wpdb->prefix}lrn_courses ( id bigint unsigned NOT NULL AUTO_INCREMENT, video_url varchar(500), content longtext, description longtext, PRIMARY KEY (id) )" );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		add_filter( 'pre_http_request', array( $this, 'offline' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$file = getenv( 'MS_TEST_VIDEO' ) ? (string) getenv( 'MS_TEST_VIDEO' ) : self::VIDEO_FILE;
		$this->assertFileExists( $file );
		$uploads = wp_upload_dir();
		$dest    = trailingslashit( $uploads['path'] ) . 'replacing-' . wp_generate_password( 6, false ) . '.mp4';
		copy( $file, $dest );
		$this->attachment = self::factory()->attachment->create_object(
			$dest,
			0,
			array(
				'post_mime_type' => 'video/mp4',
				'post_title'     => 'Lesson file',
				'post_author'    => get_current_user_id(),
			)
		);
		$this->url = (string) wp_get_attachment_url( $this->attachment );
	}

	public function tear_down(): void {
		remove_filter( 'pre_http_request', array( $this, 'offline' ) );
		parent::tear_down();
	}

	/**
	 * Refuse every HTTP request.
	 *
	 * @return \WP_Error
	 */
	public function offline() {
		return new \WP_Error( 'offline', 'Offline in tests.' );
	}

	private function lesson(): int {
		global $wpdb;
		$wpdb->insert( $wpdb->prefix . 'lrn_lessons', array( 'video_url' => $this->url, 'content' => '' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		return (int) $wpdb->insert_id;
	}

	private function post_using_file(): int {
		return self::factory()->post->create( array( 'post_content' => '<!-- wp:video --><figure class="wp-block-video"><video src="' . esc_url( $this->url ) . '"></video></figure><!-- /wp:video -->' ) );
	}

	private function create( array $replacing = array() ) {
		$args = array( 'attachment_id' => $this->attachment );
		if ( $replacing ) {
			$args['replacing'] = $replacing;
		}
		return apply_filters( 'mediashield_create_video', null, $args );
	}

	private function moved( int $video ): bool {
		return false !== strpos( (string) get_post_meta( $video, '_ms_source_url', true ), 'mediashield/v1/stream/' . $video );
	}

	/**
	 * Without `replacing`, the lesson naming the file keeps it in place.
	 */
	public function test_a_file_a_lesson_uses_stays_without_replacing(): void {
		$this->lesson();
		$id = $this->create();
		$this->assertIsInt( $id );
		$this->assertFalse( $this->moved( $id ) );
	}

	/**
	 * Used only by the lesson switching to the video: protected. Fails before 1.4.4's `replacing`.
	 */
	public function test_a_file_used_only_by_the_replacing_lesson_is_protected(): void {
		$lesson = $this->lesson();
		$id     = $this->create( array( 'type' => 'learnomy_lesson', 'id' => $lesson ) );
		$this->assertIsInt( $id );
		$this->assertTrue( $this->moved( $id ) );
		$this->assertSame( 'private', get_post_status( $this->attachment ) );
	}

	/**
	 * Also used by a post: the file stays where it is.
	 */
	public function test_a_file_also_used_by_a_post_stays(): void {
		$lesson = $this->lesson();
		$this->post_using_file();
		$id = $this->create( array( 'type' => 'learnomy_lesson', 'id' => $lesson ) );
		$this->assertIsInt( $id );
		$this->assertFalse( $this->moved( $id ) );
		$this->assertSame( 'in_use', get_post_meta( $id, '_ms_library_file', true ) );
	}

	/**
	 * Also used by another lesson: the file stays where it is.
	 */
	public function test_a_file_also_used_by_another_lesson_stays(): void {
		$lesson = $this->lesson();
		$this->lesson();
		$id = $this->create( array( 'type' => 'learnomy_lesson', 'id' => $lesson ) );
		$this->assertFalse( $this->moved( $id ) );
	}

	/**
	 * The 'post' type: a WordPress post about to switch is not a use. Fails before 1.4.4.
	 */
	public function test_the_post_type_skips_that_post_only(): void {
		$post = $this->post_using_file();
		$id   = $this->create( array( 'type' => 'post', 'id' => $post ) );
		$this->assertIsInt( $id );
		$this->assertTrue( $this->moved( $id ) );
	}

	/**
	 * A video already made for the file and left in place because of the
	 * lesson is protected when the lesson asks again with `replacing`.
	 */
	public function test_an_existing_in_use_video_is_checked_again(): void {
		$lesson = $this->lesson();
		$first  = $this->create();
		$this->assertFalse( $this->moved( $first ) );

		$again = $this->create( array( 'type' => 'learnomy_lesson', 'id' => $lesson ) );
		$this->assertSame( $first, $again );
		$this->assertTrue( $this->moved( $first ) );
	}
}
