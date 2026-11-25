<?php
/**
 * Playlist item lengths (Bunny end-to-end test, D6).
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\Player\PlaylistRenderer;
use WP_UnitTestCase;

/**
 * A clip under a minute reads 0:22, not :22.
 */
class PlaylistDurationLabelTest extends WP_UnitTestCase {

	/**
	 * Fails on HEAD: the item list printed ":22" for a 22-second video.
	 */
	public function test_short_clip_in_a_rendered_playlist(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$video    = self::factory()->post->create(
			array(
				'post_type'   => 'mediashield_video',
				'post_status' => 'publish',
				'meta_input'  => array(
					'_ms_platform'   => 'self',
					'_ms_source_url' => 'https://example.org/clip.mp4',
					'_ms_duration'   => 22,
				),
			)
		);
		$playlist = self::factory()->post->create(
			array(
				'post_type'   => 'mediashield_playlist',
				'post_status' => 'publish',
			)
		);
		global $wpdb;
		$wpdb->insert( $wpdb->prefix . 'ms_playlist_items', array( 'playlist_id' => $playlist, 'video_id' => $video, 'sort_order' => 0 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery

		$html = PlaylistRenderer::render( $playlist );

		$this->assertStringContainsString( '<span class="ms-playlist-item-duration">0:22</span>', $html );
	}

	/**
	 * Every range: seconds, minutes, hours, and past a day.
	 */
	public function test_labels(): void {
		$this->assertSame( '0:05', PlaylistRenderer::duration_label( 5 ) );
		$this->assertSame( '0:22', PlaylistRenderer::duration_label( 22 ) );
		$this->assertSame( '1:00', PlaylistRenderer::duration_label( 60 ) );
		$this->assertSame( '4:05', PlaylistRenderer::duration_label( 245 ) );
		$this->assertSame( '10:00', PlaylistRenderer::duration_label( 600 ) );
		$this->assertSame( '59:59', PlaylistRenderer::duration_label( 3599 ) );
		$this->assertSame( '1:00:00', PlaylistRenderer::duration_label( 3600 ) );
		$this->assertSame( '1:02:03', PlaylistRenderer::duration_label( 3723 ) );
		$this->assertSame( '25:00:00', PlaylistRenderer::duration_label( 90000 ) );
	}
}
