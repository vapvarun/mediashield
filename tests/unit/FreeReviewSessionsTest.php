<?php
/**
 * Free 1.4.4 review: the player, sessions and watched-spans findings
 * (#3, #5, #8, #9, #10). Each test fails on the code before the fix.
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\Access\SessionManager;
use MediaShield\Access\WatchedSpans;
use MediaShield\CPT\SourceStatus;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * What a viewer, a guest and another plugin can and cannot change.
 */
class FreeReviewSessionsTest extends WP_UnitTestCase {

	/**
	 * The oEmbed URLs and Referers asked, in order.
	 *
	 * @var array<int, array{url: string, referer: string}>
	 */
	private $asked = array();

	/**
	 * Vimeo's answer: a callable (url) => status.
	 *
	 * @var callable
	 */
	private $vimeo;

	public function set_up(): void {
		parent::set_up();
		$this->asked = array();
		add_filter( 'pre_http_request', array( $this, 'platform' ), 10, 3 );
		update_option( 'ms_lms_require_watch_pct', 0 );
	}

	public function tear_down(): void {
		remove_filter( 'pre_http_request', array( $this, 'platform' ), 10 );
		remove_all_filters( 'mediashield_measure_watch' );
		delete_option( 'ms_lms_require_watch_pct' );
		parent::tear_down();
	}

	/**
	 * Answer oEmbed with the test's status; refuse anything else.
	 *
	 * @param mixed  $pre  Short-circuit.
	 * @param array  $args Request args.
	 * @param string $url  URL.
	 * @return array|\WP_Error
	 */
	public function platform( $pre, $args, $url ) {
		if ( false !== $pre ) {
			return $pre;
		}
		if ( false === strpos( $url, 'oembed' ) || ! $this->vimeo ) {
			return new \WP_Error( 'offline', 'Offline in tests.' );
		}
		$this->asked[] = array(
			'url'     => rawurldecode( (string) wp_parse_url( $url, PHP_URL_QUERY ) ),
			'referer' => (string) ( $args['headers']['Referer'] ?? '' ),
		);
		$status = ( $this->vimeo )( rawurldecode( $url ) );
		return array(
			'headers'  => array(),
			'body'     => 200 === $status ? '{"type":"video"}' : '',
			'response' => array( 'code' => $status, 'message' => '' ),
			'cookies'  => array(),
		);
	}

	private function vimeo_video( string $link ): int {
		$id = self::factory()->post->create( array( 'post_type' => 'mediashield_video', 'post_status' => 'publish' ) );
		update_post_meta( $id, '_ms_platform', 'vimeo' );
		update_post_meta( $id, '_ms_platform_video_id', '76979871' );
		update_post_meta( $id, '_ms_source_url', $link );
		update_post_meta( $id, '_ms_protection_level', 'none' );
		return $id;
	}

	private function guest_report( int $video_id ): bool {
		wp_set_current_user( 0 );
		$request = new WP_REST_Request( 'POST', '/mediashield/v1/videos/' . $video_id . '/unavailable' );
		$request->set_body_params( array( 'code' => 'private' ) );
		return (bool) rest_do_request( $request )->get_data()['recorded'];
	}

	/**
	 * #3: an unlisted Vimeo video plays here with its hash; Vimeo answers 404
	 * without it. A guest's report asked without the hash and recorded the
	 * working video as unavailable.
	 */
	public function test_a_guest_cannot_mark_an_unlisted_vimeo_video_unavailable(): void {
		$this->vimeo = static function ( $url ) {
			return false !== strpos( $url, 'vimeo.com/76979871/abc123def0' ) ? 200 : 404;
		};
		$video = $this->vimeo_video( 'https://vimeo.com/76979871/abc123def0' );

		$this->assertFalse( $this->guest_report( $video ) );
		$this->assertNull( SourceStatus::get( $video ) );
		$this->assertStringContainsString( 'vimeo.com/76979871/abc123def0', $this->asked[0]['url'] );
		$this->assertSame( home_url( '/' ), $this->asked[0]['referer'], 'Asked as this site, for a domain-limited embed.' );
	}

	/**
	 * #3: a video limited to certain sites answers 403 to a stranger; that is
	 * not "gone", so a guest's report records nothing.
	 */
	public function test_a_domain_limited_vimeo_video_is_not_recorded_on_a_guest_report(): void {
		$this->vimeo = static function () {
			return 403;
		};
		$video = $this->vimeo_video( 'https://vimeo.com/76979871' );

		$this->assertFalse( $this->guest_report( $video ) );
		$this->assertNull( SourceStatus::get( $video ) );
	}

