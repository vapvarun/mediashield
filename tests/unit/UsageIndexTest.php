<?php
/**
 * Where each video is placed is stored when a post is saved, so My Videos,
 * "Used in" and old playlist addresses never scan post content.
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\CPT\VideoUsage;
use WP_UnitTestCase;

/**
 * Coordinator on the ponytail #2 follow-up: a scan on every view on sites
 * without a persistent object cache is not acceptable. Fails before: no
 * index existed, every read scanned.
 */
class UsageIndexTest extends WP_UnitTestCase {

	/**
	 * Content scans seen by the query filter.
	 *
	 * @var int
	 */
	private $scans = 0;

	public function set_up(): void {
		parent::set_up();
		VideoUsage::register();
		delete_option( VideoUsage::INDEX_PENDING ); // The index is built.
		VideoUsage::flush();
		add_filter(
			'query',
			function ( $sql ) {
				if ( false !== stripos( (string) $sql, 'post_content LIKE' ) ) {
					++$this->scans;
				}
				return $sql;
			}
		);
	}

	public function tear_down(): void {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->prefix}ms_watch_sessions" ); // phpcs:ignore WordPress.DB
		VideoUsage::flush();
		parent::tear_down();
	}

	private function video(): int {
		return self::factory()->post->create( array( 'post_type' => 'mediashield_video', 'post_status' => 'publish', 'post_title' => 'Indexed clip' ) );
	}

	private function page( string $content, string $status = 'publish' ): int {
		return self::factory()->post->create( array( 'post_type' => 'page', 'post_status' => $status, 'post_content' => $content ) );
	}

	private function used_in( int $id ): array {
		return array_map( 'intval', get_post_meta( $id, VideoUsage::USED_IN ) );
	}

	public function test_save_remove_and_trash_keep_the_list_right(): void {
		$video = $this->video();
		$other = $this->video();
		$code  = $this->page( '[mediashield id="' . $video . '"]' );
		$block = $this->page( '<!-- wp:mediashield/video {"videoId":' . $video . '} /-->' );
		$this->assertEqualsCanonicalizing( array( $code, $block ), $this->used_in( $video ), 'Saving a post adds it.' );

		wp_update_post( array( 'ID' => $code, 'post_content' => '[mediashield id="' . $other . '"]' ) );
		$this->assertSame( array( $block ), $this->used_in( $video ), 'Removing the embed removes it.' );
		$this->assertSame( array( $code ), $this->used_in( $other ) );

		wp_trash_post( $block );
		$this->assertSame( array(), $this->used_in( $video ), 'Trashing removes it.' );
		wp_untrash_post( $block );
		wp_publish_post( $block );
		$this->assertSame( array( $block ), $this->used_in( $video ), 'Restoring adds it back.' );

		wp_delete_post( $code, true );
		$this->assertSame( array(), $this->used_in( $other ), 'Deleting removes it.' );
		$this->assertSame( 0, $this->scans );
	}

	public function test_playlist_pages_are_indexed(): void {
		global $wpdb;
		$video    = $this->video();
		$playlist = self::factory()->post->create( array( 'post_type' => 'mediashield_playlist', 'post_status' => 'publish' ) );
		$wpdb->insert( $wpdb->prefix . 'ms_playlist_items', array( 'playlist_id' => $playlist, 'video_id' => $video, 'sort_order' => 0 ) ); // phpcs:ignore WordPress.DB
		$page = $this->page( '<!-- wp:mediashield/playlist {"playlistId":' . $playlist . '} /-->' );
		$this->assertSame( array( $page ), $this->used_in( $playlist ) );

		$member = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->assertSame( get_permalink( $page ), VideoUsage::page_for_viewer( $video, $member ) );
		$this->assertSame( get_permalink( $page ), VideoUsage::page_for_playlist( $playlist ) );
		$this->assertSame( 0, $this->scans, 'Old playlist addresses and My Videos read the index.' );
	}

	/**
	 * The 600-posts-back case, read from the index with no scan.
	 */
	public function test_old_page_behind_600_newer_embeds_without_a_scan(): void {
		$video = $this->video();
		$other = $this->video();
		$old   = $this->page( '[mediashield id="' . $video . '"]' );
		for ( $i = 0; $i < 600; $i++ ) {
			$this->page( '[mediashield id="' . $other . '"]' );
		}
		$this->scans = 0;
		VideoUsage::prime( array( $video, $other ) );

		$this->assertSame( array( $old ), wp_list_pluck( VideoUsage::for_video( $video ), 'id' ) );
		$this->assertCount( VideoUsage::MAX_PER_VIDEO, VideoUsage::for_video( $other ) );
		$this->assertSame( 0, $this->scans );
	}

	/**
	 * My Videos with no persistent object cache: no content scan.
	 */
	public function test_my_videos_runs_no_content_scan(): void {
		global $wpdb;
		$member = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$video  = $this->video();
		$page   = $this->page( '[mediashield id="' . $video . '"]' );
		$now    = current_time( 'mysql', true );
		$wpdb->insert( $wpdb->prefix . 'ms_watch_sessions', array( 'video_id' => $video, 'user_id' => $member, 'session_token' => 'idx-' . $video, 'started_at' => $now, 'last_heartbeat' => $now, 'max_position' => 40, 'total_seconds' => 40, 'completion_pct' => 40, 'is_active' => 0 ) ); // phpcs:ignore WordPress.DB
		wp_cache_flush(); // Nothing kept between requests, as without a persistent cache.
		wp_set_current_user( $member );

		$this->scans = 0;
		$html        = do_blocks( '<!-- wp:mediashield/my-videos /-->' );

		$this->assertStringContainsString( esc_url( get_permalink( $page ) ), $html );
		$this->assertSame( 0, $this->scans );
	}

	/**
	 * Existing content is indexed by the background job, 100 posts a run;
	 * reads scan until it finishes.
	 */
	public function test_build_index_walks_existing_content(): void {
		global $wpdb;
		$video = $this->video();
		// Saved without hooks, as content from before 1.4.4.
		$wpdb->insert( $wpdb->posts, array( 'post_author' => 1, 'post_title' => 'Old', 'post_content' => '[mediashield id="' . $video . '"]', 'post_excerpt' => '', 'to_ping' => '', 'pinged' => '', 'post_content_filtered' => '', 'post_status' => 'publish', 'post_type' => 'page' ) ); // phpcs:ignore WordPress.DB
		$old = (int) $wpdb->insert_id;
		update_option( VideoUsage::INDEX_PENDING, (string) ( $old - 1 ) );

		$this->scans = 0;
		VideoUsage::prime( array( $video ) );
		$this->assertSame( array( $old ), wp_list_pluck( VideoUsage::for_video( $video ), 'id' ), 'Until the index is built, the scan answers.' );
		$this->assertSame( 1, $this->scans );

		VideoUsage::build_index();
		$this->assertFalse( get_option( VideoUsage::INDEX_PENDING ), 'Fewer than 100 found: done.' );
		$this->assertSame( array( $old ), $this->used_in( $video ) );

		VideoUsage::flush();
		$this->scans = 0;
		VideoUsage::prime( array( $video ) );
		$this->assertSame( array( $old ), wp_list_pluck( VideoUsage::for_video( $video ), 'id' ) );
		$this->assertSame( 0, $this->scans );
	}

	public function test_upgrade_queues_the_index(): void {
		delete_option( VideoUsage::INDEX_PENDING );
		update_option( 'ms_db_version', 17 );
		\MediaShield\Core\Migrator::run();
		$this->assertSame( '0', get_option( VideoUsage::INDEX_PENDING ) );
	}
}
