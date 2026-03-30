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

class SelfHosted implements DriverInterface {

	/** @var string Absolute path to the upload directory. */
	private string $upload_dir;

	/** @var string URL to the upload directory. */
	private string $upload_url;

	/** @var array Allowed MIME types. */
	private const ALLOWED_MIMES = array(
		'mp4'  => 'video/mp4',
		'webm' => 'video/webm',
		'mov'  => 'video/quicktime',
		'm4v'  => 'video/x-m4v',
	);

	public function __construct() {
		$wp_upload = wp_upload_dir();
		$this->upload_dir = trailingslashit( $wp_upload['basedir'] ) . 'mediashield/';
		$this->upload_url = trailingslashit( $wp_upload['baseurl'] ) . 'mediashield/';

		$this->ensure_directory();
	}

	/**
	 * {@inheritDoc}
	 */
	public function upload( string $file_path, array $options = array() ): array {
		if ( ! file_exists( $file_path ) ) {
			return self::error( __( 'File not found.', 'mediashield' ) );
		}

		// Validate MIME type.
		$validation = $this->validate_file( $file_path );
		if ( ! $validation['valid'] ) {
			return self::error( $validation['error'] );
		}

		// Check file size.
		$max_size = (int) get_option( 'ms_max_upload_size', 2 * GB_IN_BYTES );
		if ( filesize( $file_path ) > $max_size ) {
			return self::error(
				sprintf(
					/* translators: %s: max file size */
					__( 'File exceeds maximum upload size of %s.', 'mediashield' ),
					size_format( $max_size )
				)
			);
		}

		// Generate unique filename.
		$ext      = pathinfo( $file_path, PATHINFO_EXTENSION );
		$filename = wp_unique_filename( $this->upload_dir, sanitize_file_name( basename( $file_path ) ) );
		$dest     = $this->upload_dir . $filename;

		// Move file.
		if ( ! copy( $file_path, $dest ) ) {
			return self::error( __( 'Failed to move uploaded file.', 'mediashield' ) );
		}

		// Create CPT post.
		$title   = $options['title'] ?? pathinfo( $filename, PATHINFO_FILENAME );
		$post_id = wp_insert_post( array(
			'post_type'   => 'mediashield_video',
			'post_title'  => sanitize_text_field( $title ),
			'post_status' => 'publish',
		) );

		if ( is_wp_error( $post_id ) ) {
			wp_delete_file( $dest );
			return self::error( $post_id->get_error_message() );
		}

		$embed_url = $this->upload_url . $filename;

		update_post_meta( $post_id, '_ms_platform', 'self' );
		update_post_meta( $post_id, '_ms_platform_video_id', $filename );
		update_post_meta( $post_id, '_ms_source_url', $embed_url );
		update_post_meta( $post_id, '_ms_protection_level', 'standard' );

		return array(
			'success'           => true,
			'video_id'          => $post_id,
			'platform_video_id' => $filename,
			'embed_url'         => $embed_url,
			'error'             => '',
		);
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_status( string $upload_id ): array {
		// Self-hosted uploads are synchronous — always complete or failed.
		$file = $this->upload_dir . $upload_id;

		if ( file_exists( $file ) ) {
			return array( 'status' => 'complete', 'progress' => 100, 'error' => '' );
		}

		return array( 'status' => 'not_found', 'progress' => 0, 'error' => __( 'File not found.', 'mediashield' ) );
	}

	/**
	 * {@inheritDoc}
	 */
	public function delete( string $platform_video_id ): bool {
		$file = $this->upload_dir . sanitize_file_name( $platform_video_id );

		if ( file_exists( $file ) ) {
			return wp_delete_file( $file ) !== false;
		}

		return false;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_embed_url( string $platform_video_id ): string {
		return $this->upload_url . sanitize_file_name( $platform_video_id );
	}

	/**
	 * {@inheritDoc}
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
	private function validate_file( string $file_path ): array {
		$check = wp_check_filetype_and_ext( $file_path, basename( $file_path ), self::ALLOWED_MIMES );

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

		return array( 'valid' => true, 'error' => '' );
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
