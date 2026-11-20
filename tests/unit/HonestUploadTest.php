<?php
/**
 * Honest upload (Basecamp 10530929713).
 *
 * A file over the server limit said "No file provided", and a second request
 * for the same video while the first was still sending to Bunny created a
 * second Bunny video (one orphaned and billed) and a second queue row.
 *
 * @package MediaShield\Tests
 */

use MediaShield\Upload\Drivers\DriverInterface;
use MediaShield\Upload\UploadManager;

/**
 * Counts how often a driver was asked to upload.
 */
class Honest_Upload_Counting_Driver implements DriverInterface {

	/**
	 * Uploads started.
	 *
	 * @var int
	 */
	public static $calls = 0;

	public function get_name(): string {
		return 'counting';
	}

	public function upload( string $file_path, array $options = array() ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		++self::$calls;
		return array(
			'success'           => true,
			'video_id'          => (int) ( $options['attach_to'] ?? 0 ),
			'platform_video_id' => 'remote-' . self::$calls,
			'embed_url'         => '',
			'error'             => '',
		);
	}

	public function get_status( string $upload_id ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		return array();
	}

	public function delete( string $platform_video_id ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		return false;
	}

	public function get_embed_url( string $platform_video_id ): string { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		return '';
	}
}

/**
 * Upload refusals that tell the truth, and one remote video per upload.
 */
class HonestUploadTest extends WP_UnitTestCase {

	/**
	 * Admin, a video to fill in, and the counting driver.
	 *
	 * @var int
	 */
	private $video;

	public function set_up(): void {
		parent::set_up();
		$admin = self::factory()->user->create_and_get( array( 'role' => 'administrator' ) );
		$admin->add_cap( 'upload_mediashield' );
		wp_set_current_user( $admin->ID );
		$this->video = self::factory()->post->create( array( 'post_type' => 'mediashield_video' ) );
		Honest_Upload_Counting_Driver::$calls = 0;
		add_filter( 'mediashield_upload_drivers', array( $this, 'drivers' ) );
	}

	public function tear_down(): void {
		remove_filter( 'mediashield_upload_drivers', array( $this, 'drivers' ) );
		unset( $_SERVER['CONTENT_LENGTH'] );
		parent::tear_down();
	}

	/**
	 * Add the counting driver.
	 *
	 * @param array $drivers Drivers.
	 * @return array
	 */
	public function drivers( $drivers ): array {
		$drivers['counting'] = Honest_Upload_Counting_Driver::class;
		return $drivers;
	}

	/**
	 * POST /upload/init for the test video with a small file.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	private function upload() {
		$tmp = wp_tempnam( 'ms-upload' );
		file_put_contents( $tmp, str_repeat( 'x', 1024 ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents

		$request = new WP_REST_Request( 'POST', '/mediashield/v1/upload/init' );
		$request->set_param( 'driver', 'counting' );
		$request->set_param( 'video_id', $this->video );
		$request->set_file_params(
			array(
				'file' => array(
					'name'     => 'lesson.mp4',
					'type'     => 'video/mp4',
					'tmp_name' => $tmp,
					'error'    => UPLOAD_ERR_OK,
					'size'     => 1024,
				),
			)
		);
		return rest_do_request( $request );
	}

	/**
	 * Fails before the fix: a second request for the same video while the
	 * first holds it went straight to the driver, so Bunny got two videos.
	 */
	public function test_second_upload_for_the_same_video_never_reaches_the_platform(): void {
		// The first upload, still running, in another request (another DB connection).
		$other = new mysqli( DB_HOST, DB_USER, DB_PASSWORD, DB_NAME );
		$name  = UploadManager::lock_name( '', array( 'attach_to' => $this->video ) );
		$held  = $other->query( "SELECT GET_LOCK('" . $other->real_escape_string( $name ) . "', 0)" )->fetch_row()[0];
		$this->assertSame( '1', (string) $held, 'The other request holds the upload.' );

		$response = $this->upload();

		$this->assertSame( 409, $response->get_status() );
		$this->assertSame( 'upload_in_progress', $response->get_data()['code'] );
		$this->assertSame( 0, Honest_Upload_Counting_Driver::$calls, 'No second remote video is created.' );

		$other->query( "SELECT RELEASE_LOCK('" . $other->real_escape_string( $name ) . "')" );
		$other->close();

		// Once the first upload is over, the same video uploads normally, once.
		$this->assertSame( 201, $this->upload()->get_status() );
		$this->assertSame( 1, Honest_Upload_Counting_Driver::$calls );
		$this->assertTrue( UploadManager::acquire_lock( $name ), 'The lock is released after the upload.' );
		UploadManager::release_lock( $name );
	}

	/**
	 * Fails before the fix: PHP drops an over-limit body, and the route said "No file provided".
	 */
	public function test_over_limit_body_is_reported_as_too_large_not_missing(): void {
		$_SERVER['CONTENT_LENGTH'] = (string) ( wp_convert_hr_to_bytes( (string) ini_get( 'post_max_size' ) ) + 1024 );

		$response = rest_do_request( new WP_REST_Request( 'POST', '/mediashield/v1/upload/init' ) );

		$this->assertSame( 413, $response->get_status() );
		$this->assertSame( 'file_too_large', $response->get_data()['code'] );
		$this->assertStringContainsString( size_format( wp_max_upload_size() ), $response->get_data()['message'] );
		$this->assertSame( 0, Honest_Upload_Counting_Driver::$calls );
	}

	/**
	 * A request that really carried no file still says so.
	 */
	public function test_missing_file_under_the_limit_still_says_no_file(): void {
		$_SERVER['CONTENT_LENGTH'] = '10';

		$response = rest_do_request( new WP_REST_Request( 'POST', '/mediashield/v1/upload/init' ) );

		$this->assertSame( 'no_file', $response->get_data()['code'] );
	}

	/**
	 * The uploader names its target.
	 */
	public function test_target_label_is_filterable(): void {
		$this->assertNotSame( '', UploadManager::target_label() );
		$filter = static function () {
			return 'Bunny Stream - Course videos';
		};
		add_filter( 'mediashield_upload_target_label', $filter );
		$this->assertSame( 'Bunny Stream - Course videos', UploadManager::target_label() );
		remove_filter( 'mediashield_upload_target_label', $filter );
	}
}
