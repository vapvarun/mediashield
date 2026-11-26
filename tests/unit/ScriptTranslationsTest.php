<?php
/**
 * Admin JS strings translate in the entry and in every lazy chunk.
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use WP_UnitTestCase;

/**
 * Fails before: WordPress looked only for the JSON named after
 * build/admin/index.js, while `wp i18n make-json` names each file after the
 * source file in the .po (src/...), so no admin string translated, and the
 * lazy screens' chunks are never enqueued at all.
 */
class ScriptTranslationsTest extends WP_UnitTestCase {

	/**
	 * Temporary languages folder.
	 *
	 * @var string
	 */
	private $dir;

	public function set_up(): void {
		parent::set_up();
		\MediaShield\Core\Assets::register();
		$this->dir = get_temp_dir() . 'ms-l10n-' . wp_generate_password( 6, false );
		wp_mkdir_p( $this->dir );
		// As make-json writes them: one file per source file.
		$this->json( 'src/admin/components/Sidebar.js', array( 'Analytics' => array( 'Analysen' ) ) );
		$this->json( 'src/admin/pages/Settings.js', array( 'Messages' => array( 'Meldungen' ) ) );
		add_filter( 'determine_locale', array( $this, 'german' ) );
		add_filter( 'mediashield_script_translation_dirs', array( $this, 'dirs' ) );
	}

	public function tear_down(): void {
		remove_filter( 'determine_locale', array( $this, 'german' ) );
		remove_filter( 'mediashield_script_translation_dirs', array( $this, 'dirs' ) );
		array_map( 'unlink', (array) glob( $this->dir . '/*' ) );
		rmdir( $this->dir );
		parent::tear_down();
	}

	public function german(): string {
		return 'de_DE';
	}

	public function dirs( $dirs ): array {
		return array( 'mediashield' => $this->dir ) + (array) $dirs;
	}

	private function json( string $source, array $messages ): void {
		$data = array(
			'domain'      => 'messages',
			'locale_data' => array(
				'messages' => array( '' => array( 'domain' => 'messages', 'lang' => 'de_DE' ) ) + $messages,
			),
			'source'      => $source,
		);
		file_put_contents( $this->dir . '/mediashield-de_DE-' . md5( $source ) . '.json', wp_json_encode( $data ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	public function test_the_admin_handle_gets_every_source_files_strings(): void {
		wp_register_script( 'mediashield-admin', plugins_url( 'mediashield/build/admin/index.js' ), array(), '1', true );
		$json = (string) load_script_textdomain( 'mediashield-admin', 'mediashield', $this->dir );

		$data = json_decode( $json, true );
		$this->assertSame( array( 'Analysen' ), $data['locale_data']['messages']['Analytics'] ?? null, 'A string from the entry.' );
		$this->assertSame( array( 'Meldungen' ), $data['locale_data']['messages']['Messages'] ?? null, 'A string from a lazy chunk.' );
	}

	public function test_another_domain_is_left_alone(): void {
		$this->assertNull( apply_filters( 'pre_load_script_translations', null, '/x.json', 'other', 'other-plugin' ) );
	}
}
