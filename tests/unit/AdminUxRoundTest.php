<?php
/**
 * Admin findings from the independent UX test (MSUX).
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Each test names the finding and fails on the code before its fix.
 */
class AdminUxRoundTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		\MediaShield\REST\VideoListController::register_hooks();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * MSUX-12: a watched YouTube video showed "-" for its length: the list
	 * read only the stored length, not the one members' players reported.
	 */
	public function test_list_shows_the_length_players_reported(): void {
		$id = self::factory()->post->create(
			array(
				'post_type'   => 'mediashield_video',
				'post_status' => 'publish',
				'meta_input'  => array(
					'_ms_platform'          => 'youtube',
					'_ms_platform_video_id' => 'jNQXAC9IVRw',
					'_ms_source_url'        => 'https://www.youtube.com/watch?v=jNQXAC9IVRw',
				),
			)
		);
		update_post_meta( $id, \MediaShield\Access\WatchedSpans::MEASURED, array( 7 => 19.2 ) );

		$request = new WP_REST_Request( 'GET', '/wp/v2/mediashield-videos' );
		$request->set_param( 'include', array( $id ) );
		$request->set_param( '_fields', 'id,ms_list' );
		$row = rest_do_request( $request )->get_data()[0];

		$this->assertSame( 19, $row['ms_list']['duration'] );
	}

	/**
	 * MSUX-27: the embed box led with a PHP template snippet and told owners
	 * to paste the shortcode "using the Classic Editor".
	 */
	public function test_embed_box_leads_with_the_block(): void {
		$id = self::factory()->post->create( array( 'post_type' => 'mediashield_video', 'post_status' => 'publish' ) );
		ob_start();
		\MediaShield\CPT\VideoPostType::render_embed_meta_box( get_post( $id ) );
		$html = (string) ob_get_clean();
		$this->assertStringNotContainsString( 'do_shortcode', $html );
		$this->assertStringNotContainsString( 'PHP Template', $html );
		$this->assertLessThan( strpos( $html, 'ms-shortcode-input' ), strpos( $html, 'MediaShield Video block' ), 'The block comes first.' );
	}
}
