<?php
/**
 * REST API controller for video uploads.
 *
 * Routes:
 *   POST /mediashield/v1/upload/init         — Upload a video file
 *   GET  /mediashield/v1/upload/status/<id>  — Get upload status
 *
 * @package MediaShield\REST
 */

namespace MediaShield\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use MediaShield\Upload\UploadManager;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

/**
 * Class UploadController
 *
 * REST API controller for video uploads.
 *
 * @since 1.0.0
 */
class UploadController extends WP_REST_Controller {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'mediashield/v1';

	/**
	 * Register routes.
	 */
	public function register_routes(): void {
		// POST /upload/init.
		register_rest_route(
			$this->namespace,
			'/upload/init',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'init_upload' ),
				'permission_callback' => array( $this, 'upload_permissions_check' ),
				'args'                => array(
					'title'  => array(
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					),
					'driver' => array(
						'type'              => 'string',
						'default'           => 'self_hosted',
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// GET /upload/status/<id>.
		register_rest_route(
			$this->namespace,
			'/upload/status/(?P<upload_id>[a-zA-Z0-9._-]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_status' ),
				'permission_callback' => array( $this, 'upload_permissions_check' ),
			)
		);
	}

	/**
	 * Permissions: must have upload_mediashield capability.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool
	 */
	public function upload_permissions_check( WP_REST_Request $request ): bool {
		return current_user_can( 'upload_mediashield' );
	}

	/**
	 * POST /upload/init — handle file upload.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function init_upload( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$files = $request->get_file_params();

		if ( empty( $files['file'] ) ) {
			return new WP_Error(
				'no_file',
				__( 'No file provided. Send a file in the "file" field.', 'mediashield' ),
				array( 'status' => 400 )
			);
		}

		$file = $files['file'];

		// Check for upload errors.
		if ( UPLOAD_ERR_OK !== $file['error'] ) {
			return new WP_Error(
				'upload_error',
				self::get_upload_error_message( $file['error'] ),
				array( 'status' => 400 )
			);
		}

		$driver_name = $request->get_param( 'driver' );
		$title       = $request->get_param( 'title' );
		$title       = ! empty( $title ) ? $title : pathinfo( $file['name'], PATHINFO_FILENAME );

		$result = UploadManager::upload(
			$file['tmp_name'],
			$driver_name,
			array(
				'title'         => $title,
				'original_name' => $file['name'],
			)
		);

		if ( ! $result['success'] ) {
			return new WP_Error(
				'upload_failed',
				$result['error'],
				array( 'status' => 422 )
			);
		}

		/**
		 * Fires after a video upload completes successfully.
		 *
		 * @since 1.0.0
		 *
		 * @param int    $video_id    Created video CPT post ID.
		 * @param string $driver_name Upload driver used.
		 * @param array  $result      Full upload result.
		 */
		do_action( 'mediashield_upload_complete', $result['video_id'], $driver_name, $result );

		return new WP_REST_Response(
			array(
				'video_id'          => $result['video_id'],
				'platform_video_id' => $result['platform_video_id'],
				'embed_url'         => esc_url( $result['embed_url'] ),
				'status'            => 'complete',
			),
			201
		);
	}

	/**
	 * GET /upload/status/<id> — get upload progress.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_status( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$upload_id    = sanitize_file_name( $request['upload_id'] );
		$driver_param = $request->get_param( 'driver' );
		$driver_name  = ! empty( $driver_param ) ? $driver_param : 'self_hosted';

		$driver = UploadManager::get_driver( $driver_name );

		if ( ! $driver ) {
			return new WP_Error( 'invalid_driver', __( 'Upload driver not found.', 'mediashield' ), array( 'status' => 400 ) );
		}

		$status = $driver->get_status( $upload_id );

		return rest_ensure_response( $status );
	}

	/**
	 * Map PHP upload error codes to messages.
	 *
	 * @param int $error_code PHP upload error constant.
	 * @return string Error message.
	 */
	private static function get_upload_error_message( int $error_code ): string {
		$messages = array(
			UPLOAD_ERR_INI_SIZE   => __( 'File exceeds the server upload size limit.', 'mediashield' ),
			UPLOAD_ERR_FORM_SIZE  => __( 'File exceeds the form upload size limit.', 'mediashield' ),
			UPLOAD_ERR_PARTIAL    => __( 'File was only partially uploaded.', 'mediashield' ),
			UPLOAD_ERR_NO_FILE    => __( 'No file was uploaded.', 'mediashield' ),
			UPLOAD_ERR_NO_TMP_DIR => __( 'Server missing temporary folder.', 'mediashield' ),
			UPLOAD_ERR_CANT_WRITE => __( 'Server failed to write file to disk.', 'mediashield' ),
			UPLOAD_ERR_EXTENSION  => __( 'A server extension blocked the upload.', 'mediashield' ),
		);

		return $messages[ $error_code ] ?? __( 'Unknown upload error.', 'mediashield' );
	}
}
