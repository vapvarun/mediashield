<?php
/**
 * A platform poster is fetched in the background, never inside the save
 * request (release gate P9, Basecamp 10500683933).
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\CPT\Thumbnail;
use WP_UnitTestCase;

/**
 * Fails before: saving a Vimeo video called Vimeo's oEmbed in the request.
 */
class PosterFetchQueuedTest extends WP_UnitTestCase {

	/**
	 * Outgoing HTTP requests seen.
	 *
	 * @var string[]
	 */
	private $calls = array();

	public function set_up(): void {
		parent::set_up();
		Thumbnail::register();
		// The poster only: SourceStatus::fill_duration() (Vimeo length on
		// save) is its own request, reported separately for gate P9.
		remove_action( 'save_post_mediashield_video', array( \MediaShield\CPT\SourceStatus::class, 'fill_duration' ), 25 );
		add_filter(
			'pre_http_request',
			function ( $pre, $args, $url ) {
				$this->calls[] = (string) $url;
				return new \WP_Error( 'blocked_in_test', 'No network in tests.' );
			},
			10,
			3
		);
	}

	public function test_save_queues_the_fetch_and_the_job_fetches(): void {
		$id = self::factory()->post->create(
			array(
				'post_type'   => 'mediashield_video',
				'post_status' => 'publish',
				'meta_input'  => array(
					'_ms_platform'          => 'vimeo',
					'_ms_platform_video_id' => '76979871',
				),
			)
		);

		$this->assertSame( array(), $this->calls, 'The save made no outgoing request.' );
		$queued = function_exists( 'as_has_scheduled_action' )
			? as_has_scheduled_action( Thumbnail::FETCH_JOB, array( $id ), 'mediashield' )
			: (bool) wp_next_scheduled( Thumbnail::FETCH_JOB, array( $id ) );
		$this->assertTrue( $queued, 'The fetch is queued.' );

		Thumbnail::fetch_poster( $id );
		$this->assertNotEmpty( $this->calls, 'The job asks the platform.' );
		$this->assertStringContainsString( 'vimeo.com/api/oembed', $this->calls[0] );
	}
}
