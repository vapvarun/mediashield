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
		$this->uploaded();
		delete_option( SelfHosted::LENGTHS_PENDING );
		update_option( 'ms_db_version', 14 );

		\MediaShield\Core\Migrator::run();

		$this->assertSame( '0', get_option( SelfHosted::LENGTHS_PENDING ), 'Queued from the start.' );
		$this->assertSame( array(), $this->read, 'No file was read in the upgrade request.' );
		$this->assertSame( MEDIASHIELD_DB_VERSION, (int) get_option( 'ms_db_version' ) );
	}

	/**
	 * Each run reads at most 50 files above the cursor, and the last run
	 * removes the pending flag.
	 */
	public function test_job_walks_in_batches_and_finishes(): void {
		$ids = array();
		for ( $i = 0; $i < SelfHosted::LENGTHS_BATCH + 3; $i++ ) {
			$ids[] = $this->uploaded();
		}
		// Just below this test's videos: the upgrade test's CREATE TABLE commits, so its rows outlive it.
		update_option( SelfHosted::LENGTHS_PENDING, (string) ( $ids[0] - 1 ) );

		SelfHosted::backfill_lengths();
		$this->assertSame( (string) $ids[ SelfHosted::LENGTHS_BATCH - 1 ], get_option( SelfHosted::LENGTHS_PENDING ), 'The cursor moves to the last file read.' );
		$this->assertCount( SelfHosted::LENGTHS_BATCH, $this->read );

		SelfHosted::backfill_lengths();
		$this->assertCount( SelfHosted::LENGTHS_BATCH + 3, $this->read, 'Each file read once.' );
		SelfHosted::backfill_lengths();
		$this->assertFalse( get_option( SelfHosted::LENGTHS_PENDING ), 'Done: nothing left above the cursor.' );
	}

	/**
	 * Ponytail re-review #5: the cursor was saved only after the batch, so
	 * a file that stops PHP stopped the job on every run. Fails before: the
	 * next run read the same file again and never reached the next one.
	 */
	public function test_a_file_that_stops_the_run_is_skipped_next_time(): void {
		$bad  = $this->uploaded();
		$good = $this->uploaded();
		update_option( SelfHosted::LENGTHS_PENDING, (string) ( $bad - 1 ) );
		$this->crash_on = $bad;

		try {
			SelfHosted::backfill_lengths();
			$this->fail( 'The run should have stopped on the bad file.' );
		} catch ( \RuntimeException $e ) {
			$this->assertSame( 'stopped', $e->getMessage() );
		}

		$this->read = array();
		SelfHosted::backfill_lengths();
		$this->assertNotContains( $bad, $this->read, 'The file that stopped the run is not read again.' );
		$this->assertContains( $good, $this->read, 'The files after it are read.' );
	}

	/**
	 * Videos whose files were read (by the wp_read_video_metadata filter).
	 *
	 * @var int[]
	 */
	private $read = array();

	/**
	 * Video whose read throws, as a file that stops PHP would.
	 *
	 * @var int
	 */
	private $crash_on = 0;

	public function set_up(): void {
		parent::set_up();
		$this->read     = array();
		$this->crash_on = 0;
		add_filter(
			'wp_read_video_metadata',
			function ( $meta, $file ) {
				$id = preg_match( '/len(\d+)\./', basename( (string) $file ), $m ) ? (int) $m[1] : 0;
				if ( $id > 0 && $id === $this->crash_on ) {
					throw new \RuntimeException( 'stopped' );
				}
				$this->read[] = $id;
				return $meta;
			},
			10,
			2
		);
	}

	/**
	 * An uploaded video with a readable (not real) file and no length.
	 */
	private function uploaded(): int {
		$id  = self::factory()->post->create( array( 'post_type' => 'mediashield_video' ) );
		$dir = trailingslashit( wp_upload_dir()['basedir'] ) . 'mediashield/';
		wp_mkdir_p( $dir );
		$name = 'len' . $id . '.mp4';
		file_put_contents( $dir . $name, 'not a video' ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		update_post_meta( $id, '_ms_owned_file', $name );
		update_post_meta( $id, '_ms_platform_video_id', $name );
		return $id;
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
