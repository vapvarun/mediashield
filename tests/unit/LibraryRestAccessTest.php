<?php
/**
 * QA finding (Basecamp 10545260546, 1.4.4): the library's wp/v2 routes were
 * readable by anyone.
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use WP_REST_Request;
use WP_UnitTestCase;

/**
 * /wp/v2/mediashield-playlists and /wp/v2/mediashield-videos: editors only.
 */
class LibraryRestAccessTest extends WP_UnitTestCase {

	/** @var array<string,int> Route base => post ID. */
	private $posts = array();

	public function set_up(): void {
		parent::set_up();
		$this->posts = array(
			'mediashield-playlists' => self::factory()->post->create( array( 'post_type' => 'mediashield_playlist', 'post_status' => 'publish', 'post_title' => 'Week 2' ) ),
			'mediashield-videos'    => self::factory()->post->create( array( 'post_type' => 'mediashield_video', 'post_status' => 'publish', 'post_title' => 'Studio lighting basics' ) ),
		);
	}

	private function get( string $route ): \WP_REST_Response {
		return rest_do_request( new WP_REST_Request( 'GET', $route ) );
	}

	/**
	 * Fails on 4393798: a logged-out visitor got 200 with titles, slugs and meta.
	 */
	public function test_visitor_and_member_are_refused(): void {
		foreach ( array( 0 => 401, self::factory()->user->create( array( 'role' => 'subscriber' ) ) => 403 ) as $user => $status ) {
			wp_set_current_user( $user );
			foreach ( $this->posts as $base => $id ) {
				$this->assertSame( $status, $this->get( '/wp/v2/' . $base )->get_status(), "list $base as user $user" );
				$this->assertSame( $status, $this->get( '/wp/v2/' . $base . '/' . $id )->get_status(), "single $base as user $user" );
			}
		}
	}

	/**
	 * The blocks and admin screens keep working for editors and contributors.
	 */
	public function test_editors_and_contributors_read_them(): void {
		foreach ( array( 'editor', 'contributor' ) as $role ) {
			wp_set_current_user( self::factory()->user->create( array( 'role' => $role ) ) );
			foreach ( $this->posts as $base => $id ) {
				$list = $this->get( '/wp/v2/' . $base );
				$this->assertSame( 200, $list->get_status(), "$base as $role" );
				$this->assertContains( $id, wp_list_pluck( $list->get_data(), 'id' ) );
				$this->assertSame( 200, $this->get( '/wp/v2/' . $base . '/' . $id )->get_status() );
			}
		}
	}
}
