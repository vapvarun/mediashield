<?php
/**
 * Every screen that loads the admin app bundle loads its translations.
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\Core\Assets;
use WP_UnitTestCase;

/**
 * WordPress loads a script translation file per handle. The Setup Wizard and
 * the Playlist Items screen enqueued build/admin/index.js under their own
 * handles without translations, so they stayed English on a translated site.
 */
class AdminBundleTranslationsTest extends WP_UnitTestCase {

	/**
	 * The helper registers the translation domain and path for the handle it enqueues.
	 */
	public function test_helper_sets_script_translations(): void {
		Assets::register_request_reader();
		Assets::enqueue_admin_bundle( 'mediashield-test-screen', array( 'wp-i18n' ), '1' );

		$scripts = wp_scripts();
		$this->assertTrue( $scripts->query( 'mediashield-test-screen', 'enqueued' ) );
		$script = $scripts->registered['mediashield-test-screen'];
		$this->assertSame( 'mediashield', $script->textdomain );
		$this->assertStringEndsWith( '/languages', (string) $script->translations_path );
		$this->assertContains( 'mediashield-request', $script->deps );
	}

	/**
	 * Fails on 1.4.2 and earlier: the wizard and playlist handles enqueued the bundle directly.
	 * A new screen must call the helper, not wp_enqueue_script() on the bundle.
	 */
	public function test_no_screen_enqueues_the_bundle_without_the_helper(): void {
		$offenders = array();
		$iterator  = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( MEDIASHIELD_PATH . 'includes', \FilesystemIterator::SKIP_DOTS ) );

		foreach ( $iterator as $file ) {
			if ( 'php' !== $file->getExtension() || 'Assets.php' === $file->getFilename() ) {
				continue;
			}
			if ( preg_match( '/wp_(enqueue|register)_script\(\s*[^;]*build\/admin\/index\.js|wp_(enqueue|register)_script\(\s*[^;]*\$build_url\s*\.\s*\'index\.js\'/s', (string) file_get_contents( $file->getPathname() ) ) ) {
				$offenders[] = $file->getFilename();
			}
		}

		$this->assertSame( array(), $offenders, 'Enqueue the admin bundle through Assets::enqueue_admin_bundle().' );
	}
}
