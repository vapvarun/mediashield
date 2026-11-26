<?php
/**
 * An uploaded file is shown by the name the owner uploaded.
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\Upload\Drivers\SelfHosted;
use WP_UnitTestCase;

/**
 * Coordinator check on Basecamp 10545260613 / 10545260681: the Videos list
 * printed the stored name with its 20-character random token as one
 * unbroken word, which ran down the next column.
 */
class UploadNameTest extends WP_UnitTestCase {

	/**
	 * The name kept at upload wins; an older upload loses only the token.
	 */
	public function test_display_name(): void {
		$id = self::factory()->post->create( array( 'post_type' => 'mediashield_video' ) );

		$this->assertSame( 'Beach_Male_1280x720.mp4', SelfHosted::display_name( $id, '/x/mediashield/Beach_Male_1280x720-osV6Y0VRNCtfRt4TFUcS.mp4' ) );
		$this->assertSame( 'lesson-one.mp4', SelfHosted::display_name( $id, 'lesson-one-xISnGexr5TeY711Elzag-1.mp4' ), 'A wp_unique_filename() suffix goes too.' );
		$this->assertSame( 'my-short-name.mp4', SelfHosted::display_name( $id, 'my-short-name.mp4' ), 'A name without a token is left alone.' );

		update_post_meta( $id, SelfHosted::ORIGINAL_NAME, 'Week 2 - Lighting.mp4' );
		$this->assertSame( 'Week 2 - Lighting.mp4', SelfHosted::display_name( $id, 'week-2-lighting-xISnGexr5TeY711Elzag.mp4' ) );
	}

	/**
	 * The Videos list's source field carries the display name, not the stored one.
	 */
	public function test_list_shows_the_display_name(): void {
		$id = self::factory()->post->create( array( 'post_type' => 'mediashield_video' ) );
		update_post_meta( $id, '_ms_owned_file', 'Beach_Male_1280x720-osV6Y0VRNCtfRt4TFUcS.mp4' );
		$this->assertSame( 'Beach_Male_1280x720.mp4', \MediaShield\REST\VideoListController::source_label( $id, 'self' ) );
	}
}
