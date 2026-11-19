<?php
/**
 * Regression test for Basecamp 10526281691 (1.4.3).
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\Player\Protection;
use WP_UnitTestCase;

/**
 * A Strict video never prints its raw file address, whatever Hide Video Source says.
 */
class StrictHidesSourceTest extends WP_UnitTestCase {

	/**
	 * Fails on 1.4.2: with Hide Video Source off, a Strict video printed its file URL.
	 */
	public function test_strict_video_hides_its_source_with_the_setting_off(): void {
		update_option( 'ms_hide_source', false );
		$uploads = wp_upload_dir();
		$path    = trailingslashit( $uploads['path'] ) . 'strict-' . wp_generate_password( 6, false ) . '.mp4';
		file_put_contents( $path, str_repeat( 'x', 64 ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$url = trailingslashit( $uploads['url'] ) . wp_basename( $path );

		$strict = self::factory()->post->create(
			array(
				'post_type'   => 'mediashield_video',
				'post_status' => 'publish',
				'meta_input'  => array(
					'_ms_platform'         => 'self',
					'_ms_source_url'       => $url,
					'_ms_protection_level' => 'strict',
				),
			)
		);
		$standard = self::factory()->post->create(
			array(
				'post_type'   => 'mediashield_video',
				'post_status' => 'publish',
				'meta_input'  => array(
					'_ms_platform'         => 'self',
					'_ms_source_url'       => $url,
					'_ms_protection_level' => 'standard',
				),
			)
		);

		$urls = Protection::filter_player_urls( $strict, 'self', $url, '' );
		$this->assertSame( '', $urls['source_url'] );
		$this->assertStringContainsString( 'mediashield/v1/stream/' . $strict, urldecode( $urls['stream_url'] ) );

		// Standard still follows the setting.
		$this->assertSame( $url, Protection::filter_player_urls( $standard, 'self', $url, '' )['source_url'] );
		wp_delete_file( $path );
	}
}
