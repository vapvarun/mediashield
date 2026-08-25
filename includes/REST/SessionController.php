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
use MediaShield\Core\Settings;
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
		if ( is_user_logged_in() ) {
			return true;
		}

		if ( 'POST' !== $request->get_method() ) {
			return false;
		}

		$route = (string) $request->get_route();

		// Heartbeat and end carry an HMAC-signed session token, and that token
		// IS the authentication: SessionManager verifies the signature and
		// rejects anything it did not mint. Once a guest can legitimately start
		// a session (login gate off), refusing their heartbeats would record a
		// start with zero watch time and call it analytics - the session would
		// simply age out after five minutes having measured nothing.
		//
		// Nothing is trusted from the client here beyond the token, and an
		// unsigned or tampered one fails inside SessionManager, so this widens
		// who may ASK, not what they may do.
		if ( false !== strpos( $route, '/session/heartbeat' ) || false !== strpos( $route, '/session/end' ) ) {
			return '' !== (string) $request->get_param( 'token' );
		}

		// Allow anonymous /session/start when the operator has turned the login
		// gate off, or when the targeted video opts into an alternative access
		// path declared by an extension. The downstream
		// AccessControl::can_watch() still enforces the actual gate; this just
		// lets the request reach the handler so it can return the right reason
		// to the client. Revoke remains logged-in-only (admin-only, in fact) -
		// it never originates from an anonymous viewer.
		if ( false === strpos( $route, '/session/start' ) ) {
			return false;
		}

		$video_id = (int) $request->get_param( 'video_id' );
		if ( $video_id <= 0 ) {
			return false;
		}

		$access_type = (string) get_post_meta( $video_id, '_ms_access_type', true );

		// The operator turned the login gate off, so an anonymous viewer is a
		// legitimate one and must be allowed to REACH the handler. This is the
		// server half of the same defect the client had: `ms_require_login` was
		// honoured by AccessControl::can_watch() but the request never got that
		// far, because this callback rejected every guest first. Opening only
		// the client gate would have swapped a login overlay for a 401.
		//
		// Reaching the handler is not the same as being allowed to watch:
		// can_watch() still applies the per-video role gate, the allowed-domain
		// whitelist and the mediashield_can_watch filter chain, and still
		// returns a denial reason the client can render.
		$login_gate_off = ! Settings::get( 'ms_require_login' );

		/**
		 * Filter whether to allow an anonymous /session/start for this video.
		 *
		 * Defaults to allow when the operator has turned `ms_require_login`
		 * off, or when `_ms_access_type` is set by an extension. Extensions
		 * can opt additional anon-allowable access types in.
		 *
		 * @since 1.1.0
		 * @since 1.3.0 Also defaults to allow when `ms_require_login` is off.
		 *
		 * @param bool             $allow       Whether to allow anonymous start.
		 * @param int              $video_id    Video CPT post ID.
		 * @param string           $access_type Stored `_ms_access_type` value.
		 * @param WP_REST_Request  $request     Current REST request.
		 */
		return (bool) apply_filters(
			'mediashield_session_allow_anonymous_start',
			$login_gate_off || '' !== $access_type,
			$video_id,
			$access_type,
			$request
		);
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
			// Promote the access reason to the WP_Error code so the client can
			// route to the right overlay for whichever gate denied it. Falls
			// back to the legacy 'access_denied' so any integrator listening for
			// the old code still works.
			$code = ! empty( $access['reason'] ) ? sanitize_key( $access['reason'] ) : 'access_denied';
			return new WP_Error( $code, $access['reason'], array( 'status' => 403 ) );
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
			// Key is `text` (not `username`) to match Watermark::get_config() and
			// what assets/js/watermark.js reads; otherwise the overlay renders the
			// IP only on desktop and is blank on mobile. Pro's AdvancedConfig filter
			// overwrites `text` when active.
			'text'          => wp_get_current_user()->display_name,
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
					// The EFFECTIVE level, not the raw override. Reading the meta
					// directly reported an empty string for every video that
					// relies on the site default, so the API told a client the
					// video was unprotected when it was not.
					'protection_level' => sanitize_text_field( \MediaShield\Player\Protection::resolve_level( (int) $video_id ) ),
					'duration'         => (int) get_post_meta( $video_id, '_ms_duration', true ),
					// The manifest the DRM player loads. Dropped from this payload
					// on 2026-04-20 (a security fix that also stopped self-hosted
					// file URLs bypassing the signed /stream/ handoff) and never
					// re-added — which silently killed DRM playback: drm-player.js
					// reads video.source_url, found nothing, never called
					// player.load(), so Shaka never engaged and the "protected"
					// video only ever played through the free wrapper's
					// unencrypted data-source-url.
					//
					// Scoped to DRM videos only, so the self-hosted path the
					// 2026-04-20 fix protects is untouched: a DRM video is a CDN
					// manifest (Pro derives the Bunny HLS playlist), never a raw
					// self-hosted file, and access was already granted by
					// can_watch() above before this payload is built.
					'source_url'       => self::resolve_drm_source_url( $video_id ),
				),
			)
		);
	}

	/**
	 * Resolve the DRM manifest URL for a video's watch session.
	 *
	 * Mirrors Renderer's resolution so the DRM player and the standard player
	 * agree: the filtered stream URL first (Pro derives the Bunny HLS playlist
	 * from the GUID via `mediashield_video_stream_url`), then the stored source
	 * URL as a fallback.
	 *
	 * @since 1.2.0
	 * @param int $video_id Video CPT post ID.
	 * @return string
	 */
	private static function resolve_drm_source_url( int $video_id ): string {
		// Only DRM-protected videos get a manifest here. A standard/self-hosted
		// video keeps going through the signed /stream/ handoff, never a raw URL
		// in this response.
		// Seeded with 'standard' to match Renderer. The seed is only a fallback:
		// Pro's override_player_type() reads the video's protection level itself
		// to decide about DRM, so seeding it differently here changed nothing
		// except which of the two paths a reader had to trust.
		$player_type = (string) apply_filters( 'mediashield_player_type', 'standard', $video_id );
		if ( 'drm' !== $player_type ) {
			return '';
		}

		$platform          = (string) get_post_meta( $video_id, '_ms_platform', true );
		$platform_video_id = (string) get_post_meta( $video_id, '_ms_platform_video_id', true );
		$stream_url        = (string) get_post_meta( $video_id, '_ms_stream_url', true );

		/** This filter is documented in includes/Player/Renderer.php */
		$stream_url = (string) apply_filters( 'mediashield_video_stream_url', $stream_url, $video_id, $platform ?: 'self', $platform_video_id );

		if ( '' !== $stream_url ) {
			return esc_url_raw( $stream_url );
		}

		return esc_url_raw( (string) get_post_meta( $video_id, '_ms_source_url', true ) );
	}

	/**
	 * POST /session/heartbeat — track playback progress.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function heartbeat( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		// Rate limiting: max 4 heartbeats per minute per viewer.
		// Increment first to close TOCTOU race window.
		//
		// Keyed on the session token for guests: every anonymous viewer is
		// user_id 0, so a user-id key would put the entire logged-out audience
		// in one bucket and throttle the whole site at 4 heartbeats a minute.
		// The token is per-session, which is the correct granularity, and it is
		// hashed so a session token never becomes an option name.
		$user_id  = get_current_user_id();
		$rate_key = $user_id > 0
			? 'ms_rate_' . $user_id
			: 'ms_rate_g_' . hash( 'sha256', (string) $request->get_param( 'token' ) );
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
