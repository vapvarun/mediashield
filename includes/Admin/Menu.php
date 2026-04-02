<?php
/**
 * Admin menu and React SPA asset enqueuing.
 *
 * Registers the top-level MediaShield menu and enqueues the React admin
 * bundle only on MediaShield admin pages.
 *
 * @package MediaShield\Admin
 */

namespace MediaShield\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Menu
 *
 * Admin menu and React SPA asset enqueuing.
 *
 * @since 1.0.0
 */
class Menu {

	/**
	 * Register hooks.
	 */
	public static function register(): void {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
	}

	/**
	 * Register the top-level admin menu.
	 */
	public static function register_menu(): void {
		add_menu_page(
			__( 'MediaShield', 'mediashield' ),
			__( 'MediaShield', 'mediashield' ),
			'manage_options',
			'mediashield',
			array( __CLASS__, 'render_page' ),
			'dashicons-video-alt3',
			30
		);
	}

	/**
	 * Render the admin SPA root.
	 */
	public static function render_page(): void {
		// Hardcoded HTML containers — no dynamic content, safe to output directly.
		echo '<div id="mediashield-admin-root" class="mediashield-admin"></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static HTML.
		echo '<noscript><p style="padding:20px;">';
		echo esc_html__( 'JavaScript is required for the MediaShield admin dashboard.', 'mediashield' );
		echo '</p></noscript>';
	}

	/**
	 * Enqueue admin SPA assets only on MediaShield pages.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public static function enqueue_admin_assets( string $hook_suffix ): void {
		if ( 'toplevel_page_mediashield' !== $hook_suffix ) {
			return;
		}

		$ver       = MEDIASHIELD_VERSION;
		$build_dir = MEDIASHIELD_PATH . 'build/admin/';
		$build_url = MEDIASHIELD_URL . 'build/admin/';

		// Load asset manifest if available.
		$asset_file = $build_dir . 'index.asset.php';
		$asset      = file_exists( $asset_file ) ? require $asset_file : array(
			'dependencies' => array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n', 'wp-hooks', 'wp-notices', 'wp-dom-ready' ),
			'version'      => $ver,
		);

		wp_enqueue_script(
			'mediashield-admin',
			$build_url . 'index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		// CSS: webpack outputs index.css (not style-index.css).
		$css_file = file_exists( $build_dir . 'style-index.css' ) ? 'style-index.css' : 'index.css';
		wp_enqueue_style(
			'mediashield-admin',
			$build_url . $css_file,
			array( 'wp-components' ),
			$ver
		);

		// Localize config.
		wp_localize_script(
			'mediashield-admin',
			'mediashieldAdmin',
			array(
				'restUrl'     => rest_url( 'mediashield/v1/' ),
				'wpRestUrl'   => rest_url( 'wp/v2/' ),
				'nonce'       => wp_create_nonce( 'wp_rest' ),
				'version'     => $ver,
				'userId'      => get_current_user_id(),
				'adminUrl'    => admin_url(),
				'siteUrl'     => home_url(),
				'isProActive' => defined( 'MEDIASHIELD_PRO_VERSION' ),
			)
		);

		// Load script translations.
		wp_set_script_translations( 'mediashield-admin', 'mediashield' );
	}
}
