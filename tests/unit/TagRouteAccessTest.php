<?php
/**
 * Gate G2 finding (1.4.4): the tag routes asked only "can you write posts?".
 *
 * A contributor could tag and untag the admin's videos, rename and delete
 * site-wide tags, and a subscriber could read the tags of a private video.
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\Tags\TagManager;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Video tags follow the video's edit_post; the tag list itself needs manage_categories.
 */
class TagRouteAccessTest extends WP_UnitTestCase {

	/**
	 * The administrator who owns both videos.
	 *
	 * @var int
	 */
	private $admin;

	/**
	 * The admin's private video, carrying the tag.
	 *
	 * @var int
	 */
	private $private_video;

	/**
	 * The admin's published video, untagged.
	 *
	 * @var int
	 */
	private $public_video;

	/**
	 * The admin's tag.
	 *
	 * @var int
	 */
	private $tag;

	/**
	 * Two admin videos and one tag on the private one.
	 */
	public function set_up(): void {
		parent::set_up();
		$this->admin         = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->private_video = $this->video( 'private', $this->admin );
		$this->public_video  = $this->video( 'publish', $this->admin );
		$this->tag           = (int) TagManager::create( 'Admin Tag ' . wp_generate_password( 6, false, false ), '', $this->admin );
		$this->assertGreaterThan( 0, $this->tag, 'Fixture tag was not created, so nothing below would prove anything.' );
		TagManager::assign_to_video( $this->private_video, $this->tag, $this->admin );
	}

	/**
	 * A video.
	 *
	 * @param string $status Post status.
	 * @param int    $author Author.
	 */
	private function video( string $status, int $author ): int {
		return self::factory()->post->create(
			array(
				'post_type'   => 'mediashield_video',
				'post_status' => $status,
				'post_author' => $author,
			)
		);
	}

	/**
	 * Dispatch one request.
	 *
	 * @param string               $method Method.
	 * @param string               $route  Route.
	 * @param array<string, mixed> $params Params.
	 */
	private function call( string $method, string $route, array $params = array() ): \WP_REST_Response {
		$request = new WP_REST_Request( $method, '/mediashield/v1' . $route );
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}
		return rest_do_request( $request );
	}

	/**
	 * Tag ids currently on a video.
	 *
	 * @param int $video_id Video.
	 * @return int[]
	 */
	private function tag_ids( int $video_id ): array {
		return array_map( 'intval', wp_list_pluck( TagManager::get_for_video( $video_id ), 'id' ) );
	}

	/**
	 * Fails on d63b944: 201 and 204, the admin's videos re-tagged by a contributor.
	 */
	public function test_contributor_cannot_change_the_admins_video_tags(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'contributor' ) ) );

		$this->assertSame( 404, $this->call( 'POST', '/videos/' . $this->public_video . '/tags', array( 'tag_id' => $this->tag ) )->get_status() );
		$this->assertSame( array(), $this->tag_ids( $this->public_video ) );

		$this->assertSame( 404, $this->call( 'DELETE', '/videos/' . $this->private_video . '/tags/' . $this->tag )->get_status() );
		$this->assertSame( array( $this->tag ), $this->tag_ids( $this->private_video ) );
	}

	/**
	 * Fails on d63b944: 200, a contributor renamed a site-wide tag.
	 */
	public function test_contributor_cannot_rename_or_delete_a_tag(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'contributor' ) ) );
		$name = TagManager::get( $this->tag )->name;

		$this->assertSame( 403, $this->call( 'PATCH', '/tags/' . $this->tag, array( 'name' => 'Renamed by contributor' ) )->get_status() );
		$this->assertSame( 403, $this->call( 'DELETE', '/tags/' . $this->tag )->get_status() );
		$this->assertSame( 403, $this->call( 'POST', '/tags', array( 'name' => 'Contributor tag' ) )->get_status() );
		$this->assertSame( $name, TagManager::get( $this->tag )->name );
	}

	/**
	 * Fails on d63b944: 200 with the private video's tag names.
	 */
	public function test_subscriber_cannot_read_a_private_videos_tags(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$response = $this->call( 'GET', '/videos/' . $this->private_video . '/tags' );
		$this->assertSame( 404, $response->get_status() );
		$this->assertStringNotContainsString( 'Admin Tag', (string) wp_json_encode( $response->get_data() ) );
		$this->assertSame( 403, $this->call( 'GET', '/tags' )->get_status() );
	}

	/**
	 * Fails on d63b944: any post id answered 200.
	 */
	public function test_a_post_that_is_not_a_video_is_not_found(): void {
		wp_set_current_user( $this->admin );
		$page = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$this->assertSame( 404, $this->call( 'GET', '/videos/' . $page . '/tags' )->get_status() );
		$this->assertSame( 404, $this->call( 'POST', '/videos/' . $page . '/tags', array( 'tag_id' => $this->tag ) )->get_status() );
	}

	/**
	 * The people who should keep access still have it.
	 */
	public function test_owner_and_editor_keep_their_access(): void {
		$author = self::factory()->user->create( array( 'role' => 'author' ) );
		$own    = $this->video( 'publish', $author );

		wp_set_current_user( $author );
		$this->assertSame( 201, $this->call( 'POST', '/videos/' . $own . '/tags', array( 'tag_id' => $this->tag ) )->get_status() );
		$this->assertSame( 200, $this->call( 'GET', '/videos/' . $own . '/tags' )->get_status() );
		$this->assertSame( 200, $this->call( 'GET', '/tags' )->get_status() );
		$this->assertSame( 204, $this->call( 'DELETE', '/videos/' . $own . '/tags/' . $this->tag )->get_status() );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertSame( 200, $this->call( 'PATCH', '/tags/' . $this->tag, array( 'name' => 'Renamed by editor' ) )->get_status() );
		$this->assertSame( 200, $this->call( 'GET', '/videos/' . $this->private_video . '/tags' )->get_status() );
		$this->assertSame( 204, $this->call( 'DELETE', '/tags/' . $this->tag )->get_status() );
	}
}
