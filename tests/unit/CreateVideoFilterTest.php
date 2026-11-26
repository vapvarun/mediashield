<?php
/**
 * The mediashield_create_video door for other plugins (Learnomy's lesson
 * editor, 1.4.4).
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\Support\Platforms;
use MediaShield\Upload\Drivers\SelfHosted;
use WP_UnitTestCase;

/**
 * Another plugin creates a video through one filter, never by writing MediaShield's data.
 */
class CreateVideoFilterTest extends WP_UnitTestCase {

	/**
	 * A real video file for the upload case (copied into the test container;
	 * MS_TEST_VIDEO points elsewhere).
	 */
	private const VIDEO_FILE = '/cache/fixtures/beach-male.mp4';

	public function set_up(): void {
		parent::set_up();
		// No platform lookups over the network.
		add_filter( 'pre_http_request', array( $this, 'offline' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
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

	private function create( array $args ) {
		return apply_filters( 'mediashield_create_video', null, $args );
	}

	/**
	 * Fails on d53c7b7: nothing answers the filter, so the caller gets null.
	 */
	public function test_a_good_link_creates_one_video_following_the_site_default(): void {
		$id = $this->create(
			array(
				'source_url' => 'https://www.youtube.com/watch?v=jNQXAC9IVRw',
				'title'      => 'Lesson 1: Me at the zoo',
			)
		);

		$this->assertIsInt( $id );
		$this->assertSame( 'mediashield_video', get_post_type( $id ) );
		$this->assertSame( 'publish', get_post_status( $id ) );
		$this->assertSame( 'Lesson 1: Me at the zoo', get_the_title( $id ) );
		$this->assertSame( 'youtube', get_post_meta( $id, '_ms_platform', true ) );
		$this->assertSame( 'jNQXAC9IVRw', get_post_meta( $id, '_ms_platform_video_id', true ) );
		$this->assertSame( '', get_post_meta( $id, '_ms_protection_level', true ), 'Follows the site default.' );
	}

	/**
	 * The same link, however it is written, returns the same video.
	 */
	public function test_the_same_link_twice_returns_the_same_video(): void {
		$first  = $this->create( array( 'source_url' => 'https://www.youtube.com/watch?v=jNQXAC9IVRw' ) );
		$second = $this->create( array( 'source_url' => 'https://youtu.be/jNQXAC9IVRw' ) );

		$this->assertIsInt( $first );
		$this->assertSame( $first, $second );
	}

	/**
	 * A link MediaShield cannot play is refused in the screens' own words.
	 */
	public function test_a_bad_link_is_refused_with_the_message(): void {
		$result = $this->create( array( 'source_url' => 'https://example.com/not-a-video' ) );

		$this->assertWPError( $result );
		$this->assertSame( 'mediashield_unplayable_source', $result->get_error_code() );
		$this->assertSame( Platforms::unplayable_message(), $result->get_error_message() );
		$this->assertSame( 400, $result->get_error_data()['status'] );
	}

	/**
	 * A member who may not add videos is refused.
	 */
	public function test_a_subscriber_is_refused(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$result = $this->create( array( 'source_url' => 'https://vimeo.com/76979871' ) );

		$this->assertWPError( $result );
		$this->assertSame( 403, $result->get_error_data()['status'] );
	}

	/**
	 * An uploaded file becomes a protected video that keeps the name it was
	 * uploaded with and its length; asking again returns the same video.
	 */
	public function test_an_upload_keeps_its_name_and_length(): void {
		$file = getenv( 'MS_TEST_VIDEO' ) ? (string) getenv( 'MS_TEST_VIDEO' ) : self::VIDEO_FILE;
		$this->assertFileExists( $file, 'Copy a real .mp4 to ' . self::VIDEO_FILE . ' in the test container.' );

		$uploads = wp_upload_dir();
		$dest    = trailingslashit( $uploads['path'] ) . 'Week 2 - Lighting.mp4';
		copy( $file, $dest );
		$attachment = self::factory()->attachment->create_object(
			$dest,
			0,
			array(
				'post_mime_type' => 'video/mp4',
				'post_title'     => 'Week 2 Lighting',
				'post_author'    => get_current_user_id(),
			)
		);

		$id = $this->create( array( 'attachment_id' => $attachment ) );

		$this->assertIsInt( $id );
		$this->assertSame( 'Week 2 Lighting', get_the_title( $id ) );
		$this->assertSame( 'self', get_post_meta( $id, '_ms_platform', true ) );
		$this->assertSame( sanitize_file_name( 'Week 2 - Lighting.mp4' ), get_post_meta( $id, SelfHosted::ORIGINAL_NAME, true ) );
		$this->assertGreaterThan( 0, (int) get_post_meta( $id, '_ms_duration', true ), 'The length is read from the file.' );
		$this->assertStringContainsString( 'mediashield/v1/stream/' . $id, (string) get_post_meta( $id, '_ms_source_url', true ), 'The file moved into the protected folder and plays through /stream/.' );
		$this->assertSame( 'private', get_post_status( $attachment ), 'The Media Library item is no longer public.' );
		$this->assertSame( $id, $this->create( array( 'attachment_id' => $attachment ) ), 'The same file twice is the same video.' );
	}
}
