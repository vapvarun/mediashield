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
use MediaShield\Access\SessionManager;
use MediaShield\Embed\EmbedLink;
use MediaShield\Upload\LibraryFiles;

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
		add_filter( 'learnomy_lesson_completion_rules', array( __CLASS__, 'require_watch' ), 10, 3 );
		add_filter( 'mediashield_video_access_link', array( __CLASS__, 'course_link' ), 10, 2 );
	}

	/**
	 * The Learnomy course a refused viewer can enrol in to watch this video.
	 *
	 * The published course whose published "Protected video" lesson plays it,
	 * so the denied player can say "This video is part of <course>" with a
	 * View course button (Basecamp 10530930631). Learnomy's own watch link
	 * (mediashield_video_watch_url) is only for viewers who may open the
	 * lesson, which a refused viewer cannot, so the course page is the link.
	 * Another integration's answer is kept.
	 *
	 * @since 1.4.3
	 *
	 * @param array|null $link     Link so far.
	 * @param int        $video_id Video CPT post ID.
	 * @return array|null { title, url }.
	 */
	public static function course_link( $link, $video_id ) {
		if ( ! empty( $link ) || ! function_exists( '\\Learnomy\\route_url' ) || ! class_exists( '\\Learnomy\\Models\\Lesson' ) || ! is_callable( '\\Learnomy\\Models\\Lesson::for_mediashield_video' ) || ! class_exists( '\\Learnomy\\Models\\Course' ) ) {
			return $link;
		}
		// Learnomy's own lookup (published lessons playing the video, cached).
		$course = null;
		foreach ( \Learnomy\Models\Lesson::for_mediashield_video( (int) $video_id ) as $lesson ) {
			$candidate = \Learnomy\Models\Course::find( (int) ( $lesson->course_id ?? 0 ) );
			if ( $candidate && 'published' === (string) ( $candidate->status ?? '' ) && ! empty( $candidate->slug ) ) {
				$course = $candidate;
				break;
			}
		}
		if ( ! $course ) {
			return $link;
		}
		return array(
			'title' => (string) $course->title,
			'url'   => \Learnomy\route_url( 'course', array( 'slug' => (string) $course->slug ) ),
		);
	}

	/**
	 * Completion mode reported for a lesson this rule gates, when the lesson
	 * itself is Manual. Learnomy shows its requirement strip and keeps
	 * Complete disabled for any mode other than 'manual', and auto-completes
	 * only its own auto_* modes, so the member still clicks Complete.
	 */
	public const WATCH_MODE = 'mediashield_watch';

	/**
	 * Add "Watch X% to complete" to a protected video lesson.
	 *
	 * With Settings > LMS "Require X% watched" above 0, a member cannot
	 * complete a Learnomy lesson that plays a MediaShield video (content type
	 * Protected video) until they have watched that share of it. The rule is
	 * enforced where Learnomy decides: this filter feeds the status that
	 * Progress_Service::mark_lesson_complete() checks, so a REST
	 * /lessons/{id}/complete call before the share is refused (422), not only
	 * the button. Learnomy's own enforcement modes still apply: 'off'
	 * (admins, the course's instructor) is untouched, 'advisory' shows the
	 * requirement without blocking. A completed lesson stays completed.
	 *
	 * @since 1.4.3
	 *
	 * @param array  $status  Learnomy completion status.
	 * @param int    $user_id Member.
	 * @param object $lesson  Lesson row.
	 * @return array
	 */
	public static function require_watch( $status, $user_id, $lesson ): array {
		$status = (array) $status;
		if ( self::CONTENT_TYPE !== (string) ( $lesson->content_type ?? '' )
			|| ! empty( $status['is_completed'] ) || 'off' === ( $status['enforcement'] ?? 'strict' ) ) {
			return $status;
		}
		$watch = SessionManager::watch_requirement( absint( $lesson->video_url ?? 0 ), (int) $user_id );
		if ( ! $watch ) {
			return $status;
		}
		$required = $watch['required'];
		$watched  = $watch['watched'];
		$met      = $watch['met'];

		$status['requirements']   = (array) ( $status['requirements'] ?? array() );
		$status['requirements'][] = array(
			'kind'        => 'video',
			'min_pct'     => $required,
			'current_pct' => $watched,
			'met'         => $met,
			/* translators: %d: percent of the video to watch, for example 80. */
			'label'       => sprintf( __( 'Watch %d%% to complete', 'mediashield' ), $required ),
		);
		if ( 'manual' === ( $status['completion_mode'] ?? 'manual' ) ) {
			$status['completion_mode'] = self::WATCH_MODE;
		}
		if ( ! $met && 'advisory' !== ( $status['enforcement'] ?? 'strict' ) ) {
			$status['all_met'] = false;
		}

		return $status;
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
	 * @param int    $attachment Media Library item.
	 * @return string
	 */
	public static function library_file_in_use( $where, $relative, $attachment ): string {
		if ( '' !== (string) $where || ! function_exists( '\\Learnomy\\table' ) ) {
			return (string) $where;
		}
		$relative = (string) $relative;
		$lessons  = \Learnomy\table( 'lessons' );
		// Older Learnomy schemas have no media_id; that query's error is ignored, the URL check stands.
		if ( LibraryFiles::table_mentions_file( $lessons, array( 'video_url', 'content' ), $relative )
			|| LibraryFiles::table_has_value( $lessons, 'media_id', (int) $attachment ) ) {
			return __( 'a Learnomy lesson', 'mediashield' );
		}
		return LibraryFiles::table_mentions_file( \Learnomy\table( 'courses' ), array( 'video_url', 'content', 'description' ), $relative )
			? __( 'a Learnomy course', 'mediashield' )
			: '';
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
		// watch, and the embed page re-checks that when it renders. Every
		// caller, guest or logged in, gets a link only when they may watch now
		// (Require Login, roles, domains, enrollment through mediashield_can_watch).
		// A refused viewer gets the payload unchanged: a signed link would tell
		// the client there is something to play, and only fail later, further
		// from the cause.
		$user_id = get_current_user_id();
		if ( ! AccessControl::can_watch( $video_id, $user_id )['allowed'] ) {
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
