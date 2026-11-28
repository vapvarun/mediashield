<?php
/**
 * A role that may upload but not edit posts (Learnomy's Instructor) adds its
 * own uploaded file through mediashield_create_video (Basecamp 10551985723).
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use WP_UnitTestCase;

/**
 * Own file: yes, protected. Someone else's file or a bare link: no.
 */
class InstructorAddsOwnFileTest extends WP_UnitTestCase {

	private const VIDEO_FILE = '/cache/fixtures/beach-male.mp4';

	/**
	 * Instructor-like user id.
	 *
	 * @var int
	 */
	private $instructor;

	public function set_up(): void {
		parent::set_up();
		add_filter( 'pre_http_request', array( $this, 'offline' ) );
		add_role( 'ms_test_instructor', 'Instructor', array( 'read' => true, 'upload_files' => true ) );
		$this->instructor = self::factory()->user->create( array( 'role' => 'ms_test_instructor' ) );
	}

	public function tear_down(): void {
		remove_filter( 'pre_http_request', array( $this, 'offline' ) );
		remove_role( 'ms_test_instructor' );
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

	private function upload( int $author, string $name ): int {
		$file = getenv( 'MS_TEST_VIDEO' ) ? (string) getenv( 'MS_TEST_VIDEO' ) : self::VIDEO_FILE;
		$this->assertFileExists( $file );
		$dest = trailingslashit( wp_upload_dir()['path'] ) . $name;
		copy( $file, $dest );
		return self::factory()->attachment->create_object(
			$dest,
			0,
			array(
				'post_mime_type' => 'video/mp4',
				'post_title'     => 'Lesson clip',
				'post_author'    => $author,
			)
		);
	}

	/**
	 * Fails on a06b681: 403 "You are not allowed to add videos." because the
	 * door asked for create_posts (edit_posts) only.
	 */
	public function test_an_instructor_protects_their_own_upload(): void {
		wp_set_current_user( $this->instructor );
		$this->assertFalse( current_user_can( 'edit_posts' ), 'The role has no edit_posts, like lrn_instructor.' );
		$attachment = $this->upload( $this->instructor, 'instructor-own.mp4' );

		$id = apply_filters( 'mediashield_create_video', null, array( 'attachment_id' => $attachment ) );

		$this->assertIsInt( $id, is_wp_error( $id ) ? $id->get_error_message() : '' );
		$this->assertSame( 'publish', get_post_status( $id ), 'Their own upload goes live, so the lesson it was added from plays (owner, 2026-11-28).' );
		$this->assertStringContainsString( 'mediashield/v1/stream/' . $id, (string) get_post_meta( $id, '_ms_source_url', true ), 'The file moved into the protected folder.' );
		$this->assertSame( 'private', get_post_status( $attachment ) );
	}

	/**
	 * The pasted address of their own Media Library file is the same file.
	 */
	public function test_an_instructor_pastes_the_address_of_their_own_upload(): void {
		wp_set_current_user( $this->instructor );
		$attachment = $this->upload( $this->instructor, 'instructor-link.mp4' );

		$id = apply_filters( 'mediashield_create_video', null, array( 'source_url' => wp_get_attachment_url( $attachment ) ) );

		$this->assertIsInt( $id, is_wp_error( $id ) ? $id->get_error_message() : '' );
		$this->assertSame( 'publish', get_post_status( $id ) );
		$this->assertSame( 'private', get_post_status( $attachment ) );
	}

	/**
	 * Never someone else's file, by id or by address.
	 */
	public function test_an_instructor_cannot_use_someone_elses_upload(): void {
		$other      = self::factory()->user->create( array( 'role' => 'ms_test_instructor' ) );
		$attachment = $this->upload( $other, 'someone-else.mp4' );
		wp_set_current_user( $this->instructor );

		$by_id  = apply_filters( 'mediashield_create_video', null, array( 'attachment_id' => $attachment ) );
		$by_url = apply_filters( 'mediashield_create_video', null, array( 'source_url' => wp_get_attachment_url( $attachment ) ) );

		$this->assertWPError( $by_id );
		$this->assertSame( 403, $by_id->get_error_data()['status'] );
		$this->assertSame( 'You can add only videos made from your own uploads.', $by_id->get_error_message(), 'Someone who may add their own uploads is told that, not that they cannot add videos.' );
		$this->assertWPError( $by_url );
		$this->assertSame( 403, $by_url->get_error_data()['status'] );
		$this->assertNotSame( 'private', get_post_status( $attachment ), 'The other file is untouched.' );
	}

	/**
	 * No wider than their own files: a platform link still needs create_posts.
	 */
	public function test_an_instructor_cannot_add_a_platform_link(): void {
		wp_set_current_user( $this->instructor );

		$result = apply_filters( 'mediashield_create_video', null, array( 'source_url' => 'https://vimeo.com/76979871' ) );

		$this->assertWPError( $result );
		$this->assertSame( 403, $result->get_error_data()['status'] );
	}

	/**
	 * A subscriber (no upload_files) cannot use even a file they authored.
	 */
	public function test_a_subscriber_cannot_use_a_file_they_authored(): void {
		$subscriber = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$attachment = $this->upload( $subscriber, 'subscriber.mp4' );
		wp_set_current_user( $subscriber );

		$result = apply_filters( 'mediashield_create_video', null, array( 'attachment_id' => $attachment ) );

		$this->assertWPError( $result );
		$this->assertSame( 403, $result->get_error_data()['status'] );
	}
}
