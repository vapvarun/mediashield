<?php
/**
 * Deleting a MediaShield video must never delete the master from a platform.
 *
 * This is the rule the plugin exists to keep: removing a video from MediaShield
 * removes this site's record of it, so the same video can be linked back later
 * by importing it again. It is pinned in a test because breaking it is silent,
 * immediate and irreversible - none of these services offer a trash or an undo,
 * and it once destroyed a 5.8 GB master on a customer's live library.
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\Cron\Cleanup;
use MediaShield\Upload\Drivers\DriverInterface;
use WP_UnitTestCase;

/**
 * Remote-delete safety.
 */
class RemoteMediaSafetyTest extends WP_UnitTestCase {

	/**
	 * Set when a driver's delete() is reached.
	 *
	 * @var string[]
	 */
	public static array $deleted = array();

	/**
	 * Register a spy driver on a fake platform.
	 */
	public function set_up(): void {
		parent::set_up();

		self::$deleted = array();

		add_filter(
			'mediashield_upload_drivers',
			static function ( array $drivers ): array {
				$drivers['spyplatform'] = SpyRemoteDriver::class;
				return $drivers;
			}
		);
	}

	/**
	 * Deleting a platform video leaves the platform alone.
	 */
	public function test_deleting_a_platform_video_never_calls_the_driver(): void {
		$video_id = self::factory()->post->create(
			array(
				'post_type'   => 'mediashield_video',
				'post_status' => 'publish',
			)
		);

		update_post_meta( $video_id, '_ms_platform', 'spyplatform' );
		update_post_meta( $video_id, '_ms_platform_video_id', 'remote-guid-123' );

		Cleanup::handle_video_delete( $video_id );

		$this->assertSame(
			array(),
			self::$deleted,
			'Removing a video from MediaShield must not delete it from the platform.'
		);
	}

	/**
	 * The rule holds for every platform slug, not just the one under test.
	 */
	public function test_no_platform_slug_reaches_a_remote_delete(): void {
		foreach ( array( 'bunny', 'vimeo', 'youtube', 'wistia', 'spyplatform' ) as $platform ) {
			$video_id = self::factory()->post->create(
				array(
					'post_type'   => 'mediashield_video',
					'post_status' => 'publish',
				)
			);

			update_post_meta( $video_id, '_ms_platform', $platform );
			update_post_meta( $video_id, '_ms_platform_video_id', 'guid-' . $platform );

			Cleanup::handle_video_delete( $video_id );
		}

		$this->assertSame( array(), self::$deleted );
	}

	/**
	 * Self-hosted is the deliberate exception: that file is in this site's own
	 * uploads folder, put there by this plugin, and nothing references it once
	 * the video is gone.
	 */
	public function test_self_hosted_files_are_still_removed(): void {
		$upload = wp_upload_dir();
		$dir    = trailingslashit( $upload['basedir'] ) . 'mediashield/';

		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
		}

		$filename = 'ms-test-' . wp_generate_password( 8, false ) . '.mp4';
		file_put_contents( $dir . $filename, 'x' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Test fixture.

		$video_id = self::factory()->post->create(
			array(
				'post_type'   => 'mediashield_video',
				'post_status' => 'publish',
			)
		);

		update_post_meta( $video_id, '_ms_platform', 'self' );
		update_post_meta( $video_id, '_ms_platform_video_id', $filename );

		$this->assertFileExists( $dir . $filename );

		Cleanup::handle_video_delete( $video_id );

		$this->assertFileDoesNotExist( $dir . $filename );
	}
}

/**
 * A driver that records the fact it was asked to delete, instead of deleting.
 */
class SpyRemoteDriver implements DriverInterface {

	/**
	 * {@inheritDoc}
	 *
	 * @param string       $file_path File path.
	 * @param array<mixed> $options   Options.
	 * @return array<mixed>
	 */
	public function upload( string $file_path, array $options = array() ): array {
		return array( 'success' => false );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $upload_id Upload id.
	 * @return array<mixed>
	 */
	public function get_status( string $upload_id ): array {
		return array(
			'status'   => 'complete',
			'progress' => 100,
			'error'    => '',
		);
	}

	/**
	 * Records the attempt so the test can fail on it.
	 *
	 * @param string $platform_video_id Video id.
	 * @return bool
	 */
	public function delete( string $platform_video_id ): bool {
		RemoteMediaSafetyTest::$deleted[] = $platform_video_id;
		return true;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $platform_video_id Video id.
	 * @return string
	 */
	public function get_embed_url( string $platform_video_id ): string {
		return '';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'spyplatform';
	}
}
