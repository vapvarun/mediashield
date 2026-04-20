<?php
/**
 * REST API controller for client-side protection events.
 *
 * Routes:
 *   POST /mediashield/v1/protection/devtools-event -- Report a DevTools detection
 *
 * Receives sendBeacon payloads from `protection.js` when client-side heuristics
 * (window-size delta or debugger-statement timing) suggest the viewer opened
 * developer tools. Fires the `mediashield_devtools_detected` action so Pro can
 * create a suspicious-activity alert, and writes to the PHP error log for free
 * users to surface the event.
 *
 * Rate-limited: one event per user per hour per IP, enforced via transients.
 *
 * @package MediaShield\REST
 */

namespace MediaShield\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Class ProtectionController
 *
 * Handles protection telemetry from the frontend.
 *
 * @since 1.1.0
 */
class ProtectionController extends WP_REST_Controller {

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
		register_rest_route(
			$this->namespace,
			'/protection/devtools-event',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'record_devtools_event' ),
				'permission_callback' => array( $this, 'beacon_permission' ),
				'args'                => array(
					'strategy' => array(
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
						'enum'              => array( 'size_delta', 'debugger_timing' ),
					),
					'url'      => array(
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'esc_url_raw',
					),
					'ua'       => array(
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'screen'   => array(
						'type'              => 'string',
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Beacon permission -- valid nonce required; anonymous playback allowed.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool
	 */
	public function beacon_permission( WP_REST_Request $request ): bool {
		$nonce = $request->get_header( 'X-WP-Nonce' );
		if ( ! $nonce ) {
			$nonce = $request->get_param( '_wpnonce' );
		}
		return (bool) wp_verify_nonce( (string) $nonce, 'wp_rest' );
	}

	/**
	 * Record a DevTools detection event.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function record_devtools_event( WP_REST_Request $request ): WP_REST_Response {
		$strategy = (string) $request->get_param( 'strategy' );
		$url      = (string) $request->get_param( 'url' );
		$user_id  = get_current_user_id();
		$ip       = $this->client_ip();

		// Rate limit: one event per user/IP per hour.
		$rl_key = 'ms_devtools_rl_' . md5( $user_id . '|' . $ip );
		if ( false !== get_transient( $rl_key ) ) {
			return new WP_REST_Response( array( 'recorded' => false, 'reason' => 'rate_limited' ), 200 );
		}
		set_transient( $rl_key, 1, HOUR_IN_SECONDS );

		$context = array(
			'user_id'  => $user_id,
			'ip'       => $ip,
			'url'      => $url,
			'strategy' => $strategy,
			'ua'       => (string) $request->get_param( 'ua' ),
			'screen'   => (string) $request->get_param( 'screen' ),
			'at'       => current_time( 'mysql', true ),
		);

		/**
		 * Fires when client-side heuristics detect DevTools on a protected page.
		 *
		 * Pro's SuspiciousActivity class hooks into this to create an alert row
		 * in ms_activity_alerts. Third parties may hook in for additional
		 * logging or blocking behavior.
		 *
		 * @since 1.1.0
		 *
		 * @param array $context {
		 *     Context of the detection event.
		 *
		 *     @type int    $user_id  Current user ID (0 for anonymous).
		 *     @type string $ip       Client IP.
		 *     @type string $url      Page URL where the detection fired.
		 *     @type string $strategy Detection strategy: size_delta or debugger_timing.
		 *     @type string $ua       User agent string.
		 *     @type string $screen   Viewport size (e.g. "1280x800").
		 *     @type string $at       UTC timestamp.
		 * }
		 */
		do_action( 'mediashield_devtools_detected', $context );

		return new WP_REST_Response( array( 'recorded' => true ), 200 );
	}

	/**
	 * Resolve the client IP, honoring the trusted-headers filter.
	 *
	 * @return string
	 */
	private function client_ip(): string {
		$headers = apply_filters( 'mediashield_trusted_ip_headers', array( 'REMOTE_ADDR' ) );
		foreach ( (array) $headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$raw = sanitize_text_field( wp_unslash( (string) $_SERVER[ $header ] ) );
				$ip  = trim( explode( ',', $raw )[0] );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}
		return '';
	}
}
