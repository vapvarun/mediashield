<?php
/**
 * One protection model: four levels, the same words everywhere, Strict's extras belong to Strict.
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\Player\Protection;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Basecamp 10545260510.
 */
class ProtectionModelTest extends WP_UnitTestCase {

	/**
	 * Admin; the three old switches at their defaults (an untouched site).
	 */
	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		update_option( 'ms_hide_source', 1 );
		update_option( 'ms_detect_devtools', 1 );
		update_option( 'ms_pause_on_devtools', '' );
		delete_option( Protection::SWITCHES_KEPT );
	}

	/**
	 * The four levels, each with a label and the words every screen shows.
	 */
	public function test_four_levels_with_one_set_of_words(): void {
		$levels = Protection::levels();
		$this->assertSame( array( 'none', 'basic', 'standard', 'strict' ), array_keys( $levels ) );
		foreach ( $levels as $level ) {
			$this->assertNotSame( '', $level['label'] );
			$this->assertNotSame( '', $level['summary'] );
			$this->assertStringNotContainsStringIgnoringCase( 'login', $level['summary'], 'Login is not part of a level.' );
		}
		update_option( 'ms_default_protection', 'strict' );
		$this->assertSame( 'Site default (Strict)', Protection::site_default_label() );

		// None's words stay true about the site-wide right-click switch.
		update_option( 'ms_block_right_click', 1 );
		$this->assertStringContainsString( 'Right-click stays blocked', Protection::levels()['none']['summary'] );
		update_option( 'ms_block_right_click', '' );
		$this->assertStringNotContainsString( 'Right-click', Protection::levels()['none']['summary'] );
		update_option( 'ms_block_right_click', 1 );
	}

	/**
	 * An untouched site: source hiding and devtools detection are Strict's only.
	 */
	public function test_untouched_site_leaves_the_extras_to_strict(): void {
		$this->assertSame( array(), Protection::legacy_switches() );
		$config = Protection::get_config();
		$this->assertFalse( $config['hide_source'] );
		$this->assertFalse( $config['detect_devtools'] );
		$this->assertFalse( $config['pause_on_devtools'] );
		$this->assertFalse( Protection::site_hides_source() );
	}

	/**
	 * A site that changed a switch keeps its old site-wide behaviour, and Settings says so.
	 */
	public function test_changed_switches_keep_working_until_a_level_is_picked(): void {
		update_option( 'ms_pause_on_devtools', 1 );

		// The switches that are on keep applying site-wide.
		$this->assertSame( Protection::LEGACY_SWITCHES, Protection::legacy_switches() );
		$config = Protection::get_config();
		$this->assertTrue( $config['hide_source'] );
		$this->assertTrue( $config['detect_devtools'] );
		$this->assertTrue( $config['pause_on_devtools'] );

		$get = rest_do_request( new WP_REST_Request( 'GET', '/mediashield/v1/settings' ) )->get_data();
		$this->assertSame( Protection::LEGACY_SWITCHES, $get['ms_legacy_switches'] );

		// Picking a level moves the site onto the levels.
		$put = new WP_REST_Request( 'PUT', '/mediashield/v1/settings' );
		$put->set_header( 'Content-Type', 'application/json' );
		$put->set_body( wp_json_encode( array( 'ms_default_protection' => 'standard' ) ) );
		$after = rest_do_request( $put )->get_data();

		$this->assertSame( array(), $after['ms_legacy_switches'] );
		$this->assertFalse( Protection::get_config()['pause_on_devtools'] );
	}

	/**
	 * Other settings saved alone do not end a site's own switches.
	 */
	public function test_other_saves_keep_the_switches(): void {
		update_option( 'ms_hide_source', '' );
		$put = new WP_REST_Request( 'PUT', '/mediashield/v1/settings' );
		$put->set_header( 'Content-Type', 'application/json' );
		$put->set_body( wp_json_encode( array( 'ms_require_login' => false ) ) );
		rest_do_request( $put );

		// Hide source is off here, so only developer-tools detection keeps applying.
		$this->assertSame( array( 'ms_detect_devtools' ), Protection::legacy_switches() );
	}

	/**
	 * Coordinator decision: an update from 1.4.3 with the defaults untouched
	 * keeps a Standard video's file address hidden, until a level is picked.
	 */
	public function test_update_with_untouched_defaults_keeps_standard_hidden(): void {
		$uploads = wp_upload_dir();
		$path    = trailingslashit( $uploads['path'] ) . 'upgrade-' . wp_generate_password( 6, false ) . '.mp4';
		file_put_contents( $path, str_repeat( 'x', 64 ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$url      = trailingslashit( $uploads['url'] ) . wp_basename( $path );
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

		// A new install: Standard prints the pasted file's address.
		$this->assertSame( $url, Protection::filter_player_urls( $standard, 'self', $url, '' )['source_url'] );

		// The same site updating from 1.4.3 (DB version 13).
		$db = get_option( 'ms_db_version' );
		update_option( 'ms_db_version', 13 );
		\MediaShield\Core\Migrator::run();

		$this->assertSame( array( 'ms_hide_source', 'ms_detect_devtools' ), Protection::legacy_switches() );
		$this->assertSame( '', Protection::filter_player_urls( $standard, 'self', $url, '' )['source_url'], 'Hidden, as on 1.4.3.' );
		$this->assertTrue( Protection::get_config()['detect_devtools'] );

		// Picking a level moves it onto the levels.
		$put = new WP_REST_Request( 'PUT', '/mediashield/v1/settings' );
		$put->set_header( 'Content-Type', 'application/json' );
		$put->set_body( wp_json_encode( array( 'ms_default_protection' => 'standard' ) ) );
		rest_do_request( $put );
		$this->assertSame( array(), Protection::legacy_switches() );
		$this->assertSame( $url, Protection::filter_player_urls( $standard, 'self', $url, '' )['source_url'] );

		update_option( 'ms_db_version', $db );
		wp_delete_file( $path );
	}
}
