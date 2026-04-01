<?php
/**
 * REST API controller for streaming self-hosted video files.
 *
 * Serves protected video files through a permissioned REST endpoint
 * instead of direct file access (blocked by .htaccess).
 *
 * Route:
 *   GET /mediashield/v1/stream/{video_id} — Stream a self-hosted video
 *
 * @package MediaShield\REST
 */

namespace MediaShield\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use MediaShield\Access\AccessControl;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

/**
 * Class StreamController
 *
 * REST API controller for streaming self-hosted video files.
 *
 * @since 1.0.0
 */
class StreamController extends WP_REST_Controller {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'mediashield/v1';

	/**
	 * MIME types for supported video formats.
	 *
	 * @var array<string, string>
	 */
	private const MIME_TYPES = array(
		'mp4'  => 'video/mp4',
		'webm' => 'video/webm',
		'mov'  => 'video/quicktime',
		'm4v'  => 'video/x-m4v',
	);

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/stream/(?P<video_id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'stream_video' ),
				'permission_callback' => array( $this, 'stream_permissions_check' ),
				'args'                => array(
					'video_id' => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Permission check — uses the same mediashield_can_watch filter as SessionController.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function stream_permissions_check( WP_REST_Request $request ): bool|WP_Error {
		$video_id = (int) $request->get_param( 'video_id' );
		$user_id  = get_current_user_id();

		$access = AccessControl::can_watch( $video_id, $user_id );
		if ( ! $access['allowed'] ) {
			return new WP_Error(
				'mediashield_access_denied',
				$access['reason'],
				array( 'status' => 403 )
			);
		}

		return true;
	}

	/**
	 * Stream the video file with proper headers and range support.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error|void Returns error on failure, streams and exits on success.
	 */
	public function stream_video( WP_REST_Request $request ) {
		$video_id = (int) $request->get_param( 'video_id' );

		// Verify the video exists and is a mediashield_video CPT.
		$video = get_post( $video_id );
		if ( ! $video || 'mediashield_video' !== $video->post_type ) {
			return new WP_Error( 'not_found', __( 'Video not found.', 'mediashield' ), array( 'status' => 404 ) );
		}

		if ( 'publish' !== $video->post_status ) {
			return new WP_Error( 'unpublished', __( 'Video is not available.', 'mediashield' ), array( 'status' => 403 ) );
		}

		// Only self-hosted videos are served through this endpoint.
		$platform = get_post_meta( $video_id, '_ms_platform', true );
		if ( 'self' !== $platform ) {
			return new WP_Error( 'not_self_hosted', __( 'This endpoint only serves self-hosted videos.', 'mediashield' ), array( 'status' => 400 ) );
		}

		// Resolve the file path from the platform video ID (filename).
		$filename = get_post_meta( $video_id, '_ms_platform_video_id', true );
		if ( empty( $filename ) ) {
			return new WP_Error( 'missing_file', __( 'Video file reference is missing.', 'mediashield' ), array( 'status' => 404 ) );
		}

		$wp_upload = wp_upload_dir();
		$file_path = trailingslashit( $wp_upload['basedir'] ) . 'mediashield/' . sanitize_file_name( $filename );

		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return new WP_Error( 'file_not_found', __( 'Video file not found on disk.', 'mediashield' ), array( 'status' => 404 ) );
		}

		$this->serve_file( $file_path );
	}

	/**
	 * Serve a video file with HTTP range support for seeking.
	 *
	 * @param string $file_path Absolute path to the video file.
	 */
	private function serve_file( string $file_path ): void {
		$file_size = filesize( $file_path );
		$ext       = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
		$mime      = self::MIME_TYPES[ $ext ] ?? 'application/octet-stream';

		// Determine byte range.
		$start = 0;
		$end   = $file_size - 1;

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- HTTP_RANGE is a standard header parsed below.
		$range_header = isset( $_SERVER['HTTP_RANGE'] ) ? wp_unslash( $_SERVER['HTTP_RANGE'] ) : '';

		if ( ! empty( $range_header ) ) {
			// Parse Range: bytes=start-end.
			if ( preg_match( '/bytes=(\d*)-(\d*)/', $range_header, $matches ) ) {
				$range_start = $matches[1];
				$range_end   = $matches[2];

				if ( '' !== $range_start ) {
					$start = (int) $range_start;
				}

				if ( '' !== $range_end ) {
					$end = (int) $range_end;
				}

				// Validate range.
				if ( $start > $end || $start >= $file_size || $end >= $file_size ) {
					status_header( 416 );
					header( "Content-Range: bytes */{$file_size}" );
					exit;
				}

				status_header( 206 );
				header( "Content-Range: bytes {$start}-{$end}/{$file_size}" );
			}
		} else {
			status_header( 200 );
		}

		$length = $end - $start + 1;

		// Send headers.
		header( "Content-Type: {$mime}" );
		header( "Content-Length: {$length}" );
		header( 'Accept-Ranges: bytes' );
		header( 'Cache-Control: no-store, no-cache, must-revalidate' );
		header( 'Pragma: no-cache' );

		// Prevent output buffering from interfering.
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- ob_end_clean may fail if no buffer active.
		while ( ob_get_level() ) {
			@ob_end_clean();
		}

		// Open and serve the requested range.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Streaming binary video file.
		$fp = fopen( $file_path, 'rb' );
		if ( ! $fp ) {
			status_header( 500 );
			exit;
		}

		if ( $start > 0 ) {
			fseek( $fp, $start );
		}

		// Read in 8KB chunks to avoid memory issues with large files.
		$chunk_size = 8 * KB_IN_BYTES;
		$remaining  = $length;

		while ( $remaining > 0 && ! feof( $fp ) && connection_status() === CONNECTION_NORMAL ) {
			$read_size = min( $chunk_size, $remaining );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- Streaming binary video data.
			$data = fread( $fp, $read_size );
			if ( false === $data ) {
				break;
			}
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Binary video data.
			echo $data;
			flush();
			$remaining -= strlen( $data );
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing file handle opened above.
		fclose( $fp );
		exit;
	}
}
