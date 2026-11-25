<?php
/**
 * Media Library files that a protected video plays from.
 *
 * @package MediaShield\Upload
 */

namespace MediaShield\Upload;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use MediaShield\Cron\Cleanup;
use MediaShield\REST\StreamController;
use MediaShield\Upload\Drivers\SelfHosted;

/**
 * Moves a self-hosted video's Media Library file into the protected folder.
 *
 * WHY. A self-hosted video can take its source from an ordinary Media Library
 * file. The player streams it through the gated /stream/ route, but the file
 * itself stayed a public upload: listed in /wp/v2/media and served directly by
 * the web server, so a visitor who never bought the course could download it
 * (Basecamp 10482094152).
 *
 * WHAT. The file moves into uploads/mediashield/ under an obscured name, the
 * same folder and naming as a MediaShield upload, and the video then owns it
 * exactly like an upload. The Media Library entry is kept and follows the file
 * (`_wp_attached_file`), and becomes private, so core leaves it out of
 * /wp/v2/media, attachment pages and search for anyone who cannot read private
 * media. Nothing is copied and nothing is deleted.
 *
 * WHEN IT DOES NOT. Only a file with a Media Library item moves. A file with
 * none belongs to another plugin or to something the site does not track
 * (WPMediaVerse keeps uploads under uploads/wpmediaverse/), so it stays where
 * it is (Basecamp 10517640742). A Media Library file also used elsewhere on
 * the site (embedded in a post, named by another video, a widget, a Learnomy
 * lesson or course) is left too, because moving it would break that other
 * page. The owner is told which videos those are and how to protect them.
 *
 * UNDO. restore() puts a moved file back at its old path and the Media Library
 * entry back to its old status. It runs when the video is deleted, on
 * uninstall, and from `wp mediashield repair restore-library-files`.
 *
 * Existing videos are handled once by a background job after the 1.4.2 update
 * (Migrator v8); new and edited videos on save. Files 1.4.2 moved that had no
 * Media Library item and sat outside the uploads YYYY/MM folders go back once
 * after the 1.4.3 update (Migrator v11, repair_batch()). Playback never breaks: the
 * stream route serves the file from its old path until the move, and from the
 * protected folder after it, at the same URL.
 *
 * @since 1.4.2
 */
class LibraryFiles {

	/**
	 * Option: set while the upgrade job still has videos to check.
	 */
	public const PENDING = 'ms_library_files_pending';

	/**
	 * Option: set while the 1.4.3 repair still has videos to check; holds the last video ID checked.
	 */
	public const REPAIR_PENDING = 'ms_library_files_repair_pending';

	/**
	 * Option: what moved and what was left, for the owner notice.
	 */
	private const REPORT = 'ms_library_files_report';

	/**
	 * Video meta: moved | in_use | not_in_library | none | restored. Its presence marks a video checked.
	 */
	private const STATE = '_ms_library_file';

	/**
	 * Video and attachment meta: where a moved file came from.
	 */
	private const RESTORE = '_ms_library_restore';

	/**
	 * Video meta: the source URL the owner chose to keep public by restoring it.
	 */
	private const KEPT = '_ms_library_kept';

	/**
	 * Report reason for a file that is unused but could not be moved.
	 */
	private const MOVE_FAILED = '#move_failed';

	/**
	 * Report reason for a file the person saving the video may not edit.
	 */
	private const NOT_EDITABLE = '#not_editable';

	/**
	 * Report reason, and video state, for a file with no Media Library item: not ours to move.
	 */
	private const NOT_IN_LIBRARY = 'not_in_library';

	/**
	 * Background job hook.
	 */
	private const JOB = 'ms_protect_library_files';

	/**
	 * 1.4.3 repair job hook.
	 */
	private const REPAIR_JOB = 'ms_repair_library_files';

	/**
	 * The one reference the in-use check skips while another plugin switches
	 * it to a new video: { type: 'post'|'learnomy_lesson'|…, id: int }, or
	 * empty (while_replacing()).
	 *
	 * @var array
	 */
	private static $replacing = array();

	/**
	 * Run $work while the in-use check skips one reference: the post or row
	 * that is about to play the new video instead of the file
	 * (mediashield_create_video's `replacing`). Every other use still keeps
	 * the file in place.
	 *
	 * @since 1.4.4
	 *
	 * @param array    $replacing { type: string, id: int }; empty skips nothing.
	 * @param callable $work      What to run.
	 * @return mixed What $work returned.
	 */
	public static function while_replacing( array $replacing, callable $work ) {
		$before          = self::$replacing;
		self::$replacing = $replacing;
		try {
			return $work();
		} finally {
			self::$replacing = $before;
		}
	}

