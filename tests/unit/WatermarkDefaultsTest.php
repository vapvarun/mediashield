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

		$this->assertSame( "Tom & Jerry's \u{00B7} " . get_userdata( $user )->user_email, $config['text'] ?? null );
	}

	/**
	 * Card 10530930695. Fails on b75cb14: the watermark was "Name · IP" for
	 * every viewer, with no email and no way to leave the IP out.
	 */
	public function test_default_watermark_is_name_and_email_and_ip_is_opt_in(): void {
		$_SERVER['REMOTE_ADDR'] = '198.51.100.23';
		delete_option( 'ms_watermark_show_ip' );
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
		$this->assertSame( "Aisha Khan \u{00B7} aisha@example.com", $config['text'] ?? null );
		$this->assertSame( '', $config['ip'] ?? null, 'No IP unless the owner turns it on.' );

		update_option( 'ms_watermark_show_ip', 1 );
		$this->assertSame( '198.51.100.23', $start()['ip'] ?? null );
		delete_option( 'ms_watermark_show_ip' );
	}
}
