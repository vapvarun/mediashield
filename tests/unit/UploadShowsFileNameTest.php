<?php
/**
 * An upload is shown by its file name, never by its internal address (MSUX-09).
 *
 * @package MediaShield\Tests
 */

use MediaShield\Upload\Drivers\DriverInterface;

/**
 * Stores a file the way this site's driver does: platform self, the
 * internal /stream/ address as the source, a tokened stored name.
 */
class Upload_Name_Self_Driver implements DriverInterface {

	public function get_name(): string {
		return 'self-like';
	}

	public function upload( string $file_path, array $options = array() ): array { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		$video = (int) ( $options['attach_to'] ?? 0 );
		update_post_meta( $video, '_ms_platform', 'self' );
		update_post_meta( $video, '_ms_source_url', rest_url( 'mediashield/v1/stream/' . $video ) );
		return array(
			'success'           => true,
			'video_id'          => $video,
			'platform_video_id' => 'band-music-AbCdEfGhIjKlMnOpQrSt.mp4',
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
 * Fails before: the response carried only the /stream/ address, which the
 * edit screen wrote into the Video link field.
 */
class UploadShowsFileNameTest extends WP_UnitTestCase {

	public function drivers( $drivers ): array {
		$drivers['self-like'] = Upload_Name_Self_Driver::class;
		return $drivers;
	}

	public function test_upload_answer_names_the_file(): void {
		$admin = self::factory()->user->create_and_get( array( 'role' => 'administrator' ) );
		$admin->add_cap( 'upload_mediashield' );
		wp_set_current_user( $admin->ID );
		$video = self::factory()->post->create( array( 'post_type' => 'mediashield_video' ) );
		add_filter( 'mediashield_upload_drivers', array( $this, 'drivers' ) );

		$tmp = wp_tempnam( 'ms-upload' );
		file_put_contents( $tmp, str_repeat( 'x', 1024 ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$request = new WP_REST_Request( 'POST', '/mediashield/v1/upload/init' );
		$request->set_param( 'driver', 'self-like' );
		$request->set_param( 'video_id', $video );
		$request->set_file_params( array( 'file' => array( 'name' => 'band-music.mp4', 'type' => 'video/mp4', 'tmp_name' => $tmp, 'error' => UPLOAD_ERR_OK, 'size' => 1024 ) ) );
		$data = rest_do_request( $request )->get_data();
		remove_filter( 'mediashield_upload_drivers', array( $this, 'drivers' ) );

		$this->assertSame( 'band-music.mp4', $data['file_name'] ?? null );
		$this->assertSame( 'self', $data['platform'] );
	}
}
