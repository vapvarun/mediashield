<?php
/**
 * Auto-fetch video thumbnails from platform APIs.
 *
 * @package MediaShield\CPT
 */

namespace MediaShield\CPT;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Thumbnail
 *
 * Auto-fetches video thumbnails from platform APIs on save.
 *
 * @since 1.0.0
 */
class Thumbnail {

	/**
	 * Register hooks.
	 */
	public static function register(): void {
		add_action( 'save_post_mediashield_video', array( __CLASS__, 'maybe_fetch_thumbnail' ), 20, 2 );
	}

	/**
	 * Fetch and set thumbnail on video save if none is set.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public static function maybe_fetch_thumbnail( int $post_id, \WP_Post $post ): void {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// Skip if featured image is already set.
		if ( has_post_thumbnail( $post_id ) ) {
			return;
		}

		$platform = get_post_meta( $post_id, '_ms_platform', true );
		$video_id = get_post_meta( $post_id, '_ms_platform_video_id', true );

		if ( empty( $video_id ) || 'self' === $platform ) {
			return;
		}

		$thumbnail_url = self::get_thumbnail_url( $platform, $video_id );

		if ( empty( $thumbnail_url ) ) {
			return;
		}

		// Require media functions for sideloading.
		if ( ! function_exists( 'media_sideload_image' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$attachment_id = media_sideload_image( $thumbnail_url, $post_id, $post->post_title, 'id' );

		if ( ! is_wp_error( $attachment_id ) ) {
			set_post_thumbnail( $post_id, $attachment_id );
		}
	}

	/**
	 * Get thumbnail URL for a platform video.
	 *
	 * @param string $platform Platform identifier.
	 * @param string $video_id Platform video ID.
	 * @return string Thumbnail URL or empty string.
	 */
	private static function get_thumbnail_url( string $platform, string $video_id ): string {
		switch ( $platform ) {
			case 'youtube':
				return "https://img.youtube.com/vi/{$video_id}/maxresdefault.jpg";

			case 'vimeo':
				$response = wp_remote_get( "https://vimeo.com/api/oembed.json?url=https://vimeo.com/{$video_id}" );
				if ( ! is_wp_error( $response ) ) {
					$data = json_decode( wp_remote_retrieve_body( $response ), true );
					return $data['thumbnail_url'] ?? '';
				}
				return '';

			case 'bunny':
				// Delegate to pro BunnyStream driver if available.
				if ( class_exists( '\MediaShieldPro\Upload\Drivers\BunnyStream' ) ) {
					$driver = new \MediaShieldPro\Upload\Drivers\BunnyStream();
					return $driver->get_thumbnail_url( $video_id );
				}
				return '';

			case 'wistia':
				$response = wp_remote_get( "https://fast.wistia.com/oembed?url=https://home.wistia.com/medias/{$video_id}" );
				if ( ! is_wp_error( $response ) ) {
					$data = json_decode( wp_remote_retrieve_body( $response ), true );
					return $data['thumbnail_url'] ?? '';
				}
				return '';

			default:
				return '';
		}
	}
}
