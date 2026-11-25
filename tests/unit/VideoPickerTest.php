<?php
/**
 * QA finding (Basecamp 10545260523, 1.4.4): the "Choose a Video" picker
 * showed "youtube · #81".
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use WP_REST_Request;
use WP_UnitTestCase;

/**
 * The picker reads /wp/v2/mediashield-videos with the ms_card field.
 */
class VideoPickerTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
	}

	private function picker( array $params ): \WP_REST_Response {
		$request = new WP_REST_Request( 'GET', '/wp/v2/mediashield-videos' );
		$request->set_query_params( $params + array( 'status' => 'publish', '_fields' => 'id,title,ms_card' ) );
		return rest_do_request( $request );
	}

	/**
	 * Fails on cc950e8: no ms_card, so the row could only show the slug and id.
	 */
	public function test_row_says_platform_length_and_protection_in_owners_words(): void {
		$video = self::factory()->post->create( array( 'post_type' => 'mediashield_video', 'post_status' => 'publish', 'post_title' => 'Studio lighting basics' ) );
		update_post_meta( $video, '_ms_platform', 'youtube' );
		update_post_meta( $video, '_ms_platform_video_id', 'jNQXAC9IVRw' );
		update_post_meta( $video, '_ms_duration', 724 );
		$strict = self::factory()->post->create( array( 'post_type' => 'mediashield_video', 'post_status' => 'publish', 'post_title' => 'Beach walk' ) );
		update_post_meta( $strict, '_ms_platform', 'self' );
		update_post_meta( $strict, '_ms_protection_level', 'strict' );

		$cards = wp_list_pluck( $this->picker( array() )->get_data(), 'ms_card', 'id' );

		$this->assertSame( 'YouTube', $cards[ $video ]['platform_label'] );
		$this->assertSame( 724, $cards[ $video ]['duration'] );
		$this->assertSame( 'Site default (Standard)', $cards[ $video ]['protection_label'] );
		$this->assertSame( 'Video file', $cards[ $strict ]['platform_label'] );
		$this->assertSame( 'Strict', $cards[ $strict ]['protection_label'] );
	}

	/**
	 * At 2,000+ videos the picker searches on the server and pages 20 at a time.
	 */
	public function test_search_and_pages_at_two_thousand_videos(): void {
		global $wpdb;
		$now = current_time( 'mysql', true );
		$rows = array();
		for ( $i = 1; $i <= 2050; $i++ ) {
			$rows[] = $wpdb->prepare( '(%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)', 1, $now, $now, '', 'Lesson ' . $i, '', 'publish', 'lesson-' . $i, 'mediashield_video', '' );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared -- Test fixture: 2,050 rows in one insert, each row prepared above.
		$wpdb->query( "INSERT INTO {$wpdb->posts} (post_author, post_date, post_date_gmt, post_content, post_title, post_excerpt, post_status, post_name, post_type, to_ping) VALUES " . implode( ',', $rows ) );
		$needle = self::factory()->post->create( array( 'post_type' => 'mediashield_video', 'post_status' => 'publish', 'post_title' => 'Needle in the haystack' ) );

		$first = $this->picker( array( 'per_page' => 20 ) );
		$this->assertCount( 20, $first->get_data() );
		$this->assertSame( 2051, (int) $first->get_headers()['X-WP-Total'] );
		$this->assertSame( 103, (int) $first->get_headers()['X-WP-TotalPages'] );

		$found = $this->picker( array( 'per_page' => 20, 'search' => 'Needle', 'orderby' => 'relevance' ) );
		$this->assertSame( array( $needle ), wp_list_pluck( $found->get_data(), 'id' ) );
		$this->assertSame( 1, (int) $found->get_headers()['X-WP-Total'] );
	}
}
