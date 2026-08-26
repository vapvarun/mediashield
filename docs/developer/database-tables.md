# Database Tables Reference

All tables use the `{$wpdb->prefix}` prefix (typically `wp_`). Created via `dbDelta` in `MediaShield\DB\Schema` (free, `includes/DB/Schema.php`) and `MediaShieldPro\DB\Schema` (Pro, same path in the Pro repo). Both free and Pro tables are created on plugin activation and dropped on full uninstall (deletion via Plugins > Delete). Free's tables are **not** dropped while Pro is still installed - see the uninstall note below.

Every table is `utf8mb4` / `utf8mb4_unicode_ci`. Every `datetime` column is UTC, written with `current_time( 'mysql', true )` - reads compare against that same function passed as a prepared parameter rather than MySQL `NOW()`, so retention windows do not drift with the session timezone.

13 tables total: 6 free, 7 Pro.

---

## Free plugin tables (6)

### `ms_tags`

Tag dictionary for milestone and manual video tags.

| Column | Type | Notes |
|--------|------|-------|
| `id` | BIGINT UNSIGNED, PK, AUTO_INCREMENT | |
| `name` | VARCHAR(200) NOT NULL | Display name |
| `slug` | VARCHAR(200) NOT NULL | URL-safe identifier |
| `description` | TEXT | Optional description |
| `created_by` | BIGINT UNSIGNED NOT NULL DEFAULT 0 | WordPress user ID of creator |
| `created_at` | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP | UTC creation timestamp |

**Indexes:** PRIMARY (`id`), UNIQUE `uk_slug` (`slug`).

Rows are garbage-collected: `Cron\Cleanup::handle_video_delete()` drops any tag left with no remaining `ms_video_tags` row after a video is deleted.

---

### `ms_video_tags`

Many-to-many join between videos and tags. No surrogate primary key.

| Column | Type | Notes |
|--------|------|-------|
| `video_id` | BIGINT UNSIGNED NOT NULL | `mediashield_video` CPT post ID |
| `tag_id` | BIGINT UNSIGNED NOT NULL | FK -> `ms_tags.id` |
| `tagged_by` | BIGINT UNSIGNED NOT NULL DEFAULT 0 | WordPress user ID |
| `tagged_at` | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP | UTC timestamp |

**Indexes:** UNIQUE `uk_video_tag` (`video_id`, `tag_id`), KEY `idx_tag_id` (`tag_id`).

---

### `ms_watch_sessions`

Active and recent watch session records. The concurrent-stream limit is a `SELECT COUNT(*) ... FOR UPDATE` inside a transaction in `Access\SessionManager`, counting rows for the user with `is_active = 1` **and** a `last_heartbeat` inside the past 5 minutes.

| Column | Type | Notes |
|--------|------|-------|
| `id` | BIGINT UNSIGNED, PK, AUTO_INCREMENT | |
| `video_id` | BIGINT UNSIGNED NOT NULL | CPT post ID |
| `user_id` | BIGINT UNSIGNED NOT NULL DEFAULT 0 | WordPress user ID (0 for guests) |
| `session_token` | VARCHAR(255) NOT NULL | HMAC-signed token; the credential the heartbeat/end routes authenticate on. Not unique. |
| `ip_address` | VARCHAR(45) NOT NULL DEFAULT '' | IPv4 or IPv6 |
| `user_agent` | VARCHAR(500) NOT NULL DEFAULT '' | |
| `device_type` | VARCHAR(20) NOT NULL DEFAULT '' | `desktop`, `mobile`, `tablet` |
| `browser` | VARCHAR(50) NOT NULL DEFAULT '' | |
| `started_at` | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP | UTC |
| `last_heartbeat` | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP | Updated every 30 s |
| `total_seconds` | INT UNSIGNED NOT NULL DEFAULT 0 | Running total of watched seconds |
| `max_position` | FLOAT NOT NULL DEFAULT 0 | Furthest position reached (seconds) |
| `completion_pct` | FLOAT NOT NULL DEFAULT 0 | 0-100 |
| `is_active` | TINYINT(1) NOT NULL DEFAULT 1 | `1` while session is live |

