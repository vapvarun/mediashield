<?php
/**
 * Edit video: the video first, an uploaded file shown by name, plain milestone words.
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\CPT\VideoPostType;
use WP_UnitTestCase;

/**
 * Basecamp 10545260613.
 */
class EditVideoScreenTest extends WP_UnitTestCase {

	/**
	 * Admin.
	 */
	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * The boxes are registered source first, then protection, then milestones.
	 */
	public function test_boxes_in_the_order_an_owner_works(): void {
		global $wp_meta_boxes;
		set_current_screen( 'mediashield_video' );
		VideoPostType::register_meta_boxes();
		$ids = array_keys( $wp_meta_boxes['mediashield_video']['normal']['high'] );
		$this->assertSame( array( 'mediashield-video-source', 'mediashield-video-settings' ), array_slice( $ids, 0, 2 ) );
		$this->assertSame( '1. The video', $wp_meta_boxes['mediashield_video']['normal']['high']['mediashield-video-source']['title'] );
	}

	/**
	 * An uploaded file is shown by name and size, never by its signed stream address.
	 */
	public function test_uploaded_file_shown_by_name_and_kept_on_save(): void {
		$id   = self::factory()->post->create( array( 'post_type' => 'mediashield_video' ) );
		$dir  = trailingslashit( wp_upload_dir()['basedir'] ) . 'mediashield/';
		$file = 'lesson-one-' . $id . '.mp4';
		wp_mkdir_p( $dir );
		file_put_contents( $dir . $file, str_repeat( 'x', 2048 ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		$stream = rest_url( 'mediashield/v1/stream/' . $id );
		update_post_meta( $id, '_ms_platform', 'self' );
		update_post_meta( $id, '_ms_platform_video_id', $file );
		update_post_meta( $id, '_ms_owned_file', $file );
		update_post_meta( $id, '_ms_source_url', $stream );

		ob_start();
		VideoPostType::render_source_meta_box( get_post( $id ) );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( $file, $html );
		$this->assertStringContainsString( '2 KB', $html );
		$this->assertStringNotContainsString( 'value="' . esc_url( $stream ) . '"', $html, 'The signed address is not shown as the link.' );

		// Saving with the link field left empty keeps the file.
		$_POST = array(
			'_ms_meta_nonce'  => wp_create_nonce( 'mediashield_video_meta' ),
			'_ms_source_url'  => '',
			'_ms_source_held' => '1',
			'_ms_platform'    => 'self',
		);
		VideoPostType::save_meta_box( $id, get_post( $id ) );
		$_POST = array();
		$this->assertSame( $stream, get_post_meta( $id, '_ms_source_url', true ) );

		unlink( $dir . $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	/**
	 * Milestones in plain words, at the four milestones MediaShield records.
	 */
	public function test_milestones_in_plain_words(): void {
		$id = self::factory()->post->create( array( 'post_type' => 'mediashield_video' ) );
		ob_start();
		VideoPostType::render_milestones_meta_box( get_post( $id ) );
		$html = (string) ob_get_clean();

		$this->assertStringNotContainsString( 'ms_tag_{video_id}', $html );
		$this->assertStringNotContainsString( 'global milestone settings', $html );
		$this->assertStringNotContainsString( '[10][tag]', $html, 'No 10% row: MediaShield records 25, 50, 75 and 100.' );

		update_post_meta( $id, '_ms_milestone_tags', array( 10 => array( 'tag' => 'early', 'enabled' => true ) ) );
		ob_start();
		VideoPostType::render_milestones_meta_box( get_post( $id ) );
		$this->assertStringContainsString( '[10][tag]', (string) ob_get_clean(), 'A 10% tag a site set is still shown.' );
	}
}
