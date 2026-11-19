<?php
/**
 * Regression test for Basecamp 10526725213 (1.4.3).
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\CPT\VideoPostType;
use MediaShield\Tags\TagManager;
use WP_UnitTestCase;

/**
 * Tags can be put on and taken off a video from its edit screen.
 */
class VideoTagPickerTest extends WP_UnitTestCase {

	/**
	 * Fails on 1.4.2: the edit screen had no Tags field and saving changed no tags.
	 */
	public function test_edit_screen_adds_and_removes_tags(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$video = self::factory()->post->create( array( 'post_type' => 'mediashield_video' ) );
		$old   = TagManager::create( 'Picker old ' . wp_generate_password( 4, false ) );
		$kept  = TagManager::create( 'Picker kept ' . wp_generate_password( 4, false ) );
		TagManager::assign_to_video( $video, $old );
		TagManager::assign_to_video( $video, $kept );

		ob_start();
		VideoPostType::render_settings_meta_box( get_post( $video ) );
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( 'name="_ms_add_tags"', $html );
		$this->assertStringContainsString( 'value="' . $old . '" checked', $html );

		$new   = 'Picker new ' . wp_generate_password( 4, false );
		$_POST = array(
			'_ms_meta_nonce'   => wp_create_nonce( 'mediashield_video_meta' ),
			'_ms_tags_present' => '1',
			'_ms_tag_ids'      => array( (string) $kept ),
			'_ms_add_tags'     => $new . ', ',
		);
		VideoPostType::save_meta_box( $video, get_post( $video ) );
		$_POST = array();

		$names = wp_list_pluck( TagManager::get_for_video( $video ), 'name' );
		sort( $names );
		$expected = array( $new, TagManager::get( $kept )->name );
		sort( $expected );
		$this->assertSame( $expected, $names );
	}

	/**
	 * A save without the Tags row on the form never clears tags.
	 */
	public function test_save_without_the_row_keeps_tags(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$video = self::factory()->post->create( array( 'post_type' => 'mediashield_video' ) );
		$tag   = TagManager::create( 'Picker stays ' . wp_generate_password( 4, false ) );
		TagManager::assign_to_video( $video, $tag );

		$_POST = array( '_ms_meta_nonce' => wp_create_nonce( 'mediashield_video_meta' ) );
		VideoPostType::save_meta_box( $video, get_post( $video ) );
		$_POST = array();

		$this->assertCount( 1, TagManager::get_for_video( $video ) );
	}
}
