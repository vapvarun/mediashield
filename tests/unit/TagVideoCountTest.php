<?php
/**
 * Regression test for Basecamp 10526725655 (1.4.3).
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\Tags\TagManager;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * The Tags screen's Videos column shows how many videos carry each tag.
 */
class TagVideoCountTest extends WP_UnitTestCase {

	/**
	 * Fails on 1.4.2: GET /tags returned rows without video_count, so the column read 0.
	 */
	public function test_tag_list_carries_video_counts(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$used   = TagManager::create( 'Count used ' . wp_generate_password( 4, false ) );
		$unused = TagManager::create( 'Count unused ' . wp_generate_password( 4, false ) );
		foreach ( self::factory()->post->create_many( 2, array( 'post_type' => 'mediashield_video' ) ) as $video ) {
			TagManager::assign_to_video( $video, $used );
		}

		$request = new WP_REST_Request( 'GET', '/mediashield/v1/tags' );
		$request->set_param( 'search', 'Count ' );
		$counts = array_column( rest_do_request( $request )->get_data(), 'video_count', 'id' );

		$this->assertSame( 2, $counts[ $used ] ?? null );
		$this->assertSame( 0, $counts[ $unused ] ?? null );
	}
}
