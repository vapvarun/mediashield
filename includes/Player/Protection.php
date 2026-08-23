<?php
/**
 * Anti-download protection measures.
 *
 * Adds right-click blocking, source hiding, download-prevention attributes,
 * and client-side DevTools detection to protected video players.
 *
 * @package MediaShield\Player
 */

namespace MediaShield\Player;

use MediaShield\Embed\EmbedLink;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Protection
 *
 * Anti-download protection measures for video players.
 *
 * @since 1.0.0
 */
class Protection {

	/**
	 * Get the protection configuration for JS.
	 *
	 * Values come from wp_options with sensible defaults; the result is passed
	 * through `mediashield_protection_config` for filter-based overrides.
	 *
	 * @return array Protection config.
	 */
	public static function get_config(): array {
		$config = array(
			'block_right_click' => (bool) get_option( 'ms_block_right_click', true ),
			'block_keyboard'    => (bool) get_option( 'ms_block_keyboard', true ),
			'hide_source'       => (bool) get_option( 'ms_hide_source', true ),
			'detect_devtools'   => (bool) get_option( 'ms_detect_devtools', true ),
			'pause_on_devtools' => (bool) get_option( 'ms_pause_on_devtools', false ),
			'devtools_title'    => (string) get_option( 'ms_devtools_title', __( 'Developer Tools Detected', 'mediashield' ) ),
			'devtools_message'  => (string) get_option( 'ms_devtools_message', __( 'Please close developer tools to continue watching this video.', 'mediashield' ) ),
		);

		/**
		 * Filter the protection configuration sent to the frontend.
		 *
		 * @since 1.1.0
		 *
		 * @param array $config Protection config.
		 */
		return apply_filters( 'mediashield_protection_config', $config );
	}

	/**
	 * Decide which URLs a player container may expose in its markup.
	 *
	 * "Hide Video Source URL" used to be enforced only in JS, which stripped
	 * `src` from the media element after the server had already printed the
	 * same URL into `data-source-url` on that element. View Source revealed it
	 * instantly, so the setting protected nothing (BC#10143668134).
	 *
	 * Hiding is only meaningful for SELF-HOSTED video, and only because there
	 * is somewhere else for the bytes to come from: `/mediashield/v1/stream/{id}`
	 * serves the file itself behind AccessControl::can_watch(), so the player
	 * gets a URL that is checked on every request instead of a file path anyone
	 * can copy out of the DOM.
	 *
	 * It is NOT meaningful for a platform embed. A YouTube or Vimeo player is an
	 * iframe whose src necessarily carries the provider's video id, so the id is
	 * on the page whatever we do with this attribute. Blanking it there would
	 * break playback while protecting nothing, so platform videos keep their
	 * URL and the admin says plainly that the setting does not apply to them.
	 * Claiming otherwise would be the same lie in a new place.
	 *
	 * @since 1.3.0
	 *
	 * @param int    $video_id   Video CPT post ID.
	 * @param string $platform   Platform slug (self, bunny, youtube…).
	 * @param string $source_url Resolved source URL.
	 * @param string $stream_url Resolved stream URL (may be empty).
	 * @return array{source_url:string, stream_url:string} URLs safe to print.
	 */
	public static function filter_player_urls( int $video_id, string $platform, string $source_url, string $stream_url ): array {
		if ( 'self' !== $platform || ! get_option( 'ms_hide_source', true ) ) {
			return array(
				'source_url' => $source_url,
				'stream_url' => $stream_url,
			);
		}

		// Route playback through the gated endpoint and stop printing the raw
		// file URL. An explicit stream URL (a CDN playlist, say) is left alone:
		// it is what the operator deliberately pointed the player at.
		//
		// The URL carries a signed viewer token because the browser will not
		// send an auth header for a <video src>: WordPress ignores the session
		// cookie without X-WP-Nonce, so an unsigned URL would tell every
		// logged-in member to log in. The token names the viewer; the endpoint
		// still runs can_watch() for them on every range request.
		if ( '' === $stream_url ) {
			$stream_url = add_query_arg(
				'ms_token',
				EmbedLink::token( $video_id, get_current_user_id(), EmbedLink::STREAM_TTL ),
				rest_url( 'mediashield/v1/stream/' . $video_id )
			);
		}

		return array(
			'source_url' => '',
			'stream_url' => $stream_url,
		);
	}
}
