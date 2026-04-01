<?php
/**
 * Setup wizard — redirects on first activation, renders wizard page.
 *
 * The wizard is a React component rendered within its own admin page
 * (no menu item — accessed via redirect only). Each step auto-saves
 * to /settings. Finishing sets ms_wizard_completed = true.
 *
 * @package MediaShield\Admin
 */

namespace MediaShield\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SetupWizard
 *
 * Setup wizard for first-time plugin activation.
 *
 * @since 1.0.0
 */
class SetupWizard {

	/**
	 * Register hooks.
	 */
	public static function register(): void {
		add_action( 'admin_init', array( __CLASS__, 'maybe_redirect' ) );
		add_action( 'admin_menu', array( __CLASS__, 'register_page' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );

		// REST endpoint for wizard completion.
		add_action(
			'rest_api_init',
			function () {
				register_rest_route(
					'mediashield/v1',
					'/wizard/complete',
					array(
						'methods'             => 'POST',
						'callback'            => function () {
							update_option( 'ms_wizard_completed', true );
							return rest_ensure_response( array( 'success' => true ) );
						},
						'permission_callback' => function () {
							return current_user_can( 'manage_options' );
						},
					)
				);
			}
		);
	}

	/**
	 * Redirect to wizard on first activation.
	 */
	public static function maybe_redirect(): void {
		if ( ! get_transient( 'ms_activation_redirect' ) ) {
			return;
		}

		delete_transient( 'ms_activation_redirect' );

		// Don't redirect during bulk activation, AJAX, or CLI.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Activation redirect check, no data processing.
		if ( wp_doing_ajax() || wp_doing_cron() || defined( 'WP_CLI' ) || isset( $_GET['activate-multi'] ) ) {
			return;
		}

		// Don't redirect if wizard already completed.
		if ( get_option( 'ms_wizard_completed' ) ) {
			return;
		}

		wp_safe_redirect( admin_url( 'admin.php?page=mediashield-wizard' ) );
		exit;
	}

	/**
	 * Register the wizard page (hidden — no menu item).
	 */
	public static function register_page(): void {
		add_submenu_page(
			'', // Hidden — no parent menu (empty string avoids null deprecation).
			__( 'MediaShield Setup', 'mediashield' ),
			__( 'Setup', 'mediashield' ),
			'manage_options',
			'mediashield-wizard',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Render the wizard root element.
	 */
	public static function render_page(): void {
		// Hardcoded HTML containers — no dynamic content, safe to output directly.
		echo '<div id="mediashield-wizard-root" class="mediashield-wizard"></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Static HTML.
		echo '<noscript><p style="padding:20px;">';
		echo esc_html__( 'JavaScript is required for the setup wizard.', 'mediashield' );
		echo '</p></noscript>';
	}

	/**
	 * Enqueue wizard assets on the wizard page.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 */
	public static function enqueue_assets( string $hook_suffix ): void {
		if ( 'admin_page_mediashield-wizard' !== $hook_suffix ) {
			return;
		}

		$ver       = MEDIASHIELD_VERSION;
		$build_dir = MEDIASHIELD_PATH . 'build/admin/';
		$build_url = MEDIASHIELD_URL . 'build/admin/';

		// Reuse the admin SPA bundle (wizard is a component within it).
		$asset_file = $build_dir . 'index.asset.php';
		$asset      = file_exists( $asset_file ) ? require $asset_file : array(
			'dependencies' => array( 'wp-element', 'wp-components', 'wp-api-fetch', 'wp-i18n' ),
			'version'      => $ver,
		);

		wp_enqueue_script(
			'mediashield-wizard',
			$build_url . 'index.js',
			$asset['dependencies'],
			$asset['version'],
			true
		);

		$css_file = file_exists( $build_dir . 'style-index.css' ) ? 'style-index.css' : 'index.css';
		wp_enqueue_style(
			'mediashield-wizard',
			$build_url . $css_file,
			array( 'wp-components' ),
			$ver
		);

		wp_localize_script(
			'mediashield-wizard',
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
				'isWizard'    => true,
			)
		);
	}
}
