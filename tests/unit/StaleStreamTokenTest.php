<?php
/**
 * Regression tests for the 1.4.0 playback fixes.
 *
 * Zoho #42193: a signed stream URL printed into cached page HTML went stale and
 * every viewer, the admin included, was refused. Zoho #41980: a video added
 * with the core embed block was never wrapped, because only its plain link is
 * stored in the post content.
 *
 * @package MediaShield\Tests
 */

namespace MediaShield\Tests\Unit;

use MediaShield\Embed\EmbedLink;
use MediaShield\Player\PlayerWrapper;
use MediaShield\REST\StreamController;
use WP_REST_Request;
use WP_UnitTestCase;

/**
 * Stream URL refresh and embed-block detection.
 */
class StaleStreamTokenTest extends WP_UnitTestCase {

	/**
	 * Self-hosted video fixture.
	 *
	 * @var int
	 */
	private int $video_id = 0;

	/**
	 * Create the fixture and register the routes.
	 */
	public function set_up(): void {
		parent::set_up();

		$this->video_id = self::factory()->post->create(
			array(
				'post_type'   => 'mediashield_video',
				'post_status' => 'publish',
			)
		);
		update_post_meta( $this->video_id, '_ms_platform', 'self' );

		do_action( 'rest_api_init' );
	}

	/**
	 * The signed URL names the viewer it was minted for and no one else.
	 */
	public function test_signed_url_carries_a_token_for_that_viewer(): void {
		$url = StreamController::signed_url( $this->video_id, 7 );

		parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );
		$claim = EmbedLink::verify( (string) $query['ms_token'] );

		$this->assertSame( 7, $claim['user_id'] );
		$this->assertSame( $this->video_id, $claim['video_id'] );
	}

	/**
	 * A logged-in member gets a fresh, verifiable URL back.
	 */
	public function test_refresh_route_returns_a_fresh_url_for_a_member(): void {
		$user = self::factory()->user->create();
		wp_set_current_user( $user );

		$response = rest_do_request( new WP_REST_Request( 'GET', '/mediashield/v1/stream/' . $this->video_id . '/url' ) );

		$this->assertSame( 200, $response->get_status() );
		parse_str( (string) wp_parse_url( $response->get_data()['url'], PHP_URL_QUERY ), $query );
		$this->assertSame( $user, EmbedLink::verify( (string) $query['ms_token'] )['user_id'] );
	}

	/**
	 * Negative case: a guest on a login-gated video is refused a URL, so the
	 * refresh route cannot be used to bypass the gate.
	 */
	public function test_refresh_route_refuses_a_guest_when_login_is_required(): void {
		update_option( 'ms_require_login', true );
		wp_set_current_user( 0 );

		$response = rest_do_request( new WP_REST_Request( 'GET', '/mediashield/v1/stream/' . $this->video_id . '/url' ) );

		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * The plain links an embed block stores must switch buffering on.
	 *
	 * @dataProvider plain_links
	 *
	 * @param string $content  Saved post content.
	 * @param string $platform Platform expected to be found.
	 */
	public function test_plain_embed_links_are_detected( string $content, string $platform ): void {
		$page = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_content' => $content,
			)
		);
		$this->go_to( get_permalink( $page ) );

		$method = new \ReflectionMethod( PlayerWrapper::class, 'content_embed_kinds' );
		$method->setAccessible( true );

		$this->assertContains( $platform, $method->invoke( null ) );
	}

	/**
	 * Saved shapes of the same video.
	 *
	 * @return array<string, array{string, string}>
	 */
	public function plain_links(): array {
		return array(
			'vimeo block'   => array( "<!-- wp:embed -->\n<figure class=\"wp-block-embed\"><div class=\"wp-block-embed__wrapper\">\nhttps://vimeo.com/123456\n</div></figure>\n<!-- /wp:embed -->", 'vimeo' ),
			'youtube watch' => array( "<!-- wp:embed -->\nhttps://www.youtube.com/watch?v=abc\n<!-- /wp:embed -->", 'youtube' ),
			'youtube short' => array( 'https://youtu.be/abc', 'youtube' ),
		);
	}
}
