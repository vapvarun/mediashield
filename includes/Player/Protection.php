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

use MediaShield\Core\Settings;
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
	 * Resolve the protection level that applies to one video.
	 *
	 * WHY THIS IS SHARED RATHER THAN INLINE
	 *
	 * There is more than one way a video reaches the page. Renderer handles the
	 * shortcode and the block; PlayerWrapper handles the output-buffer auto-wrap
	 * that picks up an embed a theme or another plugin emitted. Both need the
	 * same answer to "how protected is this video".
	 *
	 * They did not give the same answer. Renderer read the per-video
	 * `_ms_protection_level` meta and fell back to the global default;
	 * PlayerWrapper read the global default and never looked at the meta at all.
	 * So the same video was protected as configured when placed by shortcode and
	 * silently downgraded to the site default when the auto-wrap was what found
	 * it - with nothing on screen indicating the setting had been ignored. An
	 * owner who deliberately set one lecture to Strict got Standard on any page
	 * where the embed came from the theme.
	 *
	 * One resolver, two callers. A third render path gets the right answer by
	 * construction instead of by someone remembering to copy eight lines.
	 *
	 * Order: per-video override, then the owner's global default, then
	 * 'standard' as a last resort for an install whose option row is missing.
	 *
	 * @param int $video_id Video CPT post ID.
	 * @return string Protection level slug.
	 */
	public static function resolve_level( int $video_id ): string {
		$per_video = get_post_meta( $video_id, '_ms_protection_level', true );

		return empty( $per_video ) ? self::default_level() : (string) $per_video;
	}

	/**
	 * The protection level for a video that carries no override of its own.
	 *
	 * Split out from resolve_level() for the batch callers. A playlist reads
	 * every item's level in one JOIN specifically to avoid a get_post_meta()
	 * per row, so it cannot call resolve_level() without reintroducing the N+1
	 * it was written to avoid. It calls this instead, once, and applies it to
	 * whichever rows came back with nothing.
	 *
	 * Having it here rather than inline in each caller is the point: the
	 * playlist used to fall back to 'standard' directly, which silently
	 * ignored the owner's Settings > Default Protection Level for every video
	 * in every playlist.
	 *
	 * @return string Protection level slug.
	 */
	public static function default_level(): string {
		$default = Settings::get( 'ms_default_protection' );

		return empty( $default ) ? 'standard' : (string) $default;
	}

	/**
	 * Get the protection configuration for JS.
	 *
	 * Values come from wp_options with sensible defaults; the result is passed
	 * through `mediashield_protection_config` for filter-based overrides.
	 *
	 * @return array Protection config.
	 */
	public static function get_config(): array {
		// Read through Settings::get() rather than get_option() with a default
		// written out again here. Every hand-copied default is a second copy of
		// a value that already exists in the schema, and this one had already
		// drifted: `ms_block_keyboard` fell back to true while the schema
		// declares false. Harmless wherever seed_defaults() has run and the row
		// exists - and on any install where it does not (a partial activation, a
		// failed migration, a site restored from an incomplete export) keyboard
		// blocking switched itself on against the documented default. Settings
		// resolves the schema default and casts, so there is one source.
		$config = array(
			'block_right_click' => (bool) Settings::get( 'ms_block_right_click' ),
			'block_keyboard'    => (bool) Settings::get( 'ms_block_keyboard' ),
			'hide_source'       => (bool) Settings::get( 'ms_hide_source' ),
			'detect_devtools'   => (bool) Settings::get( 'ms_detect_devtools' ),
			'pause_on_devtools' => (bool) Settings::get( 'ms_pause_on_devtools' ),
			'devtools_title'    => (string) Settings::get( 'ms_devtools_title' ),
			'devtools_message'  => (string) Settings::get( 'ms_devtools_message' ),
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
