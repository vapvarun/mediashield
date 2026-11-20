<?php
/**
 * Regression test for Basecamp 10530930631 (1.4.3).
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use WP_REST_Request;
use WP_UnitTestCase;

/**
 * A refused member is told which course the video belongs to, with a link.
 */
class DeniedStateTest extends WP_UnitTestCase {

	/** @var int */
	private $video;

	public function set_up(): void {
		parent::set_up();
		$this->video = self::factory()->post->create( array( 'post_type' => 'mediashield_video', 'post_status' => 'publish' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
	}

	private function start(): \WP_REST_Response {
		$request = new WP_REST_Request( 'POST', '/mediashield/v1/session/start' );
		$request->set_body_params( array( 'video_id' => $this->video ) );
		return rest_do_request( $request );
	}

	/**
	 * Fails on 4e6e8db: the refusal carried only the LMS's own words, no
	 * course name and nothing to click.
	 */
	public function test_course_refusal_names_the_course_and_links_to_it(): void {
		$deny = static fn() => array( 'allowed' => false, 'reason' => 'Enroll in this course to watch this video.' );
		$link = static fn() => array( 'title' => 'Portrait Photography Fundamentals', 'url' => 'https://example.org/courses/portrait/' );
		add_filter( 'mediashield_can_watch', $deny );
		add_filter( 'mediashield_video_access_link', $link );

		$response = $this->start();
		$error    = $response->as_error();
		$data     = $error->get_error_data();

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( 'This video is part of Portrait Photography Fundamentals. Enroll to watch it.', $error->get_error_message() );
		$this->assertSame( 'https://example.org/courses/portrait/', $data['access_url'] );
		$this->assertSame( 'View course', $data['access_label'] );

		remove_filter( 'mediashield_can_watch', $deny );
		remove_filter( 'mediashield_video_access_link', $link );
	}

	public function test_role_refusal_stays_neutral_and_has_no_link(): void {
		update_post_meta( $this->video, '_ms_access_role', 'editor' );
		$link = static fn() => array( 'title' => 'Some course', 'url' => 'https://example.org/c/' );
		add_filter( 'mediashield_video_access_link', $link );

		$error = $this->start()->as_error();

		$this->assertArrayNotHasKey( 'access_url', (array) $error->get_error_data() );
		$this->assertStringNotContainsString( 'Some course', $error->get_error_message() );

		remove_filter( 'mediashield_video_access_link', $link );
	}
}
