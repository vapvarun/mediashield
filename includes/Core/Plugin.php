<?php
/**
 * Main plugin singleton — registers all hooks.
 *
 * @package MediaShield\Core
 */

namespace MediaShield\Core;

use MediaShield\CPT\VideoPostType;
use MediaShield\CPT\PlaylistPostType;
use MediaShield\CPT\Thumbnail;
use MediaShield\REST\TagController;
use MediaShield\REST\SessionController;

class Plugin {

	/** @var self|null */
	private static ?self $instance = null;

	/**
	 * Get the singleton instance.
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Bootstrap hooks.
	 */
	private function __construct() {
		// CPTs.
		VideoPostType::register();
		PlaylistPostType::register();
		Thumbnail::register();

		// REST API.
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );

		// Admin menu.
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );

		// Frontend assets.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );

		// Admin assets.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// Single video template.
		add_filter( 'single_template', array( $this, 'video_template' ) );

		/**
		 * Fires after MediaShield core has loaded.
		 *
		 * @since 1.0.0
		 */
		do_action( 'mediashield_loaded' );
	}

	/**
	 * Register REST API routes.
	 */
	public function register_rest_routes(): void {
		( new TagController() )->register_routes();
		( new SessionController() )->register_routes();
	}

	/**
	 * Register the top-level admin menu for the SPA.
	 */
	public function register_admin_menu(): void {
		add_menu_page(
			__( 'MediaShield', 'mediashield' ),
			__( 'MediaShield', 'mediashield' ),
			'manage_options',
			'mediashield',
			array( $this, 'render_admin_page' ),
			'dashicons-video-alt3',
			30
		);
	}

	/**
	 * Render the admin SPA root element.
	 */
	public function render_admin_page(): void {
		echo '<div id="mediashield-admin-root"></div>';
		echo '<noscript>' . esc_html__( 'JavaScript is required for the MediaShield admin dashboard.', 'mediashield' ) . '</noscript>';
	}

	/**
	 * Enqueue frontend assets (stub for later tasks).
	 */
	public function enqueue_frontend_assets(): void {
		// Player, watermark, tracker, protection scripts will be enqueued in Task 5.
	}

	/**
	 * Enqueue admin assets (stub for later tasks).
	 */
	public function enqueue_admin_assets( string $hook_suffix ): void {
		// Admin SPA bundle will be enqueued in Task 10.
	}

	/**
	 * Load custom single template for mediashield_video.
	 *
	 * @param string $template Default template path.
	 * @return string
	 */
	public function video_template( string $template ): string {
		if ( is_singular( 'mediashield_video' ) ) {
			$custom = MEDIASHIELD_PATH . 'templates/single-mediashield_video.php';
			if ( file_exists( $custom ) ) {
				return $custom;
			}
		}
		return $template;
	}

	/** Prevent cloning. */
	private function __clone() {}

	/** Prevent unserialization. */
	public function __wakeup() {
		throw new \Exception( 'Cannot unserialize singleton.' );
	}
}
