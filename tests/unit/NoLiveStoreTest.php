<?php
/**
 * The test suite never reaches the live store (tests/bootstrap.php).
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use WP_UnitTestCase;

/**
 * Fails if the bootstrap guard is removed: the preset activation would run as
 * http://example.org against the live store on every CI job.
 */
class NoLiveStoreTest extends WP_UnitTestCase {

	/**
	 * Both store hosts are refused and the licence helper gets no reply.
	 */
	public function test_no_licence_call_reaches_the_live_store(): void {
		$this->assertTrue( defined( 'MEDIASHIELD_NO_LICENSE_PHONE_HOME' ) && MEDIASHIELD_NO_LICENSE_PHONE_HOME );

		foreach ( array( 'https://wbcomdesigns.com/', 'https://updates.wbcomdesigns.com/' ) as $url ) {
			$response = wp_remote_get( $url );
			$this->assertWPError( $response, $url );
			$this->assertSame( 'mediashield_tests_no_store', $response->get_error_code(), $url );
		}

		$this->assertFalse(
			mediashield_licence_request(
				array(
					'edd_action' => 'check_license',
					'item_id'    => 1661218,
				)
			)
		);
	}
}
