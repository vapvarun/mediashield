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
	/**
	 * Decide which driver an upload goes to when the request did not name one.
	 *
	 * Settings > Default Upload Target used to be read by nothing. The dropdown
	 * saved, and every upload went to whatever driver the request happened to
	 * name - which for the admin uploader is always self-hosted. So an owner who
	 * connected Bunny and set this to Bunny kept filling their own disk, and the
	 * only signal was that the videos were not appearing in their library.
	 *
	 * Values match the dropdown: 'self' pins uploads to this server, a platform
	 * slug pins them to that platform, and 'auto' - the shipped default - means
	 * "the first connected cloud platform", which only Pro can answer because
	 * only Pro stores connections. Free asks through a filter and falls back to
	 * self-hosted when nothing answers.
	 *
	 * A platform the owner named explicitly is honoured even if its connection
	 * has since been removed, so the upload fails with that platform's own
	 * credentials error. Quietly redirecting it to local disk instead would put
	 * the file somewhere they did not choose and say nothing about it - the
	 * failure is the useful outcome here. Only an unrecognised value falls back,
	 * which means a driver that no longer exists at all.
	 *
	 * @return string Driver name, always one that exists.
	 */
	public static function resolve_default_driver(): string {
		$target = (string) get_option( 'ms_default_upload_target', 'auto' );

		if ( 'auto' === $target ) {
			/**
			 * Filter the driver used when the upload target is "auto".
			 *
			 * Pro answers with its first connected platform.
			 *
			 * @since 1.3.0
			 *
			 * @param string $driver Driver name. Default 'self_hosted'.
			 */
			$target = (string) apply_filters( 'mediashield_default_upload_driver', 'self_hosted' );
		} elseif ( 'self' === $target ) {
			$target = 'self_hosted';
		}

		$drivers = self::get_drivers();

		return isset( $drivers[ $target ] ) ? $target : 'self_hosted';
	}

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
