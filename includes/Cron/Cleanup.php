<?php
/**
 * Video/Playlist deletion cascade and scheduled cleanup crons.
 *
 * Hooks:
 *   before_delete_post — cascade-delete related data when a video or playlist is trashed.
 *
 * Crons (Action Scheduler):
 *   ms_cleanup_inactive_sessions (hourly)  — Mark stale sessions inactive.
 *   ms_archive_old_sessions      (monthly) — Archive sessions older than the
 *                                            configured retention window. Disabled
 *                                            unless the owner sets one.
 *
 * @package MediaShield\Cron
 */

namespace MediaShield\Cron;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use MediaShield\Upload\UploadManager;

/**
 * Class Cleanup
 *
 * Video/Playlist deletion cascade and scheduled cleanup crons.
 *
 * @since 1.0.0
 */
class Cleanup {

	/**
	 * Register hooks and cron schedules.
	 */
	public static function register(): void {
		// Cascade deletes.
		add_action( 'before_delete_post', array( __CLASS__, 'handle_video_delete' ) );
		add_action( 'before_delete_post', array( __CLASS__, 'handle_playlist_delete' ) );

		// Cron callbacks.
		add_action( 'ms_cleanup_inactive_sessions', array( __CLASS__, 'cleanup_inactive_sessions' ) );
		add_action( 'ms_archive_old_sessions', array( __CLASS__, 'archive_old_sessions' ) );
		add_action( 'ms_restore_archived_sessions', array( __CLASS__, 'restore_archived_sessions' ) );

		// Register monthly schedule for WP-Cron fallback.
		add_filter( 'cron_schedules', array( __CLASS__, 'add_monthly_schedule' ) );

		// Schedule recurring actions.
		add_action( 'init', array( __CLASS__, 'schedule_actions' ) );
	}

	/**
	 * Register a monthly cron schedule interval.
	 *
	 * @param array $schedules Existing schedules.
	 * @return array Modified schedules.
	 */
	public static function add_monthly_schedule( array $schedules ): array {
		if ( ! isset( $schedules['monthly'] ) ) {
			$schedules['monthly'] = array(
				'interval' => MONTH_IN_SECONDS,
				// The cron_schedules filter fires whenever WP evaluates schedules,
				// which can happen before init (WP-Cron spawn checks). Translating
				// the mediashield domain that early trips WP 6.7's
				// _load_textdomain_just_in_time notice. The display string is only
				// ever shown in admin cron tools, which run after init and re-run
				// this filter — so translate once init has fired, plain string
				// before then.
				'display'  => did_action( 'init' ) ? __( 'Once Monthly', 'mediashield' ) : 'Once Monthly',
			);
		}
		return $schedules;
	}

	/**
	 * Schedule recurring cleanup actions.
	 *
	 * Prefers Action Scheduler when available, falls back to WP-Cron.
	 */
	public static function schedule_actions(): void {
		if ( function_exists( 'as_has_scheduled_action' ) ) {
			self::schedule_with_action_scheduler();
			return;
		}

		// Fallback to WP-Cron.
		if ( ! wp_next_scheduled( 'ms_cleanup_inactive_sessions' ) ) {
			wp_schedule_event( time(), 'hourly', 'ms_cleanup_inactive_sessions' );
		}
		if ( ! wp_next_scheduled( 'ms_archive_old_sessions' ) ) {
			wp_schedule_event( time(), 'monthly', 'ms_archive_old_sessions' );
		}
	}

	/**
	 * Schedule via Action Scheduler when available.
	 */
	private static function schedule_with_action_scheduler(): void {
		// Hourly: mark stale sessions inactive.
		if ( false === as_has_scheduled_action( 'ms_cleanup_inactive_sessions' ) ) {
			as_schedule_recurring_action(
				time(),
				HOUR_IN_SECONDS,
				'ms_cleanup_inactive_sessions',
				array(),
				'mediashield'
			);
		}

		// Monthly: archive old sessions.
		if ( false === as_has_scheduled_action( 'ms_archive_old_sessions' ) ) {
			as_schedule_recurring_action(
				time(),
				MONTH_IN_SECONDS,
				'ms_archive_old_sessions',
				array(),
				'mediashield'
			);
		}
	}