	/**
	 * Check a video left 'in_use' again (inside while_replacing(), once the
	 * reference that kept it is the one being switched).
	 *
	 * @since 1.4.4
	 *
	 * @param int $video_id Video post ID.
	 */
	public static function recheck_in_use( int $video_id ): void {
		if ( 'in_use' !== get_post_meta( $video_id, self::STATE, true ) ) {
			return;
		}
		delete_post_meta( $video_id, self::STATE );
		self::report( $video_id, null );
		self::protect( $video_id, true );
	}

	/**
	 * Register hooks.
	 */
	public static function register(): void {
		add_action( self::JOB, array( __CLASS__, 'run_batch' ) );
		add_action( 'init', array( __CLASS__, 'ensure_job' ), 20 );
		add_action( self::REPAIR_JOB, array( __CLASS__, 'repair_batch' ) );
		// After VideoPostType::normalize_platform() (20) has settled the platform.
		add_action( 'save_post_mediashield_video', array( __CLASS__, 'on_save' ), 30 );
		add_action(
			'rest_after_insert_mediashield_video',
			static function ( \WP_Post $post ): void {
				self::on_save( $post->ID );
			},
			20
		);
		add_action( 'admin_notices', array( __CLASS__, 'render_notice' ) );
		add_action( 'admin_init', array( __CLASS__, 'dismiss_notice' ) );
	}

	/**
	 * Re-check a video whenever it is saved, so a newly picked file is protected at once.
	 *
	 * Two states are left alone only while nothing has changed:
	 * - moved: while the video still plays the file it moved (its file name is
	 *   the one it owns and its source is its /stream URL). Re-checking would
	 *   find a file it owns and drop it from the owner notice.
	 * - restored: while the source is still the file the owner moved back on
	 *   purpose. That choice is theirs for that file.
	 * Point either at a different file and the new one is protected like any
	 * other; a moved file the video no longer plays goes back to the Media
	 * Library first, so nothing is stranded in the protected folder.
	 *
	 * @param int $video_id Video post ID.
	 */
	public static function on_save( int $video_id ): void {
		if ( wp_is_post_revision( $video_id ) || wp_is_post_autosave( $video_id ) ) {
			return;
		}

		$state  = (string) get_post_meta( $video_id, self::STATE, true );
		$source = (string) get_post_meta( $video_id, '_ms_source_url', true );
		$file   = (string) get_post_meta( $video_id, '_ms_platform_video_id', true );

		if ( 'restored' === $state && $source === (string) get_post_meta( $video_id, self::KEPT, true ) ) {
			return;
		}

		if ( 'moved' === $state ) {
			$owned  = (string) get_post_meta( $video_id, '_ms_owned_file', true );
			$stream = rest_url( 'mediashield/v1/stream/' . $video_id );
			if ( '' !== $owned && $file === $owned && $source === $stream ) {
				return;
			}
			self::release( $video_id, $owned, $stream, $source, $file );
		}

		delete_post_meta( $video_id, self::KEPT );
		delete_post_meta( $video_id, self::STATE );
		self::report( $video_id, null );
		self::protect( $video_id, true );
	}

	/**
	 * A moved video was pointed somewhere else: give its old file back and keep what the owner set.
	 *
	 * The moved file goes back to the Media Library (restore(), which resets the
	 * video to it), then the owner's new source and platform id are written
	 * again over that. If the old file cannot be moved back (missing), the
	 * video still stops naming it, so protect() sees the new source.
	 *
	 * @param int    $video_id Video post ID.
	 * @param string $owned    File the video moved in and owns.
	 * @param string $stream   The video's /stream URL.
	 * @param string $source   Source URL the owner just saved.
	 * @param string $file     Platform id the owner just saved.
	 */
	private static function release( int $video_id, string $owned, string $stream, string $source, string $file ): void {
		if ( ! self::restore( $video_id ) ) {
			delete_post_meta( $video_id, '_ms_platform_video_id' );
			update_post_meta( $video_id, '_ms_owned_file', '' );
			delete_post_meta( $video_id, self::RESTORE );
		}
		if ( $source !== $stream ) {
			update_post_meta( $video_id, '_ms_source_url', $source );
		}
		if ( '' !== $file && $file !== $owned ) {
			update_post_meta( $video_id, '_ms_platform_video_id', $file );
		}
	}

	/**
	 * Queue the upgrade job while it is pending. Repairs itself if the action is lost.
	 */
	public static function ensure_job(): void {
		if ( get_option( self::PENDING ) ) {
			Cleanup::schedule_single( self::JOB, time() + MINUTE_IN_SECONDS );
		}
		if ( false !== get_option( self::REPAIR_PENDING, false ) ) {
			Cleanup::schedule_single( self::REPAIR_JOB, time() + MINUTE_IN_SECONDS );
		}
	}

