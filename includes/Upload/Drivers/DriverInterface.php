<?php
/**
 * Upload driver interface.
 *
 * All upload drivers (self-hosted, Bunny, Vimeo, YouTube, Wistia)
 * must implement this interface.
 *
 * @package MediaShield\Upload\Drivers
 */

namespace MediaShield\Upload\Drivers;

interface DriverInterface {

	/**
	 * Upload a video file.
	 *
	 * @param string $file_path Absolute path to the file.
	 * @param array  $options   Driver-specific options.
	 * @return array{success: bool, video_id: int, platform_video_id: string, embed_url: string, error: string}
	 */
	public function upload( string $file_path, array $options = array() ): array;

	/**
	 * Get the status of an upload.
	 *
	 * @param string $upload_id Upload identifier.
	 * @return array{status: string, progress: int, error: string}
	 */
	public function get_status( string $upload_id ): array;

	/**
	 * Delete a video.
	 *
	 * @param string $platform_video_id Platform-specific video identifier.
	 * @return bool Success.
	 */
	public function delete( string $platform_video_id ): bool;

	/**
	 * Get the embed URL for a video.
	 *
	 * @param string $platform_video_id Platform-specific video identifier.
	 * @return string Embed URL.
	 */
	public function get_embed_url( string $platform_video_id ): string;

	/**
	 * Get the driver identifier.
	 *
	 * @return string Driver name (e.g. 'self_hosted', 'bunny', 'vimeo').
	 */
	public function get_name(): string;
}
