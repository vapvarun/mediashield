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

use MediaShield\Player\Watermark;
use MediaShield\Player\Protection;

class Assets {

	/**
	 * Register hooks.
	 */
	public static function register(): void {
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_frontend' ) );
	}

	/**
	 * Enqueue frontend scripts and styles for video protection.
	 */
	public static function enqueue_frontend(): void {
		if ( is_admin() ) {
			return;
		}

		if ( ! get_option( 'ms_enabled', true ) ) {
			return;
		}

		$ver = MEDIASHIELD_VERSION;
		$url = MEDIASHIELD_URL;

		// Player wrapper — scans DOM for embeds and initializes protection.
		wp_enqueue_script(
			'mediashield-player-wrapper',
			$url . 'assets/js/player-wrapper.js',
			array(),
			$ver,
			true
		);

		// Watermark — canvas overlay rendering.
		wp_enqueue_script(
			'mediashield-watermark',
			$url . 'assets/js/watermark.js',
			array( 'mediashield-player-wrapper' ),
			$ver,
			true
		);

		// Tracker — heartbeat session tracking.
		wp_enqueue_script(
			'mediashield-tracker',
			$url . 'assets/js/tracker.js',
			array( 'mediashield-player-wrapper' ),
			$ver,
			true
		);

		// Protection — anti-download measures.
		wp_enqueue_script(
			'mediashield-protection',
			$url . 'assets/js/protection.js',
			array( 'mediashield-player-wrapper' ),
			$ver,
			true
		);

		// Player styles.
		wp_enqueue_style(
			'mediashield-player',
			$url . 'assets/css/player.css',
			array(),
			$ver
		);

		// Localize config for all scripts.
		$user = wp_get_current_user();

		$config = array(
			'restUrl'       => rest_url( 'mediashield/v1/' ),
			'nonce'         => wp_create_nonce( 'wp_rest' ),
			'isLoggedIn'    => is_user_logged_in(),
			'userId'        => $user->ID,
			'loginUrl'      => wp_login_url( get_permalink() ),
			'interval'      => 30000, // Heartbeat interval in ms.
			'watermark'     => Watermark::get_config(),
			'protection'    => Protection::get_config(),
		);

		wp_localize_script( 'mediashield-player-wrapper', 'mediashieldConfig', $config );
	}
}
