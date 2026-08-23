<?php
/**
 * Regression tests for the access gates fixed in 1.3.0.
 *
 * Each test here corresponds to a bug that shipped, so the assertions are
 * written against the OBSERVED broken behaviour rather than against the code
 * as it now reads — a test that only restates the implementation would have
 * passed before the fix too.
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\Core\Settings;
use MediaShield\Embed\EmbedLink;
use MediaShield\Player\Protection;
use WP_UnitTestCase;

/**
 * Login gate, source hiding and stream-token behaviour.
 */
class AccessGatesTest extends WP_UnitTestCase {

	/**
	 * A self-hosted video to exercise the player URL rules against.
	 *
	 * @var int
	 */
	private int $video_id = 0;

	/**
	 * Create a self-hosted video fixture.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->video_id = self::factory()->post->create(
			array(
				'post_type'   => 'mediashield_video',
				'post_status' => 'publish',
				'post_title'  => 'Test Video',
			)
		);

		update_post_meta( $this->video_id, '_ms_platform', 'self' );
		update_post_meta( $this->video_id, '_ms_platform_video_id', 'test.mp4' );
		update_post_meta( $this->video_id, '_ms_source_url', 'https://example.test/uploads/mediashield/test.mp4' );
	}

	/**
	 * BC#10217643237 / BC#10143667839 — the setting never reached the client.
	 *
	 * frontend_config() emitted restUrl/nonce/isLoggedIn/userId/loginUrl/
	 * interval/player/messages and nothing else, so player-wrapper.js gated on
	 * isLoggedIn alone and turning the setting off changed nothing.
	 */
	public function test_frontend_config_exposes_require_login(): void {
		$config = Settings::frontend_config();

		$this->assertArrayHasKey(
			'requireLogin',
			$config,
			'requireLogin must reach the client or the setting is inert.'
		);
	}

	/**
	 * The flag must track the option in BOTH directions.
	 */
	public function test_require_login_flag_follows_the_option(): void {
		update_option( 'ms_require_login', true );
		$this->assertTrue( (bool) Settings::frontend_config()['requireLogin'] );

		update_option( 'ms_require_login', false );
		$this->assertFalse( (bool) Settings::frontend_config()['requireLogin'] );
	}

	/**
	 * BC#10143668134 — the server printed the file URL into data-source-url
	 * while JS stripped src, so View Source revealed it instantly.
	 */
	public function test_hide_source_removes_the_self_hosted_file_url(): void {
		update_option( 'ms_hide_source', true );

		$urls = Protection::filter_player_urls(
			$this->video_id,
			'self',
			'https://example.test/uploads/mediashield/test.mp4',
			''
		);

		$this->assertSame( '', $urls['source_url'], 'The raw file URL must not be exposed.' );
		// Decoded before comparing: on plain permalinks the route arrives as a
		// percent-encoded ?rest_route= query arg, so asserting the pretty path
		// would be testing the site's permalink structure rather than the fix.
		$this->assertStringContainsString(
			'mediashield/v1/stream/' . $this->video_id,
			urldecode( $urls['stream_url'] ),
			'Playback must route through the permission-checked endpoint.'
		);
	}

	/**
	 * The stream URL must carry a viewer token: a <video src> cannot send an
	 * X-WP-Nonce header, so without one every viewer arrives as user 0 and a
	 * logged-in member is told to log in.
	 */
	public function test_hidden_source_stream_url_is_signed(): void {
		update_option( 'ms_hide_source', true );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$urls = Protection::filter_player_urls( $this->video_id, 'self', 'https://example.test/f.mp4', '' );

		$this->assertStringContainsString( 'ms_token=', $urls['stream_url'] );
	}

	/**
	 * Turning the setting off must give the plain URL back.
	 */
	public function test_hide_source_off_returns_the_url_unchanged(): void {
		update_option( 'ms_hide_source', false );

		$urls = Protection::filter_player_urls( $this->video_id, 'self', 'https://example.test/f.mp4', '' );

		$this->assertSame( 'https://example.test/f.mp4', $urls['source_url'] );
	}

	/**
	 * Platform embeds are deliberately untouched: a YouTube iframe's src
	 * necessarily carries the video id, so blanking the attribute would break
	 * playback while protecting nothing.
	 */
	public function test_hide_source_does_not_touch_platform_embeds(): void {
		update_option( 'ms_hide_source', true );

		$urls = Protection::filter_player_urls(
			$this->video_id,
			'youtube',
			'https://www.youtube.com/watch?v=abc',
			''
		);

		$this->assertSame( 'https://www.youtube.com/watch?v=abc', $urls['source_url'] );
	}

	/**
	 * An operator-supplied stream URL (a CDN playlist) is what they pointed the
	 * player at deliberately and must not be replaced.
	 */
	public function test_explicit_stream_url_is_preserved(): void {
		update_option( 'ms_hide_source', true );

		$urls = Protection::filter_player_urls(
			$this->video_id,
			'self',
			'https://example.test/f.mp4',
			'https://cdn.example.test/playlist.m3u8'
		);

		$this->assertSame( 'https://cdn.example.test/playlist.m3u8', $urls['stream_url'] );
	}

	/**
	 * The token is identity, and must not verify for anything it did not name.
	 */
	public function test_stream_token_round_trips_and_rejects_tampering(): void {
		$token = EmbedLink::token( $this->video_id, 7, HOUR_IN_SECONDS );
		$claim = EmbedLink::verify( $token );

		$this->assertIsArray( $claim );
		$this->assertSame( $this->video_id, $claim['video_id'] );
		$this->assertSame( 7, $claim['user_id'] );

		$this->assertNull( EmbedLink::verify( $token . 'x' ), 'A tampered token must not verify.' );
		$this->assertNull( EmbedLink::verify( 'not.a.real.token' ) );
		$this->assertNull( EmbedLink::verify( '' ) );
	}

	/**
	 * An expired token must be refused even though its signature is genuine.
	 */
	public function test_expired_stream_token_is_refused(): void {
		$sign = new \ReflectionMethod( EmbedLink::class, 'sign' );
		$sign->setAccessible( true );
		$expired = $sign->invoke( null, $this->video_id, 1, time() - 60 );

		$this->assertNull( EmbedLink::verify( $expired ) );
	}

	/**
	 * BC#10143667645 (Free half) — the protection-level list was a closed array,
	 * so Pro could never register the 'drm' level its own player checks for.
	 */
	public function test_protection_levels_are_filterable(): void {
		add_filter(
			'mediashield_protection_levels',
			static function ( $levels ) {
				$levels['drm'] = 'DRM';
				return $levels;
			}
		);

		ob_start();
		\MediaShield\CPT\VideoPostType::render_settings_meta_box( get_post( $this->video_id ) );
		$html = ob_get_clean();

		$this->assertStringContainsString( 'value="drm"', $html );
	}
}
