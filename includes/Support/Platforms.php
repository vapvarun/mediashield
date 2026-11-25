<?php
/**
 * One place to ask questions about video platforms.
 *
 * @package MediaShield\Support
 */

namespace MediaShield\Support;

use MediaShield\Upload\UploadManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Platform slugs and the per-platform capability questions the plugin asks.
 *
 * WHY THIS EXISTS
 *
 * Platform slugs were string literals scattered across the codebase, and one
 * question - "does this platform need an adaptive-streaming player" - was
 * written out twice, in Renderer and in PlayerWrapper. Two copies of a rule is
 * how the shortcode and auto-wrap paths came to disagree about a video's
 * protection level, which was a real bug fixed earlier in 1.3.0. The same shape
 * was sitting here waiting for someone to add a third adaptive platform and
 * update only one of them.
 *
 * A scan reported this as an "enum drift" defect across four files. It is worth
 * writing down that it mostly was not: `CPT\Thumbnail` switches on which
 * platforms expose a thumbnail API (self-hosted has none, correctly), while the
 * two player sites ask which need an HLS library. Those are different
 * questions that happen to share a variable name, and collapsing them into one
 * list would have been wrong. Only the duplicated question is unified here.
 */
class Platforms {

	/**
	 * The self-hosted slug - the one platform Free owns outright.
	 */
	public const SELF_HOSTED = 'self';

	/**
	 * Platforms whose media is delivered as an adaptive stream we must play in
	 * a real <video> element, rather than handing off to the provider's iframe.
	 *
	 * Kept as a filterable list rather than a constant so a driver added
	 * through `mediashield_upload_drivers` can opt in. Anything not listed here
	 * plays in the provider's own embed and needs no HLS library.
	 *
	 * @return string[]
	 */
	public static function adaptive(): array {
		/**
		 * Filter which platforms need the adaptive-streaming player.
		 *
		 * @since 1.3.0
		 *
		 * @param string[] $platforms Platform slugs.
		 */
		return (array) apply_filters(
			'mediashield_adaptive_platforms',
			array( self::SELF_HOSTED, 'bunny' )
		);
	}

	/**
	 * Does this platform need the adaptive-streaming player?
	 *
	 * The single implementation of the rule that used to live in both
	 * `Player\Renderer` and `Player\PlayerWrapper`. Both now call this, so the
	 * shortcode path and the output-buffer path cannot answer it differently.
	 *
	 * @param string $platform Platform slug.
	 * @return bool
	 */
	public static function needs_adaptive_player( string $platform ): bool {
		return in_array( $platform, self::adaptive(), true );
	}

	/**
	 * Read the library id and video GUID out of any Bunny Stream URL.
	 *
	 * The one Bunny parser. The CPT editor, the block and the auto-wrap path
	 * each carried their own regex, they drifted, and the same URL was saved as
	 * `self` with no id on one screen and as `bunny` on another
	 * (BC#10341091646). Everything that needs to recognise a Bunny URL asks
	 * this method instead.
	 *
	 * Recognised: {iframe,player}.mediadelivery.net and the older
	 * video.bunnycdn.com, each as /{embed,play}/{library}/{guid}, and the
	 * dashboard address dash.bunny.net/stream/{library}/library/{guid}. A
	 * dashboard collection URL is not a video and returns nothing.
	 *
	 * Pull-zone file URLs (vz-xxxx.b-cdn.net/{guid}/playlist.m3u8) carry no
	 * library id, so they match only when their host is one of this site's
	 * own pull zones - see bunny_pull_zones(). Anything else on a pull zone is
	 * a direct stream file and stays self-hosted.
	 *
	 * @param string $url Any URL.
	 * @return array{library:string, guid:string}|array{} Empty when not a Bunny video URL.
	 */
	public static function bunny_from_url( string $url ): array {
		$patterns = array(
			'#(?:(?:iframe|player)\.mediadelivery\.net|video\.bunnycdn\.com)/(?:embed|play)/(\d+)/([a-f0-9-]{36})#i',
			'#dash\.bunny\.net/stream/(\d+)/library/([a-f0-9-]{36})#i',
		);

		foreach ( $patterns as $pattern ) {
			if ( preg_match( $pattern, $url, $m ) ) {
				return array(
					'library' => $m[1],
					'guid'    => strtolower( $m[2] ),
				);
			}
		}

		$zones = self::bunny_pull_zones();
		$host  = strtolower( (string) wp_parse_url( $url, PHP_URL_HOST ) );
		if ( isset( $zones[ $host ] ) && preg_match( '#^/([a-f0-9-]{36})(?:/|$)#i', (string) wp_parse_url( $url, PHP_URL_PATH ), $m ) ) {
			return array(
				'library' => (string) $zones[ $host ],
				'guid'    => strtolower( $m[1] ),
			);
		}

		return array();
	}

