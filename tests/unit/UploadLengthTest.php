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
		update_option( SelfHosted::LENGTHS_PENDING, '0' ); // Labelled change: the job runs only while pending.

		SelfHosted::backfill_lengths();

		$this->assertSame( '95', get_post_meta( $typed, '_ms_duration', true ), 'A typed length is kept.' );
		// Labelled change: registered meta reads a missing length as 0, so ask whether a row was written.
		$this->assertFalse( metadata_exists( 'post', $empty, '_ms_duration' ), 'An unreadable file writes nothing.' );
	}

	/**
	 * Ponytail #1: the v15 step read every file inside the upgrade request.
	 * Now it only queues the job: no file is read and the version is stored.
	 * Fails before: the length was written during Migrator::run().
	 */
	public function test_upgrade_queues_the_backfill_without_reading_files(): void {
		$id = self::factory()->post->create( array( 'post_type' => 'mediashield_video' ) );
		update_post_meta( $id, '_ms_owned_file', 'missing-' . $id . '.mp4' );
		delete_option( SelfHosted::LENGTHS_PENDING );
		update_option( 'ms_db_version', 14 );

		\MediaShield\Core\Migrator::run();

		$this->assertSame( '0', get_option( SelfHosted::LENGTHS_PENDING ), 'Queued from the start.' );
		$this->assertSame( '', get_post_meta( $id, SelfHosted::LENGTH_UNREADABLE, true ), 'No file was read in the upgrade request.' );
		$this->assertSame( MEDIASHIELD_DB_VERSION, (int) get_option( 'ms_db_version' ) );
	}

	/**
	 * Each run reads at most 50 files above the cursor, marks unreadable ones,
	 * and the last run removes the pending flag.
	 */
	public function test_job_walks_in_batches_and_finishes(): void {
		$ids = self::factory()->post->create_many( SelfHosted::LENGTHS_BATCH + 3, array( 'post_type' => 'mediashield_video' ) );
		foreach ( $ids as $id ) {
			update_post_meta( $id, '_ms_owned_file', 'missing-' . $id . '.mp4' );
		}
		// Just below this test's videos: the upgrade test's CREATE TABLE commits, so its rows outlive it.
		update_option( SelfHosted::LENGTHS_PENDING, (string) ( $ids[0] - 1 ) );

		SelfHosted::backfill_lengths();
		$this->assertSame( (string) $ids[ SelfHosted::LENGTHS_BATCH - 1 ], get_option( SelfHosted::LENGTHS_PENDING ), 'The cursor moves to the last file read.' );
		$this->assertSame( '1', get_post_meta( $ids[0], SelfHosted::LENGTH_UNREADABLE, true ), 'An unreadable file is recorded.' );
		$this->assertSame( '', get_post_meta( end( $ids ), SelfHosted::LENGTH_UNREADABLE, true ), 'Not reached yet.' );

		SelfHosted::backfill_lengths();
		$this->assertSame( '1', get_post_meta( end( $ids ), SelfHosted::LENGTH_UNREADABLE, true ) );
		SelfHosted::backfill_lengths();
		$this->assertFalse( get_option( SelfHosted::LENGTHS_PENDING ), 'Done: nothing left above the cursor.' );
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
