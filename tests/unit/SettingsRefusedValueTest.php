<?php
/**
 * A refused settings value answers 400 with the reason (Basecamp 10500683933).
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Fails before: PUT a decimal for a whole-number setting kept the old value
 * and answered 200 with no message.
 */
class SettingsRefusedValueTest extends WP_UnitTestCase {

	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		do_action( 'rest_api_init' );
		update_option( 'ms_max_concurrent_streams', 2 );
		update_option( 'ms_watermark_swap_interval', 30 );
	}

	private function put( array $data ) {
		$request = new WP_REST_Request( 'PUT', '/mediashield/v1/settings' );
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $data ) );
		return rest_do_request( $request );
	}

	public function test_decimal_for_a_whole_number_is_a_400_and_saves_nothing(): void {
		$response = $this->put(
			array(
				'ms_watermark_swap_interval' => 45,
				'ms_max_concurrent_streams'  => 2.5,
			)
		);

		$this->assertSame( 400, $response->get_status() );
		$this->assertStringContainsString( 'ms_max_concurrent_streams needs a whole number', $response->get_data()['message'] );
		$this->assertSame( array( 'ms_max_concurrent_streams' ), $response->get_data()['data']['refused'] );
		$this->assertSame( 2, (int) get_option( 'ms_max_concurrent_streams' ), 'The old value stays.' );
		$this->assertSame( 30, (int) get_option( 'ms_watermark_swap_interval' ), 'Nothing in the request is saved.' );
	}

	public function test_valid_values_and_unknown_keys_still_save(): void {
		$response = $this->put(
			array(
				'ms_max_concurrent_streams' => 3,
				'not_a_mediashield_setting' => 'x',
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 3, (int) get_option( 'ms_max_concurrent_streams' ) );
	}
}
