<?php
/**
 * Upload manager — driver factory and orchestration.
 *
 * Routes uploads to the correct driver. Default: SelfHosted.
 * Pro adds platform drivers via mediashield_upload_drivers filter.
 *
 * @package MediaShield\Upload
 */

namespace MediaShield\Upload;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use MediaShield\Upload\Drivers\DriverInterface;
use MediaShield\Upload\Drivers\SelfHosted;

/**
 * Class UploadManager
 *
 * Upload manager — driver factory and orchestration.
 *
 * @since 1.0.0
 */
class UploadManager {

	/**
	 * Get all registered upload drivers.
	 *
	 * @return array<string, class-string<DriverInterface>>
	 */
	public static function get_drivers(): array {
		$drivers = array(
			'self_hosted' => SelfHosted::class,
		);

		/**
		 * Filter the available upload drivers.
		 *
		 * Pro registers Bunny, Vimeo, YouTube, Wistia drivers via this filter.
		 *
		 * @since 1.0.0
		 *
		 * @param array $drivers Map of driver_name => class_name.
		 */
		return apply_filters( 'mediashield_upload_drivers', $drivers );
	}

	/**
	 * Get a driver instance by name.
	 *
	 * @param string $name Driver name (e.g. 'self_hosted', 'bunny').
	 * @return DriverInterface|null Driver instance or null if not found.
	 */
	public static function get_driver( string $name = 'self_hosted' ): ?DriverInterface {
		$drivers = self::get_drivers();

		if ( ! isset( $drivers[ $name ] ) ) {
			return null;
		}

		$class = $drivers[ $name ];

		if ( ! class_exists( $class ) ) {
			return null;
		}

		$instance = new $class();

		if ( ! $instance instanceof DriverInterface ) {
			return null;
		}

		return $instance;
	}

	/**
	 * Upload a file using the specified driver.
	 *
	 * @param string $file_path Absolute path to the file.
	 * @param string $driver    Driver name (default: 'self_hosted').
	 * @param array  $options   Driver-specific options.
	 * @return array Upload result.
	 */
	public static function upload( string $file_path, string $driver = 'self_hosted', array $options = array() ): array {
		$instance = self::get_driver( $driver );

		if ( ! $instance ) {
			return array(
				'success' => false,
				'error'   => sprintf(
					/* translators: %s: driver name */
					__( 'Upload driver "%s" not available.', 'mediashield' ),
					$driver
				),
			);
		}

		/**
		 * Fires just before an upload begins, for any driver.
		 *
		 * Lets add-ons record the attempt (e.g. Pro's upload queue) so the full
		 * upload lifecycle is tracked from start to finish.
		 *
		 * @since 1.0.1
		 *
		 * @param string $driver    Driver name (e.g. 'self_hosted', 'bunny').
		 * @param string $file_path Absolute path to the source file.
		 * @param array  $options   Driver-specific options.
		 */
		do_action( 'mediashield_upload_started', $driver, $file_path, $options );

		$result = $instance->upload( $file_path, $options );

		if ( empty( $result['success'] ) ) {
			/**
			 * Fires when an upload fails.
			 *
			 * @since 1.0.1
			 *
			 * @param string $driver  Driver name.
			 * @param string $error   Human-readable error message.
			 * @param array  $options Driver-specific options.
			 */
			do_action( 'mediashield_upload_failed', $driver, isset( $result['error'] ) ? (string) $result['error'] : '', $options );
		} else {
			/**
			 * Fires after a video upload completes successfully (any driver/caller).
			 *
			 * @since 1.0.0
			 *
			 * @param int    $video_id Created video CPT post ID.
			 * @param string $driver   Upload driver used.
			 * @param array  $result   Full upload result.
			 */
			do_action( 'mediashield_upload_complete', isset( $result['video_id'] ) ? (int) $result['video_id'] : 0, $driver, $result );
		}

		return $result;
	}
}
