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
		// Classic editor / programmatic save: meta is written by save_meta_box at
		// priority 10, so reading platform meta at priority 20 sees the freshly
		// written values.
		add_action( 'save_post_mediashield_video', array( __CLASS__, 'maybe_fetch_thumbnail' ), 20, 2 );
		// REST create path (card #9924901405): WP_REST_Posts_Controller calls
		// wp_insert_post() WITHOUT the meta_input arg, so save_post fires while
		// _ms_platform / _ms_platform_video_id are still empty and the thumbnail
		// fetch above no-ops. The REST controller then writes meta and fires
		// rest_after_insert_{$post_type}, at which point we can safely re-run the
		// thumbnail logic (idempotent — guarded by has_post_thumbnail).
		add_action( 'rest_after_insert_mediashield_video', array( __CLASS__, 'maybe_fetch_thumbnail_rest' ), 10, 1 );
	}

	/**
	 * REST-side bridge into maybe_fetch_thumbnail().
	 *
	 * The rest_after_insert_* action passes the WP_Post directly (no separate
	 * $post_id arg), so we wrap the signature here rather than overloading the
	 * save_post handler.
	 *
	 * @param \WP_Post $post Post object inserted/updated via REST.
	 */
	public static function maybe_fetch_thumbnail_rest( \WP_Post $post ): void {
		self::maybe_fetch_thumbnail( (int) $post->ID, $post );
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

		$attachment_id = self::sideload_thumbnail( $thumbnail_url, $post_id, (string) $platform, (string) $video_id, $post->post_title );

		if ( ! is_wp_error( $attachment_id ) && $attachment_id > 0 ) {
			set_post_thumbnail( $post_id, $attachment_id );
		}
	}

	/**
	 * Download a remote image and attach it to the post.
	 *
	 * Wraps download_url() + wp_handle_sideload() instead of
	 * media_sideload_image() because the WP core helper infers the filename
	 * from the URL via a regex that requires `.jpg`/`.png`/etc. BEFORE any
	 * query string. Vimeo and Wistia serve thumbnails from CDN URLs that have
	 * no extension at all (e.g. `i.vimeocdn.com/video/…_d_640?region=us`), so
	 * media_sideload_image() rejects them with "Invalid image URL.". We build
	 * an explicit filename from the platform + video ID + content-type-derived
	 * extension instead, so every supported platform's thumbnail can be
	 * sideloaded regardless of URL shape.
	 *
	 * @param string $url       Remote image URL.
	 * @param int    $post_id   Parent post.
	 * @param string $platform  Platform slug (youtube/vimeo/wistia/bunny).
	 * @param string $video_id  Platform video ID — used in the filename.
	 * @param string $title     Post title — used for the attachment title.
	 * @return int|\WP_Error    Attachment ID on success, WP_Error on failure.
	 */
	private static function sideload_thumbnail( string $url, int $post_id, string $platform, string $video_id, string $title ) {
		// Require the sideload helpers (these aren't loaded on frontend / REST).
		if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( ! function_exists( 'wp_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
		}
		if ( ! function_exists( 'wp_read_image_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$tmp = download_url( $url, 30 );
		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}

		// Read the downloaded file's MIME via WP's image checker; this also
		// confirms the payload is a real image, not (say) an HTML error page.
		$probe = wp_check_filetype_and_ext( $tmp, basename( $tmp ) );
		$mime  = $probe['type'] ?? '';
		if ( '' === $mime ) {
			// Fall back to finfo for URLs that don't pass WP's name-based hints.
			if ( function_exists( 'mime_content_type' ) ) {
				$mime = (string) @mime_content_type( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			}
		}
		if ( '' === $mime || 0 !== strpos( $mime, 'image/' ) ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink
			return new \WP_Error( 'mediashield_thumbnail_not_image', sprintf( 'Downloaded payload is not an image (mime=%s).', $mime ) );
		}

		$ext_map = array(
			'image/jpeg' => 'jpg',
			'image/jpg'  => 'jpg',
			'image/png'  => 'png',
			'image/gif'  => 'gif',
			'image/webp' => 'webp',
		);
		$ext = $ext_map[ $mime ] ?? 'jpg';

		$filename = sprintf( 'mediashield-%s-%s.%s', sanitize_key( $platform ), sanitize_file_name( $video_id ), $ext );

		$file_array = array(
			'name'     => $filename,
			'tmp_name' => $tmp,
		);

		// wp_handle_sideload moves the file into uploads/ and returns its
		// final URL + path; test_form=false skips the form-data check that
		// would otherwise reject a programmatic upload.
		$sideloaded = wp_handle_sideload(
			$file_array,
			array(
				'test_form' => false,
				'mimes'     => array(
					'jpg|jpeg' => 'image/jpeg',
					'png'      => 'image/png',
					'gif'      => 'image/gif',
					'webp'     => 'image/webp',
				),
			)
		);

		if ( isset( $sideloaded['error'] ) ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink
			return new \WP_Error( 'mediashield_thumbnail_sideload_failed', (string) $sideloaded['error'] );
		}

		$attachment    = array(
			'post_mime_type' => $sideloaded['type'],
			'post_title'     => $title !== '' ? $title : $filename,
			'post_content'   => '',
			'post_status'    => 'inherit',
		);
		$attachment_id = wp_insert_attachment( $attachment, $sideloaded['file'], $post_id, true );
		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		// Generate metadata + intermediate sizes so the thumbnail renders at
		// the size the theme requests (e.g. post grids ask for medium).
		$metadata = wp_generate_attachment_metadata( $attachment_id, $sideloaded['file'] );
		wp_update_attachment_metadata( $attachment_id, $metadata );

		return (int) $attachment_id;
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
				$response = wp_remote_get(
					"https://vimeo.com/api/oembed.json?url=https://vimeo.com/{$video_id}",
					array( 'timeout' => 15 )
				);
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
				$response = wp_remote_get(
					"https://fast.wistia.com/oembed?url=https://home.wistia.com/medias/{$video_id}",
					array( 'timeout' => 15 )
				);
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
