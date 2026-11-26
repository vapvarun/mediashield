<?php
/**
 * Frontend and admin asset registration and enqueuing.
 *
 * Enqueues vanilla JS for player/watermark/tracker/protection on frontend,
 * and React SPA bundle on admin pages (Task 10).
 *
 * @package MediaShield\Core
 */

namespace MediaShield\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use MediaShield\Core\Settings;
use MediaShield\Player\Watermark;
use MediaShield\Player\Protection;

/**
 * Class Assets
 *
 * Frontend and admin asset registration and enqueuing.
 *
 * @since 1.0.0
 */
class Assets {

	/**
	 * Whether Shaka Player is needed on this page.
	 *
	 * @var bool
	 */
	private static bool $needs_shaka = false;

	/**
	 * Register hooks.
	 */
	public static function register(): void {
		add_action( 'init', array( __CLASS__, 'register_request_reader' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_frontend' ) );
		add_filter( 'pre_load_script_translations', array( __CLASS__, 'script_translations' ), 10, 4 );
		// The block editor's Video and Playlist blocks make requests too.
		add_action(
			'enqueue_block_editor_assets',
			static function () {
				wp_enqueue_script( 'mediashield-request' );
			}
		);

		// Listen for adaptive-streaming requests from Renderer/PlayerWrapper.
		//
		// Renderer calls Assets::enqueue() *before* firing this, so setting the
		// flag alone would always be one step too late — which is exactly why
		// no streaming library ever loaded. Enqueue here as well; by this point
		// the handle is registered, and WordPress ignores a repeat enqueue.
		add_action(
			'mediashield_needs_shaka',
			function () {
				self::$needs_shaka = true;

				// No "is it registered yet" check: a block theme renders the
				// content before wp_enqueue_scripts, so the handle is not
				// registered yet and hls.js never loaded (Chrome then failed
				// every HLS video). WordPress keeps an enqueued handle and
				// prints it once register_frontend() registers it.
				wp_enqueue_script( 'mediashield-hls' );
			}
		);
	}

	/**
	 * Register the shared request-failure reader (window.mediashieldRequest).
	 *
	 * On every request, admin and front end, so any MediaShield or MediaShield
	 * Pro script can name 'mediashield-request' as a dependency. It turns a
	 * dropped connection or a non-JSON answer into one calm sentence and keeps
	 * a real server refusal's own message (assets/js/request.js).
	 */
	public static function register_request_reader(): void {
		wp_register_script(
			'mediashield-request',
			MEDIASHIELD_URL . 'assets/js/request.js',
			array( 'wp-i18n' ),
			MEDIASHIELD_VERSION,
			true
		);
		wp_set_script_translations( 'mediashield-request', 'mediashield', MEDIASHIELD_PATH . 'languages' );
	}

	/**
	 * Enqueue the admin app bundle (build/admin/index.js) under a screen's own handle.
	 *
	 * Every screen that loads the bundle goes through here, so each handle gets
	 * the same dependencies (from index.asset.php) and its script translations.
	 * WordPress loads a translation file per handle; a handle enqueued without
	 * them shows the bundle in English on a translated site.
	 *
	 * @param string $handle Script handle of the screen.
	 */
	public static function enqueue_admin_bundle( string $handle ): void {
		$asset_file = MEDIASHIELD_PATH . 'build/admin/index.asset.php';
		$asset      = file_exists( $asset_file ) ? require $asset_file : array(
			'dependencies' => array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n', 'wp-data', 'wp-hooks', 'wp-notices', 'wp-dom-ready' ),
			'version'      => MEDIASHIELD_VERSION,
		);

		wp_enqueue_script(
			$handle,
			MEDIASHIELD_URL . 'build/admin/index.js',
			array_merge( $asset['dependencies'], array( 'mediashield-request' ) ),
			(string) $asset['version'],
			true
		);
		wp_set_script_translations( $handle, 'mediashield', MEDIASHIELD_PATH . 'languages' );
	}

	/**
	 * Every JS translation of a MediaShield text domain, for any of its scripts.
	 *
	 * WordPress loads one JSON per enqueued script, named after the script's
	 * path (build/admin/index.js). `wp i18n make-json` names each JSON after
	 * the source file in the .po (src/admin/pages/Settings.js), so none of
	 * them matched, and the admin screens' lazy chunks are never enqueued at
	 * all. Answering `pre_load_script_translations` with all of the domain's
	 * JSON for the locale, merged, translates the entry and every chunk (they
	 * share the entry's wp.i18n data) and the block scripts, whatever way the
	 * JSON was generated. The plugin's languages folder is read first, then
	 * wp-content/languages/plugins, which wins.
	 *
	 * @since 1.4.4
	 *
	 * @param string|false|null $translations JSON so far (null: WordPress reads the file).
	 * @param string|false      $file         File WordPress would read.
	 * @param string            $handle       Script handle.
	 * @param string            $domain       Text domain.
	 * @return string|false|null
	 */
	public static function script_translations( $translations, $file, $handle, $domain ) {
		/**
		 * Text domains whose JS translations are merged, with their plugin
		 * languages folder. Pro adds `mediashield-pro`.
		 *
		 * @since 1.4.4
		 *
		 * @param array<string, string> $dirs Text domain => languages folder.
		 */
		$dirs = (array) apply_filters( 'mediashield_script_translation_dirs', array( 'mediashield' => MEDIASHIELD_PATH . 'languages' ) );
		if ( null !== $translations || ! isset( $dirs[ $domain ] ) ) {
			return $translations;
		}

		$locale = determine_locale();
		$files  = array_merge(
			(array) glob( trailingslashit( $dirs[ $domain ] ) . "{$domain}-{$locale}-*.json" ),
			(array) glob( WP_LANG_DIR . "/plugins/{$domain}-{$locale}-*.json" )
		);

		$merged = null;
		foreach ( array_filter( $files ) as $json_file ) {
			$data     = json_decode( (string) file_get_contents( $json_file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A local language file.
			$messages = is_array( $data['locale_data'] ?? null ) ? reset( $data['locale_data'] ) : null;
			if ( ! is_array( $messages ) ) {
				continue;
			}
			if ( null === $merged ) {
				$data['locale_data'] = array( 'messages' => $messages );
				$merged              = $data;
				continue;
			}
			$merged['locale_data']['messages'] = array_merge( $merged['locale_data']['messages'], $messages );
		}

		return null === $merged ? $translations : (string) wp_json_encode( $merged );
	}

	/**
	 * Register (but do not enqueue) frontend scripts and styles.
	 *
	 * Scripts are only enqueued when a shortcode, block, or output buffer
	 * detects video content on the page via Assets::enqueue().
	 */
	public static function register_frontend(): void {
		if ( is_admin() ) {
			return;
		}

		/**
		 * Filter whether MediaShield frontend assets should be registered.
		 *
		 * Return false to completely prevent asset registration.
		 *
		 * @since 1.1.0
		 *
		 * @param bool $register Whether to register assets. Default true.
		 */
		if ( ! apply_filters( 'mediashield_enqueue_frontend', true ) ) {
			return;
		}

		if ( ! get_option( 'ms_enabled', true ) ) {
			return;
		}

		// In WP_DEBUG / SCRIPT_DEBUG environments use the file mtime so browser
		// caches don't serve stale player JS during local iteration. Production
		// keeps the static plugin version for predictable cache keys.
		$dev = ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) || ( defined( 'WP_DEBUG' ) && WP_DEBUG );
		$ver = $dev ? (string) filemtime( MEDIASHIELD_PATH . 'assets/js/player-wrapper.js' ) : MEDIASHIELD_VERSION;
		$url = MEDIASHIELD_URL;

		// Player wrapper — scans DOM for embeds and initializes protection.
		wp_register_script(
			'mediashield-player-wrapper',
			$url . 'assets/js/player-wrapper.js',
			array( 'mediashield-request' ),
			$ver,
			true
		);

		// Watermark — canvas overlay rendering.
		wp_register_script(
			'mediashield-watermark',
			$url . 'assets/js/watermark.js',
			array( 'mediashield-player-wrapper' ),
			$ver,
			true
		);

		// Tracker — heartbeat session tracking.
		wp_register_script(
			'mediashield-tracker',
			$url . 'assets/js/tracker.js',
			array( 'mediashield-player-wrapper' ),
			$ver,
			true
		);

		// Protection — anti-download measures.
		wp_register_script(
			'mediashield-protection',
			$url . 'assets/js/protection.js',
			array( 'mediashield-player-wrapper' ),
			$ver,
			true
		);

		// In-video ad breaks — pre/mid/post-roll engine (enqueued by the
		// renderer only when a video actually has ad breaks).
		wp_register_script(
			'mediashield-ad-breaks',
			$url . 'assets/js/ad-breaks.js',
			array( 'mediashield-player-wrapper' ),
			$dev ? (string) filemtime( MEDIASHIELD_PATH . 'assets/js/ad-breaks.js' ) : MEDIASHIELD_VERSION,
			true
		);

		// HLS playback library.
		//
		// Safari plays .m3u8 in a plain <video>; Chrome, Firefox and Edge do
		// not, so without this an HLS source silently fails everywhere except
		// Safari. Bundled rather than loaded from a CDN so the player keeps
		// working behind a firewall and under a strict CSP, and registered
		// separately so it only downloads on pages that actually stream HLS.
		wp_register_script(
			'mediashield-hls',
			$url . 'assets/vendor/hls.min.js',
			array(),
			'1.5.20',
			true
		);

		// Player styles. Versioned by its own filemtime in dev so CSS-only edits
		// bust the browser cache (the shared $ver tracks player-wrapper.js and
		// would not change on a style-only edit).
		wp_register_style(
			'mediashield-player',
			$url . 'assets/css/player.css',
			array(),
			$dev ? (string) filemtime( MEDIASHIELD_PATH . 'assets/css/player.css' ) : MEDIASHIELD_VERSION
		);

		// The owner's brand colour, when set. Buttons, the active speed option,
		// focus rings and the playlist's current row all read this one token.
		$accent = (string) Settings::get( 'ms_player_accent_color' );
		if ( '' !== $accent ) {
			wp_add_inline_style( 'mediashield-player', '.ms-protected-player,.ms-playlist-player{--ms-accent:' . sanitize_hex_color( $accent ) . '}' );
		}

		// Localize config for all scripts. Player options + messages come from
		// the central Settings service; watermark/protection keep their own
		// shape because they have additional runtime-derived fields (IP, etc.).
		$config               = Settings::frontend_config();
		$config['watermark']  = Watermark::get_config();
		$config['protection'] = Protection::get_config();

		wp_localize_script( 'mediashield-player-wrapper', 'mediashieldConfig', $config );
	}

	/**
	 * Enqueue all registered MediaShield frontend assets.
	 *
	 * Called by shortcode render, block render, and output buffer when
	 * video content is detected on the page. Safe to call multiple times.
	 *
	 * @since 1.1.0
	 */
	public static function enqueue(): void {
		wp_enqueue_script( 'mediashield-player-wrapper' );
		wp_enqueue_script( 'mediashield-watermark' );
		wp_enqueue_script( 'mediashield-tracker' );
		wp_enqueue_script( 'mediashield-protection' );
		wp_enqueue_style( 'mediashield-player' );

		// Renderer and PlayerWrapper flag adaptive-streaming platforms before
		// this runs. The flag previously set nothing loading, so an HLS source
		// only ever played in Safari.
		if ( self::$needs_shaka ) {
			wp_enqueue_script( 'mediashield-hls' );
		}
	}
}
