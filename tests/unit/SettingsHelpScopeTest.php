<?php
/**
 * Regression test for Basecamp 10531502283 and 10531502288 (1.4.3).
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use WP_UnitTestCase;

/**
 * The settings help and the settings reference describe what the code does.
 *
 * Both cards were words that promised more than the behaviour: Block Save
 * Shortcut "disables Ctrl+U, F12 and similar" while protection.js handles
 * only Ctrl/Cmd+S, and Skip Delay "0 = immediately" while a creative's own 0
 * means "use the site value" (AdManagerBridge::to_break(), AdSkipDelayTest).
 */
class SettingsHelpScopeTest extends WP_UnitTestCase {

	private function file( string $path ): string {
		return (string) file_get_contents( dirname( __DIR__, 2 ) . '/' . $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}

	private function row( string $doc, string $key ): string {
		foreach ( explode( "\n", $doc ) as $line ) {
			if ( 0 === strpos( $line, '| `' . $key . '`' ) ) {
				return $line;
			}
		}
		return '';
	}

	/**
	 * Fails on 8944051: the reference claimed Ctrl+U and F12 were blocked.
	 */
	public function test_block_keyboard_is_described_as_the_save_shortcut_only(): void {
		$js = $this->file( 'assets/js/protection.js' );
		$this->assertMatchesRegularExpression( "/e\\.key\\s*===\\s*'s'/", $js, 'protection.js still blocks only the save shortcut.' );
		$this->assertDoesNotMatchRegularExpression( "/'F12'|'u'\\s*\\)/", $js );

		$row = $this->row( $this->file( 'docs/developer/settings-reference.md' ), 'ms_block_keyboard' );
		$this->assertStringContainsString( 'Ctrl+S / Cmd+S', $row );
		$this->assertMatchesRegularExpression( '/not blocked/', $row, 'The reference says Ctrl+U and F12 are not blocked.' );
		$this->assertStringContainsString( 'Only the save shortcut', $this->file( 'src/admin/pages/Settings.js' ) );
	}

	/**
	 * Fails on 8944051: "0 = skippable immediately" with no word on a creative's own 0.
	 */
	public function test_skip_delay_explains_a_creatives_zero(): void {
		$row = $this->row( $this->file( 'docs/developer/settings-reference.md' ), 'ms_ads_skip_after' );
		$this->assertStringContainsString( 'blank', $row );
		$this->assertStringContainsString( 'uses this value', $row );
		$this->assertStringContainsString( 'an ad whose own delay is blank or 0 uses this value', $this->file( 'src/admin/pages/Settings.js' ) );
	}
}
