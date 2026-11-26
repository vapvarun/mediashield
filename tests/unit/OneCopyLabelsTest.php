<?php
/**
 * One length formatter and one platform label map (ponytail #11, #12).
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\CPT\VideoPostType;
use MediaShield\Player\PlaylistRenderer;
use MediaShield\Support\Platforms;
use WP_UnitTestCase;

/**
 * Fails before: the edit screen kept its own copies, so a rename had to be
 * made twice.
 */
class OneCopyLabelsTest extends WP_UnitTestCase {

	public function test_edit_screen_has_no_copies(): void {
		$this->assertFalse( method_exists( VideoPostType::class, 'length_label' ), 'Lengths use PlaylistRenderer::duration_label().' );
		$this->assertFalse( method_exists( VideoPostType::class, 'get_platform_label' ), 'Platform names use Platforms::label().' );
		$this->assertSame( '1:02:05', PlaylistRenderer::duration_label( 3725 ) );
		$this->assertSame( '0:27', PlaylistRenderer::duration_label( 27 ) );
		$this->assertSame( 'Video file link', Platforms::label( 'self', false ) );
	}

	public function test_edit_screen_shows_the_shared_platform_name(): void {
		$id = self::factory()->post->create( array( 'post_type' => 'mediashield_video', 'post_status' => 'publish' ) );
		update_post_meta( $id, '_ms_platform', 'bunny' );
		update_post_meta( $id, '_ms_platform_video_id', 'abc' );
		ob_start();
		VideoPostType::render_source_meta_box( get_post( $id ) );
		$html = (string) ob_get_clean();
		$this->assertStringContainsString( '<strong id="ms-detected-platform-label">' . Platforms::label( 'bunny' ) . '</strong>', $html );
	}
}
