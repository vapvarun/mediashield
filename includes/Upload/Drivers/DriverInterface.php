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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
	 * Delete a video's underlying file, where this plugin owns that file.
	 *
	 * MEDIASHIELD NEVER DELETES MEDIA FROM A HOSTING PLATFORM.
	 *
	 * Removing a video from MediaShield removes this site's record of it. A
	 * master held on Bunny, Vimeo, YouTube or Wistia is left untouched, so the
	 * same video can be linked back at any time by importing it again. Every
	 * remote driver therefore implements this as an explicit refusal that
	 * returns false without making a request.
	 *
	 * The self-hosted driver is the one meaningful implementation: that file
	 * lives in this site's own uploads folder, was put there by this plugin,
	 * and nothing references it once the video is gone.
	 *
	 * Do not re-add remote deletion here. It once destroyed a 5.8 GB master on
	 * a customer's live library, and these services have no trash and no undo.
	 * Deleting a platform video belongs in that platform's own dashboard, where
	 * the person doing it can see what else uses the asset.
	 *
	 * @param string $platform_video_id Platform-specific video identifier.
	 * @return bool True when a file this plugin owns was removed; false otherwise.
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
