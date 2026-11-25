<?php
/**
 * An uploaded file's length is recorded from the file.
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\Upload\Drivers\SelfHosted;
use WP_UnitTestCase;

/**
 * Coordinator decision on Basecamp 10545260613: lists and the edit screen
 * showed "—" for every uploaded file. The read itself is checked in the lab
 * with real clips (17.8 s -> 18, 24.6 s -> 25); here the rules around it.
 */
class UploadLengthTest extends WP_UnitTestCase {

	/**
	 * The backfill fills a missing length from the file and keeps one the owner typed.
	 */
	public function test_backfill_reads_missing_lengths_only(): void {
		$typed = self::factory()->post->create( array( 'post_type' => 'mediashield_video' ) );
		$empty = self::factory()->post->create( array( 'post_type' => 'mediashield_video' ) );
		update_post_meta( $typed, '_ms_owned_file', 'a.mp4' );
		update_post_meta( $typed, '_ms_duration', 95 );
		update_post_meta( $empty, '_ms_owned_file', 'missing-' . $empty . '.mp4' );

		SelfHosted::backfill_lengths();

		$this->assertSame( '95', get_post_meta( $typed, '_ms_duration', true ), 'A typed length is kept.' );
		$this->assertSame( '', get_post_meta( $empty, '_ms_duration', true ), 'An unreadable file writes nothing.' );
	}

	/**
	 * A file that cannot be read gives 0 and changes nothing.
	 */
	public function test_unreadable_file_records_nothing(): void {
		$id = self::factory()->post->create( array( 'post_type' => 'mediashield_video' ) );
		update_post_meta( $id, '_ms_duration', 42 );
		$this->assertSame( 0, SelfHosted::record_length( $id, '/nope/clip.mp4' ) );
		$this->assertSame( '42', get_post_meta( $id, '_ms_duration', true ) );
	}
}