	/**
	 * What a pasted video link is: its platform and the platform's video id.
	 *
	 * The one recogniser for a link someone adds: the setup wizard, Add video,
	 * the block and any REST client all end here, so a link that can never
	 * play is refused once, in the same words, instead of each screen saving
	 * it as "self-hosted" or "iframe" (Basecamp 10545260436). The browser-side
	 * detection (src/admin/utils/platform.js, assets/js/admin-video.js) only
	 * shapes what the screen shows while typing; this decides what is saved.
	 *
	 * Recognised: a YouTube, Vimeo or Wistia video link, any Bunny Stream URL
	 * bunny_from_url() reads, a link to a video file (.mp4, .webm, .ogv, .mov,
	 * .m4v, .m3u8, .mpd), MediaShield's own /stream/ address and a URL into
	 * the protected uploads folder (an uploaded file).
	 *
	 * @since 1.4.4
	 *
	 * @param string $url A link as pasted.
	 * @return array{platform:string, video_id:string}|\WP_Error The platform and id, or `mediashield_unplayable_source` with the message to show.
	 */
	public static function recognise( string $url ) {
		$url = trim( $url );

		$patterns = array(
			'youtube' => '#(?:youtube(?:-nocookie)?\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/|v/)|youtu\.be/)([A-Za-z0-9_-]{11})#i',
			'vimeo'   => '#vimeo\.com/(?:video/|channels/[^/]+/|groups/[^/]+/videos/)?(\d{5,})#i',
			'wistia'  => '#(?:wistia\.(?:com|net)/(?:medias|embed/(?:iframe|medias))/|wi\.st/(?:medias/)?)([a-z0-9]{10})#i',
		);

		if ( preg_match( '#^https?://#i', $url ) ) {
			foreach ( $patterns as $platform => $pattern ) {
				if ( preg_match( $pattern, $url, $m ) ) {
					return array(
						'platform' => $platform,
						'video_id' => $m[1],
					);
				}
			}

			$bunny = self::bunny_from_url( $url );
			if ( $bunny ) {
				return array(
					'platform' => 'bunny',
					'video_id' => $bunny['guid'],
				);
			}

			$path = (string) wp_parse_url( $url, PHP_URL_PATH );
			if ( preg_match( '/\.(mp4|webm|ogv|ogg|mov|m4v|m3u8|mpd)$/i', $path )
				|| false !== strpos( $url, 'mediashield/v1/stream/' )
				|| \MediaShield\Player\Protection::in_protected_folder( $url ) ) {
				return array(
					'platform' => self::SELF_HOSTED,
					'video_id' => '',
				);
			}
		}

		return new \WP_Error( 'mediashield_unplayable_source', self::unplayable_message(), array( 'status' => 400 ) );
	}

	/**
	 * The sentence that refuses a link MediaShield cannot play, naming what to use instead.
	 *
	 * Bunny is named only where Pro can play it.
	 *
	 * @since 1.4.4
	 *
	 * @return string
	 */
	public static function unplayable_message(): string {
		if ( defined( 'MEDIASHIELD_PRO_VERSION' ) ) {
			return __( 'This link is not a video MediaShield can play. Use a YouTube, Vimeo, Wistia or Bunny Stream link, a link to an .mp4 file, or upload a file.', 'mediashield' );
		}
		return __( 'This link is not a video MediaShield can play. Use a YouTube, Vimeo or Wistia link, a link to an .mp4 file, or upload a file.', 'mediashield' );
	}

	/**
	 * This site's Bunny pull-zone hostnames, mapped to their library ids.
	 *
	 * Empty in Free. Pro fills it from the connected Bunny library, which is
	 * the only thing that knows which library a pull zone belongs to.
	 *
	 * @return array<string,string> Lowercase hostname => library id.
	 */
	public static function bunny_pull_zones(): array {
		static $zones = null;

		if ( null === $zones ) {
			/**
			 * Filter the Bunny pull zones this site's videos are served from.
			 *
			 * @since 1.3.1
			 *
			 * @param array<string,string> $zones Lowercase hostname => library id.
			 */
			$zones = array_change_key_case( (array) apply_filters( 'mediashield_bunny_pull_zones', array() ), CASE_LOWER );
		}

		return $zones;
	}

	/**
	 * The stable, unsigned embed address for a Bunny video - what `_ms_source_url` stores.
	 *
	 * @param string $library Library id.
	 * @param string $guid    Video GUID.
	 * @return string
	 */
	public static function bunny_embed_url( string $library, string $guid ): string {
		return "https://iframe.mediadelivery.net/embed/{$library}/{$guid}";
	}

	/**
	 * Every platform slug this install can store on a video.
	 *
	 * Derived from the registered upload drivers plus self-hosted, rather than
	 * hardcoded: Pro registers Bunny, Vimeo, YouTube and Wistia through
	 * `mediashield_upload_drivers`, and a third-party driver may register more.
	 * A fixed list here would silently exclude them.
	 *
	 * NOTE: nothing validates `_ms_platform` against this on write - the REST
	 * schema declares it a plain string. Wiring that up would reject values
	 * that installs may already be storing, so it is a deliberate decision for
	 * the owner rather than something to switch on quietly. This method exists
	 * so that decision has somewhere to land.
	 *
	 * @return string[]
	 */
	public static function all(): array {
		$slugs = array_keys( UploadManager::get_drivers() );

		// The driver is registered as `self_hosted`; the meta value is `self`.
		$slugs = array_values(
			array_diff( $slugs, array( 'self_hosted' ) )
		);

		array_unshift( $slugs, self::SELF_HOSTED );

		return array_values( array_unique( $slugs ) );
	}
}
