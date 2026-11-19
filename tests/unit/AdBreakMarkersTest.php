<?php
/**
 * Regression test for Basecamp 10502007194 (1.4.3).
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\Integrations\AdManagerBridge;
use WP_UnitTestCase;

/**
 * The ad engine gets real booleans, so "Show Break Markers" off hides the ruler.
 */
class AdBreakMarkersTest extends WP_UnitTestCase {

	/**
	 * Fails on 1.4.2: wp_localize_script sent showMarkers as "", and the
	 * engine's `showMarkers === false` never matched.
	 */
	public function test_markers_off_reaches_the_engine_as_false(): void {
		if ( ! wp_script_is( 'mediashield-ad-breaks', 'registered' ) ) {
			wp_register_script( 'mediashield-ad-breaks', 'ad-breaks.js', array(), '1', true );
		}
		update_option( 'ms_ads_show_markers', 0 );
		update_option( 'ms_ads_require_full_view', 1 );

		( new AdManagerBridge() )->localize_tracking();

		$printed = (string) wp_scripts()->get_data( 'mediashield-ad-breaks', 'data' ) . implode( "\n", (array) wp_scripts()->get_data( 'mediashield-ad-breaks', 'before' ) );
		$this->assertStringContainsString( '"showMarkers":false', $printed );
		$this->assertStringContainsString( '"requireFullView":true', $printed );
	}
}
