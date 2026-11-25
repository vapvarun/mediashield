<?php
/**
 * Regression tests for Basecamp 10545260568 (1.4.4): player states.
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\CPT\SourceStatus;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Each refusal or failure has a next step, and a video the platform refuses
 * is recorded for the Videos list.
 */
class PlayerStatesTest extends WP_UnitTestCase {

	/** @var int */
	private $video;

	/** @var int Status code the fake platform answers. */
	private $oembed_status = 404;

	/** @var int Requests the fake platform received. */
	private $oembed_calls = 0;

	public function set_up(): void {
		parent::set_up();
		$this->video = self::factory()->post->create(
			array(
				'post_type'   => 'mediashield_video',
				'post_status' => 'publish',
				'post_title'  => 'Studio lighting basics',
			)
		);
		update_post_meta( $this->video, '_ms_platform', 'youtube' );
		update_post_meta( $this->video, '_ms_platform_video_id', 'dQw4w9WgXcQ' );
		add_filter( 'pre_http_request', array( $this, 'fake_oembed' ), 10, 3 );
	}

	public function tear_down(): void {
		remove_filter( 'pre_http_request', array( $this, 'fake_oembed' ), 10 );
		delete_option( 'ms_access_denied_url' );
		parent::tear_down();
	}

	/**
	 * Answer oEmbed requests without the network.
	 *
	 * @param false|array $pre  Short-circuit.
	 * @param array       $args Request args.
	 * @param string      $url  URL.
	 * @return false|array
	 */
	public function fake_oembed( $pre, $args, $url ) {
		if ( false === strpos( $url, 'oembed' ) ) {
			return $pre;
		}
		++$this->oembed_calls;
		return array(
			'headers'  => array(),
			'body'     => '',
			'response' => array(
				'code'    => $this->oembed_status,
				'message' => '',
			),
			'cookies'  => array(),
		);
	}

	private function report( int $video_id, string $code = 'removed' ): \WP_REST_Response {
		$request = new WP_REST_Request( 'POST', '/mediashield/v1/videos/' . $video_id . '/unavailable' );
		$request->set_param( 'code', $code );
		return rest_do_request( $request );
	}

	/**
	 * Fails on bfa2b26: no route, nothing recorded, the Videos list kept saying Ready.
	 */
	public function test_guest_report_confirmed_by_the_platform_is_recorded(): void {
		wp_set_current_user( 0 );

		$response = $this->report( $this->video );

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['recorded'] );
		$this->assertSame( 'removed', SourceStatus::get( $this->video )['code'] );
		$this->assertSame( 1, $this->oembed_calls );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$list = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/mediashield-videos/' . $this->video ) )->get_data();
		$this->assertSame( 'unavailable', $list['ms_list']['status'] );
	}

	/**
	 * A guest cannot mark a working video broken.
	 */
	public function test_report_the_platform_denies_is_not_recorded(): void {
		wp_set_current_user( 0 );
		$this->oembed_status = 200;

		$this->assertFalse( $this->report( $this->video )->get_data()['recorded'] );
		$this->assertNull( SourceStatus::get( $this->video ) );

		// And cannot make the server ask the platform again and again.
		$this->report( $this->video );
		$this->assertSame( 1, $this->oembed_calls );
	}

	/**
	 * A platform with no public check: only someone who edits the video is believed.
	 */
	public function test_unverifiable_platform_needs_an_editor(): void {
		update_post_meta( $this->video, '_ms_platform', 'wistia' );

		wp_set_current_user( 0 );
		$this->assertFalse( $this->report( $this->video )->get_data()['recorded'] );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertTrue( $this->report( $this->video )->get_data()['recorded'] );
		$this->assertSame( 0, $this->oembed_calls );
	}

	/**
	 * Saving the video (a new link, say) clears the record.
	 */
	public function test_saving_the_video_clears_the_record(): void {
		SourceStatus::mark_unavailable( $this->video, 'private' );

		wp_update_post(
			array(
				'ID'         => $this->video,
				'post_title' => 'Studio lighting basics (new link)',
			)
		);

		$this->assertNull( SourceStatus::get( $this->video ) );
	}

	/**
	 * Fails on bfa2b26: a member refused a video got the message and no next step.
	 */
	public function test_refused_member_gets_the_owners_membership_link(): void {
		update_post_meta( $this->video, '_ms_access_role', 'editor' );
		update_option( 'ms_access_denied_url', 'https://example.org/join/' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$request = new WP_REST_Request( 'POST', '/mediashield/v1/session/start' );
		$request->set_body_params( array( 'video_id' => $this->video ) );
		$data = rest_do_request( $request )->as_error()->get_error_data();

		$this->assertSame( 403, $data['status'] );
		$this->assertSame( 'https://example.org/join/', $data['access_url'] );
		$this->assertSame( 'See membership options', $data['access_label'] );
	}

	/**
	 * No page set: no button, as before.
	 */
	public function test_no_membership_page_means_no_button(): void {
		update_post_meta( $this->video, '_ms_access_role', 'editor' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$request = new WP_REST_Request( 'POST', '/mediashield/v1/session/start' );
		$request->set_body_params( array( 'video_id' => $this->video ) );
		$data = rest_do_request( $request )->as_error()->get_error_data();

		$this->assertArrayNotHasKey( 'access_url', $data );
	}

	/**
	 * Fails on bfa2b26: the player had no way to tell an owner what broke or where to fix it.
	 */
	public function test_edit_link_reaches_the_player_only_for_editors(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertStringContainsString( 'data-edit-url="', \MediaShield\Player\Renderer::render( $this->video ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->assertStringNotContainsString( 'data-edit-url', \MediaShield\Player\Renderer::render( $this->video ) );
	}
}
