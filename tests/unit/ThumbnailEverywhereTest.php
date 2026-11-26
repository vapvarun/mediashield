<?php
/**
 * Thumbnails for platform videos (MSUX-11).
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\CPT\Thumbnail;
use WP_UnitTestCase;

/**
 * Fails before: a YouTube video showed no thumbnail anywhere until the
 * background job had sideloaded one, and the job tried only maxresdefault,
 * which older videos (Me at the zoo) do not have.
 */
class ThumbnailEverywhereTest extends WP_UnitTestCase {

	/**
	 * Image URLs the job asked for.
	 *
	 * @var string[]
	 */
	private $asked = array();

	private function youtube(): int {
		return self::factory()->post->create(
			array(
				'post_type'   => 'mediashield_video',
				'post_status' => 'publish',
				'meta_input'  => array(
					'_ms_platform'          => 'youtube',
					'_ms_platform_video_id' => 'jNQXAC9IVRw',
					'_ms_source_url'        => 'https://www.youtube.com/watch?v=jNQXAC9IVRw',
				),
			)
		);
	}

	public function test_youtube_without_a_poster_shows_youtubes_image_to_a_viewer(): void {
		$id = $this->youtube();
		$this->assertSame( 'https://i.ytimg.com/vi/jNQXAC9IVRw/hqdefault.jpg', Thumbnail::poster_url( $id, 'thumbnail', true ) );
		$this->assertSame( '', Thumbnail::poster_url( $id, 'thumbnail', false ), 'Never the video ID to someone who may not watch.' );
	}

	public function test_manage_items_uses_the_same_poster(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		global $wpdb;
		$video    = $this->youtube();
		$playlist = self::factory()->post->create( array( 'post_type' => 'mediashield_playlist', 'post_status' => 'publish' ) );
		$wpdb->insert( $wpdb->prefix . 'ms_playlist_items', array( 'playlist_id' => $playlist, 'video_id' => $video, 'sort_order' => 0 ) ); // phpcs:ignore WordPress.DB
		$items = rest_do_request( new \WP_REST_Request( 'GET', '/mediashield/v1/playlists/' . $playlist . '/items' ) )->get_data();
		$items = $items['items'] ?? $items;
		$this->assertSame( 'https://i.ytimg.com/vi/jNQXAC9IVRw/hqdefault.jpg', $items[0]['thumbnail'] ?? null );
	}

	public function test_job_falls_back_to_hqdefault(): void {
		$id = $this->youtube();
		add_filter(
			'pre_http_request',
			function ( $pre, $args, $url ) {
				$this->asked[] = (string) $url;
				if ( false !== strpos( (string) $url, 'youtube.com/oembed' ) ) {
					return array( 'headers' => array(), 'body' => '{}', 'response' => array( 'code' => 200, 'message' => '' ), 'cookies' => array() );
				}
				return array( 'headers' => array(), 'body' => '', 'response' => array( 'code' => 404, 'message' => '' ), 'cookies' => array() );
			},
			10,
			3
		);
		Thumbnail::fetch_poster( $id );
		$this->assertNotEmpty( array_filter( $this->asked, fn( $u ) => false !== strpos( $u, '/hqdefault.jpg' ) ), 'After maxresdefault fails, hqdefault is tried.' );
	}
}
