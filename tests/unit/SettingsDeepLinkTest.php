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

		// The committed bundle carries it too.
		$this->assertStringContainsString( '#/settings/', $this->source( 'build/admin/index.js' ) );
	}

	/**
	 * Lesson Completion, the setting Learnomy links to, is in the Integrations group.
	 */
	public function test_lesson_completion_is_in_the_integrations_section(): void {
		$this->assertMatchesRegularExpression( "/group=\"integrations\"\\s*title=\\{ __\\( 'Lesson Completion'/", $this->source( 'src/admin/pages/Settings.js' ) );
	}
}
