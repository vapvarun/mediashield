<?php
/**
 * /stream/{id}/url answers only where the page render would print the address.
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Security review of the token refresh for BC#10497708913. The route dates
 * from 1.4.0; before this it checked can_watch() only, not the video itself.
 */
class StreamRefreshAccessTest extends WP_UnitTestCase {

	const STREAM = 'https://cdn.example.test/v/playlist.m3u8';

	/**
	 * Published video with a stored stream address.
	 *
	 * @var int
	 */
	private int $video = 0;

	/**
	 * Fixtures.
	 */
	public function set_up(): void {
		parent::set_up();
		do_action( 'rest_api_init' );
		update_option( 'ms_require_login', true );
		$this->video = self::factory()->post->create( array( 'post_type' => 'mediashield_video', 'post_status' => 'publish' ) );
		update_post_meta( $this->video, '_ms_platform', 'self' );
		update_post_meta( $this->video, '_ms_stream_url', self::STREAM );
	}

	/**
	 * GET the refresh route.
	 *
	 * @param int $id Video id.
	 * @return \WP_REST_Response
	 */
	private function refresh( int $id ) {
		return rest_do_request( new WP_REST_Request( 'GET', "/mediashield/v1/stream/{$id}/url" ) );
	}

	/**
	 * An allowed viewer gets the address the render prints.
	 */
	public function test_allowed_viewer_gets_the_render_address(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$res = $this->refresh( $this->video );

		$this->assertSame( 200, $res->get_status() );
		$this->assertSame( self::STREAM, $res->get_data()['url'] );
		$this->assertStringContainsString( 'data-stream-url="' . esc_attr( self::STREAM ) . '"', \MediaShield\Player\Renderer::render( $this->video ) );
	}

	/**
	 * Anonymous with Require Login is refused.
	 */
	public function test_anonymous_with_require_login_is_refused(): void {
		wp_set_current_user( 0 );
		$this->assertSame( 403, $this->refresh( $this->video )->get_status() );
	}

	/**
	 * A logged-in viewer an access rule refuses (LMS enrollment hooks this filter) is refused.
	 */
	public function test_viewer_refused_by_an_access_rule_is_refused(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$deny = static function () {
			return array( 'allowed' => false, 'reason' => 'Not enrolled.' );
		};
		add_filter( 'mediashield_can_watch', $deny );

		$status = $this->refresh( $this->video )->get_status();
		remove_filter( 'mediashield_can_watch', $deny );

		$this->assertSame( 403, $status );
	}

	/**
	 * Fails before the fix: a draft video got a fresh address. Refused as not
	 * found since BC#10499264493, the same answer as an unknown id.
	 */
	public function test_draft_video_is_refused(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		wp_update_post( array( 'ID' => $this->video, 'post_status' => 'draft' ) );

		$this->assertSame( 404, $this->refresh( $this->video )->get_status() );
	}

	/**
	 * Fails before the fix: any post id got an address.
	 */
	public function test_non_video_post_and_unknown_id_are_not_found(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$post = self::factory()->post->create();

		$this->assertSame( 404, $this->refresh( $post )->get_status() );
		$this->assertSame( 404, $this->refresh( 999999 )->get_status() );
	}

	/**
	 * A draft id answers exactly like an unknown id, so a guest cannot probe
	 * which ids are unpublished videos (BC#10499264493). Fails before the fix:
	 * a draft answered 403 "This video is not published." on the refresh, 403
	 * on the stream and 403 on session start, an unknown id 404.
	 */
	public function test_a_draft_answers_like_an_unknown_id(): void {
		update_option( 'ms_require_login', false );
		wp_set_current_user( 0 );
		$draft = self::factory()->post->create( array( 'post_type' => 'mediashield_video', 'post_status' => 'draft' ) );
		update_post_meta( $draft, '_ms_platform', 'self' );

		foreach ( array( $draft, 999999 ) as $id ) {
			$this->assertSame( 404, $this->refresh( $id )->get_status(), "refresh {$id}" );
			$this->assertSame( 404, rest_do_request( new WP_REST_Request( 'GET', "/mediashield/v1/stream/{$id}" ) )->get_status(), "stream {$id}" );

			$start = new WP_REST_Request( 'POST', '/mediashield/v1/session/start' );
			$start->set_param( 'video_id', $id );
			$this->assertSame( 404, rest_do_request( $start )->get_status(), "session start {$id}" );
		}
	}
}
