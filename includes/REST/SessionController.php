<?php
/**
 * REST API controller for video watch sessions.
 *
 * Routes:
 *   POST /mediashield/v1/session/start       — Start/resume a session
 *   POST /mediashield/v1/session/heartbeat   — Track playback progress
 *   POST /mediashield/v1/session/end         — End a session
 *   POST /mediashield/v1/session/revoke-user — Revoke all sessions for a user (admin)
 *
 * @package MediaShield\REST
 */

namespace MediaShield\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use MediaShield\Access\AccessControl;
use MediaShield\Access\SessionManager;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

/**
 * Class SessionController
 *
 * REST API controller for video watch sessions.
 *
 * @since 1.0.0
 */
class SessionController extends WP_REST_Controller {

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
		// POST /session/start.
		register_rest_route(
			$this->namespace,
			'/session/start',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'start_session' ),
				'permission_callback' => array( $this, 'session_permissions_check' ),
				'args'                => array(
					'video_id' => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// POST /session/heartbeat.
		register_rest_route(
			$this->namespace,
			'/session/heartbeat',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'heartbeat' ),
				'permission_callback' => array( $this, 'session_permissions_check' ),
				'args'                => array(
					'token'    => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'position' => array(
						'type'              => 'number',
						'required'          => true,
						'sanitize_callback' => function ( $value ) {
							return (float) $value; },
					),
					'duration' => array(
						'type'              => 'number',
						'required'          => true,
						'sanitize_callback' => function ( $value ) {
							return (float) $value; },
					),
					'playing'  => array(
						'type'    => 'boolean',
						'default' => true,
					),
					'focused'  => array(
						'type'    => 'boolean',
						'default' => true,
					),
				),
			)
		);

		// POST /session/end.
		register_rest_route(
			$this->namespace,
			'/session/end',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'end_session' ),
				'permission_callback' => array( $this, 'session_permissions_check' ),
				'args'                => array(
					'token' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// POST /session/revoke-user (admin only).
		register_rest_route(
			$this->namespace,
			'/session/revoke-user',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'revoke_user' ),
				'permission_callback' => array( $this, 'admin_permissions_check' ),
				'args'                => array(
					'user_id' => array(
						'type'              => 'integer',
						'required'          => true,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Permissions: any logged-in user can manage their own sessions.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool
	 */
	public function session_permissions_check( WP_REST_Request $request ): bool {
		return is_user_logged_in();
	}

	/**
	 * Permissions: admin-only for revoking user sessions.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool
	 */
	public function admin_permissions_check( WP_REST_Request $request ): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * POST /session/start — start or resume a watch session.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function start_session( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$video_id = (int) $request->get_param( 'video_id' );
		$user_id  = get_current_user_id();

		// Verify video exists and is published.
		if ( $video_id <= 0 ) {
			return new WP_Error( 'invalid_video_id', __( 'Invalid video ID.', 'mediashield' ), array( 'status' => 400 ) );
		}

		$video = get_post( $video_id );
		if ( ! $video || 'mediashield_video' !== $video->post_type ) {
			return new WP_Error( 'not_found', __( 'Video not found.', 'mediashield' ), array( 'status' => 404 ) );
		}

		if ( 'publish' !== $video->post_status ) {
			return new WP_Error( 'unpublished', __( 'Video is not available.', 'mediashield' ), array( 'status' => 403 ) );
		}

		// Access control check.
		$access = AccessControl::can_watch( $video_id, $user_id );
		if ( ! $access['allowed'] ) {
			return new WP_Error( 'access_denied', $access['reason'], array( 'status' => 403 ) );
		}

		// Start session.
		$ip     = self::get_client_ip();
		$ua     = $request->get_header( 'user-agent' ) ?? '';
		$result = SessionManager::start( $video_id, $user_id, $ip, $ua );

		if ( false === $result ) {
			return new WP_Error(
				'concurrent_limit',
				__( 'Too many active streams. Please close another video first.', 'mediashield' ),
				array( 'status' => 429 )
			);
		}

		// Build watermark config for the client.
		$watermark_config = array(
			'enabled'       => (bool) get_option( 'ms_enabled', true ),
			'opacity'       => (float) get_option( 'ms_watermark_opacity', 0.3 ),
			'color'         => get_option( 'ms_watermark_color', '#ffffff' ),
			'swap_interval' => (int) get_option( 'ms_watermark_swap_interval', 20 ),
			'username'      => wp_get_current_user()->display_name,
			'ip'            => $ip,
		);

		/**
		 * Filter the watermark configuration sent to the client.
		 *
		 * Pro hooks into this to add email, timestamp, custom text, etc.
		 *
		 * @since 1.0.0
		 *
		 * @param array $watermark_config Watermark configuration.
		 * @param int   $video_id         Video CPT post ID.
		 * @param int   $user_id          User ID.
		 */
		$watermark_config = apply_filters( 'mediashield_watermark_config', $watermark_config, $video_id, $user_id );

		return rest_ensure_response(
			array(
				'session_token'    => $result['session_token'],
				'resume_position'  => $result['resume_position'],
				'is_resumed'       => $result['is_resumed'],
				'watermark_config' => $watermark_config,
				'video'            => array(
					'id'               => $video_id,
					'title'            => sanitize_text_field( $video->post_title ),
					'platform'         => sanitize_text_field( get_post_meta( $video_id, '_ms_platform', true ) ),
					'protection_level' => sanitize_text_field( get_post_meta( $video_id, '_ms_protection_level', true ) ),
					'duration'         => (int) get_post_meta( $video_id, '_ms_duration', true ),
				),
			)
		);
	}

	/**
	 * POST /session/heartbeat — track playback progress.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function heartbeat( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		// Rate limiting: max 4 heartbeats per minute per user.
		// Increment first to close TOCTOU race window.
		$rate_key = 'ms_rate_' . get_current_user_id();
		$count    = (int) get_transient( $rate_key );
		++$count;
		set_transient( $rate_key, $count, 60 );

		if ( $count > 4 ) {
			return new WP_Error(
				'rate_limited',
				__( 'Too many requests.', 'mediashield' ),
				array( 'status' => 429 )
			);
		}

		$success = SessionManager::heartbeat(
			$request->get_param( 'token' ),
			(float) $request->get_param( 'position' ),
			(float) $request->get_param( 'duration' ),
			(bool) $request->get_param( 'playing' ),
			(bool) $request->get_param( 'focused' )
		);

		if ( ! $success ) {
			return new WP_Error( 'invalid_token', __( 'Invalid session token.', 'mediashield' ), array( 'status' => 401 ) );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'status'  => 'recorded',
			)
		);
	}

	/**
	 * POST /session/end — end a watch session.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function end_session( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$success = SessionManager::end( $request->get_param( 'token' ) );

		if ( ! $success ) {
			return new WP_Error( 'invalid_token', __( 'Invalid session token.', 'mediashield' ), array( 'status' => 401 ) );
		}

		return rest_ensure_response( array( 'success' => true ) );
	}

	/**
	 * POST /session/revoke-user — revoke all sessions for a user (admin only).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function revoke_user( WP_REST_Request $request ): WP_REST_Response {
		$user_id = (int) $request->get_param( 'user_id' );
		$count   = SessionManager::revoke_user( $user_id );

		return rest_ensure_response(
			array(
				'success' => true,
				'revoked' => $count,
				'user_id' => $user_id,
			)
		);
	}

	/**
	 * Get client IP address.
	 *
	 * Only trusts REMOTE_ADDR by default. Sites behind a reverse proxy
	 * can add trusted headers via the mediashield_trusted_ip_headers filter.
	 *
	 * @return string IP address.
	 */
	private static function get_client_ip(): string {
		/**
		 * Filter the trusted IP headers for client IP detection.
		 *
		 * Only add proxy headers if your server is actually behind that proxy.
		 * Example: add 'HTTP_CF_CONNECTING_IP' if behind Cloudflare.
		 *
		 * @since 1.0.0
		 *
		 * @param array $headers Server variable names to check, in priority order.
		 */
		$headers = apply_filters( 'mediashield_trusted_ip_headers', array( 'REMOTE_ADDR' ) );

		foreach ( $headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
				// X-Forwarded-For may contain multiple IPs; take the first.
				if ( str_contains( $ip, ',' ) ) {
					$ip = trim( explode( ',', $ip )[0] );
				}
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return '0.0.0.0';
	}
}