	/**
	 * #3: a video recorded as unavailable that Vimeo plays again is cleared by
	 * the next report; one that plays here is cleared by its first heartbeat.
	 */
	public function test_a_later_check_or_play_clears_the_record(): void {
		$this->vimeo = static function () {
			return 200;
		};
		$video = $this->vimeo_video( 'https://vimeo.com/76979871' );
		SourceStatus::mark_unavailable( $video, 'private' );

		$this->assertFalse( $this->guest_report( $video ) );
		$this->assertNull( SourceStatus::get( $video ), 'The platform plays it again.' );

		SourceStatus::mark_unavailable( $video, 'private' );
		$member  = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$session = SessionManager::start( $video, $member, '203.0.113.9', 'Laptop' );
		$this->assertTrue( SessionManager::heartbeat( $session['session_token'], 12.0, 100.0, true, true, array( array( 0, 12 ) ) ) );
		$this->assertNull( SourceStatus::get( $video ), 'It played here.' );
	}

	/**
	 * #5: a guest's viewing counts the time between reports (at most 30 s),
	 * not 30 s for every playing report.
	 */
	public function test_a_guest_viewing_counts_real_time_not_30_seconds_a_beat(): void {
		global $wpdb;
		$video   = $this->vimeo_video( 'https://vimeo.com/76979871' );
		$session = SessionManager::start( $video, 0, '203.0.113.1', 'Guest' );
		$table   = $wpdb->prefix . 'ms_watch_sessions';
		$back    = static function ( int $seconds ) use ( $wpdb, $table, $session ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update( $table, array( 'last_heartbeat' => gmdate( 'Y-m-d H:i:s', time() - $seconds ) ), array( 'id' => (int) $session['session_id'] ) );
		};
		$total = static function () use ( $wpdb, $table, $session ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return (int) $wpdb->get_var( $wpdb->prepare( "SELECT total_seconds FROM {$table} WHERE id = %d", (int) $session['session_id'] ) );
		};

		$back( 6 );
		SessionManager::heartbeat( $session['session_token'], 6.0, 100.0, true );
		$this->assertEqualsWithDelta( 6, $total(), 1, 'The early beat after 6 s counts 6 s.' );

		$back( 600 );
		SessionManager::heartbeat( $session['session_token'], 40.0, 100.0, true );
		$this->assertLessThanOrEqual( 37, $total(), 'A long gap counts one beat at most.' );

		$back( 10 );
		$before = $total();
		SessionManager::heartbeat( $session['session_token'], 40.0, 100.0, false );
		$this->assertSame( $before, $total(), 'A paused report adds nothing.' );
	}

	/**
	 * #8: the site's "Watch X% to complete" is about lessons. A None video on
	 * a blog post is not measured; an LMS says where it applies.
	 */
	public function test_site_rule_is_measured_only_where_an_lms_says_so(): void {
		update_option( 'ms_lms_require_watch_pct', 50 );
		$video = $this->vimeo_video( 'https://vimeo.com/76979871' );
		$post  = self::factory()->post->create();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->go_to( get_permalink( $post ) );

		$this->assertStringNotContainsString( 'data-watch-track', \MediaShield\Player\Renderer::render( $video ), 'A blog post: no viewing, no requests.' );

		$seen = array();
		add_filter(
			'mediashield_measure_watch',
			static function ( $measure, $video_id, $post_id ) use ( &$seen ) {
				$seen = array( $video_id, $post_id );
				return true;
			},
			10,
			3
		);
		$this->assertStringContainsString( 'data-watch-track="1"', \MediaShield\Player\Renderer::render( $video ) );
		$this->assertSame( array( $video, $post ), $seen );
	}

	/**
	 * Give a member $seconds of watched time on the video.
	 */
	private static function watched( int $user, int $video, float $seconds ): void {
		update_user_meta( $user, WatchedSpans::META_KEY, array( $video => array( 's' => array( array( 0, $seconds ) ) ) ) );
	}

