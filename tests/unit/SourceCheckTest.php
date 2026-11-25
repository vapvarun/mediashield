<?php
/**
 * A link MediaShield cannot play is refused where it is added.
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\Support\Platforms;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Basecamp 10545260436: the wizard, Add video and the block saved any link.
 */
class SourceCheckTest extends WP_UnitTestCase {

	/**
	 * Admin, REST meta, no network.
	 */
	public function set_up(): void {
		parent::set_up();
		\MediaShield\CPT\VideoPostType::register_meta();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		add_filter(
			'pre_http_request',
			static function () {
				return new \WP_Error( 'ms_test_offline', 'No network in tests.' );
			}
		);
	}

	/**
	 * POST a video the way the wizard and the block do.
	 *
	 * @param string $url Link.
	 * @return \WP_REST_Response
	 */
	private function create( string $url ): \WP_REST_Response {
		$request = new WP_REST_Request( 'POST', '/wp/v2/mediashield-videos' );
		$request->set_body_params(
			array(
				'title'  => 'Wizard video',
				'status' => 'publish',
				'meta'   => array(
					'_ms_platform'          => 'iframe',
					'_ms_platform_video_id' => '',
					'_ms_source_url'        => $url,
				),
			)
		);
		return rest_do_request( $request );
	}

	/**
	 * Count the videos in the library.
	 */
	private function videos(): int {
		return count( get_posts( array( 'post_type' => 'mediashield_video', 'post_status' => 'any', 'fields' => 'ids', 'numberposts' => -1 ) ) );
	}

	/**
	 * The links owners paste are read to a platform and id.
	 */
	public function test_recognises_supported_links(): void {
		$cases = array(
			'https://www.youtube.com/watch?v=jNQXAC9IVRw'          => array( 'youtube', 'jNQXAC9IVRw' ),
			'https://youtu.be/jNQXAC9IVRw?t=3'                     => array( 'youtube', 'jNQXAC9IVRw' ),
			'https://www.youtube.com/shorts/jNQXAC9IVRw'           => array( 'youtube', 'jNQXAC9IVRw' ),
			'https://vimeo.com/76979871'                           => array( 'vimeo', '76979871' ),
			'https://player.vimeo.com/video/76979871'              => array( 'vimeo', '76979871' ),
			'https://fast.wistia.net/embed/iframe/e4a27b971d'      => array( 'wistia', 'e4a27b971d' ),
			'https://home.wistia.com/medias/e4a27b971d'            => array( 'wistia', 'e4a27b971d' ),
			'https://cdn.example.org/lesson-1.mp4'                 => array( 'self', '' ),
			'https://cdn.example.org/stream/master.m3u8?token=abc' => array( 'self', '' ),
			'https://iframe.mediadelivery.net/embed/806115/0b2b1c9e-3f0a-4e5f-9a1b-2c3d4e5f6a7b' => array( 'bunny', '0b2b1c9e-3f0a-4e5f-9a1b-2c3d4e5f6a7b' ),
		);
		foreach ( $cases as $url => $expected ) {
			$found = Platforms::recognise( $url );
			$this->assertIsArray( $found, $url );
			$this->assertSame( $expected, array( $found['platform'], $found['video_id'] ), $url );
		}
	}

	/**
	 * Links that can never play are refused with the message that says what to use.
	 */
	public function test_refuses_links_that_cannot_play(): void {
		foreach ( array( 'https://example.com/not-a-video', 'hello world', 'https://www.youtube.com/', 'https://vimeo.com/channels', 'ftp://example.com/a.mp4' ) as $url ) {
			$found = Platforms::recognise( $url );
			$this->assertWPError( $found, $url );
			$this->assertSame( 'mediashield_unplayable_source', $found->get_error_code() );
			$this->assertStringContainsString( 'upload a file', $found->get_error_message() );
		}
	}

	/**
	 * The wizard and the block (REST) no longer create a video from a junk link.
	 */
	public function test_rest_create_refuses_a_link_that_cannot_play(): void {
		$before   = $this->videos();
		$response = $this->create( 'https://example.com/not-a-video' );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'mediashield_unplayable_source', $response->get_data()['code'] );
		$this->assertSame( $before, $this->videos(), 'No library video is created.' );
	}

	/**
	 * A good link is saved with the platform the server read, not the browser's guess.
	 */
	public function test_rest_create_stores_the_recognised_platform(): void {
		$response = $this->create( 'https://youtu.be/jNQXAC9IVRw' );

		$this->assertSame( 201, $response->get_status() );
		$id = (int) $response->get_data()['id'];
		$this->assertSame( 'youtube', get_post_meta( $id, '_ms_platform', true ) );
		$this->assertSame( 'jNQXAC9IVRw', get_post_meta( $id, '_ms_platform_video_id', true ) );
	}

	/**
	 * Changing an existing video's link to junk over REST is refused; its old link stays.
	 */
	public function test_rest_update_refuses_and_keeps_the_old_link(): void {
		$id = (int) $this->create( 'https://vimeo.com/76979871' )->get_data()['id'];

		$request = new WP_REST_Request( 'POST', '/wp/v2/mediashield-videos/' . $id );
		$request->set_body_params( array( 'meta' => array( '_ms_source_url' => 'https://example.com/page' ) ) );
		$response = rest_do_request( $request );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'https://vimeo.com/76979871', get_post_meta( $id, '_ms_source_url', true ) );
	}

	/**
	 * The edit screen does not save a junk link and keeps the old one.
	 */
	public function test_edit_screen_keeps_the_old_link(): void {
		$id = self::factory()->post->create( array( 'post_type' => 'mediashield_video' ) );
		update_post_meta( $id, '_ms_source_url', 'https://vimeo.com/76979871' );
		update_post_meta( $id, '_ms_platform', 'vimeo' );

		$_POST = array(
			'_ms_meta_nonce' => wp_create_nonce( 'mediashield_video_meta' ),
			'_ms_source_url' => 'https://example.com/page',
			'_ms_platform'   => 'self',
			'_ms_duration'   => '30',
		);
		\MediaShield\CPT\VideoPostType::save_meta_box( $id, get_post( $id ) );
		$_POST = array();

		$this->assertSame( 'https://vimeo.com/76979871', get_post_meta( $id, '_ms_source_url', true ) );
		$this->assertSame( 'vimeo', get_post_meta( $id, '_ms_platform', true ) );
		$this->assertSame( '30', get_post_meta( $id, '_ms_duration', true ), 'The rest of the form still saves.' );
		$this->assertStringContainsString( 'ms_source_refused=1', \MediaShield\CPT\VideoPostType::flag_refused_source( admin_url( 'post.php' ), $id ) );
	}
}
