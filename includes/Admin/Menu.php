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
		add_action( 'admin_notices', array( __CLASS__, 'maybe_show_pro_notice' ) );
		add_action( 'wp_ajax_ms_dismiss_pro_notice', array( __CLASS__, 'dismiss_pro_notice' ) );
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

	/**
	 * Show a dismissible Pro upsell notice after 7 days.
	 *
	 * Only displays on the MediaShield admin page and only when Pro is not active.
	 */
	public static function maybe_show_pro_notice(): void {
		if ( defined( 'MEDIASHIELD_PRO_VERSION' ) ) {
			return;
		}

		if ( get_option( 'ms_pro_notice_dismissed' ) ) {
			return;
		}

		$activated = get_option( 'ms_activated_at' );
		if ( ! $activated ) {
			update_option( 'ms_activated_at', time() );
			return;
		}

		// Show after 7 days.
		if ( time() - (int) $activated < 7 * DAY_IN_SECONDS ) {
			return;
		}

		// Only on MediaShield admin pages.
		$screen = get_current_screen();
		if ( ! $screen || 'toplevel_page_mediashield' !== $screen->base ) {
			return;
		}

		$nonce = wp_create_nonce( 'ms_dismiss_pro' );
		?>
		<div class="notice notice-info is-dismissible ms-pro-notice" data-nonce="<?php echo esc_attr( $nonce ); ?>">
			<p>
				<strong><?php esc_html_e( 'Unlock the full power of MediaShield', 'mediashield' ); ?></strong> &mdash;
				<?php esc_html_e( 'DRM encryption, heatmap analytics, LMS integration, email gate, and more.', 'mediashield' ); ?>
				<a href="https://wbcomdesigns.com/downloads/mediashield-pro/" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Get MediaShield Pro', 'mediashield' ); ?> &rarr;</a>
			</p>
		</div>
		<script>
		jQuery(function($){
			$(document).on('click', '.ms-pro-notice .notice-dismiss', function(){
				$.post(ajaxurl, { action: 'ms_dismiss_pro_notice', nonce: $(this).closest('.ms-pro-notice').data('nonce') });
			});
		});
		</script>
		<?php
	}

	/**
	 * AJAX handler: dismiss the Pro upsell notice permanently.
	 */
	public static function dismiss_pro_notice(): void {
		check_ajax_referer( 'ms_dismiss_pro', 'nonce' );
		update_option( 'ms_pro_notice_dismissed', true );
		wp_send_json_success();
	}
}
