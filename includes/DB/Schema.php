<?php
/**
 * Database schema for MediaShield free tables.
 *
 * @package MediaShield\DB
 */

namespace MediaShield\DB;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Schema
 *
 * Creates and manages the database schema for all free plugin tables.
 *
 * @since 1.0.0
 */
class Schema {

	/**
	 * Create all free plugin tables via dbDelta.
	 */
	public static function create_tables(): void {
		global $wpdb;

		$charset_collate = 'DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Table: ms_tags.
		dbDelta(
			"CREATE TABLE {$wpdb->prefix}ms_tags (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(200) NOT NULL,
			slug VARCHAR(200) NOT NULL,
			description TEXT,
			created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY uk_slug (slug)
		) {$charset_collate};"
		);

		// Table: ms_video_tags.
		dbDelta(
			"CREATE TABLE {$wpdb->prefix}ms_video_tags (
			video_id BIGINT UNSIGNED NOT NULL,
			tag_id BIGINT UNSIGNED NOT NULL,
			tagged_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
			tagged_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			UNIQUE KEY uk_video_tag (video_id, tag_id),
			KEY idx_tag_id (tag_id)
		) {$charset_collate};"
		);

		// Table: ms_watch_sessions.
		$sessions_sql = "CREATE TABLE {$wpdb->prefix}ms_watch_sessions (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			video_id BIGINT UNSIGNED NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			session_token VARCHAR(255) NOT NULL,
			ip_address VARCHAR(45) NOT NULL DEFAULT '',
			user_agent VARCHAR(500) NOT NULL DEFAULT '',
			device_type VARCHAR(20) NOT NULL DEFAULT '',
			browser VARCHAR(50) NOT NULL DEFAULT '',
			started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			last_heartbeat DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			total_seconds INT UNSIGNED NOT NULL DEFAULT 0,
			max_position FLOAT NOT NULL DEFAULT 0,
			completion_pct FLOAT NOT NULL DEFAULT 0,
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			PRIMARY KEY (id),
			KEY idx_video_user (video_id, user_id),
			KEY idx_active (user_id, is_active, last_heartbeat),
			KEY idx_user (user_id),
			KEY idx_started (started_at)
		) {$charset_collate};";

		dbDelta( $sessions_sql );

		// Table: ms_watch_sessions_archive (same schema).
		dbDelta(
			str_replace(
				"{$wpdb->prefix}ms_watch_sessions",
				"{$wpdb->prefix}ms_watch_sessions_archive",
				$sessions_sql
			)
		);

		// Table: ms_milestones.
		dbDelta(
			"CREATE TABLE {$wpdb->prefix}ms_milestones (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			video_id BIGINT UNSIGNED NOT NULL,
			user_id BIGINT UNSIGNED NOT NULL,
			milestone_pct TINYINT UNSIGNED NOT NULL,
			reached_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			session_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY (id),
			UNIQUE KEY uk_video_user_pct (video_id, user_id, milestone_pct),
			KEY idx_user_id (user_id)
		) {$charset_collate};"
		);

		// Table: ms_playlist_items.
		dbDelta(
			"CREATE TABLE {$wpdb->prefix}ms_playlist_items (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			playlist_id BIGINT UNSIGNED NOT NULL,
			video_id BIGINT UNSIGNED NOT NULL,
			sort_order INT UNSIGNED NOT NULL DEFAULT 0,
			added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY idx_playlist (playlist_id, sort_order),
			KEY idx_video (video_id)
		) {$charset_collate};"
		);
	}

	/**
	 * Drop all free plugin tables.
	 */
	public static function drop_tables(): void {
		global $wpdb;

		$tables = array(
			'ms_video_tags',
			'ms_playlist_items',
			'ms_milestones',
			'ms_watch_sessions_archive',
			'ms_watch_sessions',
			'ms_tags',
		);

		foreach ( $tables as $table ) {
			$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Drop custom tables.
		}
	}
}
