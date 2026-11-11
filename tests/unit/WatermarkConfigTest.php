<?php
/**
 * The player's watermark config: one source, and the edges-only option.
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\Core\Settings;
use MediaShield\Player\Watermark;
use WP_UnitTestCase;

/**
 * BC#10498012081 (Keep the watermark at the edges).
 */
class WatermarkConfigTest extends WP_UnitTestCase {

	/**
	 * Off by default, so existing sites keep the centre position.
	 */
	public function test_edges_only_defaults_off(): void {
		delete_option( 'ms_watermark_edges_only' );

		$this->assertFalse( Watermark::get_config()['edges_only'] );
	}

	/**
	 * Fails before the fix: the key was not in the schema, so a save was ignored
	 * and the player never received it.
	 */
	public function test_edges_only_saves_and_reaches_the_player_config(): void {
		$this->assertTrue( Settings::sanitize( 'ms_watermark_edges_only', true ) );

		update_option( 'ms_watermark_edges_only', Settings::sanitize( 'ms_watermark_edges_only', true ) );

		$this->assertTrue( Watermark::get_config()['edges_only'] );
	}
}
