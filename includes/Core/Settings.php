<?php
/**
 * Single source of truth for free-plugin settings: schema, defaults, types,
 * and the frontend payload emitted to JS.
 *
 * Owners must add new options here — Activator seeds, SettingsController
 * casts/saves, and Assets emits to the frontend all derive from this class.
 *
 * @package MediaShield\Core
 */

namespace MediaShield\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Settings {

	/**
	 * Schema for every free-plugin option.
	 *
	 * Each entry: `'<option_name>' => array( 'type' => '<scalar>', 'default' => <value> )`.
	 *
	 * Translatable defaults must be returned from a method (not a const) so the
	 * text domain is loaded before the strings are read.
	 *
	 * @return array<string, array{type: string, default: mixed}>
	 */
	public static function schema(): array {
		return array(
			// Core.
			'ms_enabled'                 => array( 'type' => 'boolean', 'default' => true ),
			'ms_default_protection'      => array( 'type' => 'string',  'default' => 'standard' ),
			'ms_require_login'           => array( 'type' => 'boolean', 'default' => true ),

			// Watermark.
			'ms_watermark_opacity'       => array( 'type' => 'float',   'default' => 0.5 ),
			'ms_watermark_color'         => array( 'type' => 'string',  'default' => '#ffffff' ),
			'ms_watermark_swap_interval' => array( 'type' => 'integer', 'default' => 30 ),

			// Access.
			'ms_allowed_domains'         => array( 'type' => 'string',  'default' => '' ),
			'ms_max_concurrent_streams'  => array( 'type' => 'integer', 'default' => 2 ),

			// Upload.
			'ms_max_upload_size'         => array( 'type' => 'integer', 'default' => 500 ),
			'ms_custom_url_patterns'     => array( 'type' => 'string',  'default' => '' ),

			// Badge.
			'ms_show_badge'              => array( 'type' => 'boolean', 'default' => true ),

			// Login & access messages.
			'ms_login_overlay_text'      => array( 'type' => 'string',  'default' => __( 'Please log in to watch this video', 'mediashield' ) ),
			'ms_login_button_text'       => array( 'type' => 'string',  'default' => __( 'Log In', 'mediashield' ) ),
			'ms_access_denied_text'      => array( 'type' => 'string',  'default' => __( 'You do not have access to this video', 'mediashield' ) ),

			// Player controls.
			'ms_player_speed_control'    => array( 'type' => 'boolean', 'default' => true ),
			'ms_player_sticky'           => array( 'type' => 'boolean', 'default' => false ),
			'ms_player_keyboard'         => array( 'type' => 'boolean', 'default' => true ),
			'ms_player_resume'           => array( 'type' => 'boolean', 'default' => true ),
			'ms_player_endscreen'        => array( 'type' => 'boolean', 'default' => false ),
			'ms_player_endscreen_text'   => array( 'type' => 'string',  'default' => '' ),
			'ms_player_endscreen_url'    => array( 'type' => 'string',  'default' => '' ),

			// Protection controls (right-click, keyboard, devtools).
			'ms_block_right_click'       => array( 'type' => 'boolean', 'default' => true ),
			'ms_block_keyboard'          => array( 'type' => 'boolean', 'default' => false ),
			'ms_hide_source'             => array( 'type' => 'boolean', 'default' => true ),
			'ms_detect_devtools'         => array( 'type' => 'boolean', 'default' => true ),
			'ms_pause_on_devtools'       => array( 'type' => 'boolean', 'default' => false ),
			'ms_devtools_title'          => array( 'type' => 'string',  'default' => __( 'Developer Tools Detected', 'mediashield' ) ),
			'ms_devtools_message'        => array( 'type' => 'string',  'default' => __( 'Please close developer tools to continue watching this video.', 'mediashield' ) ),
		);
	}

	/**
	 * Map of option name → scalar type.
	 *
	 * @return array<string, string>
	 */
	public static function types(): array {
		return array_map( static fn( array $entry ): string => $entry['type'], self::schema() );
	}

