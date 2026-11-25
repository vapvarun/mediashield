<?php
/**
 * Regression tests for Basecamp 10545260523 (1.4.4): the Video block reuses
 * library videos and names the video it shows.
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\CPT\VideoUsage;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * GET /videos/match and GET /videos/{id}/summary.
 */
class VideoBlockLibraryTest extends WP_UnitTestCase {

	/** @var int */
	private $video;

	public function set_up(): void {
		parent::set_up();
		VideoUsage::flush();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->video = self::factory()->post->create(
			array(
				'post_type'   => 'mediashield_video',
				'post_status' => 'publish',
				'post_title'  => 'Studio lighting basics',
			)
		);
		update_post_meta( $this->video, '_ms_platform', 'youtube' );
		update_post_meta( $this->video, '_ms_platform_video_id', 'jNQXAC9IVRw' );
		update_post_meta( $this->video, '_ms_source_url', 'https://www.youtube.com/watch?v=jNQXAC9IVRw' );
		update_post_meta( $this->video, '_ms_duration', 724 );
	}

	private function get( string $route, array $params = array() ): \WP_REST_Response {
		$request = new WP_REST_Request( 'GET', '/mediashield/v1' . $route );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}
		return rest_do_request( $request );
	}

	/**
	 * Fails on 2a78d40 (no route): pasting a link already in the library made a second video.
	 */
	public function test_a_link_already_in_the_library_is_found_in_any_spelling(): void {
		foreach ( array( 'https://youtu.be/jNQXAC9IVRw', 'https://www.youtube.com/watch?v=jNQXAC9IVRw&t=10s' ) as $link ) {
			$response = $this->get( '/videos/match', array( 'url' => $link ) );

			$this->assertSame( 200, $response->get_status(), $link );
			$match = $response->get_data()['match'];
			$this->assertSame( $this->video, $match['id'], $link );
			$this->assertSame( 'Studio lighting basics', $match['title'] );
			$this->assertSame( 'YouTube', $match['platform_label'] );
			$this->assertSame( 724, $match['duration'] );
			// Test change: a video that follows the site reads like everywhere else.
			$this->assertSame( 'Site default (Standard)', $match['protection_label'] );
		}
	}

	/**
	 * A new link, a video file, a trashed copy and a link that is not a video.
	 */
	public function test_new_file_trashed_and_unplayable_links(): void {
		$this->assertNull( $this->get( '/videos/match', array( 'url' => 'https://vimeo.com/76979871' ) )->get_data()['match'] );

		$file = self::factory()->post->create( array( 'post_type' => 'mediashield_video', 'post_status' => 'publish', 'post_title' => 'Beach walk' ) );
		update_post_meta( $file, '_ms_platform', 'self' );
		update_post_meta( $file, '_ms_source_url', 'https://cdn.example.org/beach.mp4' );
		$this->assertSame( $file, $this->get( '/videos/match', array( 'url' => 'https://cdn.example.org/beach.mp4' ) )->get_data()['match']['id'] );
		$this->assertSame( 'Video file', $this->get( '/videos/match', array( 'url' => 'https://cdn.example.org/beach.mp4' ) )->get_data()['match']['platform_label'] );

		wp_trash_post( $this->video );
		$this->assertNull( $this->get( '/videos/match', array( 'url' => 'https://youtu.be/jNQXAC9IVRw' ) )->get_data()['match'] );

		$this->assertSame( 400, $this->get( '/videos/match', array( 'url' => 'hello world' ) )->get_status() );
	}

	/**
	 * Fails on 2a78d40 (no route): the sidebar showed "ID: 8" and its own protection select.
	 */
	public function test_summary_names_the_video_and_where_else_it_is_used(): void {
		$here  = self::factory()->post->create( array( 'post_type' => 'page', 'post_title' => 'Week 2: Lighting', 'post_content' => '<!-- wp:mediashield/video {"videoId":' . $this->video . '} /-->' ) );
		$other = self::factory()->post->create( array( 'post_type' => 'page', 'post_title' => 'Week 3: Colour', 'post_content' => '[mediashield id=' . $this->video . ']' ) );
		update_post_meta( $this->video, '_ms_protection_level', 'strict' );

		$data = $this->get( '/videos/' . $this->video . '/summary', array( 'post_id' => $here ) )->get_data();

		$this->assertSame( 'Studio lighting basics', $data['title'] );
		$this->assertSame( 'YouTube', $data['platform_label'] );
		$this->assertSame( 'Strict', $data['protection_label'] );
		$this->assertFalse( $data['follows_default'] );
		$this->assertStringContainsString( 'post=' . $this->video, $data['edit_url'] );
		$this->assertSame( array( $other ), wp_list_pluck( $data['used_in'], 'id' ), 'The page being edited is not counted.' );
		$this->assertSame( 'https://i.ytimg.com/vi/jNQXAC9IVRw/hqdefault.jpg', $data['thumbnail'], 'YouTube\'s thumbnail when the owner set no poster.' );
	}

	/**
	 * QA finding: the block showed "Rick Astley &#8211; Never Gonna Give You Up"
	 * and "Standard (site default)". Fails on 4393798.
	 */
	public function test_title_is_plain_text_and_default_reads_like_everywhere_else(): void {
		wp_update_post(
			array(
				'ID'         => $this->video,
				'post_title' => 'Rick Astley - Never Gonna Give You Up',
			)
		);

		$data = $this->get( '/videos/' . $this->video . '/summary' )->get_data();

		$this->assertSame( "Rick Astley \u{2013} Never Gonna Give You Up", $data['title'] );
		$this->assertStringNotContainsString( '&#', $data['title'] );
		$this->assertSame( \MediaShield\Player\Protection::site_default_label(), $data['protection_label'] );
		$this->assertSame( 'Site default (Standard)', $data['protection_label'] );
	}

	/**
	 * Members cannot read library details.
	 */
	public function test_subscriber_is_refused(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$this->assertSame( 403, $this->get( '/videos/' . $this->video . '/summary' )->get_status() );
		$this->assertSame( 403, $this->get( '/videos/match', array( 'url' => 'https://youtu.be/jNQXAC9IVRw' ) )->get_status() );
	}
}
