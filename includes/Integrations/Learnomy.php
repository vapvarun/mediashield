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

use MediaShield\Access\AccessControl;
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
		add_filter( 'mediashield_library_file_in_use', array( __CLASS__, 'library_file_in_use' ), 10, 3 );
	}

	/**
	 * Is this Media Library file played or embedded by a Learnomy lesson or course?
	 *
	 * A plain "Video" lesson, a course intro video or lesson text can name the
	 * file by URL in Learnomy's own tables, where MediaShield's post scan
	 * cannot see it. Moving the file would break that lesson, so it is
	 * reported as used and left in place (Upload\LibraryFiles). A query error
	 * also counts as used: when in doubt the file stays where it is.
	 *
	 * @since 1.4.2
	 *
	 * @param string $where      Where it is used so far, '' when nowhere.
	 * @param string $relative   File path relative to the uploads folder.
	 * @param int    $attachment Media Library item, or 0.
	 * @return string
	 */
	public static function library_file_in_use( $where, $relative, $attachment ): string {
		if ( '' !== (string) $where || ! function_exists( '\\Learnomy\\table' ) ) {
			return (string) $where;
		}
		global $wpdb;
		$like = '%' . $wpdb->esc_like( (string) $relative ) . '%';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reference scan; results must be live.
		$lesson = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM %i WHERE video_url LIKE %s OR content LIKE %s LIMIT 1',
				\Learnomy\table( 'lessons' ),
				$like,
				$like
			)
		);
		$failed = '' !== $wpdb->last_error;
		if ( ! $lesson && ! $failed && (int) $attachment > 0 ) {
			// Older Learnomy schemas have no media_id; that error is ignored, the URL check above stands.
			$lesson = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE media_id = %d LIMIT 1', \Learnomy\table( 'lessons' ), (int) $attachment ) );
		}
		if ( $lesson || $failed ) {
			return __( 'a Learnomy lesson', 'mediashield' );
		}

		$course = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT id FROM %i WHERE video_url LIKE %s OR content LIKE %s OR description LIKE %s LIMIT 1',
				\Learnomy\table( 'courses' ),
				$like,
				$like,
				$like
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $course || '' !== $wpdb->last_error ? __( 'a Learnomy course', 'mediashield' ) : '';
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

		// A drip- or sequence-locked lesson is metadata only. Learnomy marks it
		// before this filter runs and strips only its own fields afterwards, so
		// a link added here would play a lesson the student cannot open yet.
		if ( ! empty( $payload['locked'] ) ) {
			return $payload;
		}

		// Minted for the CALLER, not for the lesson: the link carries who may
		// watch, and the embed page re-checks that when it renders. A guest
		// gets a link only when they may watch now (a free preview under
		// Require Login, for one); otherwise the link would only fail later,
		// further from the cause.
		$user_id = get_current_user_id();
		if ( ! $user_id && ! AccessControl::can_watch( $video_id, 0 )['allowed'] ) {
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
