<?php
/**
 * Regression test for the 1.4.4 standalone deep check.
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\Player\PlayerWrapper;
use WP_UnitTestCase;

/**
 * A MediaShield video's file placed with core's [video] shortcode is wrapped
 * like a pasted <video>. Core adds a `?_=1` counter to the address, so the
 * exact match against `_ms_source_url` failed and the file played
 * unprotected, its address and a download link in the page. Fails on 1.4.3.
 */
class CoreVideoShortcodeTest extends WP_UnitTestCase {

	public function test_core_video_shortcode_of_a_library_file_is_wrapped(): void {
		$url   = 'https://example.org/wp-content/uploads/2026/11/ms-clip.mp4';
		$video = self::factory()->post->create(
			array(
				'post_type'   => 'mediashield_video',
				'post_status' => 'publish',
				'meta_input'  => array(
					'_ms_platform'   => 'self',
					'_ms_source_url' => $url,
				),
			)
		);

		$core = do_shortcode( '[video src="' . $url . '"]' );
		$this->assertStringContainsString( 'ms-clip.mp4?_=', $core, 'core adds its counter' );

		$wrapped = PlayerWrapper::process_buffer( '<p>x</p>' . $core );
		$this->assertStringContainsString( 'data-video-id="' . $video . '"', $wrapped, 'the core [video] is wrapped as the MediaShield video' );
		$this->assertStringNotContainsString( 'href="' . $url . '"', $wrapped, 'core\'s download link to the file is gone' );
	}
}
