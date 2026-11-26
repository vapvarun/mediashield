<?php
/**
 * Regression test for the Settings section deep link (Learnomy card 10533275569).
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use WP_UnitTestCase;

/**
 * `#/settings/<section>` opens Settings at that section, in the source and in
 * the committed bundle. The browser walk is journey admin-menu-groups step 4.
 */
class SettingsDeepLinkTest extends WP_UnitTestCase {

	/**
	 * Read a plugin file.
	 *
	 * @param string $path Path from the plugin root.
	 * @return string
	 */
	private function source( string $path ): string {
		return (string) file_get_contents( dirname( __DIR__, 2 ) . '/' . $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
	}

	/**
	 * Fails on bfb96ac: the router matched the hash exactly (so
	 * #/settings/integrations fell back to the Dashboard) and Settings never
	 * read a section from the hash.
	 */
	public function test_a_route_answers_its_sub_paths_and_settings_reads_the_section(): void {
		$app = $this->source( 'src/admin/App.js' );
		$this->assertStringContainsString( "currentHash.startsWith( r.hash + '/' )", $app );
		$this->assertStringContainsString( '<ErrorBoundary key={ routeHash }>', $app );

		$settings = $this->source( 'src/admin/pages/Settings.js' );
		$this->assertStringContainsString( 'const linkedGroup = () =>', $settings );
		$this->assertStringContainsString( "window.addEventListener( 'hashchange', follow )", $settings );
		// Pro's sections join the same list the link is checked against.
		$this->assertStringContainsString( "applyFilters( 'mediashield_settings_sections'", $settings );
		$this->assertStringContainsString( '<SettingsNav groups={ navGroups } />', $settings );

		// The committed bundle carries it too. Labelled change (release gate
		// P2): screens are lazy chunks, so the entry and its chunks are read.
		$built = $this->source( 'build/admin/index.js' );
		foreach ( (array) glob( dirname( __DIR__, 2 ) . '/build/admin/chunks/*.js' ) as $chunk ) {
			$built .= (string) file_get_contents( $chunk ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		}
		$this->assertStringContainsString( '#/settings/', $built );
	}

	/**
	 * Lesson Completion, the setting Learnomy links to, is in the Integrations group.
	 */
	public function test_lesson_completion_is_in_the_integrations_section(): void {
		// Test change (Basecamp 10545260577): a card's title is its nav item's
		// label, so the card reads it from the group list instead of repeating it.
		$settings = $this->source( 'src/admin/pages/Settings.js' );
		$this->assertStringContainsString( "{ key: 'integrations', label: __( 'Lesson completion', 'mediashield' ) }", $settings );
		$this->assertMatchesRegularExpression( "/group=\"integrations\"\s*title=\{ groupLabel\( 'integrations' \) \}/", $settings );
	}

	/**
	 * Coordinator decision on Basecamp 10545260590: no hidden rule. While
	 * LearnDash's required % is 0 the stored completion % still decides
	 * (LearnDashAdapter::completion_pct()), so Settings shows it.
	 */
	public function test_learndash_stored_completion_pct_is_shown_while_it_applies(): void {
		$settings = $this->source( 'src/admin/pages/Settings.js' );
		$this->assertStringContainsString( "( 'learndash' !== lmsName || 0 === Number( settings?.ms_lms_require_watch_pct ?? 0 ) )", $settings );
		$this->assertStringContainsString( 'LearnDash lessons complete at (while the required % above is 0)', $settings );
	}
}
