<?php
/**
 * Bunny Direct playback prints only the stream it plays (Bunny end-to-end test, D3).
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\Player\Renderer;
use WP_UnitTestCase;

/**
 * With Direct playback the page carries the signed stream URL, not the embed URL.
 */
class BunnyDirectHidesEmbedTest extends WP_UnitTestCase {

	private const EMBED  = 'https://iframe.mediadelivery.net/embed/806115/0b1c2d3e-4f50-6172-8394-a5b6c7d8e9f0';
	private const STREAM = 'https://vz-test.b-cdn.net/0b1c2d3e-4f50-6172-8394-a5b6c7d8e9f0/playlist.m3u8?token=abc&expires=1';

	/**
	 * A Bunny video.
	 *
	 * @var int
	 */
	private $video;

	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		$this->video = self::factory()->post->create(
			array(
				'post_type'   => 'mediashield_video',
				'post_status' => 'publish',
				'meta_input'  => array(
					'_ms_platform'          => 'bunny',
					'_ms_platform_video_id' => '0b1c2d3e-4f50-6172-8394-a5b6c7d8e9f0',
					'_ms_source_url'        => self::EMBED,
				),
			)
		);
	}

	public function tear_down(): void {
		remove_all_filters( 'mediashield_video_stream_url' );
		parent::tear_down();
	}

	/**
	 * Fails on HEAD: data-source-url carried the unsigned embed URL.
	 */
	public function test_direct_playback_prints_no_embed_url(): void {
		// What Pro's BunnyUrls::supply_stream_url() does with Direct playback on.
		add_filter(
			'mediashield_video_stream_url',
			static function ( $url, $video_id, $platform ) {
				return 'bunny' === $platform ? self::STREAM : $url;
			},
			10,
			3
		);

		$html = Renderer::render( $this->video );

		$this->assertStringContainsString( 'data-stream-url="' . esc_url( self::STREAM ) . '"', $html );
		$this->assertStringContainsString( 'data-source-url=""', $html );
		$this->assertStringNotContainsString( 'iframe.mediadelivery.net', $html );
	}

	/**
	 * Direct playback off: no stream URL, so the embed is what plays and stays.
	 */
	public function test_embed_playback_keeps_the_embed_url(): void {
		$html = Renderer::render( $this->video );

		$this->assertStringContainsString( 'data-source-url="' . esc_url( self::EMBED ) . '"', $html );
		$this->assertStringContainsString( 'data-stream-url=""', $html );
	}
}
