<?php
/**
 * One word per metric (Basecamp 10552391910): "Views" for the count shown,
 * "playback session" for one play, "watch history" for what is kept.
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use WP_UnitTestCase;

/**
 * No "viewing(s)" left in the translation catalog or the admin source.
 */
class NoViewingsWordingTest extends WP_UnitTestCase {

	/**
	 * Fails on a06b681: the catalog had "Viewings", "%d viewing", "Keep viewings for (months)" and more.
	 */
	public function test_no_viewing_strings_in_the_catalog_or_admin_source(): void {
		$root = dirname( __DIR__, 2 );
		$pot  = (string) file_get_contents( $root . '/languages/mediashield.pot' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		preg_match_all( '/^msgid(?:_plural)? "(.*\bviewings?\b.*)"$/mi', $pot, $found );
		$this->assertSame( array(), $found[1], 'The .pot still carries "viewing" strings.' );

		$hits = array();
		$it   = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $root . '/src', \FilesystemIterator::SKIP_DOTS ) );
		foreach ( $it as $file ) {
			if ( ! preg_match( '/\.(js|json)$/', $file->getFilename() ) ) {
				continue;
			}
			foreach ( file( $file->getPathname() ) as $n => $line ) {
				// Code comments may say what they like; strings may not.
				if ( preg_match( '#^\s*(//|\*|/\*)#', $line ) ) {
					continue;
				}
				if ( preg_match( '/[\'"][^\'"]*\bviewings?\b[^\'"]*[\'"]/i', $line ) ) {
					$hits[] = basename( $file->getPathname() ) . ':' . ( $n + 1 );
				}
			}
		}
		$this->assertSame( array(), $hits );
	}
}
