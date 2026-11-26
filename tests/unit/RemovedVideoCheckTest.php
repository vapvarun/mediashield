<?php
/**
 * A removed YouTube video is found after a save, not when a member fails
 * to play it, and its edit screen says so (MSUX-10).
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\CPT\SourceStatus;
use MediaShield\CPT\Thumbnail;
use WP_UnitTestCase;

/**
 * Fails before: the poster job never asked the platform, a video with a
 * poster was not even queued, and the edit screen had no warning.
 */
class RemovedVideoCheckTest extends WP_UnitTestCase {

	/**
	 * Status YouTube's oEmbed answers with.
	 *
	 * @var int
	 */
	private $status = 404;

	public function set_up(): void {
		parent::set_up();
		Thumbnail::register();
		add_filter( 'pre_http_request', array( $this, 'fake' ), 10, 3 );
	}

	public function tear_down(): void {
		remove_filter( 'pre_http_request', array( $this, 'fake' ), 10 );
		parent::tear_down();
	}

	public function fake( $pre, $args, $url ) {
		if ( false !== strpos( (string) $url, 'youtube.com/oembed' ) ) {
			return array(
				'headers'  => array(),
				'body'     => 404 === $this->status ? 'Not Found' : '{"title":"x","thumbnail_url":""}',
				'response' => array( 'code' => $this->status, 'message' => '' ),
				'cookies'  => array(),
			);
		}
		return new \WP_Error( 'no_network', 'No network in tests.' );
	}

	private function youtube( bool $with_poster = false ): int {
		$id = self::factory()->post->create(
			array(
				'post_type'   => 'mediashield_video',
				'post_status' => 'publish',
				'meta_input'  => array(
					'_ms_platform'          => 'youtube',
					'_ms_platform_video_id' => 'dQw4w9WgXcA',
					'_ms_source_url'        => 'https://www.youtube.com/watch?v=dQw4w9WgXcA',
				),
			)
		);
		if ( $with_poster ) {
			set_post_thumbnail( $id, self::factory()->attachment->create_object( 'poster.jpg', $id, array( 'post_mime_type' => 'image/jpeg' ) ) );
		}
		return $id;
	}

	public function test_the_job_records_a_removed_video_and_clears_a_playing_one(): void {
		$id = $this->youtube();
		Thumbnail::fetch_poster( $id );
		$this->assertSame( 'removed', SourceStatus::get( $id )['code'] ?? null );

		$this->status = 200;
		Thumbnail::fetch_poster( $id );
		$this->assertNull( SourceStatus::get( $id ), 'Playing again: the record goes.' );
	}

	public function test_a_video_with_a_poster_is_still_checked(): void {
		$id = $this->youtube( true );
		// Clear what the first save queued, then save the video that has a poster.
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( Thumbnail::FETCH_JOB, array( $id ), 'mediashield' );
		}
		wp_clear_scheduled_hook( Thumbnail::FETCH_JOB, array( $id ) );
		wp_update_post( array( 'ID' => $id, 'post_title' => 'Saved again' ) );

		$queued = function_exists( 'as_has_scheduled_action' )
			? as_has_scheduled_action( Thumbnail::FETCH_JOB, array( $id ), 'mediashield' )
			: (bool) wp_next_scheduled( Thumbnail::FETCH_JOB, array( $id ) );
		$this->assertTrue( $queued );
	}

	public function test_edit_screen_warns(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$id = $this->youtube();
		SourceStatus::mark_unavailable( $id, 'removed' );
		ob_start();
		\MediaShield\CPT\VideoPostType::render_source_meta_box( get_post( $id ) );
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( 'Unavailable at YouTube', $html );
		$this->assertStringContainsString( 'no longer plays this video', $html );
	}
}
