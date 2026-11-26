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
		add_filter( 'mediashield_library_file_in_use', array( __CLASS__, 'library_file_in_use' ), 10, 5 );
		add_filter( 'learnomy_lesson_completion_rules', array( __CLASS__, 'require_watch' ), 10, 3 );
		add_filter( 'mediashield_video_access_link', array( __CLASS__, 'course_link' ), 10, 2 );
		add_filter( 'mediashield_watch_rule_applies', array( __CLASS__, 'watch_rule_applies' ), 10, 2 );
		add_filter( 'mediashield_video_used_in', array( __CLASS__, 'used_in' ), 10, 2 );
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
		return ! empty( get_option( self::RULE_MOVED_OPTION ) )
			|| (int) Settings::get( 'ms_lms_require_watch_pct' ) <= 0;
	}

	/**
	 * Does a published protected video lesson complete by watching this video?
	 *
	 * The lesson's own watch % once Learnomy owns the rule, else the site's
	 * "Watch X% to complete" (owns_watch_rule()). For the owner's "add this
	 * video's length" line (VideoStatus::needs_length()).
	 *
	 * @since 1.4.4
	 *
	 * @param bool $applies  Earlier answer.
	 * @param int  $video_id Video ID.
	 * @return bool
	 */
	public static function watch_rule_applies( $applies, $video_id ): bool {
		if ( $applies ) {
			return true;
		}
		$owns = self::owns_watch_rule();
		$site = (int) Settings::get( 'ms_lms_require_watch_pct' );
		foreach ( self::lessons_playing( (int) $video_id ) as $lesson ) {
			if ( $owns ? (int) ( $lesson->min_video_watch_pct ?? 0 ) > 0 : $site > 0 ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Add the Learnomy lessons that play each listed video to "Used in"
	 * (the Videos list, the Video block's sidebar): a video used only in a
	 * lesson said "Not used yet", so an owner tidying up could trash a
	 * lesson's video (Basecamp 10545260458).
	 *
	 * Lessons are rows in Learnomy's table, not posts: each entry's id is
	 * "learnomy_lesson:N", so it never matches a post id, and the link is
	 * Learnomy's lesson editor for someone who may edit lessons, else the
	 * lesson page.
	 * ponytail: one cached Learnomy lookup per listed video (a page of 20);
	 * batch it in Learnomy if lists grow.
	 *
	 * @since 1.4.4
	 *
	 * @param array $usage     Places by video ID.
	 * @param int[] $video_ids Videos being listed.
	 * @return array
	 */
	public static function used_in( $usage, $video_ids ): array {
		$usage = (array) $usage;
		$label = function_exists( '\\Learnomy\\lesson_label' ) ? (string) \Learnomy\lesson_label() : __( 'Lesson', 'mediashield' );
		$edits = current_user_can( 'learnomy_manage_own_courses' ) || current_user_can( 'manage_options' );
		foreach ( (array) $video_ids as $video_id ) {
			// Drafts too, as Used in lists draft pages: an owner tidying up
			// must not trash the video a lesson in progress needs (round 2).
			$lessons = self::lessons_playing( (int) $video_id, false );
			if ( ! $lessons ) {
				continue;
			}
			$places = isset( $usage[ $video_id ] ) ? (array) $usage[ $video_id ] : array();
			foreach ( $lessons as $lesson ) {
				$places[] = array(
					'id'    => 'learnomy_lesson:' . (int) $lesson->id,
					'title' => '' !== (string) ( $lesson->title ?? '' ) ? wp_strip_all_tags( (string) $lesson->title ) : __( '(no title)', 'mediashield' ),
					'url'   => $edits ? admin_url( 'admin.php?page=learnomy-lesson-editor&lesson_id=' . (int) $lesson->id ) : self::lesson_url( $lesson ),
					'type'  => $label,
				);
			}
			$usage[ $video_id ] = $places;
		}
		return $usage;
	}

	/**
	 * A lesson's page, '' when Learnomy cannot say.
	 *
	 * @param object $lesson Lesson row.
	 * @return string
	 */
	private static function lesson_url( $lesson ): string {
		if ( ! function_exists( '\\Learnomy\\route_url' ) || ! class_exists( '\\Learnomy\\Models\\Course' ) ) {
			return '';
		}
		$course = \Learnomy\Models\Course::find( (int) ( $lesson->course_id ?? 0 ) );
		if ( ! $course || empty( $course->slug ) || empty( $lesson->slug ) ) {
			return '';
		}
		return \Learnomy\route_url(
			'lesson',
			array(
				'slug'        => (string) $course->slug,
				'lesson_slug' => (string) $lesson->slug,
			)
		);
	}

	/**
	 * The published protected video lessons that play this video, free
	 * previews first, through Learnomy's own cached lookup:
	 * Lesson::naming_mediashield_video() (2.4.1, every lesson naming it in
	 * any status, so the published protected ones are kept here), else
	 * Lesson::for_mediashield_video() (2.4.0, published ones only). MediaShield
	 * 1.4.4 called only the second, which 2.4.1 does not have, so neither the
	 * "Length needed" prompt nor the course link on a refusal ever showed for
	 * a Learnomy lesson (Learnomy card 10536470184).
	 *
	 * Used in passes $published_only false: there a draft lesson counts too.
	 * Access, the watch rule and the course link keep published lessons only.
	 *
	 * @param int  $video_id       Video ID.
	 * @param bool $published_only Only published lessons (default), or any status.
	 * @return object[] Lesson rows.
	 */
	private static function lessons_playing( int $video_id, bool $published_only = true ): array {
		if ( $video_id <= 0 || ! class_exists( '\\Learnomy\\Models\\Lesson' ) ) {
			return array();
		}
		if ( is_callable( '\\Learnomy\\Models\\Lesson::naming_mediashield_video' ) ) {
			$rows = \Learnomy\Models\Lesson::naming_mediashield_video( $video_id );
		} elseif ( is_callable( '\\Learnomy\\Models\\Lesson::for_mediashield_video' ) ) {
			$rows = \Learnomy\Models\Lesson::for_mediashield_video( $video_id );
		} else {
			return array();
		}
		$rows = array_values(
			array_filter(
				(array) $rows,
				static function ( $lesson ) use ( $video_id, $published_only ) {
					return self::lesson_video( $lesson ) === $video_id && ( ! $published_only || 'published' === (string) ( $lesson->status ?? 'published' ) );
				}
			)
		);
		usort(
			$rows,
			static function ( $a, $b ) {
				return (int) ( $b->is_preview ?? 0 ) <=> (int) ( $a->is_preview ?? 0 );
			}
		);
		return $rows;
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
	 * Lesson completion "Default for new protected video lessons"
	 * (ms_learnomy_new_lesson_pct: 80 on a new install, the stored Require X%
	 * watched on a site that updates); 0 leaves Learnomy's own default.
	 *
	 * @since 1.4.4
	 *
	 * @param int $pct Default so far.
	 * @return int
	 */
	public static function default_watch_pct( $pct ): int {
		$setting = (int) Settings::get( 'ms_learnomy_new_lesson_pct' );
		return $setting > 0 ? $setting : (int) $pct;
	}

	/**
	 * Learnomy's record of its one-time move of the site-wide watch rule.
	 * Learnomy owns it and writes it, including on a site with nothing to
	 * move; MediaShield only reads it (owns_watch_rule()).
	 */
	public const RULE_MOVED_OPTION = 'learnomy_protected_video_rule_moved';

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
		if ( ! empty( $link ) || ! function_exists( '\\Learnomy\\route_url' ) || ! class_exists( '\\Learnomy\\Models\\Course' ) ) {
			return $link;
		}
		// Learnomy's own lookup (published lessons playing the video, cached).
		$course = null;
		foreach ( self::lessons_playing( (int) $video_id ) as $lesson ) {
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
	 * @param int    $video_id   Video being protected (unused).
	 * @param array  $replacing  { type, id }: a lesson being switched to this
	 *                           video ('learnomy_lesson') is not a use (1.4.4).
	 * @return string
	 */
	public static function library_file_in_use( $where, $relative, $attachment, $video_id = 0, $replacing = array() ): string {
		unset( $video_id );
		if ( '' !== (string) $where || ! function_exists( '\\Learnomy\\table' ) ) {
			return (string) $where;
		}
		$relative = (string) $relative;
		$lessons  = \Learnomy\table( 'lessons' );
		$skip     = 'learnomy_lesson' === ( $replacing['type'] ?? '' ) ? absint( $replacing['id'] ?? 0 ) : 0;
		// Older Learnomy schemas have no media_id; that query's error is ignored, the URL check stands.
		if ( LibraryFiles::table_mentions_file( $lessons, array( 'video_url', 'content' ), $relative, $skip )
			|| LibraryFiles::table_has_value( $lessons, 'media_id', (int) $attachment, $skip ) ) {
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
