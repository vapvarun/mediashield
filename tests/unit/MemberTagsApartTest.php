<?php
/**
 * MSUX-18: member milestone tags are kept apart from video tags.
 *
 * @package MediaShield
 */

namespace MediaShield\Tests\Unit;

use MediaShield\Milestones\MilestoneTracker;
use MediaShield\Tags\TagManager;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Reaching a milestone tags the member, never the video; the upgrade takes
 * off the links older versions made; Videos filters by a video tag.
 */
class MemberTagsApartTest extends WP_UnitTestCase {

	/**
	 * The list filters are added on rest_api_init, which an earlier test may have used up.
	 */
	public function set_up(): void {
		parent::set_up();
		\MediaShield\REST\VideoListController::register_hooks();
	}

	/**
	 * A milestone's tag went onto the video and into the owner's tag list.
	 */
	public function test_a_milestone_tags_the_member_not_the_video(): void {
		$video  = self::factory()->post->create( array( 'post_type' => 'mediashield_video', 'post_status' => 'publish' ) );
		$member = self::factory()->user->create();
		update_post_meta( $video, '_ms_milestone_tags', array( 50 => array( 'enabled' => true, 'tag' => 'halfway-module-1' ) ) );

		MilestoneTracker::check( $video, $member, 55.0 );

		$this->assertSame( array(), TagManager::get_for_video( $video ), 'Not a video tag.' );
		$this->assertNull( TagManager::get_by_slug( 'halfway-module-1' ), 'Not in the owner\'s tag list.' );
		$this->assertSame( array( 'halfway-module-1' ), array_values( wp_list_pluck( get_user_meta( $member, '_ms_video_tags', true ), 'tag' ) ), 'On the member.' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$detail = rest_do_request( new WP_REST_Request( 'GET', "/mediashield/v1/analytics/users/{$member}" ) )->get_data();
		$this->assertSame( array( 'halfway-module-1' ), $detail['milestone_tags'], 'Shown on the member\'s page.' );
	}

	/**
	 * The update only queues the job; the job takes off links members'
	 * milestones made, and only those, across batches.
	 */
	public function test_upgrade_takes_member_tags_off_videos_and_keeps_the_owners(): void {
		global $wpdb;
		$owner  = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$first  = self::factory()->user->create();
		$later  = self::factory()->user->create();
		$video  = self::factory()->post->create( array( 'post_type' => 'mediashield_video', 'post_status' => 'publish' ) );
		$video2 = self::factory()->post->create( array( 'post_type' => 'mediashield_video', 'post_status' => 'publish' ) );
		$record = static fn( $v, $t ) => array( $v . '_50' => array( 'video_id' => $v, 'pct' => 50, 'tag' => 'finished-intro', 'tag_id' => $t, 'earned_at' => '2026-11-01 10:00:00' ) );

		// What 1.4.3 did: the first member to reach 50% created the tag.
		$earned = TagManager::ensure( 'finished-intro', $first );
		TagManager::assign_to_video( $video, $earned, $first );
		update_user_meta( $first, '_ms_video_tags', $record( $video, $earned ) );
		// A full batch of other earn records, then a later member on another video.
		$wpdb->query( "INSERT INTO {$wpdb->usermeta} (user_id, meta_key, meta_value) VALUES " . implode( ',', array_fill( 0, TagManager::MEMBER_TAGS_BATCH, "(999999, '_ms_video_tags', 'a:0:{}')" ) ) ); // phpcs:ignore WordPress.DB
		TagManager::assign_to_video( $video2, $earned, $later );
		update_user_meta( $later, '_ms_video_tags', $record( $video2, $earned ) );
		// The owner's own tag on a video, and an owner tag on no video.
		$mine = TagManager::ensure( 'beginner', $owner );
		TagManager::assign_to_video( $video, $mine, $owner );
		$spare = TagManager::ensure( 'spare', $owner );

		update_option( 'ms_db_version', 18 );
		\MediaShield\Core\Migrator::run();
		$this->assertSame( '0', get_option( TagManager::MEMBER_TAGS_PENDING ), 'The update only queues the job.' );
		$this->assertCount( 2, TagManager::get_for_video( $video ), 'Nothing removed during the update.' );

		for ( $runs = 1; false !== get_option( TagManager::MEMBER_TAGS_PENDING ) && $runs < 10; $runs++ ) {
			do_action( 'ms_separate_member_tags' );
		}
		$wpdb->delete( $wpdb->usermeta, array( 'user_id' => 999999 ) ); // phpcs:ignore WordPress.DB

		$this->assertSame( 2, $runs - 1, 'Two batches.' );
		$this->assertSame( array( 'beginner' ), wp_list_pluck( TagManager::get_for_video( $video ), 'name' ) );
		$this->assertSame( array(), TagManager::get_for_video( $video2 ) );
		$this->assertNull( TagManager::get( $earned ), 'The member-made tag, now on no video, is gone.' );
		$this->assertNotNull( TagManager::get( $spare ), 'An owner tag stays even on no video.' );
		$this->assertSame( 'finished-intro', get_user_meta( $first, '_ms_video_tags', true )[ $video . '_50' ]['tag'], 'The member keeps it.' );
	}

	/**
	 * The Videos list filters by a video tag (the Tags screen's counts link there).
	 */
	public function test_videos_list_filters_by_tag(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$tagged = self::factory()->post->create( array( 'post_type' => 'mediashield_video', 'post_status' => 'publish' ) );
		self::factory()->post->create( array( 'post_type' => 'mediashield_video', 'post_status' => 'publish' ) );
		$tag = TagManager::ensure( 'module-2' );
		TagManager::assign_to_video( $tagged, $tag );

		$request = new WP_REST_Request( 'GET', '/wp/v2/mediashield-videos' );
		$request->set_param( 'ms_tag', $tag );
		$this->assertSame( array( $tagged ), wp_list_pluck( rest_do_request( $request )->get_data(), 'id' ) );
	}
}
