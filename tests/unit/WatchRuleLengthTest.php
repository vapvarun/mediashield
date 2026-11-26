<?php
/**
 * 1.4.4: a video a watch rule completes by needs a stored length, which
 * the platform fills where it can and the owner is asked for otherwise.
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\CPT\VideoStatus;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Lengths from the platform on save; the owner's "add this video's length" line.
 */
class WatchRuleLengthTest extends WP_UnitTestCase {

	private const LINE = "Add this video's length so the watch rule can't be skipped.";

	public function set_up(): void {
		parent::set_up();
		add_filter( 'pre_http_request', array( $this, 'platform' ), 10, 3 );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	public function tear_down(): void {
		remove_filter( 'pre_http_request', array( $this, 'platform' ), 10 );
		remove_all_filters( 'mediashield_watch_rule_applies' );
		parent::tear_down();
	}

	/**
	 * Wistia's oEmbed with a duration; everything else offline.
	 *
	 * @param mixed  $pre  Short-circuit.
	 * @param array  $args Args.
	 * @param string $url  URL.
	 * @return array|\WP_Error
	 */
	public function platform( $pre, $args, $url ) {
		if ( false === strpos( $url, 'fast.wistia.com/oembed' ) ) {
			return new \WP_Error( 'offline', 'Offline in tests.' );
		}
		return array(
			'headers'  => array(),
			'body'     => '{"type":"video","duration":95.4,"title":"Wistia lesson"}',
			'response' => array( 'code' => 200, 'message' => '' ),
			'cookies'  => array(),
		);
	}

	private function video( int $duration = 0 ): int {
		$id = self::factory()->post->create( array( 'post_type' => 'mediashield_video', 'post_status' => 'publish' ) );
		update_post_meta( $id, '_ms_platform', 'self' );
		update_post_meta( $id, '_ms_source_url', 'https://example.org/lesson.mp4' );
		if ( $duration ) {
			update_post_meta( $id, '_ms_duration', $duration );
		}
		return $id;
	}

	/**
	 * A Wistia link saved with no Duration takes Wistia's. Fails before 1.4.4.
	 */
	public function test_saving_a_wistia_link_stores_the_platform_duration(): void {
		$id = apply_filters( 'mediashield_create_video', null, array( 'source_url' => 'https://home.wistia.com/medias/e4a27b9' . wp_rand( 100, 999 ) ) );
		$this->assertIsInt( $id );
		$this->assertSame( 95, (int) get_post_meta( $id, '_ms_duration', true ) );
	}

	/**
	 * Asked only while a rule applies and no Duration is stored.
	 */
	public function test_needs_length_only_while_a_rule_uses_a_video_without_one(): void {
		$bare = $this->video();
		$set  = $this->video( 300 );
		$this->assertFalse( VideoStatus::needs_length( $bare ), 'No rule.' );

		add_filter( 'mediashield_watch_rule_applies', '__return_true' );
		$this->assertTrue( VideoStatus::needs_length( $bare ) );
		$this->assertFalse( VideoStatus::needs_length( $set ), 'A Duration is stored.' );
	}

	/**
	 * The edit screen says it next to Duration, and the Videos list carries it.
	 */
	public function test_edit_screen_and_list_ask_for_the_length(): void {
		$bare = $this->video();
		add_filter( 'mediashield_watch_rule_applies', '__return_true' );

		ob_start();
		\MediaShield\CPT\VideoPostType::render_source_meta_box( get_post( $bare ) );
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( esc_html( self::LINE ), $html );
		$this->assertStringContainsString( 'aria-describedby="ms-duration-needed"', $html );

		$request = new WP_REST_Request( 'GET', '/wp/v2/mediashield-videos' );
		$request->set_param( 'include', array( $bare ) );
		$request->set_param( 'status', 'publish' );
		$rows = rest_do_request( $request )->get_data();
		$this->assertTrue( $rows[0]['ms_list']['needs_length'] );

		remove_all_filters( 'mediashield_watch_rule_applies' );
		ob_start();
		\MediaShield\CPT\VideoPostType::render_source_meta_box( get_post( $bare ) );
		$this->assertStringNotContainsString( esc_html( self::LINE ), (string) ob_get_clean(), 'No rule: no line.' );
	}
}
