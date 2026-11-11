<?php
/**
 * A pasted platform video takes the platform's title, with one bounded request.
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Card 10482552039: Thumbnail::maybe_use_platform_title().
 */
class PlatformTitleTest extends WP_UnitTestCase {

	/**
	 * Every outgoing request URL, in order.
	 *
	 * @var string[]
	 */
	private array $requests = array();

	/**
	 * Answer oEmbed requests locally and refuse everything else.
	 */
	public function set_up(): void {
		parent::set_up();
		// The test case resets post types between tests, which drops the REST meta.
		\MediaShield\CPT\VideoPostType::register_meta();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		add_filter(
			'pre_http_request',
			function ( $pre, $args, $url ) {
				$this->requests[] = $url;
				if ( false !== strpos( $url, '/oembed' ) ) {
					return array(
						'headers'  => array(),
						'body'     => wp_json_encode( array( 'type' => 'video', 'version' => '1.0', 'title' => 'Me at the zoo', 'html' => '<iframe></iframe>' ) ),
						'response' => array( 'code' => 200, 'message' => 'OK' ),
						'cookies'  => array(),
					);
				}
				return new \WP_Error( 'msv_test_offline', 'No network in tests.' );
			},
			10,
			3
		);
	}

	/**
	 * Create a video through REST the way the wizard and the block do.
	 *
	 * @param string $platform Platform slug.
	 * @param string $id       Platform video id.
	 * @param string $url      Source URL.
	 * @return int Post ID.
	 */
	private function create( string $platform, string $id, string $url ): int {
		$request = new WP_REST_Request( 'POST', '/wp/v2/mediashield-videos' );
		$request->set_body_params(
			array(
				'title'  => "{$platform} - {$id}",
				'status' => 'publish',
				'meta'   => array(
					'_ms_platform'          => $platform,
					'_ms_platform_video_id' => $id,
					'_ms_source_url'        => $url,
				),
			)
		);
		return (int) rest_do_request( $request )->get_data()['id'];
	}

	/**
	 * Number of oEmbed requests sent so far.
	 */
	private function oembed_requests(): int {
		return count( array_filter( $this->requests, static fn( $u ) => false !== strpos( $u, '/oembed' ) ) );
	}

	/**
	 * Create: one oEmbed request, and the title is the platform's.
	 */
	public function test_create_takes_the_platform_title_with_one_request(): void {
		$id = $this->create( 'youtube', 'jNQXAC9IVRw', 'https://www.youtube.com/watch?v=jNQXAC9IVRw' );

		$this->assertSame( 'Me at the zoo', get_post( $id )->post_title );
		$this->assertSame( 1, $this->oembed_requests() );
	}

	/**
	 * Update: no request, even while the title is still the placeholder.
	 */
	public function test_update_sends_no_request(): void {
		$id = self::factory()->post->create( array( 'post_type' => 'mediashield_video', 'post_title' => 'youtube - jNQXAC9IVRw' ) );
		update_post_meta( $id, '_ms_platform', 'youtube' );
		update_post_meta( $id, '_ms_platform_video_id', 'jNQXAC9IVRw' );
		update_post_meta( $id, '_ms_source_url', 'https://www.youtube.com/watch?v=jNQXAC9IVRw' );

		$request = new WP_REST_Request( 'POST', '/wp/v2/mediashield-videos/' . $id );
		$request->set_body_params( array( 'meta' => array( '_ms_protection_level' => 'strict' ) ) );
		rest_do_request( $request );

		$this->assertSame( 0, $this->oembed_requests() );
		$this->assertSame( 'youtube - jNQXAC9IVRw', get_post( $id )->post_title );
	}

	/**
	 * A host with no known oEmbed provider sends nothing (no discovery).
	 */
	public function test_unknown_provider_sends_no_request(): void {
		$id = $this->create( 'bunny', 'abc-123', 'https://iframe.mediadelivery.net/embed/806115/abc-123' );

		// No oEmbed: the neutral title, never the ID (Basecamp 10499285942).
		$this->assertSame( \MediaShield\CPT\Thumbnail::neutral_title( $id ), get_post( $id )->post_title );
		$this->assertSame( array(), array_filter( $this->requests, static fn( $u ) => false !== strpos( $u, 'mediadelivery' ) ) );
	}
}
