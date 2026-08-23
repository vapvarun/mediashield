<?php
/**
 * Self-hosted upload driver.
 *
 * Uploads videos to wp-content/uploads/mediashield/ with .htaccess protection.
 * Creates a mediashield_video CPT post on successful upload.
 *
 * @package MediaShield\Upload\Drivers
 */

namespace MediaShield\Upload\Drivers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SelfHosted
 *
 * Self-hosted upload driver for local video storage.
 *
 * @since 1.0.0
 */
class SelfHosted implements DriverInterface {

	/**
	 * Absolute path to the upload directory.
	 *
	 * @var string
	 */
	private string $upload_dir;

	/**
	 * URL to the upload directory.
	 *
	 * @var string
	 */
	private string $upload_url;

	/**
	 * Allowed MIME types.
	 *
	 * @var array
	 */
	private const ALLOWED_MIMES = array(
		'mp4'  => 'video/mp4',
		'webm' => 'video/webm',
		'mov'  => 'video/quicktime',
		'm4v'  => 'video/x-m4v',
	);

	/**
	 * Constructor — sets up upload directory paths.
	 */
	public function __construct() {
		$wp_upload        = wp_upload_dir();
		$this->upload_dir = trailingslashit( $wp_upload['basedir'] ) . 'mediashield/';
		$this->upload_url = trailingslashit( $wp_upload['baseurl'] ) . 'mediashield/';

		$this->ensure_directory();
	}

	/**
	 * Upload a video file to the local uploads directory.
	 *
	 * @param string $file_path Absolute path to the file.
	 * @param array  $options   Driver-specific options.
	 * @return array Upload result.
	 */
	public function upload( string $file_path, array $options = array() ): array {
		if ( ! file_exists( $file_path ) ) {
			return self::error( __( 'File not found.', 'mediashield' ) );
		}

		// Validate against the name the browser sent, not the path on disk.
		// PHP moves an upload to a temp file with no extension, so typing it
		// from $file_path asks WordPress to identify "/tmp/phpAbC123" - which it
		// cannot, so every real multipart upload was rejected as an invalid file
		// type no matter what it actually was. It is why /upload/init had no
		// admin caller worth adding: the route did not work from a browser.
		$original_name = isset( $options['original_name'] ) && '' !== $options['original_name']
			? (string) $options['original_name']
			: basename( $file_path );

		$validation = $this->validate_file( $file_path, $original_name );
		if ( ! $validation['valid'] ) {
			return self::error( $validation['error'] );
		}

		// No size check here. By the time a file reaches this driver it is already
		// on disk: PHP enforced upload_max_filesize/post_max_size before any of our
		// code ran, and UploadController reports that rejection. A second limit of
		// our own could only ever be lower than the server's, so it would restrict
		// the owner without protecting anything.

		// Name the stored file after what was uploaded, for the same reason:
		// basename() of the temp path yields "phpAbC123" with no extension, so
		// stored files lost their type and the streaming endpoint could not work
		// out a MIME type to serve them with.
		$filename = wp_unique_filename( $this->upload_dir, sanitize_file_name( $original_name ) );
		$dest     = $this->upload_dir . $filename;

		// Move file.
		if ( ! copy( $file_path, $dest ) ) {
			return self::error( __( 'Failed to move uploaded file.', 'mediashield' ) );
		}

		// Use REST streaming endpoint instead of direct file URL (blocked by .htaccess).
		// Build the post ID first via a placeholder is not possible, so derive the
		// stream URL after insert; but pass the platform meta via `meta_input` so
		// it is present BEFORE `save_post` fires (Thumbnail::maybe_fetch_thumbnail()
		// runs at save_post priority 20 and reads `_ms_platform`). For self-hosted
		// the thumbnail fetcher early-returns on the 'self' platform, so this is
		// belt-and-suspenders here — but it keeps every driver's insert shape
		// identical and future-proof. See Basecamp card 9915014554.
		$title = $options['title'] ?? pathinfo( $filename, PATHINFO_FILENAME );

		// `attach_to` names an EXISTING video to fill in, rather than creating a
		// new one. The admin uploader needs this: "Add New Video" is already
		// editing a post, so a driver that always inserts would leave the owner
		// with two records per upload - the one they were editing and the one
		// the upload made - and no indication which the shortcode should use.
		//
		// The caller has already confirmed the target exists, is the right post
		// type, and that this user may edit it; this only writes.
		$attach_to = isset( $options['attach_to'] ) ? (int) $options['attach_to'] : 0;

		if ( $attach_to > 0 ) {
			$post_id = $attach_to;

			update_post_meta( $post_id, '_ms_platform', 'self' );
			update_post_meta( $post_id, '_ms_platform_video_id', $filename );

			// Protection level is only seeded when the post has none, so an
			// operator who already chose one for this video keeps it.
			if ( '' === (string) get_post_meta( $post_id, '_ms_protection_level', true ) ) {
				update_post_meta( $post_id, '_ms_protection_level', 'standard' );
			}
		} else {
			$post_id = wp_insert_post(
				array(
					'post_type'   => 'mediashield_video',
					'post_title'  => sanitize_text_field( $title ),
					'post_status' => 'publish',
					'meta_input'  => array(
						'_ms_platform'          => 'self',
						'_ms_platform_video_id' => $filename,
						'_ms_protection_level'  => 'standard',
					),
				)
			);

			if ( is_wp_error( $post_id ) ) {
				wp_delete_file( $dest );
				return self::error( $post_id->get_error_message() );
			}
		}

		// The streaming URL depends on the new post ID, so it must be set after
		// insert. It is not consumed by the thumbnail fetcher, so the timing is fine.
		$stream_url = rest_url( "mediashield/v1/stream/{$post_id}" );
		update_post_meta( $post_id, '_ms_source_url', $stream_url );

		return array(
			'success'           => true,
			'video_id'          => $post_id,
			'platform_video_id' => $filename,
			'embed_url'         => $stream_url,
			'error'             => '',
		);
	}

