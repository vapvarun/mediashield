<?php
/**
 * The Videos list filters are for people who edit videos.
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use WP_REST_Request;
use WP_UnitTestCase;

/**
 * A visitor must not be able to read a video's protection level by filtering the
 * public collection on it (an oracle on settings 1.4.1 hides from visitors).
 */
class VideoListFilterAccessTest extends WP_UnitTestCase {

	/**
	 * A server another test built keeps hooks this test has since rolled back; start a fresh one.
	 */
	public function set_up(): void {
		parent::set_up();
		$GLOBALS['wp_rest_server'] = null;
	}

	/**
	 * Leave no server built with this test's hooks behind.
	 */
	public function tear_down(): void {
		$GLOBALS['wp_rest_server'] = null;
		parent::tear_down();
	}

	/**
	 * Ids a request returns.
	 *
	 * @param array<string,string> $params Query params.
	 * @return int[]
	 */
	private function ids( array $params ): array {
		$request = new WP_REST_Request( 'GET', '/wp/v2/mediashield-videos' );
		$request->set_query_params( $params );
		$response = rest_do_request( $request );

		return array_map( 'intval', wp_list_pluck( (array) $response->get_data(), 'id' ) );
	}

	/**
	 * Fails before the guard: a logged-out filter on a level returned only matching videos.
	 */
	public function test_visitor_filter_does_not_reveal_protection_level(): void {
		$strict = self::factory()->post->create(
			array(
				'post_type'   => 'mediashield_video',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $strict, '_ms_protection_level', 'strict' );
		$open = self::factory()->post->create(
			array(
				'post_type'   => 'mediashield_video',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $open, '_ms_protection_level', 'basic' );

		// Test change (1.4.4): a visitor no longer reads the library at all
		// (VideoListController::editors_only()), so no filter can tell them a level.
		wp_set_current_user( 0 );
		foreach ( array( array(), array( 'ms_protection' => 'strict' ) ) as $params ) {
			$request = new WP_REST_Request( 'GET', '/wp/v2/mediashield-videos' );
			$request->set_query_params( $params );
			$this->assertSame( 401, rest_do_request( $request )->get_status(), 'A visitor is refused whatever level is asked for.' );
		}

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->assertSame( array( $strict ), $this->ids( array( 'ms_protection' => 'strict' ) ) );
		$this->assertContains( $open, $this->ids( array() ) );
	}
}
