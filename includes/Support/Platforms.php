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