	/**
	 * Cascade-delete video-related data when a video CPT is permanently deleted.
	 *
	 * @param int $post_id Post ID being deleted.
	 */

	public static function handle_video_delete( int $post_id ): void {
		if ( 'mediashield_video' !== get_post_type( $post_id ) ) {
			return;
		}

		// REMOVING A VIDEO HERE NEVER TOUCHES THE HOSTING PLATFORM.
		//
		// Deleting a MediaShield video removes this site's record of it. The
		// master on Bunny, Vimeo, YouTube or Wistia is left exactly where it
		// is, so the same video can be linked back at any time by importing it
		// again from Videos > Import.
		//
		// This used to call the driver's delete(), which meant tidying up a
		// video list in WordPress could destroy source media held on another
		// service - media the owner is paying to store, may be using elsewhere,
		// and never asked WordPress to manage. It is not recoverable: none of
		// these providers offer a trash or an undo. That is not hypothetical;
		// it destroyed a 5.8 GB master on a live library during 1.3.0 testing.
		//
		// 1.3.0 first made it opt-in behind a filter. That was not enough - the
		// capability still existed, so a site could switch back on the one
		// behaviour we never want. The call is gone instead. If a platform
		// video genuinely needs deleting, the place to do that is the
		// platform's own dashboard, where the person doing it can see what
		// else uses the asset.
		//
		// Self-hosted is the deliberate exception, and it is not an exception
		// to the rule above: that file is in this site's own uploads folder,
		// put there by this plugin, and nothing references it once the CPT is
		// gone. Deleting it is delegated to the driver that owns it rather
		// than rebuilt inline, so there is one implementation of where that
		// file lives.
		$platform          = get_post_meta( $post_id, '_ms_platform', true );
		$platform_video_id = get_post_meta( $post_id, '_ms_platform_video_id', true );

		if ( 'self' === $platform && ! empty( $platform_video_id ) ) {
			$driver = UploadManager::get_driver( 'self_hosted' );
			if ( $driver ) {
				$driver->delete( (string) $platform_video_id );
			}
		}

		global $wpdb;

		$video_tags             = "{$wpdb->prefix}ms_video_tags";
		$tags                   = "{$wpdb->prefix}ms_tags";
		$watch_sessions         = "{$wpdb->prefix}ms_watch_sessions";
		$watch_sessions_archive = "{$wpdb->prefix}ms_watch_sessions_archive";
		$milestones             = "{$wpdb->prefix}ms_milestones";
		$playlist_items         = "{$wpdb->prefix}ms_playlist_items";

		// Capture the tag IDs linked to this video before removing the links, so
		// we can drop any tag that becomes orphaned (no remaining video) below.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table query.
		$linked_tag_ids = $wpdb->get_col( $wpdb->prepare( "SELECT tag_id FROM {$video_tags} WHERE video_id = %d", $post_id ) );

		// Delete video tags (the video↔tag links).
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table delete.
		$wpdb->delete( $video_tags, array( 'video_id' => $post_id ), array( '%d' ) );

		// Drop tag-dictionary rows that no longer reference any video (orphans).
		$linked_tag_ids = array_values( array_unique( array_map( 'intval', (array) $linked_tag_ids ) ) );
		if ( ! empty( $linked_tag_ids ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $linked_tag_ids ), '%d' ) );
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Custom tables with dynamic IN clause.
			$wpdb->query(
				$wpdb->prepare(
					"DELETE t FROM {$tags} t
					 WHERE t.id IN ({$placeholders})
					 AND NOT EXISTS ( SELECT 1 FROM {$video_tags} vt WHERE vt.tag_id = t.id )",
					...$linked_tag_ids
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
		}

		// Collect session IDs before deleting (needed for pro tables).
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table query.
		$session_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$watch_sessions} WHERE video_id = %d",
				$post_id
			)
		);

		// Also collect session IDs from the archive table.
		$archived_session_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$watch_sessions_archive} WHERE video_id = %d",
				$post_id
			)
		);

		$session_ids = array_merge( $session_ids, $archived_session_ids );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Delete watch sessions.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table delete.
		$wpdb->delete( $watch_sessions, array( 'video_id' => $post_id ), array( '%d' ) );

		// Delete archived watch sessions.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table delete.
		$wpdb->delete( $watch_sessions_archive, array( 'video_id' => $post_id ), array( '%d' ) );

		// Delete milestones.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table delete.
		$wpdb->delete( $milestones, array( 'video_id' => $post_id ), array( '%d' ) );

		// Delete playlist items referencing this video.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table delete.
		$wpdb->delete( $playlist_items, array( 'video_id' => $post_id ), array( '%d' ) );

		// Cleanup user-meta milestone records for this video. Each user has a
		// single `_ms_video_tags` meta entry — a map keyed `<video_id>_<pct>`
		// — and we drop every entry that points at the post being deleted so
		// orphan tag records can't accumulate.
		self::purge_user_milestone_tags_for_video( $post_id );

		// Pro tables cascade (only if pro is active).
		if ( defined( 'MEDIASHIELD_PRO_VERSION' ) ) {
			// Delete video_id-based pro data (no session dependency).
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Pro table delete.
			$wpdb->delete( "{$wpdb->prefix}ms_activity_alerts", array( 'video_id' => $post_id ), array( '%d' ) );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Pro table delete.
			$wpdb->delete( "{$wpdb->prefix}ms_drm_licenses", array( 'video_id' => $post_id ), array( '%d' ) );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Pro table delete.
			$wpdb->delete( "{$wpdb->prefix}ms_heatmap_cache", array( 'video_id' => $post_id ), array( '%d' ) );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Pro table delete.
			$wpdb->delete( "{$wpdb->prefix}ms_drm_keys", array( 'video_id' => $post_id ), array( '%d' ) );

			// Delete playback events for those sessions (session-dependent).
			if ( ! empty( $session_ids ) ) {
				$playback_events = "{$wpdb->prefix}ms_playback_events";
				$placeholders    = implode( ',', array_fill( 0, count( $session_ids ), '%d' ) );

				// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Custom table with dynamic IN clause.
				$wpdb->query(
					$wpdb->prepare(
						"DELETE FROM {$playback_events} WHERE session_id IN ({$placeholders})",
						...$session_ids
					)
				);
				// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			}
		}
	}

	/**
	 * Drop every `_ms_video_tags` user-meta entry that references the deleted
	 * video. Each user stores a single serialised map keyed `<video_id>_<pct>`;
	 * we narrow the SQL scan with a LIKE on the serialised representation of
	 * the video_id key, then verify in PHP before saving.
	 *
	 * @param int $video_id Video CPT post ID being deleted.
	 */
	private static function purge_user_milestone_tags_for_video( int $video_id ): void {
		global $wpdb;

		// Narrow the scan: the map keys look like `<video_id>_25`, `<video_id>_50`, ...
		// — so the serialised form always contains `"<video_id>_` as a key prefix.
		$like = '%"' . $video_id . '_%';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Per-video cleanup of user meta blobs.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT user_id, meta_value FROM {$wpdb->usermeta} WHERE meta_key = '_ms_video_tags' AND meta_value LIKE %s",
				$like
			)
		);
		if ( empty( $rows ) ) {
			return;
		}

		foreach ( $rows as $row ) {
			$user_tags = maybe_unserialize( $row->meta_value );
			if ( ! is_array( $user_tags ) ) {
				continue;
			}

			$changed = false;
			foreach ( $user_tags as $key => $entry ) {
				$matches_by_field = is_array( $entry ) && (int) ( $entry['video_id'] ?? 0 ) === $video_id;
				$matches_by_key   = is_string( $key ) && str_starts_with( $key, $video_id . '_' );
				if ( $matches_by_field || $matches_by_key ) {
					unset( $user_tags[ $key ] );
					$changed = true;
				}
			}

			if ( $changed ) {
				if ( empty( $user_tags ) ) {
					delete_user_meta( (int) $row->user_id, '_ms_video_tags' );
				} else {
					update_user_meta( (int) $row->user_id, '_ms_video_tags', $user_tags );
				}
			}
		}
	}

	/**
	 * Cascade-delete playlist items when a playlist CPT is permanently deleted.
	 *
	 * @param int $post_id Post ID being deleted.
	 */
	public static function handle_playlist_delete( int $post_id ): void {
		if ( 'mediashield_playlist' !== get_post_type( $post_id ) ) {
			return;
		}

		global $wpdb;

		$playlist_items = "{$wpdb->prefix}ms_playlist_items";
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table delete.
		$wpdb->delete( $playlist_items, array( 'playlist_id' => $post_id ), array( '%d' ) );
	}

	/**
	 * Hourly: Mark sessions inactive when heartbeat is stale (>10 minutes).
	 */
	public static function cleanup_inactive_sessions(): void {
		try {
			global $wpdb;

			$table = "{$wpdb->prefix}ms_watch_sessions";

			// `last_heartbeat` is written via `current_time( 'mysql', true )` (UTC)
			// in SessionManager. Pass the SAME function's output as a prepared
			// parameter on the read side so the comparison stays on a single clock
			// — the WP-idiomatic alternative to relying on MySQL's `NOW()` (which
			// inherits session_tz) or `UTC_TIMESTAMP()` (host-dependent SQL_MODE).
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table bulk update.
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table} SET is_active = 0 WHERE is_active = 1 AND last_heartbeat < DATE_SUB( %s, INTERVAL %d MINUTE )",
					current_time( 'mysql', true ),
					10
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} catch ( \Throwable $e ) {
			error_log( 'MediaShield cron cleanup_inactive_sessions failed: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * Move previously archived sessions back into the live table.
	 *
	 * Archiving used to run at 24 months for every install, into a table no
	 * read path queries. Any site that has been running long enough therefore
	 * has real analytics history sitting somewhere the reports cannot see
	 * (BC#10217642180). Turning archiving off going forward stops the bleeding
	 * but does not give those months back - this does.
	 *
	 * Batched and self-rescheduling. A site with years of history can hold a
	 * lot of rows, and a single unbounded INSERT..SELECT on upgrade is how a
	 * migration times out halfway and leaves the data split across two tables
	 * with nothing tracking which rows moved.
	 *
	 * INSERT IGNORE plus delete-what-we-moved makes a re-run safe: a batch
	 * interrupted after the insert but before the delete re-inserts nothing and
	 * simply deletes the duplicates on the next pass.
	 *
	 * @since 1.3.0
	 */
	public static function restore_archived_sessions(): void {
		global $wpdb;

		$sessions = "{$wpdb->prefix}ms_watch_sessions";
		$archive  = "{$wpdb->prefix}ms_watch_sessions_archive";

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table migration.
		$archive_exists = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s',
				DB_NAME,
				$archive
			)
		);

		if ( ! $archive_exists ) {
			return;
		}

		$batch = (int) apply_filters( 'mediashield_restore_archive_batch_size', 2000 );
		$batch = max( 100, min( 20000, $batch ) );

		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$archive} ORDER BY id ASC LIMIT %d", $batch ) );

		if ( empty( $ids ) ) {
			return;
		}

		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$ids          = array_map( 'intval', $ids );

		// Every column EXCEPT id, so the live table assigns fresh primary keys.
		//
		// `SELECT *` would carry each archived row's original id back with it,
		// and those ids have since been handed out again by AUTO_INCREMENT to
		// newer sessions. With INSERT IGNORE that silently drops the colliding
		// rows, and the DELETE below then removes them from the archive too -
		// so a migration written to RECOVER history would destroy it instead.
		// Caught by watching the row counts during testing: the archive drained
		// to zero while the live table did not grow.
		//
		// id is the only unique key on the table (session_token is not), so
		// re-keying is safe and no IGNORE is needed.
		$columns = 'video_id, user_id, session_token, ip_address, user_agent, device_type, browser, started_at, last_heartbeat, total_seconds, max_position, completion_pct, is_active';

		$moved = $wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$sessions} ({$columns}) SELECT {$columns} FROM {$archive} WHERE id IN ({$placeholders})",
				...$ids
			)
		);

		// Only drop the archived rows once they are demonstrably somewhere else.
		// A partial or failed insert leaves the batch in place for the next run
		// rather than trading recoverable rows for lost ones.
		if ( false === $moved || (int) $moved !== count( $ids ) ) {
			return;
		}

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$archive} WHERE id IN ({$placeholders})",
				...$ids
			)
		);

		$remaining = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$archive}" );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( $remaining > 0 ) {
			self::schedule_single( 'ms_restore_archived_sessions', time() + MINUTE_IN_SECONDS );
		}
	}

	/**
	 * Schedule a one-off action, preferring Action Scheduler.
	 *
	 * @param string $hook Hook name.
	 * @param int    $when Unix timestamp.
	 */
	private static function schedule_single( string $hook, int $when ): void {
		if ( function_exists( 'as_schedule_single_action' ) ) {
			if ( false === as_next_scheduled_action( $hook ) ) {
				as_schedule_single_action( $when, $hook, array(), 'mediashield' );
			}
			return;
		}

		if ( ! wp_next_scheduled( $hook ) ) {
			wp_schedule_single_event( $when, $hook );
		}
	}

	/**
	 * Monthly: move sessions past the configured retention window into the
	 * archive table and delete the originals.
	 *
	 * Does nothing unless the owner has set a retention window. See the
	 * `ms_session_retention_months` note in Core\Settings for why the default
	 * is "keep everything".
	 */
	public static function archive_old_sessions(): void {
		try {
			global $wpdb;

			// Retention is opt-in. This used to archive at 24 months for every
			// install, into a table no read path queries - so at month 25 every
			// report silently lost its history and nothing in the UI said the
			// data had moved (BC#10217642180). An owner who never asked for a
			// retention policy keeps their analytics.
			$retention_months = (int) \MediaShield\Core\Settings::get( 'ms_session_retention_months' );
			if ( $retention_months < 1 ) {
				return;
			}

			$sessions = "{$wpdb->prefix}ms_watch_sessions";
			$archive  = "{$wpdb->prefix}ms_watch_sessions_archive";

			// Check if archive table exists; skip if it doesn't.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Schema check.
			$archive_exists = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = %s AND table_name = %s',
					DB_NAME,
					$archive
				)
			);

			if ( ! $archive_exists ) {
				return;
			}

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table archive + cleanup.

			// Use a transaction so INSERT + DELETE are atomic.
			$wpdb->query( 'START TRANSACTION' );

			// `started_at` is UTC; pass current_time('mysql', true) as a prepared
			// %s parameter so insertion and comparison share the same clock.
			$inserted = $wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$archive} SELECT * FROM {$sessions} WHERE started_at < DATE_SUB( %s, INTERVAL %d MONTH )",
					current_time( 'mysql', true ),
					$retention_months
				)
			);

			if ( false === $inserted ) {
				$wpdb->query( 'ROLLBACK' );
				// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				return;
			}

			// Delete archived sessions from main table. Same prepared
			// current_time('mysql', true) parameter as the matching INSERT.
			$deleted = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$sessions} WHERE started_at < DATE_SUB( %s, INTERVAL %d MONTH )",
					current_time( 'mysql', true ),
					$retention_months
				)
			);

			if ( false === $deleted ) {
				$wpdb->query( 'ROLLBACK' );
				// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				return;
			}

			$wpdb->query( 'COMMIT' );

			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} catch ( \Throwable $e ) {
			error_log( 'MediaShield cron archive_old_sessions failed: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}
}