	/**
	 * Get the status of an upload.
	 *
	 * @param string $upload_id Upload identifier.
	 * @return array Upload status.
	 */
	public function get_status( string $upload_id ): array {
		// Self-hosted uploads are synchronous — always complete or failed.
		$file = $this->upload_dir . $upload_id;

		if ( file_exists( $file ) ) {
			return array(
				'status'   => 'complete',
				'progress' => 100,
				'error'    => '',
			);
		}

		return array(
			'status'   => 'not_found',
			'progress' => 0,
			'error'    => __( 'File not found.', 'mediashield' ),
		);
	}

	/**
	 * Delete a video file.
	 *
	 * @param string $platform_video_id Platform-specific video identifier.
	 * @return bool Success.
	 */
	public function delete( string $platform_video_id ): bool {
		$file = $this->upload_dir . sanitize_file_name( $platform_video_id );

		if ( file_exists( $file ) ) {
			return wp_delete_file( $file ) !== false;
		}

		return false;
	}

	/**
	 * Get the embed URL for a video.
	 *
	 * Looks up the video CPT post by filename to build the REST streaming URL.
	 * Falls back to direct URL if no post is found (should not happen in practice).
	 *
	 * @param string $platform_video_id Platform-specific video identifier (filename).
	 * @return string Embed URL.
	 */
	public function get_embed_url( string $platform_video_id ): string {
		// Find the video post by platform_video_id meta to build the streaming URL.
		$posts = get_posts(
			array(
				'post_type'   => 'mediashield_video',
				'meta_key'    => '_ms_platform_video_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'  => sanitize_file_name( $platform_video_id ), // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'fields'      => 'ids',
				'numberposts' => 1,
			)
		);

		if ( ! empty( $posts ) ) {
			return rest_url( "mediashield/v1/stream/{$posts[0]}" );
		}

		// Fallback to direct URL (file is .htaccess-protected, but this is a safety net).
		return $this->upload_url . sanitize_file_name( $platform_video_id );
	}

	/**
	 * Get the driver identifier.
	 *
	 * @return string Driver name.
	 */
	public function get_name(): string {
		return 'self_hosted';
	}

	/**
	 * Validate a file's MIME type using wp_check_filetype_and_ext.
	 *
	 * @param string $file_path Path to file.
	 * @return array{valid: bool, error: string}
	 */
	private function validate_file( string $file_path, string $original_name = '' ): array {
		// The name is what carries the extension; the path is a temp file. Both
		// are passed so WordPress can cross-check the claimed extension against
		// the real content and reject a .mp4 that is not one.
		$name  = '' !== $original_name ? $original_name : basename( $file_path );
		$check = wp_check_filetype_and_ext( $file_path, $name, self::ALLOWED_MIMES );

		if ( empty( $check['type'] ) || ! in_array( $check['type'], self::ALLOWED_MIMES, true ) ) {
			return array(
				'valid' => false,
				'error' => sprintf(
					/* translators: %s: allowed formats */
					__( 'Invalid file type. Allowed formats: %s.', 'mediashield' ),
					implode( ', ', array_keys( self::ALLOWED_MIMES ) )
				),
			);
		}

		return array(
			'valid' => true,
			'error' => '',
		);
	}

	/**
	 * Ensure the upload directory exists with .htaccess protection.
	 */
	private function ensure_directory(): void {
		if ( ! file_exists( $this->upload_dir ) ) {
			wp_mkdir_p( $this->upload_dir );
		}

		$htaccess = $this->upload_dir . '.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			// Deny all direct access — files served via PHP only.
			$rules = "<FilesMatch \".*\">\n  Require all denied\n</FilesMatch>\n";
			file_put_contents( $htaccess, $rules ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}

		// Also add index.php for directory listing prevention.
		$index = $this->upload_dir . 'index.php';
		if ( ! file_exists( $index ) ) {
			file_put_contents( $index, '<?php // Silence is golden.' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		}
	}

	/**
	 * Build an error result.
	 *
	 * @param string $message Error message.
	 * @return array Error result array.
	 */
	private static function error( string $message ): array {
		return array(
			'success'           => false,
			'video_id'          => 0,
			'platform_video_id' => '',
			'embed_url'         => '',
			'error'             => $message,
		);
	}
}
