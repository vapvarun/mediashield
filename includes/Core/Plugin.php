<?php
/**
 * Main plugin singleton — registers all hooks.
 *
 * @package MediaShield\Core
 */

namespace MediaShield\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use MediaShield\CPT\VideoPostType;
use MediaShield\CPT\PlaylistPostType;
use MediaShield\CPT\Thumbnail;
use MediaShield\REST\TagController;
use MediaShield\REST\SessionController;
use MediaShield\Player\PlayerWrapper;
use MediaShield\Block\VideoBlock;
use MediaShield\Block\PlaylistBlock;
use MediaShield\Block\PlaylistShortcode;
use MediaShield\Block\Shortcode;
use MediaShield\REST\PlaylistController;
use MediaShield\REST\UploadController;
use MediaShield\REST\SettingsController;
use MediaShield\REST\AnalyticsController;
use MediaShield\REST\StreamController;
use MediaShield\REST\ProtectionController;
use MediaShield\Admin\HealthCheck;
use MediaShield\Admin\Menu;
use MediaShield\Admin\SetupWizard;
use MediaShield\Core\Assets;
use MediaShield\Cron\Cleanup;
use MediaShield\Privacy\PrivacyExporter;
use MediaShield\Privacy\PrivacyEraser;
use MediaShield\Block\MyVideosBlock;
use MediaShield\Embed\EmbedPage;
use MediaShield\Integrations\Learnomy as LearnomyIntegration;

/**
 * Class Plugin
 *
 * Main plugin singleton that registers all hooks.
 *
 * @since 1.0.0
 */
class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
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
		PlaylistShortcode::register();

		// Player wrapper (output buffer for video detection + wrapping).
		PlayerWrapper::register();

		// Frontend assets (JS/CSS for player, watermark, tracker, protection).
		Assets::register();

		// In-video ad breaks: optional bridge that sources video creatives from
		// WB Ad Manager (no-op when that plugin / its video ads are absent).
		( new \MediaShield\Integrations\AdManagerBridge() )->register();

		// Admin menu + SPA assets.
		Menu::register();

		// Setup wizard (first activation redirect).
		SetupWizard::register();

		// Site Health: asks the web server whether it will serve video files
		// directly, rather than assuming the .htaccess deny rule is honoured.
		// It is not, on nginx (BC#10231033764).
		HealthCheck::register();

		// Cron cleanup + video/playlist deletion cascade (Task 22).
		Cleanup::register();

		// GDPR privacy exporters/erasers (Task 23).
		PrivacyExporter::register();
		PrivacyEraser::register();

		// My Videos block + shortcode (Task 24).
		MyVideosBlock::register();

		// Single video template.

		// Signed embed page — the one way a client that cannot run PHP (a
		// native app, an LMS on another host) can play a protected video.
		EmbedPage::register();

		// Learnomy lessons: resolve the stored video ID into a playable URL on
		// the REST payload. No-op without Learnomy.
		LearnomyIntegration::register();

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
		( new StreamController() )->register_routes();
		( new ProtectionController() )->register_routes();
	}


	/** Prevent cloning. */
	private function __clone() {}

	/**
	 * Prevent unserialization.
	 *
	 * @throws \Exception Always.
	 */
	public function __wakeup() {
		throw new \Exception( 'Cannot unserialize singleton.' );
	}
}
