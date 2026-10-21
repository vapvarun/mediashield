<?php
/**
 * Access and token rules found in the 1.4.0 security review.
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\Embed\EmbedLink;
use MediaShield\Player\Renderer;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Stream address, playlist password and embed token spelling.
 */
class ReleaseSecurityTest extends WP_UnitTestCase {

	/**
	 * A video on a platform that supplies its own stream address (Bunny direct play).
	 *
	 * @return int
	 */
	private function bunny_video(): int {
		$video = self::factory()->post->create(
			array(
				'post_type'   => 'mediashield_video',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $video, '_ms_platform', 'bunny' );
		update_post_meta( $video, '_ms_platform_video_id', 'abc-guid' );
		return $video;
	}

	/**
	 * Fails before the fix: the signed CDN address was printed for a logged-out visitor.
	 */
	public function test_cdn_stream_address_is_withheld_from_someone_who_may_not_watch(): void {
		update_option( 'ms_require_login', true );
		$video = $this->bunny_video();
		add_filter(
			'mediashield_video_stream_url',
			static fn() => 'https://cdn.example.test/secret.m3u8?token=abc'
		);

		wp_set_current_user( 0 );
		$this->assertStringNotContainsString( 'secret.m3u8', Renderer::render( $video ) );

		wp_set_current_user( self::factory()->user->create() );
		$this->assertStringContainsString( 'secret.m3u8', Renderer::render( $video ) );
	}

	/**
	 * Fails before the fix: a playlist password did not stop the player route.
	 */
	public function test_player_route_respects_a_playlist_password(): void {
		global $wpdb;

		$playlist = self::factory()->post->create(
			array(
				'post_type'     => 'mediashield_playlist',
				'post_status'   => 'publish',
				'post_password' => 'letmein',
			)
		);
		$video    = $this->bunny_video();
		$wpdb->insert(
			"{$wpdb->prefix}ms_playlist_items",
			array(
				'playlist_id' => $playlist,
				'video_id'    => $video,
				'sort_order'  => 1,
			)
		);

		wp_set_current_user( 0 );
		$status = rest_do_request( new WP_REST_Request( 'GET', "/mediashield/v1/playlists/{$playlist}/player/{$video}" ) )->get_status();

		$this->assertSame( 404, $status );
	}

	/**
	 * Fails before the fix: "<id>x" cast to the same id and verified.
	 */
	public function test_embed_token_has_one_spelling(): void {
		$token = EmbedLink::token( 7, 3 );
		$this->assertIsArray( EmbedLink::verify( $token ) );

		$parts    = explode( '.', $token );
		$parts[2] = $parts[2] . 'x';
		$this->assertNull( EmbedLink::verify( implode( '.', $parts ) ) );

		$parts    = explode( '.', $token );
		$parts[0] = '+' . $parts[0];
		$this->assertNull( EmbedLink::verify( implode( '.', $parts ) ) );
	}
}
