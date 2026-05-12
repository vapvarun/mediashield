<?php
/**
 * REST API controller for plugin settings.
 *
 * Routes:
 *   GET /mediashield/v1/settings — Get all settings
 *   PUT /mediashield/v1/settings — Update settings (auto-save)
 *
 * @package MediaShield\REST
 */

namespace MediaShield\REST;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use MediaShield\Core\Settings;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

class SettingsController extends WP_REST_Controller {

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
			'/settings',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'admin_permissions_check' ),
				),
				array(
					'methods'             => 'PUT',
					'callback'            => array( $this, 'update_settings' ),
					'permission_callback' => array( $this, 'admin_permissions_check' ),
				),
			)
		);
	}

	/**
	 * Admin only.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool
	 */
	public function admin_permissions_check( WP_REST_Request $request ): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * GET /settings — return all settings.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function get_settings( WP_REST_Request $request ): WP_REST_Response {
		$settings = Settings::get_all();

		/**
		 * Filter the settings response.
		 *
		 * Pro hooks this to merge pro settings (watermark fields, DRM, etc.).
		 *
		 * @since 1.0.0
		 *
		 * @param array $settings All settings.
		 */
		$settings = apply_filters( 'mediashield_settings_response', $settings );

		return rest_ensure_response( $settings );
	}

	/**
	 * PUT /settings — update settings (supports partial updates for auto-save).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_settings( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$data = $request->get_json_params();

		if ( empty( $data ) || ! is_array( $data ) ) {
			return new WP_Error( 'invalid_data', __( 'No settings provided.', 'mediashield' ), array( 'status' => 400 ) );
		}

		/**
		 * Filter the settings data before saving.
		 *
		 * Pro hooks this to handle pro-specific fields. Pro callbacks unset
		 * their keys from $data after persisting them so the loop below skips them.
		 *
		 * @since 1.0.0
		 *
		 * @param array $data Settings data from the request.
		 */
		$data = apply_filters( 'mediashield_settings_update', $data );

		foreach ( $data as $key => $value ) {
			$sanitized = Settings::sanitize( (string) $key, $value );

			// Unknown keys (not in the schema and not consumed by Pro) are ignored.
			if ( null === $sanitized ) {
				continue;
			}

			update_option( $key, $sanitized );
		}

		// Return updated settings.
		return $this->get_settings( $request );
	}
}