**Indexes:** PRIMARY (`id`), KEY `idx_video_user` (`video_id`, `user_id`), KEY `idx_active` (`user_id`, `is_active`, `last_heartbeat`), KEY `idx_user` (`user_id`), KEY `idx_started` (`started_at`).

**Cleanup:** two separate jobs, easy to confuse.

- `ms_cleanup_inactive_sessions` (hourly) only sets `is_active = 0` where `last_heartbeat` is older than 10 minutes. It moves nothing.
- `ms_archive_old_sessions` (monthly) is the only job that writes to the archive table, and it is a no-op unless the owner sets `ms_session_retention_months` (default `0` = keep everything).

See [cron-and-background-jobs.md](cron-and-background-jobs.md).

---

### `ms_watch_sessions_archive`

Identical schema to `ms_watch_sessions` - `Schema::create_tables()` builds it from the same SQL string with the table name substituted, so every column and index above applies here too.

Receives rows only when a retention window is configured. **No read path in either plugin queries this table for analytics**: the dashboard, the Pro heatmaps, the realtime viewers list and the CSV exports all read `ms_watch_sessions` alone. The only readers are the GDPR exporter and eraser (`includes/Privacy/`), the deletion cascade, and the WP-CLI scale command.

That is why `ms_restore_archived_sessions` exists (1.3.0): archiving used to run unconditionally at 24 months, so every install older than that lost its reporting history to a table nothing reads. The job walks those rows back into the live table in batches.

**Cleanup:** none. Nothing prunes the archive.

---

### `ms_milestones`

Per-user milestone completion records.

| Column | Type | Notes |
|--------|------|-------|
| `id` | BIGINT UNSIGNED, PK, AUTO_INCREMENT | |
| `video_id` | BIGINT UNSIGNED NOT NULL | CPT post ID |
| `user_id` | BIGINT UNSIGNED NOT NULL | WordPress user ID |
| `milestone_pct` | TINYINT UNSIGNED NOT NULL | 25, 50, 75, or 100 (or custom via the `mediashield_milestone_thresholds` filter) |
| `reached_at` | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP | UTC |
| `session_id` | BIGINT UNSIGNED NOT NULL DEFAULT 0 | FK -> `ms_watch_sessions.id` |

**Indexes:** PRIMARY (`id`), UNIQUE `uk_video_user_pct` (`video_id`, `user_id`, `milestone_pct`), KEY `idx_user_id` (`user_id`).

The unique constraint prevents double-recording the same milestone; the `INSERT IGNORE` in `MilestoneTracker` relies on it.

---

### `ms_playlist_items`

Ordered video items within a playlist CPT.

| Column | Type | Notes |
|--------|------|-------|
| `id` | BIGINT UNSIGNED, PK, AUTO_INCREMENT | |
| `playlist_id` | BIGINT UNSIGNED NOT NULL | `mediashield_playlist` CPT post ID |
| `video_id` | BIGINT UNSIGNED NOT NULL | `mediashield_video` CPT post ID |
| `sort_order` | INT UNSIGNED NOT NULL DEFAULT 0 | Display order (ascending) |
| `added_at` | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP | UTC |

**Indexes:** PRIMARY (`id`), KEY `idx_playlist` (`playlist_id`, `sort_order`), KEY `idx_video` (`video_id`).

---

## Pro plugin tables (7)

### `ms_playback_events`

Granular playback event log. Raw input for heatmap aggregation.

| Column | Type | Notes |
|--------|------|-------|
| `id` | BIGINT UNSIGNED, PK, AUTO_INCREMENT | |
| `session_id` | BIGINT UNSIGNED NOT NULL | FK -> `ms_watch_sessions.id`. This is the **only** link to a video - there is no `video_id` or `user_id` column here; the heatmap query joins through the sessions table to get them. |
| `event_type` | ENUM('play','pause','seek','buffer','complete','focus_lost','focus_gained') NOT NULL | |
| `position` | FLOAT NOT NULL DEFAULT 0 | Video position in seconds at event time |
| `timestamp` | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP | UTC. The column is `timestamp`, not `created_at`. |
| `metadata` | JSON | Event-specific payload |

**Indexes:** PRIMARY (`id`), KEY `idx_session` (`session_id`), KEY `idx_position` (`session_id`, `position`), KEY `idx_timestamp` (`timestamp`).