	/**
	 * One pass of the 1.4.3 repair: give back files 1.4.2 took from other plugins.
	 *
	 * 1.4.2 moved any video file under uploads, including files with no Media
	 * Library item that another plugin keeps in its own folder (Basecamp
	 * 10517640742). Such a file moved by 1.4.2 is recorded on its video as
	 * `_ms_library_restore` with `attachment` 0 and its old path in `file`;
	 * when that path is outside the uploads YYYY/MM folders, restore() moves it
	 * back there and the video plays it from there again, listed in the owner
	 * notice as not protected. Media Library files, and files in the YYYY/MM
	 * folders, stay protected.
	 *
	 * Walks videos with a restore record in ID order after the stored cursor,
	 * so a pass interrupted half way resumes, a large library is done in small
	 * background pages, and a second run finds nothing left to move back. The
	 * option is removed when none are left.
	 */
	public static function repair_batch(): void {
		global $wpdb;

		$batch  = max( 10, min( 200, (int) apply_filters( 'mediashield_library_files_batch_size', 50 ) ) );
		$cursor = (int) get_option( self::REPAIR_PENDING, 0 );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off background walk; ID > cursor paging is not expressible in WP_Query.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s
				 WHERE p.post_type = 'mediashield_video' AND p.ID > %d
				 ORDER BY p.ID ASC LIMIT %d",
				self::RESTORE,
				$cursor,
				$batch
			)
		);

		if ( empty( $ids ) ) {
			delete_option( self::REPAIR_PENDING );
			return;
		}

		$failed = array();
		foreach ( $ids as $id ) {
			$id     = (int) $id;
			$record = get_post_meta( $id, self::RESTORE, true );
			if ( ! is_array( $record ) || ! empty( $record['attachment'] ) || preg_match( '#^\d{4}/\d{2}/[^/]+$#', ltrim( (string) ( $record['file'] ?? '' ), '/' ) ) ) {
				continue;
			}
			if ( self::restore( $id ) ) {
				self::report( $id, self::NOT_IN_LIBRARY );
				self::mark( $id, self::NOT_IN_LIBRARY );
			} else {
				$failed[] = $id;
			}
		}
		if ( $failed ) {
			error_log( 'MediaShield library file repair could not move back the files of videos ' . implode( ', ', $failed ) . '; run wp mediashield repair restore-library-files --video=<id> to retry.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}

		update_option( self::REPAIR_PENDING, (int) end( $ids ), false );
		Cleanup::schedule_single( self::REPAIR_JOB, time() + MINUTE_IN_SECONDS );
	}

	/**
	 * One pass of the upgrade job: check the next self-hosted videos not yet checked.
	 *
	 * Resumable: every checked video carries a state, so a pass interrupted
	 * half way simply picks up the rest on the next run.
	 */
	public static function run_batch(): void {
		$batch = max( 10, min( 200, (int) apply_filters( 'mediashield_library_files_batch_size', 50 ) ) );

		$ids = get_posts(
			array(
				'post_type'     => 'mediashield_video',
				'post_status'   => 'any',
				'fields'        => 'ids',
				'numberposts'   => $batch,
				'orderby'       => 'ID',
				'order'         => 'ASC',
				'no_found_rows' => true,
				'meta_query'    => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- One-off background walk.
					array(
						'key'   => '_ms_platform',
						'value' => 'self',
					),
					array(
						'key'     => self::STATE,
						'compare' => 'NOT EXISTS',
					),
				),
			)
		);

		if ( empty( $ids ) ) {
			delete_option( self::PENDING );
			return;
		}

		foreach ( $ids as $id ) {
			self::protect( (int) $id );
		}

		Cleanup::schedule_single( self::JOB, time() + MINUTE_IN_SECONDS );
	}

	/**
	 * Move this video's Media Library file into the protected folder, when it has one.
	 *
	 * Only a file with a Media Library item moves: a file with none is left
	 * where it is and listed in the owner notice, because another plugin may
	 * keep it (Basecamp 10517640742). From a save, the file moves only when the
	 * person saving may edit its Media Library item. Anyone who may edit a
	 * video can type any upload's address into it, and must not take someone
	 * else's file that way. The background job runs as nobody and checks the
	 * videos that were already there.
	 *
	 * @param int  $video_id Video post ID.
	 * @param bool $on_save  Whether the current user's save asked for it.
	 * @return string moved | in_use | not_in_library | none
	 */
	public static function protect( int $video_id, bool $on_save = false ): string {
		// Only a self-hosted video playing a file it does not already own. An
		// upload names its file in `_ms_platform_video_id` and is protected already.
		if ( 'self' !== get_post_meta( $video_id, '_ms_platform', true ) || '' !== (string) get_post_meta( $video_id, '_ms_platform_video_id', true ) ) {
			return self::mark( $video_id, 'none' );
		}

		// The same lookup the stream route plays from: a video file inside
		// this site's uploads, or '' for an external URL we cannot protect.
		$path    = StreamController::resolve_file( $video_id );
		$uploads = wp_upload_dir();
		$root    = trailingslashit( (string) realpath( $uploads['basedir'] ) );
		if ( '' === $path || 0 !== strpos( $path, $root ) || 0 === stripos( $path, $root . 'mediashield/' ) ) {
			return self::mark( $video_id, 'none' );
		}

		$relative   = substr( $path, strlen( $root ) );
		$attachment = attachment_url_to_postid( trailingslashit( $uploads['baseurl'] ) . $relative );

		// No Media Library item: another plugin's file (or one nothing tracks). Not ours to move.
		if ( ! $attachment ) {
			self::report( $video_id, self::NOT_IN_LIBRARY );
			return self::mark( $video_id, self::NOT_IN_LIBRARY );
		}

		if ( $on_save && ! current_user_can( 'edit_post', $attachment ) ) {
			self::report( $video_id, self::NOT_EDITABLE );
			return self::mark( $video_id, 'in_use' );
		}

		$used = self::used_elsewhere( $video_id, $relative, $attachment );
		if ( '' !== $used ) {
			self::report( $video_id, $used );
			return self::mark( $video_id, 'in_use' );
		}

		$name = ( new SelfHosted() )->adopt( $path );
		if ( '' === $name ) {
			self::report( $video_id, self::MOVE_FAILED );
			return self::mark( $video_id, 'in_use' );
		}
		$stored = trailingslashit( $uploads['basedir'] ) . 'mediashield/' . $name;

		update_post_meta(
			$attachment,
			self::RESTORE,
			array(
				'file'   => (string) get_post_meta( $attachment, '_wp_attached_file', true ),
				'status' => (string) get_post_field( 'post_status', $attachment ),
			)
		);
		update_attached_file( $attachment, $stored );
		wp_update_post(
			array(
				'ID'          => $attachment,
				'post_status' => 'private',
			)
		);

		update_post_meta(
			$video_id,
			self::RESTORE,
			array(
				'url'        => (string) get_post_meta( $video_id, '_ms_source_url', true ),
				'file'       => $relative,
				'attachment' => $attachment,
				'name'       => $name,
			)
		);
		// File name before the claim: while the 1.4.0 claim backfill runs, a
		// file-name change clears an unclaimed video's claim (Cleanup::forfeit_claim_on_edit()).
		update_post_meta( $video_id, '_ms_platform_video_id', $name );
		update_post_meta( $video_id, '_ms_owned_file', $name );
		update_post_meta( $video_id, '_ms_source_url', rest_url( 'mediashield/v1/stream/' . $video_id ) );

		self::report( $video_id, '' );
		return self::mark( $video_id, 'moved' );
	}

	/**
	 * Where else does the site use this file? '' means nowhere, and only then may it move.
	 *
	 * A use is the path below uploads, written plainly or as JSON with escaped
	 * slashes (Elementor's `_elementor_data`, `2026\/07\/x.mp4`), or the
	 * Media Library item's id: a block's `"id"` / `"mediaId"`, a featured
	 * image, an Elementor media control, an ACF field (a meta row whose `_`
	 * twin names an ACF field key, on a post or an options page) and the core
	 * Video widget. Matches are deliberately loose and a query that fails, or
	 * more candidate posts than are checked, counts as a use: a false match
	 * only leaves a file in place, never moves one a page needs.
	 * ponytail: LIKE scans of posts, postmeta and options per checked video; the
	 * job is background and batched. Index-backed reference tracking if a site
	 * with tens of thousands of self-hosted videos finds it slow.
	 *
	 * @param int    $video_id   Video being protected.
	 * @param string $relative   File path relative to the uploads folder.
	 * @param int    $attachment Media Library item for the file.
	 * @return string Where it is used, for the owner notice, or ''.
	 */
	private static function used_elsewhere( int $video_id, string $relative, int $attachment ): string {
		global $wpdb;
		$paths    = array( $relative, str_replace( '/', '\\/', $relative ) );
		$like     = '%' . $wpdb->esc_like( $paths[0] ) . '%';
		$like_esc = '%' . $wpdb->esc_like( $paths[1] ) . '%';
		$id       = (string) $attachment;
		$skip     = 'post' === ( self::$replacing['type'] ?? '' ) ? absint( self::$replacing['id'] ?? 0 ) : 0; // The post switching to the new video is not a use.
		$unsure   = __( 'a part of your site MediaShield could not check', 'mediashield' );
		$acf      = $wpdb->esc_like( 'field_' ) . '%'; // An ACF field key, in a value's "_" twin row.
		$limit    = 50;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Reference scan; results must be live. Interpolated parts are fixed SQL fragments; arguments are spread.
		$args  = array( $like, $like_esc, '%' . $wpdb->esc_like( '"id":' . $id ) . '%', '%' . $wpdb->esc_like( '"mediaId":' . $id ) . '%' );
		$posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_content FROM {$wpdb->posts} WHERE ( post_content LIKE %s OR post_content LIKE %s OR post_content LIKE %s OR post_content LIKE %s ) AND post_type <> 'revision' AND post_status NOT IN ('trash', 'auto-draft') AND ID NOT IN (%d, %d, %d) LIMIT {$limit}",
				...array_merge( $args, array( $video_id, $attachment, $skip ) )
			)
		);
		if ( self::query_failed() ) {
			return $unsure;
		}
		foreach ( (array) $posts as $post ) {
			// The Video block keeps a copy of the source URL in its attributes
			// that its renderer never reads (it plays by video id), so that
			// copy is not a use of the file.
			$content = (string) preg_replace( '#<!--\s+wp:mediashield/video\s.*?-->#s', '', (string) $post->post_content );
			$names   = false !== strpos( $content, $paths[0] ) || false !== strpos( $content, $paths[1] );
			// A block naming the item by id: core Video/File {"id":N}, Media & Text {"mediaId":N}.
			if ( $names || preg_match( '#<!--\s+wp:\S+\s+\{[^>]*?"(?:id|mediaId)":' . $attachment . '(?![0-9])#', $content ) ) {
				/* translators: %s: post title */
				return sprintf( __( 'the content of "%s"', 'mediashield' ), get_the_title( (int) $post->ID ) );
			}
		}
		if ( count( (array) $posts ) >= $limit ) {
			return $unsure;
		}

		// The path in any meta value, plain or JSON-escaped; and the id where
		// a meta row is known to hold one.
		$args       = array( $like, $like_esc, $id, '%' . $wpdb->esc_like( '"id":' . $id . ',' ) . '%', '%' . $wpdb->esc_like( '"id":' . $id . '}' ) . '%', $id, '%' . $wpdb->esc_like( '"' . $id . '"' ) . '%', $acf );
		$meta_owner = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT m.post_id FROM {$wpdb->postmeta} m WHERE ( meta_value LIKE %s OR meta_value LIKE %s
					OR ( meta_key = '_thumbnail_id' AND meta_value = %s )
					OR ( meta_key = '_elementor_data' AND ( meta_value LIKE %s OR meta_value LIKE %s ) )
					OR ( ( meta_value = %s OR meta_value LIKE %s ) AND EXISTS ( SELECT 1 FROM {$wpdb->postmeta} f WHERE f.post_id = m.post_id AND f.meta_key = CONCAT( '_', m.meta_key ) AND f.meta_value LIKE %s ) ) ) AND meta_key <> %s AND post_id NOT IN (%d, %d, %d) LIMIT 1",
				...array_merge(
					$args,
					array(
						self::RESTORE, // Our own record of where an earlier file came from is not a use.
						$video_id,
						$attachment,
						$skip,
					)
				)
			)
		);
		if ( self::query_failed() ) {
			return $unsure;
		}
		if ( $meta_owner ) {
			return 'mediashield_video' === get_post_type( $meta_owner )
				/* translators: %s: video title */
				? sprintf( __( 'the video "%s"', 'mediashield' ), get_the_title( $meta_owner ) )
				/* translators: %s: post title */
				: sprintf( __( 'the settings of "%s"', 'mediashield' ), get_the_title( $meta_owner ) );
		}

		// Widgets and settings: the path, the core Video widget's
		// attachment_id, or an ACF options-page field holding the id.
		$args   = array( $like, $like_esc, '%' . $wpdb->esc_like( '"attachment_id";i:' . $id . ';' ) . '%', $id, '%' . $wpdb->esc_like( '"' . $id . '"' ) . '%', $acf );
		$option = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT o.option_id FROM {$wpdb->options} o WHERE option_value LIKE %s OR option_value LIKE %s OR option_value LIKE %s
					OR ( ( option_value = %s OR option_value LIKE %s ) AND EXISTS ( SELECT 1 FROM {$wpdb->options} f WHERE f.option_name = CONCAT( '_', o.option_name ) AND f.option_value LIKE %s ) ) LIMIT 1",
				...$args
			)
		);
		if ( self::query_failed() ) {
			return $unsure;
		}
		if ( $option ) {
			return __( 'a widget or site setting', 'mediashield' );
		}
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber

		/**
		 * Report a use of a Media Library file that MediaShield cannot see.
		 *
		 * A plugin that keeps file URLs in its own tables returns where the file
		 * is used, and MediaShield leaves the file in place and tells the owner.
		 *
		 * @since 1.4.2
		 *
		 * @param string $where      '' when unused so far, else where it is used.
		 * @param string $relative   File path relative to the uploads folder.
		 * @param int    $attachment Media Library item (always set since 1.4.3: a file with none is never moved).
		 * @param int    $video_id   Video being protected.
		 * @param array  $replacing  Since 1.4.4. { type: string, id: int } of the one
		 *                           reference being switched to this video, which
		 *                           is not a use (skip your own row of that type
		 *                           and id); empty otherwise.
		 */
		return (string) apply_filters( 'mediashield_library_file_in_use', '', $relative, $attachment, $video_id, self::$replacing );
	}

	/**
	 * Did the last database query fail? A failed reference scan is never "unused".
	 *
	 * @phpstan-impure It reads the state the last query left.
	 */
	private static function query_failed(): bool {
		global $wpdb;
		return '' !== $wpdb->last_error;
	}

	/**
	 * Does a row in another plugin's table name this file?
	 *
	 * For integrations answering mediashield_library_file_in_use from their own
	 * tables. Use table_has_value() to match a Media Library id instead.
	 *
	 * @since 1.4.2
	 *
	 * @param string   $table    Full table name.
	 * @param string[] $columns  Columns that may hold the file URL.
	 * @param string   $relative File path relative to the uploads folder.
	 * @param int      $skip_id  Since 1.4.4. A row (by its `id` column) that is not a use, 0 for none.
	 * @return bool True when a row names the file, or the query failed: when in doubt the file stays.
	 */
	public static function table_mentions_file( string $table, array $columns, string $relative, int $skip_id = 0 ): bool {
		global $wpdb;
		$like = '%' . $wpdb->esc_like( $relative ) . '%';
		$args = array( $table );
		foreach ( $columns as $column ) {
			array_push( $args, $column, $like );
		}
		$where = implode( ' OR ', array_fill( 0, count( $columns ), '%i LIKE %s' ) );
		if ( $skip_id > 0 ) {
			$where  = '( ' . $where . ' ) AND id <> %d';
			$args[] = $skip_id;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Reference scan, results must be live; $where is only %i/%s placeholders.
		$found = $wpdb->get_var( $wpdb->prepare( "SELECT 1 FROM %i WHERE {$where} LIMIT 1", ...$args ) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (bool) $found || '' !== $wpdb->last_error;
	}

	/**
	 * Does a row in another plugin's table hold this id in a column?
	 *
	 * The id counterpart of table_mentions_file(). A query error counts as no
	 * match, so a column missing on an older schema is simply not a use.
	 *
	 * @since 1.4.2
	 *
	 * @param string $table  Full table name.
	 * @param string $column Column holding the id.
	 * @param int    $id      Id to look for, such as a Media Library item.
	 * @param int    $skip_id Since 1.4.4. A row (by its `id` column) that is not a use, 0 for none.
	 * @return bool
	 */
	public static function table_has_value( string $table, string $column, int $id, int $skip_id = 0 ): bool {
		global $wpdb;
		if ( $skip_id > 0 ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reference scan; results must be live.
			return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM %i WHERE %i = %d AND id <> %d LIMIT 1', $table, $column, $id, $skip_id ) );
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Reference scan; results must be live.
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SELECT 1 FROM %i WHERE %i = %d LIMIT 1', $table, $column, $id ) );
	}

	/**
	 * Move a moved file back to where it was. Never overwrites anything: if
	 * the old name has been taken since, the file gets the next free name in
	 * the same folder and the video and Media Library item follow it.
	 *
	 * @param int $video_id Video post ID.
	 * @return bool True when the file is back at its old path.
	 */
	public static function restore( int $video_id ): bool {
		$from_meta = get_post_meta( $video_id, self::RESTORE, true );
		if ( ! is_array( $from_meta ) || empty( $from_meta['file'] ) ) {
			return false;
		}

		$uploads = wp_upload_dir();
		$base    = trailingslashit( $uploads['basedir'] );
		// The stored name is kept with the restore record, because the edit
		// screen may already have cleared the video's file field when the
		// owner pointed it elsewhere.
		$name = (string) ( $from_meta['name'] ?? get_post_meta( $video_id, '_ms_owned_file', true ) );
		$from = $base . 'mediashield/' . sanitize_file_name( $name );
		$to   = $base . ltrim( (string) $from_meta['file'], '/' );
		if ( ! is_file( $from ) || ! wp_mkdir_p( dirname( $to ) ) ) {
			return false;
		}
		// Something else took the old name since (a re-upload of the same
		// file, say): take the next free name beside it rather than overwrite.
		$renamed = file_exists( $to );
		if ( $renamed ) {
			$to = trailingslashit( dirname( $to ) ) . wp_unique_filename( dirname( $to ), wp_basename( $to ) );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename, WordPress.PHP.NoSilencedErrors.Discouraged -- Moving a file back inside uploads; failure is reported by the return value.
		if ( ! @rename( $from, $to ) ) {
			return false;
		}

		$attachment = (int) ( $from_meta['attachment'] ?? 0 );
		if ( $attachment && get_post( $attachment ) ) {
			$old = (array) get_post_meta( $attachment, self::RESTORE, true );
			update_attached_file( $attachment, $to );
			wp_update_post(
				array(
					'ID'          => $attachment,
					'post_status' => ! empty( $old['status'] ) ? $old['status'] : 'inherit',
				)
			);
			delete_post_meta( $attachment, self::RESTORE );
		}

		update_post_meta( $video_id, '_ms_source_url', $renamed ? trailingslashit( $uploads['baseurl'] ) . substr( $to, strlen( $base ) ) : (string) $from_meta['url'] );
		delete_post_meta( $video_id, '_ms_platform_video_id' );
		update_post_meta( $video_id, '_ms_owned_file', '' );
		delete_post_meta( $video_id, self::RESTORE );
		update_post_meta( $video_id, self::KEPT, (string) get_post_meta( $video_id, '_ms_source_url', true ) );
		self::mark( $video_id, 'restored' );
		self::report( $video_id, null );
		return true;
	}

	/**
	 * Restore every moved file. Used by WP-CLI and on uninstall.
	 *
	 * @return array{restored: int, failed: int[]} Count restored, ids that could not be.
	 */
	public static function restore_all(): array {
		$ids = get_posts(
			array(
				'post_type'     => 'mediashield_video',
				'post_status'   => 'any',
				'fields'        => 'ids',
				'numberposts'   => -1, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_numberposts -- Only videos with a moved file; one-off.
				'no_found_rows' => true,
				'meta_key'      => self::RESTORE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			)
		);

		$result = array(
			'restored' => 0,
			'failed'   => array(),
		);
		foreach ( $ids as $id ) {
			if ( self::restore( (int) $id ) ) {
				++$result['restored'];
			} else {
				$result['failed'][] = (int) $id;
			}
		}
		return $result;
	}

	/**
	 * Tell the owner, once the job is done, what moved and what was left.
	 *
	 * Stays until the owner clicks "Got it", because it names videos that are
	 * still downloadable and what to do about them.
	 */
	public static function render_notice(): void {
		if ( ! current_user_can( 'manage_options' ) || get_option( self::PENDING ) ) {
			return;
		}
		$report = get_option( self::REPORT );
		if ( ! is_array( $report ) || ( empty( $report['moved'] ) && empty( $report['left'] ) ) ) {
			return;
		}

		// A video deleted since the report was written is not listed.
		$moved = array_values( array_filter( array_map( 'intval', (array) ( $report['moved'] ?? array() ) ), static fn( $id ) => (bool) get_post( $id ) ) );
		$left  = array_filter( (array) ( $report['left'] ?? array() ), static fn( $id ) => (bool) get_post( (int) $id ), ARRAY_FILTER_USE_KEY );
		if ( ! $moved && ! $left ) {
			return;
		}
		$dismiss = wp_nonce_url( add_query_arg( 'ms_library_notice', 'dismiss' ), 'ms_library_notice' );
		$list    = static function ( array $items ): void {
			echo '<ul class="ul-disc">';
			foreach ( array_slice( $items, 0, 10, true ) as $id => $where ) {
				$link = get_edit_post_link( (int) $id );
				echo '<li>' . ( $link ? '<a href="' . esc_url( $link ) . '">' . esc_html( get_the_title( (int) $id ) ) . '</a>' : esc_html( get_the_title( (int) $id ) ) );
				if ( self::MOVE_FAILED === $where ) {
					echo ' ' . esc_html__( '(the file could not be moved; check that wp-content/uploads is writable, then save the video again)', 'mediashield' );
				} elseif ( self::NOT_IN_LIBRARY === $where ) {
					echo ' ' . esc_html__( '(its file is not in your Media Library, so it may belong to another plugin and was left where it is)', 'mediashield' );
				} elseif ( self::NOT_EDITABLE === $where ) {
					echo ' ' . esc_html__( '(it was saved by someone who cannot edit its Media Library file; save the video yourself to protect it)', 'mediashield' );
				} elseif ( is_string( $where ) && '' !== $where ) {
					/* translators: %s: where else the file is used, for example: the content of "Welcome" */
					echo ' ' . esc_html( sprintf( __( '(also used in %s)', 'mediashield' ), $where ) );
				}
				echo '</li>';
			}
			echo '</ul>';
			if ( count( $items ) > 10 ) {
				/* translators: %d: number of further videos not listed */
				echo '<p>' . esc_html( sprintf( __( 'And %d more.', 'mediashield' ), count( $items ) - 10 ) ) . '</p>';
			}
		};
		?>
		<div class="notice notice-info ms-library-notice">
			<p><strong><?php esc_html_e( 'MediaShield now keeps uploaded video files in its protected folder.', 'mediashield' ); ?></strong></p>
			<?php if ( $moved ) : ?>
				<p>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: number of videos */
							_n(
								'%d video played from a public Media Library file. The file now sits in MediaShield\'s protected folder and plays only through the protected player. Its Media Library entry is kept and is visible to editors only.',
								'%d videos played from public Media Library files. The files now sit in MediaShield\'s protected folder and play only through the protected player. Their Media Library entries are kept and are visible to editors only.',
								count( $moved ),
								'mediashield'
							),
							count( $moved )
						)
					);
					?>
				</p>
				<?php $list( array_fill_keys( $moved, '' ) ); ?>
			<?php endif; ?>
			<?php if ( $left ) : ?>
				<p>
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: number of videos */
							_n(
								'%d video still plays from a public file, because the file is used elsewhere on your site or is not in your Media Library. It was left where it is so nothing else breaks. To protect the video, open it and upload the file under "Or upload a file"; MediaShield then plays a private copy.',
								'%d videos still play from public files, because the files are used elsewhere on your site or are not in your Media Library. They were left where they are so nothing else breaks. To protect a video, open it and upload the file under "Or upload a file"; MediaShield then plays a private copy.',
								count( $left ),
								'mediashield'
							),
							count( $left )
						)
					);
					?>
				</p>
				<?php $list( $left ); ?>
			<?php endif; ?>
			<p>
				<?php
				if ( $moved ) {
					printf(
						/* translators: %s: WP-CLI command */
						esc_html__( 'To put moved files back where they were, run %s.', 'mediashield' ),
						'<code>wp mediashield repair restore-library-files</code>'
					);
					echo ' ';
				}
				?>
				<a href="<?php echo esc_url( $dismiss ); ?>"><?php esc_html_e( 'Got it', 'mediashield' ); ?></a>
			</p>
		</div>
		<?php
	}

	/**
	 * Clear the notice when the owner clicks "Got it".
	 */
	public static function dismiss_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce checked below.
		if ( ! isset( $_GET['ms_library_notice'] ) || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		check_admin_referer( 'ms_library_notice' );
		delete_option( self::REPORT );
		wp_safe_redirect( remove_query_arg( array( 'ms_library_notice', '_wpnonce' ) ) );
		exit;
	}

	/**
	 * Record a video's state.
	 *
	 * @param int    $video_id Video post ID.
	 * @param string $state    State.
	 * @return string The state.
	 */
	private static function mark( int $video_id, string $state ): string {
		update_post_meta( $video_id, self::STATE, $state );
		return $state;
	}

	/**
	 * Add a video to the owner report: moved when $where is '', left and why
	 * otherwise, and taken off it when null.
	 *
	 * @param int         $video_id Video post ID.
	 * @param string|null $where    '' for moved, where the file is used, or null.
	 */
	private static function report( int $video_id, ?string $where ): void {
		$report = get_option( self::REPORT );
		$report = is_array( $report ) ? $report : array();
		$moved  = array_diff( array_map( 'intval', (array) ( $report['moved'] ?? array() ) ), array( $video_id ) );
		$left   = (array) ( $report['left'] ?? array() );
		unset( $left[ $video_id ] );

		if ( '' === $where ) {
			$moved[] = $video_id;
		} elseif ( null !== $where ) {
			$left[ $video_id ] = $where;
		}

		update_option(
			self::REPORT,
			array(
				'moved' => array_values( $moved ),
				'left'  => $left,
			),
			false
		);
	}
}
