<?php
/**
 * Video/Playlist deletion cascade and scheduled cleanup crons.
 *
 * Hooks:
 *   before_delete_post — cascade-delete related data when a video or playlist is trashed.
 *
 * Crons (Action Scheduler):
 *   ms_cleanup_inactive_sessions (hourly)  — Mark stale sessions inactive.
 *   ms_archive_old_sessions      (monthly) — Archive sessions older than 24 months.
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
				'display'  => __( 'Once Monthly', 'mediashield' ),
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

		// Delete the video file/resource from the hosting platform.
		$platform          = get_post_meta( $post_id, '_ms_platform', true );
		$platform_video_id = get_post_meta( $post_id, '_ms_platform_video_id', true );

		if ( ! empty( $platform ) && ! empty( $platform_video_id ) ) {
			if ( 'self' === $platform ) {
				// Self-hosted: delete the physical file directly.
				$wp_upload = wp_upload_dir();
				$file_path = trailingslashit( $wp_upload['basedir'] ) . 'mediashield/' . sanitize_file_name( $platform_video_id );
				if ( file_exists( $file_path ) ) {
					wp_delete_file( $file_path );
				}
			} else {
				// External platform: use the driver's delete method.
				$driver_name = str_replace( '-', '_', $platform );
				$driver      = UploadManager::get_driver( $driver_name );
				if ( $driver ) {
					$driver->delete( $platform_video_id );
				}
			}
		}

		global $wpdb;

		$video_tags             = "{$wpdb->prefix}ms_video_tags";
		$watch_sessions         = "{$wpdb->prefix}ms_watch_sessions";
		$watch_sessions_archive = "{$wpdb->prefix}ms_watch_sessions_archive";
		$milestones             = "{$wpdb->prefix}ms_milestones";
		$playlist_items         = "{$wpdb->prefix}ms_playlist_items";

		// Delete video tags.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table delete.
		$wpdb->delete( $video_tags, array( 'video_id' => $post_id ), array( '%d' ) );

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

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table bulk update.
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table} SET is_active = 0 WHERE is_active = 1 AND last_heartbeat < DATE_SUB( NOW(), INTERVAL %d MINUTE )",
					10
				)
			);
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		} catch ( \Throwable $e ) {
			error_log( 'MediaShield cron cleanup_inactive_sessions failed: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * Monthly: Archive sessions older than 24 months and delete originals.
	 */
	public static function archive_old_sessions(): void {
		try {
			global $wpdb;

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

			// Insert old sessions into archive.
			$inserted = $wpdb->query(
				$wpdb->prepare(
					"INSERT INTO {$archive} SELECT * FROM {$sessions} WHERE started_at < DATE_SUB( NOW(), INTERVAL %d MONTH )",
					24
				)
			);

			if ( false === $inserted ) {
				$wpdb->query( 'ROLLBACK' );
				// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				return;
			}

			// Delete archived sessions from main table.
			$deleted = $wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$sessions} WHERE started_at < DATE_SUB( NOW(), INTERVAL %d MONTH )",
					24
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
