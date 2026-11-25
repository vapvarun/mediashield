<?php
/**
 * Regression tests for Basecamp 10545260546 (1.4.4).
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use WP_REST_Request;
use WP_UnitTestCase;

/**
 * A playlist has no page of its own, and its order saves from the admin screens.
 */
class PlaylistPageAndOrderTest extends WP_UnitTestCase {

	/** @var int */
	private $owner;

	/** @var int */
	private $playlist;

	/** @var int[] Item row IDs, in the order they were added. */
	private $items = array();

	public function set_up(): void {
		parent::set_up();
		global $wpdb;

		$this->owner    = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->playlist = self::factory()->post->create(
			array(
				'post_type'   => 'mediashield_playlist',
				'post_status' => 'publish',
				'post_title'  => 'Week 2',
				'post_name'   => 'week-2',
				'post_author' => $this->owner,
			)
		);

		foreach ( array( 1, 2, 3 ) as $order ) {
			$video = self::factory()->post->create( array( 'post_type' => 'mediashield_video', 'post_status' => 'publish' ) );
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->prefix . 'ms_playlist_items',
				array(
					'playlist_id' => $this->playlist,
					'video_id'    => $video,
					'sort_order'  => $order,
				)
			);
			$this->items[] = (int) $wpdb->insert_id;
		}
	}

	/**
	 * The order the playlist plays in, as item IDs.
	 *
	 * @return int[]
	 */
	private function saved_order(): array {
		global $wpdb;
		return array_map(
			'intval',
			$wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare( "SELECT id FROM {$wpdb->prefix}ms_playlist_items WHERE playlist_id = %d ORDER BY sort_order ASC, id ASC", $this->playlist )
			)
		);
	}

	private function reorder( string $method, array $ids ): \WP_REST_Response {
		$request = new WP_REST_Request( $method, '/mediashield/v1/playlists/' . $this->playlist . '/items/reorder' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			wp_json_encode(
				array(
					'order' => array_map(
						static fn( $id, $i ) => array(
							'item_id'    => $id,
							'sort_order' => $i,
						),
						$ids,
						array_keys( $ids )
					),
				)
			)
		);
		return rest_do_request( $request );
	}

	/**
	 * Fails on 08a5469: the route took only PUT, which a browser reaches as
	 * POST plus X-HTTP-Method-Override. Without that header the request found
	 * no route (404) and the new order was lost.
	 */
	public function test_new_order_saves_from_a_plain_post(): void {
		wp_set_current_user( $this->owner );
		$wanted = array( $this->items[2], $this->items[0], $this->items[1] );

		$response = $this->reorder( 'POST', $wanted );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( $wanted, $this->saved_order() );
	}

	/**
	 * PUT keeps working for any integration that already sends it.
	 */
	public function test_put_still_saves(): void {
		wp_set_current_user( $this->owner );
		$wanted = array( $this->items[1], $this->items[2], $this->items[0] );

		$this->assertSame( 200, $this->reorder( 'PUT', $wanted )->get_status() );
		$this->assertSame( $wanted, $this->saved_order() );
	}

	/**
	 * Fails on 08a5469: any user with edit_posts, a contributor included,
	 * could reorder anyone's playlist.
	 */
	public function test_contributor_cannot_reorder_someone_elses_playlist(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'contributor' ) ) );
		$before = $this->saved_order();

		$response = $this->reorder( 'PUT', array_reverse( $before ) );

		$this->assertSame( 403, $response->get_status() );
		$this->assertSame( $before, $this->saved_order() );
	}

	/**
	 * Fails on 08a5469: the post type was public, so every playlist had a
	 * /playlist/<slug>/ page owners never asked for.
	 */
	public function test_playlist_has_no_page_of_its_own(): void {
		global $wp_rewrite;

		$this->assertFalse( is_post_type_viewable( 'mediashield_playlist' ), 'Not viewable.' );

		$wp_rewrite->set_permalink_structure( '/%postname%/' );
		\MediaShield\CPT\PlaylistPostType::register_post_type();
		flush_rewrite_rules();

		$this->go_to( home_url( '/playlist/week-2/' ) );
		$this->assertTrue( is_404(), 'The old address answers 404.' );

		$this->go_to( add_query_arg( 'mediashield_playlist', 'week-2', home_url( '/' ) ) );
		$this->assertFalse( is_singular( 'mediashield_playlist' ), 'The query string does not reach it either.' );

		$wp_rewrite->set_permalink_structure( '' );
		flush_rewrite_rules();
	}

	/**
	 * Fails on b6b3243: an old /playlist/<slug>/ link answered 404 even when
	 * a page shows the playlist. It now leads to that page (301), skipping a
	 * draft and a password-protected page; plain permalinks too.
	 */
	public function test_old_address_leads_to_the_page_that_shows_the_playlist(): void {
		global $wp_rewrite;
		$wp_rewrite->set_permalink_structure( '/%postname%/' );
		flush_rewrite_rules();

		self::factory()->post->create( array( 'post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Draft holder', 'post_content' => '[mediashield_playlist id=' . $this->playlist . ']' ) );
		self::factory()->post->create( array( 'post_type' => 'page', 'post_title' => 'Locked holder', 'post_password' => 'x', 'post_content' => '[mediashield_playlist id=' . $this->playlist . ']' ) );
		$page = self::factory()->post->create( array( 'post_type' => 'page', 'post_title' => 'Week 2 lessons', 'post_content' => '<!-- wp:mediashield/playlist {"playlistId":' . $this->playlist . '} /-->' ) );

		$this->go_to( home_url( '/playlist/week-2/' ) );
		$this->assertSame( get_permalink( $page ), \MediaShield\CPT\PlaylistPostType::old_address_target() );

		$_GET['mediashield_playlist'] = 'week-2';
		$this->go_to( home_url( '/?mediashield_playlist=week-2' ) );
		$this->assertSame( get_permalink( $page ), \MediaShield\CPT\PlaylistPostType::old_address_target(), 'Plain permalinks.' );
		unset( $_GET['mediashield_playlist'] );

		$wp_rewrite->set_permalink_structure( '' );
		flush_rewrite_rules();
	}

	/**
	 * No page shows the playlist (or it is not published): 404. Any other
	 * address is left alone.
	 */
	public function test_old_address_with_no_page_is_a_404_and_others_are_untouched(): void {
		global $wp_rewrite;
		$wp_rewrite->set_permalink_structure( '/%postname%/' );
		flush_rewrite_rules();

		$this->go_to( home_url( '/playlist/week-2/' ) );
		$this->assertSame( '', \MediaShield\CPT\PlaylistPostType::old_address_target() );

		$this->go_to( home_url( '/playlist/no-such-playlist/' ) );
		$this->assertSame( '', \MediaShield\CPT\PlaylistPostType::old_address_target() );

		$this->go_to( home_url( '/some-other-missing-page/' ) );
		$this->assertNull( \MediaShield\CPT\PlaylistPostType::old_address_target() );

		$wp_rewrite->set_permalink_structure( '' );
		flush_rewrite_rules();
	}

	/**
	 * Fails on b6b3243: playlists opened in the block editor, with the items
	 * in a collapsed drawer, while videos open on the classic screen.
	 */
	public function test_playlist_edits_on_the_classic_screen_with_its_videos_first(): void {
		$this->assertFalse( use_block_editor_for_post_type( 'mediashield_playlist' ) );
		$this->assertTrue( post_type_supports( 'mediashield_playlist', 'editor' ), 'The description field is kept.' );

		set_current_screen( 'mediashield_playlist' );
		\MediaShield\CPT\PlaylistPostType::register_items_meta_box();
		global $wp_meta_boxes;
		$this->assertArrayHasKey( 'mediashield-playlist-items', $wp_meta_boxes['mediashield_playlist'][ \MediaShield\CPT\PlaylistPostType::MAIN_CONTEXT ]['high'] );

		ob_start();
		\MediaShield\CPT\PlaylistPostType::render_main_boxes( get_post( $this->playlist ) );
		$this->assertStringContainsString( 'mediashield-playlist-items-root', (string) ob_get_clean() );
	}

	/**
	 * Placed with the shortcode, the playlist still renders its queue.
	 */
	public function test_shortcode_still_renders_the_playlist(): void {
		$html = do_shortcode( '[mediashield_playlist id=' . $this->playlist . ']' );

		$this->assertStringContainsString( 'data-playlist-id="' . $this->playlist . '"', $html );
	}
}
