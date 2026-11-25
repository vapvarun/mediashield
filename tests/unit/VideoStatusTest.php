<?php
/**
 * Each video's real status, stored and counted with COUNT(*).
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\CPT\SourceStatus;
use MediaShield\CPT\VideoStatus;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Basecamp 10545260458: every video looked Ready.
 */
class VideoStatusTest extends WP_UnitTestCase {

	/**
	 * Admin; REST meta registered.
	 */
	public function set_up(): void {
		parent::set_up();
		\MediaShield\CPT\VideoPostType::register_meta();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * A video with the given source and post status.
	 *
	 * @param string $source Source URL ('' for none).
	 * @param string $status Post status.
	 * @return int
	 */
	private function video( string $source, string $status = 'publish' ): int {
		$id = self::factory()->post->create( array( 'post_type' => 'mediashield_video', 'post_status' => $status ) );
		if ( '' !== $source ) {
			update_post_meta( $id, '_ms_source_url', $source );
		}
		VideoStatus::on_save( $id );
		return $id;
	}

	/**
	 * Ready, No source, Unavailable and Draft are stored and counted.
	 */
	public function test_counts_each_status(): void {
		$ready       = $this->video( 'https://vimeo.com/76979871' );
		$none        = $this->video( '' );
		$junk        = $this->video( 'https://example.com/old-embed' );
		$draft       = $this->video( 'https://youtu.be/jNQXAC9IVRw', 'draft' );
		$unavailable = $this->video( 'https://www.youtube.com/watch?v=dQw4w9WgXcA' );
		SourceStatus::mark_unavailable( $unavailable, 'not_embeddable' );

		$counts = VideoStatus::counts();
		$this->assertSame( 5, $counts['all'] );
		$this->assertSame( 1, $counts['ready'] );
		$this->assertSame( 3, $counts['attention'] );
		$this->assertSame( 1, $counts['draft'] );

		$this->assertSame( 'ready', VideoStatus::of( $ready ) );
		$this->assertSame( 'no_source', VideoStatus::of( $none ) );
		$this->assertSame( 'no_source', VideoStatus::of( $junk ), 'A stored link that cannot play is No source.' );
		$this->assertSame( 'draft', VideoStatus::of( $draft ) );
		$this->assertSame( 'unavailable', VideoStatus::of( $unavailable ) );
	}

	/**
	 * Adding a link (by any writer) clears No source; removing it sets it again.
	 */
	public function test_status_follows_the_source(): void {
		$id = $this->video( '' );
		$this->assertSame( 'no_source', VideoStatus::of( $id ) );

		update_post_meta( $id, '_ms_source_url', 'https://vimeo.com/76979871' );
		$this->assertSame( 'ready', VideoStatus::of( $id ) );

		delete_post_meta( $id, '_ms_source_url' );
		$this->assertSame( 'no_source', VideoStatus::of( $id ) );
	}

	/**
	 * The chip counts are one query, whatever the library size.
	 */
	public function test_counts_run_one_query(): void {
		for ( $i = 0; $i < 25; $i++ ) {
			$this->video( 0 === $i % 3 ? '' : 'https://vimeo.com/7697987' . $i );
		}
		global $wpdb;
		$before = $wpdb->num_queries;
		$counts = VideoStatus::counts();
		$this->assertSame( 1, $wpdb->num_queries - $before );
		$this->assertSame( 25, $counts['all'] );
		$this->assertSame( 9, $counts['attention'] );
	}

	/**
	 * The Videos list filter and field show the status; the counts route answers editors only.
	 */
	public function test_list_filter_field_and_counts_route(): void {
		$ready = $this->video( 'https://vimeo.com/76979871' );
		$none  = $this->video( '' );

		$request = new WP_REST_Request( 'GET', '/wp/v2/mediashield-videos' );
		$request->set_param( 'ms_state', 'attention' );
		$request->set_param( 'context', 'edit' );
		$data = rest_do_request( $request )->get_data();
		$this->assertSame( array( $none ), wp_list_pluck( $data, 'id' ) );
		$this->assertSame( 'no_source', $data[0]['ms_list']['status'] );

		$request->set_param( 'ms_state', 'ready' );
		$this->assertSame( array( $ready ), wp_list_pluck( rest_do_request( $request )->get_data(), 'id' ) );

		$this->assertSame( 1, rest_do_request( new WP_REST_Request( 'GET', '/mediashield/v1/videos/counts' ) )->get_data()['attention'] );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->assertSame( 403, rest_do_request( new WP_REST_Request( 'GET', '/mediashield/v1/videos/counts' ) )->get_status() );
	}

	/**
	 * The Dashboard counts only Ready videos as protected.
	 */
	public function test_dashboard_counts_ready_videos_only(): void {
		$this->video( 'https://vimeo.com/76979871' );
		$this->video( '' );
		$gone = $this->video( 'https://www.youtube.com/watch?v=dQw4w9WgXcA' );
		SourceStatus::mark_unavailable( $gone, 'removed' );

		$data = rest_do_request( new WP_REST_Request( 'GET', '/mediashield/v1/analytics/overview' ) )->get_data();
		$this->assertSame( 1, $data['protection']['total'] );
		$this->assertSame( 1, $data['protection']['protected'] );
		$this->assertSame( 2, $data['protection']['attention'] );
		$this->assertSame( 1, $data['total_videos'] );
	}

	/**
	 * The upgrade pass marks existing videos with nothing to play.
	 */
	public function test_backfill_marks_existing_videos(): void {
		$none = self::factory()->post->create( array( 'post_type' => 'mediashield_video', 'post_status' => 'publish' ) );
		$junk = self::factory()->post->create( array( 'post_type' => 'mediashield_video', 'post_status' => 'publish' ) );
		$good = self::factory()->post->create( array( 'post_type' => 'mediashield_video', 'post_status' => 'publish' ) );
		update_post_meta( $junk, '_ms_source_url', 'https://example.com/page' );
		update_post_meta( $good, '_ms_source_url', 'https://vimeo.com/76979871' );
		delete_post_meta( $none, VideoStatus::NO_SOURCE );
		delete_post_meta( $junk, VideoStatus::NO_SOURCE );

		VideoStatus::backfill();

		$this->assertSame( '1', get_post_meta( $none, VideoStatus::NO_SOURCE, true ) );
		$this->assertSame( '1', get_post_meta( $junk, VideoStatus::NO_SOURCE, true ) );
		$this->assertSame( '', get_post_meta( $good, VideoStatus::NO_SOURCE, true ) );
	}
}
