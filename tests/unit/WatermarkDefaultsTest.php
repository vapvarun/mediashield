<?php
/**
 * The player gets the watermark defaults the Settings screen shows (card 10498012017).
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\Core\Settings;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * session/start watermark_config on a site that never saved the watermark.
 */
class WatermarkDefaultsTest extends WP_UnitTestCase {

	/**
	 * session/start commits its own transaction, so its rows outlive the
	 * test's rollback; remove them so later tests see no active sessions.
	 */
	public function tear_down(): void {
		global $wpdb;
		foreach ( $this->videos as $video ) {
			$wpdb->delete( "{$wpdb->prefix}ms_watch_sessions", array( 'video_id' => $video ) );
		}
		parent::tear_down();
	}

	/**
	 * Videos the test started sessions on.
	 *
	 * @var int[]
	 */
	private array $videos = array();

	/**
	 * With no stored rows, session/start sends the schema defaults.
	 */
	public function test_session_start_uses_the_settings_defaults(): void {
		delete_option( 'ms_watermark_opacity' );
		delete_option( 'ms_watermark_swap_interval' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$video          = self::factory()->post->create( array( 'post_type' => 'mediashield_video', 'post_status' => 'publish' ) );
		$this->videos[] = $video;

		$request = new WP_REST_Request( 'POST', '/mediashield/v1/session/start' );
		$request->set_body_params( array( 'video_id' => $video ) );
		$config = rest_do_request( $request )->get_data()['watermark_config'] ?? array();

		$this->assertSame( (float) Settings::get( 'ms_watermark_opacity' ), $config['opacity'] ?? null );
		$this->assertSame( (int) Settings::get( 'ms_watermark_swap_interval' ), $config['swap_interval'] ?? null );
		$this->assertSame( array( 0.5, 30 ), array( $config['opacity'], $config['swap_interval'] ), 'Defaults the Settings screen shows.' );
	}

	/**
	 * Card 10497708826: the name is painted as written, not as HTML entities.
	 */
	public function test_watermark_text_is_the_name_as_written(): void {
		$user = self::factory()->user->create( array( 'display_name' => "Tom & Jerry's" ) );
		wp_set_current_user( $user );
		$video          = self::factory()->post->create( array( 'post_type' => 'mediashield_video', 'post_status' => 'publish' ) );
		$this->videos[] = $video;

		$request = new WP_REST_Request( 'POST', '/mediashield/v1/session/start' );
		$request->set_body_params( array( 'video_id' => $video ) );
		$config = rest_do_request( $request )->get_data()['watermark_config'] ?? array();

		// Test change (Basecamp 10545260667): the email is opt-in since 1.4.4;
		// this test is about the name, which is always shown.
		$this->assertSame( "Tom & Jerry's", $config['text'] ?? null );
	}

	/**
	 * Card 10530930695, then 10545260667. Fails on b75cb14: the watermark was
	 * "Name · IP" for every viewer. Since 1.4.4 a new install shows the name
	 * only; email and IP are each the owner's choice.
	 */
	public function test_default_watermark_is_the_name_and_email_and_ip_are_opt_in(): void {
		$_SERVER['REMOTE_ADDR'] = '198.51.100.23';
		delete_option( 'ms_watermark_show_ip' );
		delete_option( 'ms_watermark_show_email' );
		$user = self::factory()->user->create(
			array(
				'display_name' => 'Aisha Khan',
				'user_email'   => 'aisha@example.com',
			)
		);
		wp_set_current_user( $user );
		$start = function () {
			$video          = self::factory()->post->create( array( 'post_type' => 'mediashield_video', 'post_status' => 'publish' ) );
			$this->videos[] = $video;
			$request        = new WP_REST_Request( 'POST', '/mediashield/v1/session/start' );
			$request->set_body_params( array( 'video_id' => $video ) );
			return rest_do_request( $request )->get_data()['watermark_config'] ?? array();
		};

		$config = $start();
		$this->assertSame( 'Aisha Khan', $config['text'] ?? null, 'No email unless the owner turns it on.' );
		$this->assertSame( '', $config['ip'] ?? null, 'No IP unless the owner turns it on.' );

		update_option( 'ms_watermark_show_email', 1 );
		$this->assertSame( "Aisha Khan \u{00B7} aisha@example.com", $start()['text'] ?? null );
		delete_option( 'ms_watermark_show_email' );

		update_option( 'ms_watermark_show_ip', 1 );
		$this->assertSame( '198.51.100.23', $start()['ip'] ?? null );
		delete_option( 'ms_watermark_show_ip' );
	}

	/**
	 * Card 10545260667: email starts off on a new install and stays on for a
	 * site that updates; a value the site stored is never touched.
	 */
	public function test_email_default_is_off_on_install_and_kept_on_update(): void {
		$db = get_option( 'ms_db_version' );

		delete_option( 'ms_watermark_show_email' );
		delete_option( 'ms_db_version' );
		Settings::seed_defaults();
		$this->assertFalse( Settings::get( 'ms_watermark_show_email' ), 'New install: name only.' );

		delete_option( 'ms_watermark_show_email' );
		update_option( 'ms_db_version', 12 );
		Settings::seed_defaults();
		$this->assertTrue( Settings::get( 'ms_watermark_show_email' ), 'Update: the email keeps showing.' );

		update_option( 'ms_watermark_show_email', '' );
		Settings::seed_defaults();
		$this->assertFalse( Settings::get( 'ms_watermark_show_email' ), 'A stored choice is kept.' );

		update_option( 'ms_db_version', $db );
	}
}
