<?php
/**
 * Independent UX test on a model site (1.4.4): the player and front-end
 * findings MSUX-02, 04, 07, 19, 25 and 33.
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\Access\SessionManager;
use MediaShield\Access\WatchedSpans;
use MediaShield\Core\Settings;
use MediaShield\Upload\Drivers\SelfHosted;
use WP_UnitTestCase;

/**
 * What a member sees on the player, the playlist and My Videos.
 */
class IndependentUxTest extends WP_UnitTestCase {

	/**
	 * A real video file in the test container (MS_TEST_VIDEO points elsewhere).
	 */
	private const VIDEO_FILE = '/cache/fixtures/beach-male.mp4';

	/** @var int */
	private $member;

	public function set_up(): void {
		parent::set_up();
		$this->member = self::factory()->user->create( array( 'role' => 'subscriber' ) );
	}

	private function video( int $duration = 0 ): int {
		$id = self::factory()->post->create( array( 'post_type' => 'mediashield_video', 'post_status' => 'publish', 'post_title' => 'Beach walk' ) );
		update_post_meta( $id, '_ms_platform', 'self' );
		update_post_meta( $id, '_ms_source_url', 'https://example.org/beach.mp4' );
		if ( $duration ) {
			update_post_meta( $id, '_ms_duration', $duration );
		}
		return $id;
	}

	/**
	 * MSUX-02: an upload played to its last frame (17.808 s, stored as 18)
	 * is 100%: the viewing, the 100% milestone and the watched share. Fails
	 * before 1.4.4 (98.9%).
	 */
	public function test_reaching_the_end_is_100_percent(): void {
		global $wpdb;
		$video   = $this->video( 18 );
		$session = SessionManager::start( $video, $this->member, '203.0.113.9', 'Laptop' );
		update_user_meta( $this->member, WatchedSpans::META_KEY, array( $video => array( 's' => array( array( 0, 17.8 ) ) ) ) );

		$this->assertTrue( SessionManager::heartbeat( $session['session_token'], 17.808, 17.808, false ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->assertSame( 100.0, (float) $wpdb->get_var( $wpdb->prepare( "SELECT completion_pct FROM {$wpdb->prefix}ms_watch_sessions WHERE id = %d", (int) $session['session_id'] ) ) );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$this->assertSame( '1', (string) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}ms_milestones WHERE video_id = %d AND user_id = %d AND milestone_pct = 100", $video, $this->member ) ) );
		$this->assertSame( 100, WatchedSpans::percent( $this->member, $video ) );

		$this->assertEqualsWithDelta( 50.0, SessionManager::completion( 9, 18 ), 0.01, 'The middle is the middle.' );
		$this->assertEqualsWithDelta( 91.7, SessionManager::completion( 16.5, 18 ), 0.1, 'More than a second short is not the end.' );
	}

	/**
	 * MSUX-02, MSUX-25: an upload's length is its exact length in whole
	 * seconds down, as the player's clock reads it. Fails before 1.4.4
	 * (WordPress rounds up).
	 */
	public function test_upload_length_is_whole_seconds_down(): void {
		$file = getenv( 'MS_TEST_VIDEO' ) ? (string) getenv( 'MS_TEST_VIDEO' ) : self::VIDEO_FILE;
		$this->assertFileExists( $file );
		if ( ! class_exists( 'getID3', false ) ) {
			require ABSPATH . WPINC . '/ID3/getid3.php';
		}
		$exact = (float) ( ( new \getID3() )->analyze( $file )['playtime_seconds'] ?? 0 );
		$this->assertGreaterThan( 0, $exact );

		$video = $this->video();
		$this->assertSame( (int) floor( $exact ), SelfHosted::record_length( $video, $file ) );
	}

	/**
	 * MSUX-25: a playlist item with no Duration shows the length players
	 * measured. Fails before 1.4.4 (no length).
	 */
	public function test_playlist_shows_a_measured_length(): void {
		$video = $this->video();
		update_post_meta( $video, WatchedSpans::MEASURED, array( 7 => 212.4 ) );
		$playlist = self::factory()->post->create( array( 'post_type' => 'mediashield_playlist', 'post_status' => 'publish' ) );
		global $wpdb;
		$wpdb->insert( $wpdb->prefix . 'ms_playlist_items', array( 'playlist_id' => $playlist, 'video_id' => $video, 'sort_order' => 0 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		wp_set_current_user( $this->member );

		$this->assertStringContainsString( '<span class="ms-playlist-item-duration">3:32</span>', \MediaShield\Player\PlaylistRenderer::render( $playlist ) );
	}

	/**
	 * MSUX-07: the player carries its title for the login wall.
	 */
	public function test_player_carries_its_title(): void {
		$video = $this->video();
		wp_set_current_user( 0 );
		update_option( 'ms_require_login', 1 );
		$this->assertStringContainsString( 'data-title="Beach walk"', \MediaShield\Player\Renderer::render( $video ) );
	}

	/**
	 * MSUX-33: "Log in" on the player, also on a site that stored the old default.
	 */
	public function test_log_in_is_spelled_one_way(): void {
		$this->assertSame( 'Log in', Settings::frontend_config()['messages']['loginButton'] );
		update_option( 'ms_login_button_text', 'Log In' );
		$this->assertSame( 'Log in', Settings::frontend_config()['messages']['loginButton'] );
		update_option( 'ms_login_button_text', 'Sign in to watch' );
		$this->assertSame( 'Sign in to watch', Settings::frontend_config()['messages']['loginButton'], 'An owner\'s own text stays.' );
	}

	/**
	 * MSUX-04: Strict says what it can and cannot do on a platform video.
	 */
	public function test_strict_wording_is_honest_about_platforms(): void {
		$this->assertStringContainsString( 'the platform still serves the video', \MediaShield\Player\Protection::levels()['strict']['summary'] );
	}

	/**
	 * MSUX-19: a video in progress on no page the member can open still has
	 * Resume, to its own player page. Fails before 1.4.4 (no button).
	 */
	public function test_every_card_in_progress_has_resume(): void {
		global $wpdb;
		$video = $this->video( 100 );
		$session = SessionManager::start( $video, $this->member, '203.0.113.9', 'Laptop' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->update( $wpdb->prefix . 'ms_watch_sessions', array( 'max_position' => 40, 'total_seconds' => 40, 'completion_pct' => 40 ), array( 'id' => (int) $session['session_id'] ) );
		wp_set_current_user( $this->member );

		$html = do_shortcode( '[mediashield_my_videos]' );

		$this->assertMatchesRegularExpression( '/class="ms-my-videos__resume"|href="[^"]*mediashield_embed=[^"]*" class="ms-my-videos__resume"/', $html );
		$this->assertMatchesRegularExpression( '/<a href="[^"]*mediashield_embed=[^"]+" class="ms-my-videos__resume"/', $html );
	}
}
