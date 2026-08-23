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
	 * Each entry: `array( 'type' => <scalar>, 'default' => <value>, 'validate' => ?callable )`.
	 *
	 * Translatable defaults must be returned from a method (not a const) so the
	 * text domain is loaded before the strings are read.
	 *
	 * `validate` runs AFTER type-casting in {@see self::sanitize()}. It must
	 * return the (possibly clamped/normalised) value to save, or `null` to
	 * reject — `Settings::sanitize()` propagates the null up and the REST
	 * controller skips the update so the existing value sticks.
	 *
	 * @return array<string, array{type: string, default: mixed, validate?: callable}>
	 */
	public static function schema(): array {
		// Validator helpers — declared inline so the schema entries read as a
		// single decision table.
		$enum         = static fn( array $allowed ) => static function ( $value ) use ( $allowed ) {
			return in_array( $value, $allowed, true ) ? $value : null;
		};
		$hex_color    = static function ( $value ) {
			return preg_match( '/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', (string) $value )
				? strtolower( (string) $value )
				: null;
		};
		$clamp        = static fn( float $min, float $max ) => static function ( $value ) use ( $min, $max ) {
			$num = is_numeric( $value ) ? (float) $value : $min;
			return max( $min, min( $max, $num ) );
		};
		$min_int      = static fn( int $min ) => static function ( $value ) use ( $min ) {
			$num = is_numeric( $value ) ? (int) $value : $min;
			return max( $min, $num );
		};
		$url_or_empty = static function ( $value ) {
			$value = trim( (string) $value );
			if ( '' === $value ) {
				return '';
			}
			$clean = esc_url_raw( $value );
			return '' !== $clean ? $clean : null;
		};

		return array(
			// Core.
			'ms_enabled'                 => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'ms_default_protection'      => array(
				'type'     => 'string',
				'default'  => 'standard',
				'validate' => $enum( array( 'none', 'basic', 'standard', 'strict' ) ),
			),
			'ms_require_login'           => array(
				'type'    => 'boolean',
				'default' => true,
			),

			// Watermark.
			'ms_watermark_opacity'       => array(
				'type'     => 'float',
				'default'  => 0.5,
				'validate' => $clamp( 0.0, 1.0 ),
			),
			'ms_watermark_color'         => array(
				'type'     => 'string',
				'default'  => '#ffffff',
				'validate' => $hex_color,
			),
			'ms_watermark_swap_interval' => array(
				'type'     => 'integer',
				'default'  => 30,
				'validate' => $min_int( 1 ),
			),

			// Access.
			'ms_allowed_domains'         => array(
				'type'    => 'string',
				'default' => '',
			),
			'ms_max_concurrent_streams'  => array(
				'type'     => 'integer',
				'default'  => 2,
				'validate' => $min_int( 1 ),
			),

			// Badge.
			'ms_show_badge'              => array(
				'type'    => 'boolean',
				'default' => true,
			),

			// Login & access messages.
			// Defaults are plain strings, not __(): schema() is read by
			// seed_defaults() on plugins_loaded (before init), and calling __()
			// there triggers the WP 6.7+ "translation loaded too early" notice.
			// These are admin-editable message defaults (a seeded translation would
			// freeze the locale at activation time anyway), so literals are correct.
			'ms_login_overlay_text'      => array(
				'type'    => 'string',
				'default' => 'Please log in to watch this video',
			),
			'ms_login_button_text'       => array(
				'type'    => 'string',
				'default' => 'Log In',
			),
			'ms_access_denied_text'      => array(
				'type'    => 'string',
				'default' => 'You do not have access to this video',
			),

			// Player controls.
			'ms_player_speed_control'    => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'ms_player_prevent_forward_seek' => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'ms_player_sticky'           => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'ms_player_keyboard'         => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'ms_player_resume'           => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'ms_player_endscreen'        => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'ms_player_endscreen_text'   => array(
				'type'    => 'string',
				'default' => '',
			),
			'ms_player_endscreen_url'    => array(
				'type'     => 'string',
				'default'  => '',
				'validate' => $url_or_empty,
			),

			// In-video ads (pre / mid / post-roll engine + WB Ad Manager bridge).
			// These give the site owner plugin-level control over how sponsor
			// ads run inside protected videos without touching any ad plugin.
			'ms_ads_enabled'             => array(
				'type'    => 'boolean',
				'default' => true,
			),
			// Mandatory viewing: when on, the Skip button never appears and the
			// viewer must watch each ad in full before the lesson continues
			// (CLE / compliance use case). Overrides the per-ad skip delay.
			'ms_ads_require_full_view'   => array(
				'type'    => 'boolean',
				'default' => false,
			),
			// Seconds before the Skip button unlocks (only when full-view is
			// off). 0 = skippable immediately. Clamped 0-60.
			'ms_ads_skip_after'          => array(
				'type'     => 'integer',
				'default'  => 5,
				'validate' => static function ( $v ) {
					return max( 0, min( 60, (int) $v ) );
				},
			),
			// Play a pre-roll before the video starts.
			'ms_ads_preroll'             => array(
				'type'    => 'boolean',
				'default' => true,
			),
			// How many mid-rolls to space across the video (0 disables). Clamped 0-10.
			'ms_ads_midroll_count'       => array(
				'type'     => 'integer',
				'default'  => 3,
				'validate' => static function ( $v ) {
					return max( 0, min( 10, (int) $v ) );
				},
			),
			// Show the ad-break marker ruler under the player.
			'ms_ads_show_markers'        => array(
				'type'    => 'boolean',
				'default' => true,
			),

			// Protection controls (right-click, keyboard, devtools).
			'ms_block_right_click'       => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'ms_block_keyboard'          => array(
				'type'    => 'boolean',
				'default' => false,
			),
			'ms_hide_source'             => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'ms_detect_devtools'         => array(
				'type'    => 'boolean',
				'default' => true,
			),
			'ms_pause_on_devtools'       => array(
				'type'    => 'boolean',
				'default' => false,
			),
			// Plain-string defaults (see the login-message note above re: __() and
			// the early-translation notice).
			'ms_devtools_title'          => array(
				'type'    => 'string',
				'default' => 'Developer Tools Detected',
			),
			'ms_devtools_message'        => array(
				'type'    => 'string',
				'default' => 'Please close developer tools to continue watching this video.',
			),
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
	 * Pipeline:
	 *   1. Reject unknown keys (return `null`).
	 *   2. Type-cast (boolean/integer/float/string with sanitize_textarea_field).
	 *   3. Apply the per-field `validate` callable from the schema, if any.
	 *      The validator must return the (possibly clamped/normalised) value
	 *      or `null` to reject.
	 *
	 * `null` propagates up: `SettingsController::update_settings()` skips
	 * `update_option()` for `null` values, so the existing stored value sticks.
	 *
	 * @param string $key   Option name.
	 * @param mixed  $value Raw value from a REST request.
	 * @return mixed|null Sanitized value, or null if the key is unknown or the input is invalid.
	 */
	public static function sanitize( string $key, mixed $value ): mixed {
		$schema = self::schema();
		if ( ! isset( $schema[ $key ] ) ) {
			return null;
		}

		$entry     = $schema[ $key ];
		$sanitized = match ( $entry['type'] ) {
			'boolean' => (bool) $value,
			'integer' => (int) $value,
			'float'   => (float) $value,
			default   => sanitize_textarea_field( (string) $value ),
		};

		if ( isset( $entry['validate'] ) && is_callable( $entry['validate'] ) ) {
			$validated = ( $entry['validate'] )( $sanitized );
			if ( null === $validated ) {
				return null;
			}
			return $validated;
		}

		return $sanitized;
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
			// The login gate the client applies before it will call
			// /session/start. Without this key the wrapper fell back to
			// gating on `isLoggedIn` alone, so turning the setting OFF
			// changed nothing for a guest: the player showed the login
			// overlay and returned before the permissive server decision
			// was ever reached. The setting was inert for exactly the case
			// it exists to serve - a public marketing video with analytics.
			'requireLogin' => (bool) self::get( 'ms_require_login' ),
			'interval'   => 30000,
			'player'     => array(
				'speedControl'  => self::get( 'ms_player_speed_control' ),
				'preventForwardSeek' => self::get( 'ms_player_prevent_forward_seek' ),
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