	/**
	 * #9: the length is the video's, measured by its viewers: a member whose
	 * player never reported one still gets a share, and a member reporting a
	 * short length cannot make a long video 100%.
	 */
	public function test_the_measured_length_is_kept_on_the_video(): void {
		$video = $this->vimeo_video( 'https://vimeo.com/76979871' );
		$a     = self::factory()->user->create();
		$b     = self::factory()->user->create();
		$short = self::factory()->user->create();
		$quiet = self::factory()->user->create();

		WatchedSpans::record( $a, $video, array(), 200.0 );
		WatchedSpans::record( $b, $video, array(), 200.0 );
		$this->assertSame( 200.0, WatchedSpans::measured( $video ) );

		self::watched( $short, $video, 10 );
		WatchedSpans::record( $short, $video, array(), 10.0 );
		$this->assertSame( 5, WatchedSpans::percent( $short, $video ), '10 s of 200 s, not of the 10 s it reported.' );

		self::watched( $quiet, $video, 20 );
		$this->assertSame( 10, WatchedSpans::percent( $quiet, $video ), 'No length of its own: the video\'s.' );
	}

	/**
	 * Re-review #1: one member reporting a huge length cannot hold everyone
	 * at 0%: above 24 hours it is not believed, and under it the median of
	 * the honest reports wins.
	 */
	public function test_one_bogus_length_does_not_move_anyones_share(): void {
		$video  = $this->vimeo_video( 'https://vimeo.com/76979871' );
		$honest = self::factory()->user->create();
		$bogus  = self::factory()->user->create();
		$other  = self::factory()->user->create();
		self::watched( $honest, $video, 100 );

		WatchedSpans::record( $honest, $video, array(), 200.0 );
		WatchedSpans::record( $bogus, $video, array(), 99999999.0 );
		$this->assertSame( 50, WatchedSpans::percent( $honest, $video ), 'Over 24 hours: not believed.' );

		WatchedSpans::record( $bogus, $video, array(), 80000.0 );
		$this->assertSame( 50, WatchedSpans::percent( $honest, $video ), 'Two reports: the lower middle one.' );

		WatchedSpans::record( $other, $video, array(), 210.0 );
		WatchedSpans::record( $bogus, $video, array(), 80000.0 );
		$this->assertSame( 47, WatchedSpans::percent( $honest, $video ), 'Median of 200, 210 and the bogus one: 210.' );
	}

	/**
	 * Re-review #1: the median of the honest reports is used, and a report far
	 * above it is not kept once three are known.
	 */
	public function test_the_median_of_honest_reports_is_used(): void {
		$video = $this->vimeo_video( 'https://vimeo.com/76979871' );
		$users = self::factory()->user->create_many( 4 );
		foreach ( array( 180.0, 200.0, 190.0 ) as $i => $length ) {
			WatchedSpans::record( $users[ $i ], $video, array(), $length );
		}
		$this->assertSame( 190.0, WatchedSpans::measured( $video ) );

		WatchedSpans::record( $users[3], $video, array(), 5000.0 );
		$this->assertCount( 3, get_post_meta( $video, WatchedSpans::MEASURED, true ), 'Far above the median: not kept.' );
		$this->assertSame( 190.0, WatchedSpans::measured( $video ) );
	}

	/**
	 * Re-review #1: the owner swaps in a shorter video; the old length goes.
	 */
	public function test_a_new_source_forgets_the_measured_length(): void {
		$video  = $this->vimeo_video( 'https://vimeo.com/76979871' );
		$member = self::factory()->user->create();
		WatchedSpans::record( $member, $video, array(), 3600.0 );
		self::watched( $member, $video, 60 );
		$this->assertSame( 1, WatchedSpans::percent( $member, $video ) );

		update_post_meta( $video, '_ms_source_url', 'https://vimeo.com/76979872' );

		$this->assertSame( 0.0, WatchedSpans::measured( $video ) );
		WatchedSpans::record( $member, $video, array(), 120.0 );
		$this->assertSame( 50, WatchedSpans::percent( $member, $video ) );
	}

	/**
	 * #9: a video measured before 1.4.4 kept the length on the member ('d');
	 * the first read seeds the video with it.
	 */
	public function test_a_length_kept_on_the_member_seeds_the_video(): void {
		$video  = $this->vimeo_video( 'https://vimeo.com/76979871' );
		$member = self::factory()->user->create();
		update_user_meta( $member, WatchedSpans::META_KEY, array( $video => array( 's' => array( array( 0, 30 ) ), 'd' => 120.0 ) ) );

		$this->assertSame( 25, WatchedSpans::percent( $member, $video ) );
		$this->assertSame( 120.0, WatchedSpans::measured( $video ) );
	}

