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
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_frontend' ) );

		// Listen for Shaka Player requests from Renderer/PlayerWrapper.
		add_action(
			'mediashield_needs_shaka',
			function () {
				self::$needs_shaka = true;
			}
		);
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
			array(),
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

		// Player styles.
		wp_register_style(
			'mediashield-player',
			$url . 'assets/css/player.css',
			array(),
			$ver
		);

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
	}
}
