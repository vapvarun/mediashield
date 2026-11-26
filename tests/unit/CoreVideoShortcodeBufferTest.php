<?php
/**
 * Regression test for the 1.4.4 guest check of core's [video] shortcode.
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\Player\PlayerWrapper;
use WP_UnitTestCase;

/**
 * A post whose only video is core's [video] shortcode of a MediaShield file is
 * protected on the page, not only inside process_buffer().
 *
 * start_buffer() buffers a page only when the saved content holds a video
 * signature. The saved content holds `[video src=...]`, not `<video`, so the
 * buffer never started and a guest got core's raw <source src="...?_=1"> and
 * a download link. Fails on 1.4.4 before the '[video' signature was added.
 */
class CoreVideoShortcodeBufferTest extends WP_UnitTestCase {

	public function test_post_with_only_a_core_video_shortcode_is_wrapped_on_the_page(): void {
		$url   = 'https://example.org/wp-content/uploads/2026/11/ms-guest-clip.mp4';
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
		$post  = self::factory()->post->create(
			array(
				'post_status'  => 'publish',
				'post_content' => '[video src="' . $url . '"]',
			)
		);

		wp_set_current_user( 0 );
		$this->go_to( get_permalink( $post ) );

		// The front end: template_redirect starts the buffer, the theme prints
		// the_content, the buffer's callback runs when it is flushed.
		ob_start();
		$level = ob_get_level();
		PlayerWrapper::start_buffer();
		$started = ob_get_level() > $level;
		if ( have_posts() ) {
			the_post();
			the_content();
		}
		if ( $started ) {
			ob_end_flush();
		}
		$page = (string) ob_get_clean();

		$this->assertTrue( $started, 'the page is buffered' );
		$this->assertStringContainsString( 'data-video-id="' . $video . '"', $page, 'the core [video] is wrapped as the MediaShield video' );
		$this->assertStringNotContainsString( 'href="' . $url . '"', $page, 'no download link to the file' );
		$this->assertStringNotContainsString( 'ms-guest-clip.mp4?_=', $page, 'no raw <source> address' );
	}
}
