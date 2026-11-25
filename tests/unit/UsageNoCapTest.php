<?php
/**
 * "Used in" finds a video on an old page, however many newer pages embed
 * other videos (Basecamp 10545260458).
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\CPT\VideoUsage;
use WP_UnitTestCase;

/**
 * The scan asks for the listed ids only, with no cap on posts.
 */
class UsageNoCapTest extends WP_UnitTestCase {

	public function tear_down(): void {
		VideoUsage::flush();
		parent::tear_down();
	}

	/**
	 * A post saved $days ago.
	 */
	private function post( string $content, int $days ): int {
		$date = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		$id   = self::factory()->post->create(
			array(
				'post_type'     => 'page',
				'post_status'   => 'publish',
				'post_content'  => $content,
				'post_date'     => $date,
				'post_date_gmt' => $date,
			)
		);
		global $wpdb;
		$wpdb->update( $wpdb->posts, array( 'post_modified' => $date, 'post_modified_gmt' => $date ), array( 'ID' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		clean_post_cache( $id );
		return $id;
	}

	/**
	 * Fails on HEAD: the scan read the 500 newest embedding posts only, so a
	 * video placed on an older page read "Not used yet" and My Videos and
	 * the old playlist address found no page.
	 */
	public function test_old_page_is_found_behind_600_newer_embeds(): void {
		global $wpdb;
		$video    = self::factory()->post->create( array( 'post_type' => 'mediashield_video', 'post_status' => 'publish' ) );
		$in_list  = self::factory()->post->create( array( 'post_type' => 'mediashield_video', 'post_status' => 'publish' ) );
		$other    = self::factory()->post->create( array( 'post_type' => 'mediashield_video', 'post_status' => 'publish' ) );
		$playlist = self::factory()->post->create( array( 'post_type' => 'mediashield_playlist', 'post_status' => 'publish' ) );
		$wpdb->insert( $wpdb->prefix . 'ms_playlist_items', array( 'playlist_id' => $playlist, 'video_id' => $in_list, 'sort_order' => 0 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		$old_code  = $this->post( '[mediashield id="' . $video . '"]', 900 );
		$old_block = $this->post( '<!-- wp:mediashield/playlist {"playlistId":' . $playlist . '} /-->', 900 );
		// A longer id and another plugin's id="N" are not this video.
		$this->post( '[mediashield id="' . $video . '0"] [gallery id="' . $video . '"]', 1 );

		$values = array();
		for ( $i = 0; $i < 600; $i++ ) {
			$values[] = $wpdb->prepare( '(%d, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)', 1, '[mediashield id="' . $other . '"]', 'Newer ' . $i, '', '', '', '', 'publish', 'page', gmdate( 'Y-m-d H:i:s' ), gmdate( 'Y-m-d H:i:s' ) );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- Rows prepared above.
		$wpdb->query( "INSERT INTO {$wpdb->posts} ( post_author, post_content, post_title, post_excerpt, to_ping, pinged, post_content_filtered, post_status, post_type, post_modified, post_modified_gmt ) VALUES " . implode( ', ', $values ) );

		VideoUsage::prime( array( $video, $in_list, $other ) );

		$this->assertSame( array( $old_code ), wp_list_pluck( VideoUsage::for_video( $video ), 'id' ) );
		$this->assertSame( array( $playlist ), wp_list_pluck( VideoUsage::for_video( $in_list ), 'id' ) );
		$this->assertCount( VideoUsage::MAX_PER_VIDEO, VideoUsage::for_video( $other ) );

		$member = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->assertSame( get_permalink( $old_block ), VideoUsage::page_for_viewer( $in_list, $member ) );
		$this->assertSame( get_permalink( $old_block ), VideoUsage::page_for_playlist( $playlist ) );
	}
}
