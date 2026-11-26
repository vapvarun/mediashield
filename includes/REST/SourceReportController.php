<?php
/**
 * POST /videos/{id}/unavailable - a player says the platform refused the video.
 *
 * @package MediaShield\REST
 */

namespace MediaShield\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use MediaShield\CPT\SourceStatus;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Receives the player's report; SourceStatus confirms it with the platform.
 */
class SourceReportController extends WP_REST_Controller {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'mediashield/v1';

	/**
	 * Register the route.
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/videos/(?P<id>\d+)/unavailable',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'report' ),
				'permission_callback' => array( $this, 'report_permissions_check' ),
				'args'                => array(
					'code' => array(
						'type'    => 'string',
						'enum'    => SourceStatus::CODES,
						'default' => 'unknown',
					),
				),
			)
		);
	}

	/**
	 * Anyone may report, on purpose: the guest who hit the broken video is
	 * the one who saw it. Nothing is written on their word alone;
	 * SourceStatus::report() asks the platform first, at most once per ten
	 * minutes per video, and believes only an editor where it cannot ask.
	 *
	 * @return true
	 */
	public function report_permissions_check(): bool {
		return true;
	}

	/**
	 * Record the refusal when the platform confirms it.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function report( WP_REST_Request $request ) {
		$video_id = (int) $request['id'];

		if ( 'mediashield_video' !== get_post_type( $video_id ) || 'publish' !== get_post_status( $video_id ) ) {
			return new WP_Error( 'not_found', __( 'Video not found.', 'mediashield' ), array( 'status' => 404 ) );
		}

		return new WP_REST_Response(
			array( 'recorded' => SourceStatus::report( $video_id, (string) $request->get_param( 'code' ) ) ),
			200
		);
	}
}