**Cleanup:** two jobs. `ms_heatmap_aggregation` (hourly) folds new rows into `ms_heatmap_cache`; `ms_playback_event_retention` (daily, since 1.2.0) then deletes rows older than 90 days on `idx_timestamp`. The window is filterable via `mediashield_pro_playback_event_retention_days` and floored at 2 days. Pruning is safe because the cache holds cumulative totals independent of the raw rows.

---

### `ms_platform_connections`

API credentials for external video platforms.

| Column | Type | Notes |
|--------|------|-------|
| `id` | BIGINT UNSIGNED, PK, AUTO_INCREMENT | |
| `platform` | VARCHAR(50) NOT NULL | `bunny`, `youtube`, `vimeo`, `wistia` |
| `api_key` | TEXT NOT NULL | Encrypted with AES-256-CBC using `SECURE_AUTH_SALT` |
| `api_secret` | TEXT NOT NULL | Encrypted the same way; empty string when the platform needs only a key |
| `extra_config` | JSON | Platform-specific fields (e.g. Bunny `library_id`, `pull_zone_hostname`, `cdn_token_key`) |
| `is_active` | TINYINT(1) NOT NULL DEFAULT 1 | The settings response only lists connections where this is 1 |
| `connected_by` | BIGINT UNSIGNED NOT NULL DEFAULT 0 | WordPress user ID |
| `connected_at` | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP | UTC. Named `connected_at`, not `created_at`. |

**Indexes:** PRIMARY (`id`), KEY `idx_platform` (`platform`).

There is no `label` column - the admin UI labels a connection by its `platform` value.

---

### `ms_upload_queue`

Upload job tracking for platform uploads. Written by `Upload\UploadQueue`, which listens on the free `mediashield_upload_started` / `_complete` / `_failed` actions.

| Column | Type | Notes |
|--------|------|-------|
| `id` | BIGINT UNSIGNED, PK, AUTO_INCREMENT | |
| `video_id` | BIGINT UNSIGNED DEFAULT NULL | CPT post ID. NULL until the video record exists. |
| `file_path` | TEXT NOT NULL | Absolute path to the source file |
| `target_platform` | VARCHAR(50) NOT NULL | Target platform. Named `target_platform`, not `platform`. |
| `status` | ENUM('pending','uploading','processing','complete','failed') NOT NULL DEFAULT 'pending' | |
| `progress` | TINYINT UNSIGNED NOT NULL DEFAULT 0 | 0-100. Named `progress`, not `progress_pct`. |
| `error_message` | TEXT | Set on failure |
| `uploaded_by` | BIGINT UNSIGNED NOT NULL DEFAULT 0 | WordPress user ID |
| `created_at` | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP | UTC |
| `completed_at` | DATETIME DEFAULT NULL | UTC, NULL while running |

**Indexes:** PRIMARY (`id`), KEY `idx_status` (`status`).

There is no `platform_connection_id` column - the connection is resolved at upload time from `target_platform`.

---

### `ms_activity_alerts`

Suspicious viewing pattern alerts.

| Column | Type | Notes |
|--------|------|-------|
| `id` | BIGINT UNSIGNED, PK, AUTO_INCREMENT | |
| `user_id` | BIGINT UNSIGNED NOT NULL | WordPress user ID |
| `video_id` | BIGINT UNSIGNED NOT NULL | CPT post ID |
| `alert_type` | ENUM('multi_ip','devtools','rapid_seek','concurrent_stream','vpn_detected') NOT NULL | |
| `severity` | VARCHAR(20) NOT NULL DEFAULT 'info' | |
| `message` | VARCHAR(500) NOT NULL DEFAULT '' | Human-readable summary |
| `details` | JSON | Alert-specific data. Named `details`, not `context`. |
| `is_dismissed` | TINYINT(1) NOT NULL DEFAULT 0 | Boolean flag - there is no `dismissed_at` timestamp. |
| `created_at` | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP | UTC |

**Indexes:** PRIMARY (`id`), KEY `idx_user` (`user_id`), KEY `idx_severity` (`severity`), KEY `idx_created` (`created_at`).

**Cleanup:** `ms_alert_pruning` (daily) deletes rows with `is_dismissed = 1` and `created_at` older than 90 days. Because dismissal is not timestamped, the age is measured from creation, not from when the alert was dismissed.

