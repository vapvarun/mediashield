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

		// Schedule recurring actions.
		add_action( 'init', array( __CLASS__, 'schedule_actions' ) );
	}

	/**
	 * Schedule recurring Action Scheduler actions.
	 */
	public static function schedule_actions(): void {
		if ( ! function_exists( 'as_has_scheduled_action' ) ) {
			return;
		}

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

		global $wpdb;

		$video_tags      = "{$wpdb->prefix}ms_video_tags";
		$watch_sessions  = "{$wpdb->prefix}ms_watch_sessions";
		$milestones      = "{$wpdb->prefix}ms_milestones";
		$playlist_items  = "{$wpdb->prefix}ms_playlist_items";

		// Delete video tags.
		$wpdb->delete( $video_tags, array( 'video_id' => $post_id ), array( '%d' ) );

		// Collect session IDs before deleting (needed for pro tables).
		$session_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$watch_sessions} WHERE video_id = %d",
				$post_id
			)
		);

		// Delete watch sessions.
		$wpdb->delete( $watch_sessions, array( 'video_id' => $post_id ), array( '%d' ) );

		// Delete milestones.
		$wpdb->delete( $milestones, array( 'video_id' => $post_id ), array( '%d' ) );

		// Delete playlist items referencing this video.
		$wpdb->delete( $playlist_items, array( 'video_id' => $post_id ), array( '%d' ) );

		// Pro tables cascade (only if pro is active).
		if ( defined( 'MEDIASHIELD_PRO_VERSION' ) && ! empty( $session_ids ) ) {
			$playback_events = "{$wpdb->prefix}ms_playback_events";
			$activity_alerts = "{$wpdb->prefix}ms_activity_alerts";
			$drm_licenses    = "{$wpdb->prefix}ms_drm_licenses";
			$heatmap_cache   = "{$wpdb->prefix}ms_heatmap_cache";
			$drm_keys        = "{$wpdb->prefix}ms_drm_keys";

			// Delete playback events for those sessions.
			$placeholders = implode( ',', array_fill( 0, count( $session_ids ), '%d' ) );

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM {$playback_events} WHERE session_id IN ({$placeholders})",
					...$session_ids
				)
			);

			// Delete activity alerts for this video.
			$wpdb->delete( $activity_alerts, array( 'video_id' => $post_id ), array( '%d' ) );

			// Delete DRM licenses for this video.
			$wpdb->delete( $drm_licenses, array( 'video_id' => $post_id ), array( '%d' ) );

			// Delete heatmap cache for this video.
			$wpdb->delete( $heatmap_cache, array( 'video_id' => $post_id ), array( '%d' ) );

			// Delete DRM keys for this video.
			$wpdb->delete( $drm_keys, array( 'video_id' => $post_id ), array( '%d' ) );
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
		$wpdb->delete( $playlist_items, array( 'playlist_id' => $post_id ), array( '%d' ) );
	}

	/**
	 * Hourly: Mark sessions inactive when heartbeat is stale (>10 minutes).
	 */
	public static function cleanup_inactive_sessions(): void {
		global $wpdb;

		$table = "{$wpdb->prefix}ms_watch_sessions";

		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET is_active = 0 WHERE is_active = 1 AND last_heartbeat < DATE_SUB( NOW(), INTERVAL %d MINUTE )",
				10
			)
		);
	}

	/**
	 * Monthly: Archive sessions older than 24 months and delete originals.
	 */
	public static function archive_old_sessions(): void {
		global $wpdb;

		$sessions = "{$wpdb->prefix}ms_watch_sessions";
		$archive  = "{$wpdb->prefix}ms_watch_sessions_archive";

		// Check if archive table exists; skip if it doesn't.
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

		// Insert old sessions into archive.
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$archive} SELECT * FROM {$sessions} WHERE started_at < DATE_SUB( NOW(), INTERVAL %d MONTH )",
				24
			)
		);

		// Delete archived sessions from main table.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$sessions} WHERE started_at < DATE_SUB( NOW(), INTERVAL %d MONTH )",
				24
			)
		);
	}
}
