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
use MediaShield\Player\PlayerWrapper;
use MediaShield\Block\VideoBlock;
use MediaShield\Block\PlaylistBlock;
use MediaShield\Block\Shortcode;
use MediaShield\REST\PlaylistController;
use MediaShield\REST\UploadController;
use MediaShield\REST\SettingsController;
use MediaShield\REST\AnalyticsController;
use MediaShield\Admin\Menu;
use MediaShield\Admin\SetupWizard;
use MediaShield\Core\Assets;

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

		// Gutenberg blocks + shortcode.
		VideoBlock::register();
		PlaylistBlock::register();
		Shortcode::register();

		// Player wrapper (output buffer for video detection + wrapping).
		PlayerWrapper::register();

		// Frontend assets (JS/CSS for player, watermark, tracker, protection).
		Assets::register();

		// Admin menu + SPA assets.
		Menu::register();

		// Setup wizard (first activation redirect).
		SetupWizard::register();

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
		( new PlaylistController() )->register_routes();
		( new UploadController() )->register_routes();
		( new SettingsController() )->register_routes();
		( new AnalyticsController() )->register_routes();
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
