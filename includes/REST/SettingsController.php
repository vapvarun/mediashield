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

use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

/**
 * Class SettingsController
 *
 * REST API controller for plugin settings.
 *
 * @since 1.0.0
 */
class SettingsController extends WP_REST_Controller {

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'mediashield/v1';

	/**
	 * Default settings with their types.
	 *
	 * @var array
	 */
	private const SETTINGS = array(
		'ms_enabled'                 => 'boolean',
		'ms_default_protection'      => 'string',
		'ms_require_login'           => 'boolean',
		'ms_watermark_opacity'       => 'float',
		'ms_watermark_color'         => 'string',
		'ms_watermark_swap_interval' => 'integer',
		'ms_allowed_domains'         => 'string',
		'ms_max_concurrent_streams'  => 'integer',
		'ms_custom_url_patterns'     => 'string',
		'ms_show_badge'              => 'boolean',
		'ms_max_upload_size'         => 'integer',
		'ms_login_overlay_text'      => 'string',
		'ms_login_button_text'       => 'string',
		'ms_access_denied_text'      => 'string',

		// Player controls.
		'ms_player_speed_control'    => 'boolean',
		'ms_player_sticky'           => 'boolean',
		'ms_player_keyboard'         => 'boolean',
		'ms_player_resume'           => 'boolean',
		'ms_player_endscreen'        => 'boolean',
		'ms_player_endscreen_text'   => 'string',
		'ms_player_endscreen_url'    => 'string',
	);

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
		$settings = array();

		foreach ( self::SETTINGS as $key => $type ) {
			$value = get_option( $key );

			// Cast to correct type.
			$settings[ $key ] = match ( $type ) {
				'boolean' => (bool) $value,
				'integer' => (int) $value,
				'float'   => (float) $value,
				default   => (string) ( $value ?? '' ),
			};
		}

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
		 * Pro hooks this to handle pro-specific fields.
		 *
		 * @since 1.0.0
		 *
		 * @param array $data Settings data from the request.
		 */
		$data = apply_filters( 'mediashield_settings_update', $data );

		foreach ( $data as $key => $value ) {
			// Only allow settings explicitly defined in the SETTINGS const.
			// Pro settings are handled by their own filter callbacks (DRMSettings, ProSettings)
			// which call update_option directly and unset the key from $data.
			if ( ! isset( self::SETTINGS[ $key ] ) ) {
				continue;
			}

			$type = self::SETTINGS[ $key ];

			// Sanitize by type.
			$sanitized = match ( $type ) {
				'boolean' => (bool) $value,
				'integer' => (int) $value,
				'float'   => (float) $value,
				default   => sanitize_textarea_field( (string) $value ),
			};

			update_option( $key, $sanitized );
		}

		// Return updated settings.
		return $this->get_settings( $request );
	}
}
