<?php
/**
 * Learnomy integration — put a playable URL on a MediaShield lesson.
 *
 * WHY THIS LIVES HERE AND NOT IN LEARNOMY
 *
 * Learnomy stores the `mediashield_video` post ID in a lesson's `video_url`
 * column and hands it to clients as-is. The web player copes because it can run
 * `[mediashield id=N]` in PHP; the mobile app cannot, so it received `"291"`
 * and rendered an empty media area (Basecamp 10199485635).
 *
 * The fix is a URL, and the plugin that owns the player owns the URL. Learnomy
 * publishes `learnomy_rest_lesson_payload` for exactly this, and its H5P bridge
 * uses the same seam — the provider-specific resolver lives with the provider,
 * so Learnomy never learns anyone's internals. Nothing here runs without
 * Learnomy: no filter, no cost.
 *
 * @package MediaShield
 * @since   1.3.0
 */

namespace MediaShield\Integrations;

use MediaShield\Embed\EmbedLink;

defined( 'ABSPATH' ) || exit;

/**
 * Adds a `mediashield` block to Learnomy's lesson payload.
 */
class Learnomy {

	/**
	 * Learnomy's content-type slug for a protected video lesson.
	 */
	private const CONTENT_TYPE = 'mediashield';

	/**
	 * Hook up.
	 */
	public static function register(): void {
		add_filter( 'learnomy_rest_lesson_payload', array( __CLASS__, 'add_embed_to_payload' ), 10, 3 );
	}

	/**
	 * Resolve the stored post ID into something a client can play.
	 *
	 * Only added when the id resolves to a video that exists and is published.
	 * A lesson pointing at a deleted video gets no `mediashield` block at all,
	 * so a client can tell "there is nothing here" from "here is a URL that
	 * fails" — the same contract the H5P bridge follows.
	 *
	 * @param array                $payload Lesson REST payload.
	 * @param object               $lesson  Lesson row.
	 * @param \WP_REST_Request|null $request The request being answered.
	 * @return array
	 */
	public static function add_embed_to_payload( array $payload, $lesson, $request = null ): array {
		if ( self::CONTENT_TYPE !== (string) ( $lesson->content_type ?? '' ) ) {
			return $payload;
		}

		$video_id = absint( $lesson->video_url ?? 0 );
		if ( ! $video_id ) {
			return $payload;
		}

		// Minted for the CALLER, not for the lesson: the link carries who may
		// watch, and the embed page re-checks that when it renders. An
		// unauthenticated read gets no link rather than a guest one, because a
		// guest link would only fail later, further from the cause.
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return $payload;
		}

		$embed_url = EmbedLink::url( $video_id, $user_id );
		if ( '' === $embed_url ) {
			return $payload;
		}

		$payload['mediashield'] = array(
			'video_id'   => $video_id,
			'embed_url'  => $embed_url,
			// Short-lived and per-viewer, so a client must fetch the lesson
			// again at playback rather than cache this. Stated in the payload
			// rather than left in documentation nobody reads at 2am.
			'expires_in' => EmbedLink::TTL,
			'poster_url' => self::poster_url( $video_id ),
		);

		return $payload;
	}

	/**
	 * The video's own thumbnail, when it has one.
	 *
	 * One source only: the featured image. `CPT\Thumbnail` sideloads the
	 * platform's thumbnail into a real attachment and calls
	 * `set_post_thumbnail()`, so there is no separate URL meta to fall back to.
	 * An earlier draft here read `_ms_thumbnail_url`, a key nothing in this
	 * plugin has ever written — a fallback that could only ever return nothing
	 * while reading like a safety net.
	 *
	 * @param int $video_id Video CPT post ID.
	 * @return string Empty string when there is no poster.
	 */
	private static function poster_url( int $video_id ): string {
		$thumbnail = get_the_post_thumbnail_url( $video_id, 'large' );

		return is_string( $thumbnail ) ? $thumbnail : '';
	}
}
