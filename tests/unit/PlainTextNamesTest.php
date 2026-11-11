<?php
/**
 * Names and titles reach the admin screens as written (BC#10499286227).
 *
 * WordPress stores a display name "Tom & Jerry" as "Tom &amp; Jerry", and a
 * title saved without unfiltered_html the same way. The Viewers and
 * Milestones screens print the REST value as React text, so an encoded value
 * showed "Tom &amp; Jerry" to the owner.
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Analytics routes answer plain text.
 */
class PlainTextNamesTest extends WP_UnitTestCase {

	/**
	 * Viewer whose display name has an ampersand.
	 *
	 * @var int
	 */
	private int $viewer = 0;

	/**
	 * Video whose stored title is entity-encoded.
	 *
	 * @var int
	 */
	private int $video = 0;

	/**
	 * Fixtures: one viewer, one video, one session, one milestone.
	 */
	public function set_up(): void {
		parent::set_up();
		global $wpdb;
		do_action( 'rest_api_init' );

		$this->viewer = self::factory()->user->create( array( 'display_name' => 'Tom & Jerry' ) );
		$this->video  = self::factory()->post->create( array( 'post_type' => 'mediashield_video', 'post_status' => 'publish' ) );
		// The title as kses stores it for an author without unfiltered_html.
		$wpdb->update( $wpdb->posts, array( 'post_title' => 'Q&amp;A lesson' ), array( 'ID' => $this->video ) );
		clean_post_cache( $this->video );

		$now = current_time( 'mysql', true );
		$wpdb->insert(
			$wpdb->prefix . 'ms_watch_sessions',
			array(
				'video_id'       => $this->video,
				'user_id'        => $this->viewer,
				'session_token'  => 'plain-text-names',
				'started_at'     => $now,
				'last_heartbeat' => $now,
				'completion_pct' => 50,
				'is_active'      => 0,
			)
		);
		$wpdb->insert(
			$wpdb->prefix . 'ms_milestones',
			array(
				'video_id'      => $this->video,
				'user_id'       => $this->viewer,
				'milestone_pct' => 25,
				'reached_at'    => $now,
				'session_id'    => (int) $wpdb->insert_id,
			)
		);

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * GET a MediaShield route.
	 *
	 * @param string $route Route after the namespace.
	 * @return mixed Response data.
	 */
	private function get( string $route ) {
		return rest_do_request( new WP_REST_Request( 'GET', '/mediashield/v1/' . $route ) )->get_data();
	}

	/**
	 * Precondition: WordPress really stores the encoded form.
	 */
	public function test_wordpress_stores_the_name_encoded(): void {
		$this->assertSame( 'Tom &amp; Jerry', get_userdata( $this->viewer )->display_name );
	}

	/**
	 * The Viewers list.
	 */
	public function test_viewers_list_names_are_plain_text(): void {
		$this->assertSame( 'Tom & Jerry', $this->get( 'analytics/users' )[0]['display_name'] );
	}

	/**
	 * The viewer drill-down: name and video title.
	 */
	public function test_viewer_detail_name_and_title_are_plain_text(): void {
		$data = $this->get( 'analytics/users/' . $this->viewer );

		$this->assertSame( 'Tom & Jerry', $data['user']['display_name'] );
		$this->assertSame( 'Q&A lesson', $data['sessions'][0]['title'] );
	}

	/**
	 * The Milestones list and the Dashboard's recent milestones.
	 */
	public function test_milestone_names_are_plain_text(): void {
		$row = $this->get( 'analytics/milestones' )[0];
		$this->assertSame( 'Tom & Jerry', $row['user_name'] );
		$this->assertSame( 'Q&A lesson', $row['video_title'] );

		$recent = $this->get( 'analytics/overview' )['recent_milestones'][0];
		$this->assertSame( 'Tom & Jerry', $recent['user_name'] );
		$this->assertSame( 'Q&A lesson', $recent['video_title'] );
	}

	/**
	 * Decoding never turns stored text into markup.
	 */
	public function test_decoded_markup_is_still_stripped(): void {
		global $wpdb;
		$wpdb->update( $wpdb->users, array( 'display_name' => '&lt;b&gt;Mira&lt;/b&gt;' ), array( 'ID' => $this->viewer ) );
		clean_user_cache( $this->viewer );

		$this->assertSame( 'Mira', $this->get( 'analytics/users' )[0]['display_name'] );
	}
}
