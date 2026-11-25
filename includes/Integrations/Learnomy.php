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
use MediaShield\Access\WatchedSpans;
use MediaShield\Core\Settings;
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
		// Learnomy 2.4.1 owns "watch the video to complete" on each lesson;
		// MediaShield reports how much was watched and the starting %.
		// Hooking the first filter is what tells Learnomy a provider measures
		// protected videos (\Learnomy\protected_video_measured()).
		add_filter( 'learnomy_lesson_watched_pct', array( __CLASS__, 'watched_pct' ), 10, 3 );
		add_filter( 'learnomy_protected_video_default_watch_pct', array( __CLASS__, 'default_watch_pct' ) );
		// When Learnomy has copied the % onto its Manual protected lessons it
		// saves the option learnomy_protected_video_rule_moved, which
		// owns_watch_rule() waits for. The setting ms_lms_require_watch_pct
		// stays as it is: it still gates LearnDash lessons (Pro), feeds the
		// player's early report (SessionController) and is the starting % for
		// new Learnomy lessons (default_watch_pct()), so clearing it would
		// change those.
	}

	/**
	 * Does Learnomy decide "watch the video to complete" on each lesson?
	 *
	 * True from Learnomy 2.4.1, which defines protected_video_measured(), once
	 * it has finished moving the site-wide % onto each lesson (it saves
	 * `learnomy_protected_video_rule_moved`, { pct, moved, at }, on the last
	 * page of its background move), or straight away when the site-wide % is
	 * 0 and there is nothing to move. Then the lesson's own Completion rule
	 * decides and MediaShield only reports the watched share (watched_pct()).
	 *
	 * False with Learnomy 2.4.0 or older, and with 2.4.1 while the move is
	 * still running: until then the lessons have no watch gate of their own,
	 * so the site-wide "Require X% watched" keeps applying (require_watch())
	 * and Settings keeps its control. The admin Settings screen reads the
	 * same answer (Admin\Menu, learnomyOwnsWatchRule).
	 *
	 * @since 1.4.4
	 *
	 * @return bool
	 */
	public static function owns_watch_rule(): bool {
		if ( ! function_exists( '\\Learnomy\\protected_video_measured' ) ) {
			return false;
		}
		return ! empty( get_option( 'learnomy_protected_video_rule_moved' ) )
			|| (int) Settings::get( 'ms_lms_require_watch_pct' ) <= 0;
	}

	/**
	 * The MediaShield video a protected video lesson plays, 0 when none.
	 *
	 * Learnomy stores the video's post ID in `video_url` for content type
	 * 'mediashield'. Anything else (another content type, a URL, an ID that
	 * is not a MediaShield video) is not ours to answer.
	 *
	 * @since 1.4.4
	 *
	 * @param object $lesson Lesson row.
	 * @return int
	 */
	private static function lesson_video( $lesson ): int {
		if ( ! is_object( $lesson ) || self::CONTENT_TYPE !== (string) ( $lesson->content_type ?? '' ) ) {
			return 0;
		}
		$id = trim( (string) ( $lesson->video_url ?? '' ) );
		if ( '' === $id || ! ctype_digit( $id ) ) {
			return 0;
		}
		return 'mediashield_video' === get_post_type( (int) $id ) ? (int) $id : 0;
	}

	/**
	 * How much of a protected video lesson's video has this member watched?
	 *
	 * Answers Learnomy 2.4.1's `learnomy_lesson_watched_pct` with the share
	 * measured on the server (WatchedSpans: time spent watching, merged
	 * across sittings, so skipping ahead earns nothing). Another lesson's
	 * value is passed through untouched (null by default), so other
	 * providers are not overridden. For the site-wide rule use
	 * SessionManager::watch_requirement().
	 *
	 * @since 1.4.4
	 *
	 * @param int|null $pct     Share so far; null when no provider answered.
	 * @param int      $user_id Member; 0 (a guest) has watched nothing.
	 * @param object   $lesson  Lesson row.
	 * @return int|null 0-100, or $pct for a lesson that is not a protected video.
	 */
	public static function watched_pct( $pct, $user_id, $lesson ) {
		$video_id = self::lesson_video( $lesson );
		if ( ! $video_id ) {
			return $pct;
		}
		return (int) $user_id > 0 ? WatchedSpans::percent( (int) $user_id, $video_id ) : 0;
	}

	/**
	 * Starting "watch %" for a new Learnomy protected video lesson.
	 *
	 * Answers `learnomy_protected_video_default_watch_pct` with Settings >
	 * Integrations "Require X% watched", so a site that chose a share keeps
	 * it on new lessons; 0 (off) leaves Learnomy's own default.
	 *
	 * @since 1.4.4
	 *
	 * @param int $pct Default so far.
	 * @return int
	 */
	public static function default_watch_pct( $pct ): int {
		$setting = (int) Settings::get( 'ms_lms_require_watch_pct' );
		return $setting > 0 ? $setting : (int) $pct;
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
	 * Once Learnomy owns the rule (owns_watch_rule(): 2.4.1 with the % moved
	 * onto each lesson) the lesson's own Completion rule decides (it asks
	 * watched_pct() for the share), so this adds nothing: two rules on one
	 * lesson would show two strips and could disagree. With Learnomy 2.4.0,
	 * and with 2.4.1 until its move has finished, it behaves exactly as in
	 * 1.4.3, so no lesson is left without a watch gate in between.
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
		if ( self::owns_watch_rule() ) {
			return $status;
		}
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