	/**
	 * Re-review #2: guests' reports ask the platform once per ten minutes,
	 * whatever it answered; an editor's report still records where it cannot
	 * tell.
	 */
	public function test_guest_reports_call_the_platform_once_per_interval(): void {
		$this->vimeo = static function () {
			return 403;
		};
		$video = $this->vimeo_video( 'https://vimeo.com/76979871' );

		for ( $i = 0; $i < 5; $i++ ) {
			$this->assertFalse( $this->guest_report( $video ) );
		}
		$this->assertCount( 1, $this->asked );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$this->assertTrue( SourceStatus::report( $video, 'private' ) );
		$this->assertCount( 1, $this->asked );
		$this->assertNotNull( SourceStatus::get( $video ) );
	}

	/**
	 * #10: mediashield_create_video follows core: a contributor's video waits
	 * for review, an editor's is published.
	 */
	public function test_create_video_is_pending_for_someone_who_cannot_publish(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'contributor' ) ) );
		$id = apply_filters( 'mediashield_create_video', null, array( 'source_url' => 'https://vimeo.com/111111111' ) );
		$this->assertIsInt( $id );
		$this->assertSame( 'pending', get_post_status( $id ) );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		$id = apply_filters( 'mediashield_create_video', null, array( 'source_url' => 'https://vimeo.com/222222222' ) );
		$this->assertSame( 'publish', get_post_status( $id ) );
	}

	/**
	 * Final check #4: a Vimeo video saved with no Duration takes Vimeo's own;
	 * a typed Duration is never replaced and nothing is asked.
	 */
	public function test_saving_a_vimeo_link_stores_the_platform_duration(): void {
		$this->vimeo = static function () {
			return 200;
		};
		add_filter( 'pre_http_request', array( $this, 'with_duration' ), 9, 3 );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );

		$id = apply_filters( 'mediashield_create_video', null, array( 'source_url' => 'https://vimeo.com/333444555' ) );
		$this->assertSame( 212, (int) get_post_meta( $id, '_ms_duration', true ) );

		// Its own asks carry this site as Referer (the poster fetch does not).
		$own   = function () {
			return count( wp_list_filter( $this->asked, array( 'referer' => home_url( '/' ) ) ) );
		};
		$asked = $own();
		update_post_meta( $id, '_ms_duration', 300 );
		wp_update_post( array( 'ID' => $id, 'post_title' => 'Renamed' ) );
		$this->assertSame( 300, (int) get_post_meta( $id, '_ms_duration', true ), 'A typed Duration wins.' );
		$this->assertSame( $asked, $own(), 'Nothing asked while a Duration is stored.' );
		remove_filter( 'pre_http_request', array( $this, 'with_duration' ), 9 );
	}

	/**
	 * Vimeo's oEmbed answer with a duration (212 s).
	 *
	 * @param mixed  $pre  Short-circuit.
	 * @param array  $args Request args.
	 * @param string $url  URL.
	 * @return mixed
	 */
	public function with_duration( $pre, $args, $url ) {
		if ( false === strpos( $url, 'vimeo.com/api/oembed' ) ) {
			return $pre;
		}
		$this->asked[] = array(
			'url'     => $url,
			'referer' => (string) ( $args['headers']['Referer'] ?? '' ),
		);
		return array(
			'headers'  => array(),
			'body'     => '{"type":"video","duration":212,"title":"Vimeo lesson"}',
			'response' => array( 'code' => 200, 'message' => '' ),
			'cookies'  => array(),
		);
	}

	/**
	 * Final check #5: a filtered shortcode link is for that render only; two
	 * renders leave the stored link and the measured lengths alone.
	 */
	public function test_a_filtered_shortcode_link_is_not_stored(): void {
		$video = $this->vimeo_video( 'https://vimeo.com/76979871' );
		$users = self::factory()->user->create_many( 2 );
		WatchedSpans::record( $users[0], $video, array(), 200.0 );
		WatchedSpans::record( $users[1], $video, array(), 200.0 );
		$filter = static function () {
			return 'https://cdn.example.org/signed/lesson.mp4?token=abc';
		};
		add_filter( 'mediashield_shortcode_source_url', $filter );
		wp_set_current_user( $users[0] );

		$first  = do_shortcode( '[mediashield id="' . $video . '"]' );
		$second = do_shortcode( '[mediashield id="' . $video . '"]' );
		remove_filter( 'mediashield_shortcode_source_url', $filter );

		$this->assertNotSame( '', $first );
		$this->assertSame( $first, $second );
		$this->assertSame( 'https://vimeo.com/76979871', get_post_meta( $video, '_ms_source_url', true ) );
		$this->assertCount( 2, get_post_meta( $video, WatchedSpans::MEASURED, true ) );
		$this->assertSame( 200.0, WatchedSpans::measured( $video ) );
	}
}
