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
	 * Options that were site-wide switches before 1.4.4 and now belong to Strict.
	 */
	public const LEGACY_SWITCHES = array( 'ms_hide_source', 'ms_detect_devtools', 'ms_pause_on_devtools' );

	/**
	 * The four protection levels in the words every screen uses.
	 *
	 * One list for Settings, the video edit screen, the setup wizard, the
	 * Videos list and the block (Basecamp 10545260510). Each level includes
	 * the one before it. Login is not part of a level: it is the Who can
	 * watch setting (`ms_require_login`) and applies at every level.
	 *
	 * `summary` is the whole promise of the level; `adds` is the one line it
	 * adds to the level before it, for a list that reads top to bottom.
	 *
	 * @since 1.4.4
	 *
	 * @return array<string, array{label: string, summary: string, adds: string}>
	 */
	public static function levels(): array {
		// None follows the site-wide right-click switch (owner decision,
		// Basecamp 10537111817), so its words say so while the switch is on.
		$none = Settings::get( 'ms_block_right_click' )
			? __( 'The video plays as the platform shows it. Right-click stays blocked while Block right-click on every video is on.', 'mediashield' )
			: __( 'The video plays as the platform shows it.', 'mediashield' );

		return array(
			'none'     => array(
				'label'   => __( 'None', 'mediashield' ),
				'summary' => $none,
				'adds'    => $none,
			),
			'basic'    => array(
				'label'   => __( 'Basic', 'mediashield' ),
				'summary' => __( 'Right-click and download blocked.', 'mediashield' ),
				'adds'    => __( 'Right-click and download blocked.', 'mediashield' ),
			),
			'standard' => array(
				'label'   => __( 'Standard', 'mediashield' ),
				'summary' => __( 'Right-click and download blocked, a watermark with the viewer\'s name, and each viewing tracked.', 'mediashield' ),
				'adds'    => __( 'Plus a watermark and tracked viewing.', 'mediashield' ),
			),
			'strict'   => array(
				'label'   => __( 'Strict', 'mediashield' ),
				'summary' => __( 'Everything in Standard, plus the file address hidden from the page and developer tools detected.', 'mediashield' ),
				'adds'    => __( 'Plus file address hidden and developer tools detected.', 'mediashield' ),
			),
		);
	}

	/**
	 * The label for a video that follows the site default: "Site default (Standard)".
	 *
	 * @since 1.4.4
	 *
	 * @return string
	 */
	public static function site_default_label(): string {
		$levels = self::levels();
		$level  = self::default_level();

		return sprintf(
			/* translators: %s: the site-wide protection level, e.g. Standard. */
			__( 'Site default (%s)', 'mediashield' ),
			$levels[ $level ]['label'] ?? $level
		);
	}

	/**
	 * Option set on a site that updated from 1.4.3 or earlier (Migrator v14).
	 */
	public const SWITCHES_KEPT = 'ms_protection_switches_kept';

	/**
	 * The pre-1.4.4 site-wide protection switches that still apply on this site.
	 *
	 * Until 1.4.4, Hide Video Source, Detect Developer Tools and Pause Video
	 * When Detected were site-wide switches that applied at every protected
	 * level. They now belong to Strict only (Basecamp 10545260510). An update
	 * must not change what members get, so a site that ran 1.4.3 or earlier
	 * (SWITCHES_KEPT, set by Migrator v14), or that changed a switch, keeps
	 * the switches that are on working site-wide, and Settings names them,
	 * until the owner picks a level there (SettingsController removes the
	 * switches and the marker then). A new install gets the levels only.
	 *
	 * @since 1.4.4
	 *
	 * @return string[] Switch options still applying site-wide; empty = the levels decide.
	 */
	public static function legacy_switches(): array {
		$defaults = Settings::defaults();
		$old      = (bool) get_option( self::SWITCHES_KEPT, false );
		foreach ( self::LEGACY_SWITCHES as $key ) {
			$stored = get_option( $key, null );
			if ( null !== $stored && (bool) $stored !== (bool) $defaults[ $key ] ) {
				$old = true;
			}
		}
		if ( ! $old ) {
			return array();
		}
		return array_values( array_filter( self::LEGACY_SWITCHES, static fn( string $key ): bool => (bool) Settings::get( $key ) ) );
	}

	/**
	 * Does the site hide a pasted file's address at every level, not only Strict?
	 *
	 * Only a site that kept its pre-1.4.4 switches (legacy_switches()) with
	 * Hide Video Source on. Otherwise hiding is Strict's.
	 *
	 * @since 1.4.4
	 *
	 * @return bool
	 */
	public static function site_hides_source(): bool {
		return in_array( 'ms_hide_source', self::legacy_switches(), true );
	}

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
		//
		// Source hiding and developer-tools detection belong to Strict, which
		// the player turns on from the video's level (protection.js). These
		// three keys are the site-wide versions, on only for a site that kept
		// its pre-1.4.4 switches (legacy_switches()).
		$legacy = self::legacy_switches();
		$config = array(
			'block_right_click' => (bool) Settings::get( 'ms_block_right_click' ),
			'block_keyboard'    => (bool) Settings::get( 'ms_block_keyboard' ),
			'hide_source'       => in_array( 'ms_hide_source', $legacy, true ),
			'detect_devtools'   => in_array( 'ms_detect_devtools', $legacy, true ),
			'pause_on_devtools' => in_array( 'ms_pause_on_devtools', $legacy, true ),
			'devtools_title'    => Settings::message( 'ms_devtools_title', __( 'Developer Tools Detected', 'mediashield' ) ),
			'devtools_message'  => Settings::message( 'ms_devtools_message', __( 'Please close developer tools to continue watching this video.', 'mediashield' ) ),
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
	 * @since 1.4.3 A file MediaShield holds is always signed, whatever Hide Video Source says.
	 * @since 1.4.3 A Strict video hides its source whatever Hide Video Source says.
	 * @since 1.4.4 A Bunny video with a direct stream URL no longer prints its embed URL.
	 *
	 * @param int    $video_id   Video CPT post ID.
	 * @param string $platform   Platform slug (self, bunny, youtube…).
	 * @param string $source_url Resolved source URL.
	 * @param string $stream_url Resolved stream URL (may be empty).
	 * @return array{source_url:string, stream_url:string} URLs safe to print.
	 */
	public static function filter_player_urls( int $video_id, string $platform, string $source_url, string $stream_url ): array {
		$unchanged = array(
			'source_url' => $source_url,
			'stream_url' => $stream_url,
		);

		// Bunny playing as a direct stream (Pro's Direct playback supplies the
		// signed playlist URL): the player never loads the embed page, so its
		// unsigned address is not printed. It would play the video in Bunny's
		// own player with no watermark and no tracking. With Direct off there
		// is no stream URL and the embed is what plays, so it stays.
		if ( 'bunny' === $platform && '' !== $stream_url ) {
			return array(
				'source_url' => '',
				'stream_url' => $stream_url,
			);
		}

		if ( 'self' !== $platform ) {
			return $unchanged;
		}

		// A file MediaShield holds is always played through the gated endpoint,
		// whatever Hide Video Source says. Its raw address cannot play: the
		// protected folder denies direct requests, and withhold_addresses()
		// blanks any URL into it for every viewer. With Hide Source off this
		// used to hand back the raw folder URL, which was then blanked, so an
		// uploaded video rendered with no address at all (BC#10501905898).
		// An uploaded file's own address IS the gated endpoint, and a <video>
		// cannot send a login cookie the REST API will accept, so it is always
		// signed too (BC#10370649456).
		if ( self::must_gate( $video_id, $source_url, $stream_url ) ) {
			// An explicit stream URL outside the folder (a CDN playlist, say) is
			// what the operator deliberately pointed the player at; keep it. One
			// into the folder is just another raw file address.
			if ( '' === $stream_url || self::in_protected_folder( $stream_url ) ) {
				$stream_url = \MediaShield\REST\StreamController::signed_url( $video_id, get_current_user_id() );
				self::skip_page_cache();
			}

			return array(
				'source_url' => '',
				'stream_url' => $stream_url,
			);
		}

		// Anything else (a URL pasted into this site's uploads, outside our
		// folder) follows the setting, and a Strict video always hides it: the
		// level promises source hiding (BC#10526281691). Only route through
		// /stream/ when it can serve the file: an explicit stream URL, or one we
		// can find on disk. An external direct URL has no local file, and
		// routing it there was a guaranteed 404 (BC#10258678064).
		if ( ( ! self::site_hides_source() && 'strict' !== self::resolve_level( $video_id ) )
			|| ( '' === $stream_url && '' === \MediaShield\REST\StreamController::resolve_file( $video_id ) ) ) {
			return $unchanged;
		}

		// Route playback through the gated endpoint and stop printing the raw
		// file URL. An explicit stream URL is left alone, as above.
		//
		// The URL carries a signed viewer token because the browser will not
		// send an auth header for a <video src>: WordPress ignores the session
		// cookie without X-WP-Nonce, so an unsigned URL would tell every
		// logged-in member to log in. The token names the viewer; the endpoint
		// still runs can_watch() for them on every range request.
		if ( '' === $stream_url ) {
			$stream_url = \MediaShield\REST\StreamController::signed_url( $video_id, get_current_user_id() );
			self::skip_page_cache();
		}

		return array(
			'source_url' => '',
			'stream_url' => $stream_url,
		);
	}

	/**
	 * Must this self-hosted video play only through the signed /stream/ URL?
	 *
	 * Yes when MediaShield holds its file: the source is already the gated
	 * endpoint, an address sits inside the protected folder, or the video
	 * names a file it owns (`_ms_owned_file`). Hide Video Source does not
	 * apply to these; it only decides about URLs outside the folder.
	 *
	 * @since 1.4.3
	 *
	 * @param int    $video_id   Video CPT post ID.
	 * @param string $source_url Resolved source URL.
	 * @param string $stream_url Resolved stream URL (may be empty).
	 * @return bool
	 */
	private static function must_gate( int $video_id, string $source_url, string $stream_url ): bool {
		return false !== strpos( $source_url, 'mediashield/v1/stream/' . $video_id )
			|| self::in_protected_folder( $source_url )
			|| self::in_protected_folder( $stream_url )
			|| '' !== (string) get_post_meta( $video_id, '_ms_owned_file', true );
	}

	/**
	 * Does this URL point into MediaShield's protected folder (uploads/mediashield/)?
	 *
	 * The one test for it: Protection uses it to decide a video must play
	 * through /stream/, Renderer::withhold_addresses() to blank raw folder
	 * URLs for every viewer. The scheme is ignored (a URL pasted as http on an
	 * https site is the same file) and so is case, because over-matching only
	 * ever routes a URL through the gated endpoint.
	 *
	 * For a file path on disk use StreamController::resolve_file(); this
	 * answers about URLs only.
	 *
	 * @since 1.4.3
	 *
	 * @param string $url Any URL.
	 * @return bool
	 */
	public static function in_protected_folder( string $url ): bool {
		if ( '' === $url ) {
			return false;
		}

		$folder = preg_replace( '#^https?:#i', '', trailingslashit( wp_upload_dir()['baseurl'] ) . 'mediashield/' );

		return false !== stripos( (string) preg_replace( '#^https?:#i', '', $url ), (string) $folder );
	}

	/**
	 * Keep the page out of page caches once it prints a viewer's stream token.
	 *
	 * The token names whoever the page was rendered for. A cached copy would
	 * hand that identity to every later visitor, or - once the token expires -
	 * leave members with a player that is refused (Zoho #42193).
	 */
	private static function skip_page_cache(): void {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- The cache plugins' own flag.
		}
		if ( ! headers_sent() ) {
			nocache_headers();
		}
	}
}
