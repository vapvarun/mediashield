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

	/**
	 * Ponytail re-review #4: a copied video (Duplicate Post copies its meta)
	 * showed the original's pages. Fails before: the copy listed the page.
	 */
	public function test_copied_video_has_no_places(): void {
		$video = $this->video();
		$page  = $this->page( '[mediashield id="' . $video . '"]' );
		$copy  = $this->video();
		foreach ( get_post_meta( $video, VideoUsage::USED_IN ) as $row ) {
			add_post_meta( $copy, VideoUsage::USED_IN, $row ); // As a meta copy brings it.
		}

		VideoUsage::prime( array( $video, $copy ) );

		$this->assertSame( array( $page ), wp_list_pluck( VideoUsage::for_video( $video ), 'id' ) );
		$this->assertSame( array(), VideoUsage::for_video( $copy ), 'The copy is not on the page.' );
		$this->assertSame( '', VideoUsage::page_for_viewer( $copy, self::factory()->user->create() ) );
		$this->assertContains( VideoUsage::USED_IN, VideoUsage::skip_on_copy( array() ), 'Duplicate Post skips the index.' );
		$this->assertContains( VideoUsage::EMBEDS, VideoUsage::skip_on_copy( array() ) );
	}

	/**
	 * An import brings a page's EMBEDS without the video's row: its next
	 * save writes the row.
	 */
	public function test_save_restores_a_missing_row(): void {
		$video = $this->video();
		$page  = $this->page( '[mediashield id="' . $video . '"]' );
		delete_post_meta( $video, VideoUsage::USED_IN );

		wp_update_post( array( 'ID' => $page, 'post_title' => 'Imported page' ) );

		$this->assertSame( array( $page ), $this->used_in( $video ) );
	}

	/**
	 * Ponytail re-review #6: each video reads at most PER_TARGET rows.
	 * Fails before: one unlimited read.
	 */
	public function test_each_video_reads_a_limited_number_of_rows(): void {
		$busy  = $this->video();
		$quiet = $this->video();
		$sql   = array();
		add_filter(
			'query',
			function ( $q ) use ( &$sql ) {
				if ( false !== strpos( (string) $q, VideoUsage::USED_IN ) ) {
					$sql[] = $q;
				}
				return $q;
			}
		);
		VideoUsage::prime( array( $busy, $quiet ) );

		$this->assertCount( 1, $sql, 'One query for the page.' );
		$this->assertSame( 2, substr_count( $sql[0], 'LIMIT ' . VideoUsage::PER_TARGET ), 'A limit per video.' );
	}

	/**
	 * Final check #3: a stale row is deleted the first time a read sees it.
	 * Fails before: it was skipped but kept, filling the read window.
	 */
	public function test_stale_row_is_deleted_when_seen(): void {
		$video = $this->video();
		$this->page( '[mediashield id="' . $video . '"]' );
		$copy = $this->video();
		foreach ( get_post_meta( $video, VideoUsage::USED_IN ) as $row ) {
			add_post_meta( $copy, VideoUsage::USED_IN, $row );
		}

		VideoUsage::prime( array( $copy ) );

		$this->assertSame( array(), get_post_meta( $copy, VideoUsage::USED_IN ) );
	}

	/**
	 * Final check #1: reading "Used in" never loads all meta of the posts
	 * that show the video. Fails before: update_meta_cache() on every host.
	 */
	public function test_read_does_not_load_host_meta(): void {
		$video = $this->video();
		$page  = $this->page( '[mediashield id="' . $video . '"]' );
		wp_cache_flush();
		$loads = 0;
		add_filter(
			'query',
			function ( $q ) use ( &$loads, $page ) {
				if ( preg_match( '/post_id IN \(\s*' . $page . '\s*\)/', (string) $q ) ) {
					++$loads;
				}
				return $q;
			}
		);

		VideoUsage::prime( array( $video ) );

		$this->assertSame( array( $page ), wp_list_pluck( VideoUsage::for_video( $video ), 'id' ) );
		$this->assertSame( 0, $loads );
	}

	/**
	 * Final check #2: saving a post that shows a video does not read the
	 * video's whole list. Fails before: get_post_meta() on the target.
	 */
	public function test_save_does_not_read_the_whole_list(): void {
		$video = $this->video();
		$page  = $this->page( '[mediashield id="' . $video . '"]' );
		for ( $i = 0; $i < 30; $i++ ) {
			add_post_meta( $video, VideoUsage::USED_IN, (string) ( 900000 + $i ) );
		}
		wp_cache_flush();
		$loads = 0;
		add_filter(
			'query',
			function ( $q ) use ( &$loads, $video ) {
				if ( preg_match( '/post_id IN \(\s*' . $video . '\s*\)/', (string) $q ) ) {
					++$loads;
				}
				return $q;
			}
		);

		wp_update_post( array( 'ID' => $page, 'post_title' => 'Edited' ) );

		$this->assertSame( 0, $loads );
		$this->assertContains( $page, $this->used_in( $video ) );
	}

	public function test_upgrade_queues_the_index(): void {
		delete_option( VideoUsage::INDEX_PENDING );
		update_option( 'ms_db_version', 17 );
		\MediaShield\Core\Migrator::run();
		$this->assertSame( '0', get_option( VideoUsage::INDEX_PENDING ) );
	}

	/**
	 * MediaShield card 10551900031: a video used in more places than a list
	 * row shows lists every place on request (drafts and Learnomy lessons
	 * too), and the row says there are more. Fails before: no way to read
	 * past 5, the 6th and 7th page (and any lesson after them) were dropped.
	 */
	public function test_every_place_is_listed_beyond_the_row_preview(): void {
		$video = $this->video();
		$pages = array( $this->page( '[mediashield id="' . $video . '"]', 'draft' ) );
		for ( $i = 0; $i < 6; $i++ ) {
			$pages[] = $this->page( '<!-- wp:mediashield/video {"videoId":' . $video . '} /-->' );
		}
		$lesson = static function ( $usage ) use ( $video ) {
			$usage[ $video ][] = array( 'id' => 'learnomy_lesson:9', 'title' => 'Week 1', 'url' => '', 'type' => 'Lesson' );
			return $usage;
		};
		add_filter( 'mediashield_video_used_in', $lesson );
		$this->scans = 0;

		$this->assertCount( VideoUsage::MAX_PER_VIDEO, VideoUsage::for_video( $video ), 'A list row keeps its short preview.' );
		$this->assertTrue( VideoUsage::has_more( $video ), 'The row says there are more.' );

		$all = wp_list_pluck( VideoUsage::all_for_video( $video ), 'id' );
		remove_filter( 'mediashield_video_used_in', $lesson );
		$this->assertCount( 8, $all, 'Seven pages and the lesson.' );
		$this->assertEqualsCanonicalizing( array_merge( $pages, array( 'learnomy_lesson:9' ) ), $all );
		$this->assertSame( 0, $this->scans, 'Read from the index.' );

		$single = $this->video();
		$this->page( '[mediashield id="' . $single . '"]' );
		$this->assertFalse( VideoUsage::has_more( $single ), 'One place: nothing more to show.' );
	}

	/**
	 * Card 10551900031: Show all reads /videos/<id>/used-in, for people who
	 * may edit the video only; the list row carries used_in_more.
	 */
	public function test_show_all_route_lists_every_place_for_editors_only(): void {
		do_action( 'rest_api_init' );
		$video = $this->video();
		for ( $i = 0; $i < 7; $i++ ) {
			$this->page( '[mediashield id="' . $video . '"]', 0 === $i ? 'draft' : 'publish' );
		}
		$request = new \WP_REST_Request( 'GET', '/mediashield/v1/videos/' . $video . '/used-in' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->assertSame( 403, rest_get_server()->dispatch( $request )->get_status(), 'A member cannot list places.' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$response = rest_get_server()->dispatch( $request );
		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( 7, $response->get_data()['places'], 'Every place, the draft too.' );

		$page = new \WP_REST_Request( 'GET', '/mediashield/v1/videos/' . self::factory()->post->create() . '/used-in' );
		$this->assertSame( 403, rest_get_server()->dispatch( $page )->get_status(), 'Only a video.' );

		$list = rest_get_server()->dispatch( new \WP_REST_Request( 'GET', '/wp/v2/mediashield-videos/' . $video ) )->get_data();
		$this->assertTrue( $list['ms_list']['used_in_more'] ?? false, 'The list row says there are more.' );
		$this->assertCount( VideoUsage::MAX_PER_VIDEO, $list['ms_list']['used_in'] );
	}
}