---

### `ms_drm_licenses`

DRM license records issued to users.

| Column | Type | Notes |
|--------|------|-------|
| `id` | BIGINT UNSIGNED, PK, AUTO_INCREMENT | |
| `video_id` | BIGINT UNSIGNED NOT NULL | CPT post ID |
| `user_id` | BIGINT UNSIGNED NOT NULL | WordPress user ID |
| `license_type` | ENUM('streaming','persistent') NOT NULL DEFAULT 'streaming' | `persistent` is legacy only: offline licensing was removed in 1.2.0 and nothing issues one now. |
| `license_token` | VARCHAR(255) NOT NULL | Issued license token |
| `device_id` | VARCHAR(255) NOT NULL DEFAULT '' | Optional `device_id` from the license request |
| `expires_at` | DATETIME NOT NULL | UTC. `ms_drm_license_duration_streaming` (default 86400) after issue. |
| `created_at` | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP | UTC |
| `revoked_at` | DATETIME DEFAULT NULL | NULL unless revoked. Any non-NULL row for a user+video pair denies all future issues (`WidevineLicense::is_revoked()`). |

**Indexes:** PRIMARY (`id`), KEY `idx_video_user` (`video_id`, `user_id`), KEY `idx_expires` (`expires_at`).

There is no `key_id` column here (that lives on `ms_drm_keys`) and no `issued_at` (it is `created_at`).

> The column comments in `Schema.php` are deliberately kept out of the SQL string: `dbDelta` parses `CREATE TABLE` line by line and a trailing `-- comment` becomes part of the column definition, silently failing the whole statement. That is what stopped fresh 1.2.0 installs from getting this table.

---

### `ms_heatmap_cache`

Aggregated heatmap data per video, written by the `ms_heatmap_aggregation` cron.

| Column | Type | Notes |
|--------|------|-------|
| `id` | BIGINT UNSIGNED, PK, AUTO_INCREMENT | |
| `video_id` | BIGINT UNSIGNED NOT NULL | CPT post ID |
| `position_bucket` | SMALLINT UNSIGNED NOT NULL | Start of the 10-second bucket, in seconds. Named `position_bucket`, not `bucket_start`. SMALLINT caps at 65535 s (about 18 h). |
| `view_count` | INT UNSIGNED NOT NULL DEFAULT 0 | Number of playback events in this bucket |
| `avg_duration` | FLOAT NOT NULL DEFAULT 0 | Average session `total_seconds` for the events in the bucket |
| `last_aggregated` | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP | UTC. Named `last_aggregated`, not `last_updated`. |

**Indexes:** PRIMARY (`id`), UNIQUE `uk_video_position` (`video_id`, `position_bucket`) - the target of the aggregation's `ON DUPLICATE KEY UPDATE`.

**Refresh:** hourly via Action Scheduler. The `ms_heatmap_last_aggregated` option records the watermark the next run reads from.

---

### `ms_drm_keys`

Encrypted AES-128 content keys for DRM-packaged videos.

| Column | Type | Notes |
|--------|------|-------|
| `id` | BIGINT UNSIGNED, PK, AUTO_INCREMENT | |
| `video_id` | BIGINT UNSIGNED NOT NULL | One key per video (enforced by the unique key below) |
| `key_id` | VARCHAR(255) NOT NULL | 32-char hex key ID (16 random bytes) |
| `content_key_encrypted` | TEXT NOT NULL | Hex content key, encrypted at rest with AES-256-CBC using `SECURE_AUTH_SALT`. Named `content_key_encrypted`, not `content_key`. |
| `created_at` | DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP | UTC |

**Indexes:** PRIMARY (`id`), UNIQUE `uk_video` (`video_id`).

A legacy `iv VARCHAR(32)` column existed in DB v1 but was never read - the IV is embedded in the OpenSSL ciphertext. `Migrator::run()` drops it at DB v2 and fresh installs never create it.

---

## Removed tables

`ms_email_captures` was dropped when the email gate was removed in 1.2.0. Pro's `Migrator` drops it on upgrade and `Schema::drop_tables()` still names it so an uninstall on a never-upgraded install cleans it up.