	/**
	 * Map of option name → default value.
	 *
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array_map( static fn( array $entry ): mixed => $entry['default'], self::schema() );
	}

	/**
	 * Get a single setting, falling back to the schema default.
	 *
	 * Always returns the correctly-typed value — callers can stop casting at call sites.
	 *
	 * @param string $key Option name.
	 * @return mixed Typed setting value, or null if the key is unknown.
	 */
	public static function get( string $key ): mixed {
		$schema = self::schema();
		if ( ! isset( $schema[ $key ] ) ) {
			return null;
		}

		$entry = $schema[ $key ];
		$value = get_option( $key, $entry['default'] );

		return self::cast( $value, $entry['type'] );
	}

	/**
	 * Get all settings as a typed map, applying defaults for any missing options.
	 *
	 * @return array<string, mixed>
	 */
	public static function get_all(): array {
		$out = array();
		foreach ( self::schema() as $key => $entry ) {
			$out[ $key ] = self::cast( get_option( $key, $entry['default'] ), $entry['type'] );
		}
		return $out;
	}

	/**
	 * Seed defaults for every option that does not yet exist. Idempotent — safe
	 * to call on every activation.
	 */
	public static function seed_defaults(): void {
		foreach ( self::defaults() as $key => $value ) {
			if ( false === get_option( $key, false ) ) {
				add_option( $key, $value );
			}
		}
	}

	/**
	 * Cast a stored value to its declared scalar type.
	 *
	 * @param mixed  $value Raw value from get_option().
	 * @param string $type  One of: boolean, integer, float, string.
	 */
	private static function cast( mixed $value, string $type ): mixed {
		return match ( $type ) {
			'boolean' => (bool) $value,
			'integer' => (int) $value,
			'float'   => (float) $value,
			default   => (string) ( $value ?? '' ),
		};
	}

	/**
	 * Sanitize an incoming value for a known setting key.
	 *
	 * @param string $key   Option name.
	 * @param mixed  $value Raw value from a REST request.
	 * @return mixed|null Sanitized value, or null if the key is unknown.
	 */
	public static function sanitize( string $key, mixed $value ): mixed {
		$types = self::types();
		if ( ! isset( $types[ $key ] ) ) {
			return null;
		}

		return match ( $types[ $key ] ) {
			'boolean' => (bool) $value,
			'integer' => (int) $value,
			'float'   => (float) $value,
			default   => sanitize_textarea_field( (string) $value ),
		};
	}

	/**
	 * Build the frontend config payload exposed to JS as `mediashieldConfig`.
	 *
	 * Carries only the keys the frontend actually needs (player options, badge
	 * toggle, login/access messages, devtools strings). Pro can extend via
	 * the `mediashield_frontend_config` filter.
	 *
	 * @return array<string, mixed>
	 */
	public static function frontend_config(): array {
		$user = wp_get_current_user();

		$config = array(
			'restUrl'    => rest_url( 'mediashield/v1/' ),
			'nonce'      => wp_create_nonce( 'wp_rest' ),
			'isLoggedIn' => is_user_logged_in(),
			'userId'     => $user->ID,
			'loginUrl'   => wp_login_url( get_permalink() ),
			'interval'   => 30000,
			'player'     => array(
				'speedControl'  => self::get( 'ms_player_speed_control' ),
				'keyboard'      => self::get( 'ms_player_keyboard' ),
				'resume'        => self::get( 'ms_player_resume' ),
				'sticky'        => self::get( 'ms_player_sticky' ),
				'endscreen'     => self::get( 'ms_player_endscreen' ),
				'endscreenText' => self::get( 'ms_player_endscreen_text' ),
				'endscreenUrl'  => self::get( 'ms_player_endscreen_url' ),
			),
			'messages'   => array(
				'loginOverlay' => self::get( 'ms_login_overlay_text' ),
				'loginButton'  => self::get( 'ms_login_button_text' ),
				'accessDenied' => self::get( 'ms_access_denied_text' ),
			),
		);

		/**
		 * Filter the frontend config payload before it is localized to JS.
		 *
		 * Pro hooks this to inject premium player options, DRM hints, etc.
		 *
		 * @since 1.1.0
		 *
		 * @param array $config Frontend config payload.
		 */
		return apply_filters( 'mediashield_frontend_config', $config );
	}
}
