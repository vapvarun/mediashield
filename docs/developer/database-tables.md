# Database Tables Reference

All tables use the `{$wpdb->prefix}` prefix (typically `wp_`). Created via `dbDelta` in `DB\Schema` (free) and the Pro schema class. Both free and Pro tables are created on plugin activation and dropped on full uninstall (deletion via Plugins > Delete).

---

## Free plugin tables (6)

### `ms_tags`

Tag taxonomy for milestone and manual video tags.

| Column | Type | Notes |
|--------|------|-------|
| `id` | int, PK, AUTO_INCREMENT | |
| `name` | varchar(255) | Display name |
| `slug` | varchar(255), UNIQUE | URL-safe identifier |
| `description` | text | Optional description |
| `created_by` | bigint | WordPress user ID of creator |
| `created_at` | datetime | UTC creation timestamp |

**Indexes:** PRIMARY (`id`), UNIQUE (`slug`).

---

### `ms_video_tags`

Many-to-many join between videos and tags.

| Column | Type | Notes |
|--------|------|-------|
| `video_id` | bigint | `mediashield_video` CPT post ID |
| `tag_id` | int | FK → `ms_tags.id` |
| `tagged_by` | bigint | WordPress user ID |
| `tagged_at` | datetime | UTC timestamp |

**Indexes:** UNIQUE (`video_id`, `tag_id`).

---

### `ms_watch_sessions`

Active and recent watch session records. The concurrent-stream limit check reads this table via `COUNT(*)` on active rows for a given `user_id`.

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint, PK, AUTO_INCREMENT | |
| `video_id` | bigint | CPT post ID |
| `user_id` | bigint | WordPress user ID (0 for guests) |
| `session_token` | varchar(64) | HMAC-derived token, used for heartbeat authentication |
| `ip_address` | varchar(45) | IPv4 or IPv6 |
| `user_agent` | text | |
| `device_type` | varchar(20) | `desktop`, `mobile`, `tablet` |
| `browser` | varchar(50) | |
| `started_at` | datetime | UTC |
| `last_heartbeat` | datetime | Updated every 30 s |
| `total_seconds` | int | Running total of watched seconds |
| `max_position` | int | Furthest position reached (seconds) |
| `completion_pct` | float | 0–100 |
| `is_active` | tinyint(1) | `1` while session is live |

**Indexes:** PRIMARY (`id`), KEY on `(user_id, is_active)`, KEY on `(session_token, video_id)`.

**Cleanup:** `Cron\Cleanup` archives rows with `last_heartbeat` older than 5 minutes into `ms_watch_sessions_archive` and sets `is_active = 0`. See [cron-and-background-jobs.md](cron-and-background-jobs.md).

---

### `ms_watch_sessions_archive`

Same schema as `ms_watch_sessions`. Receives rows moved from the active table by `Cron\Cleanup`. Used for historical analytics.

**Cleanup:** No automatic pruning in the free plugin. Pro's export endpoints read both tables.

---

### `ms_milestones`

Per-user milestone completion records.

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint, PK, AUTO_INCREMENT | |
| `video_id` | bigint | CPT post ID |
| `user_id` | bigint | WordPress user ID |
| `milestone_pct` | tinyint | 25, 50, 75, or 100 (or custom via `mediashield_milestone_thresholds` filter) |
| `reached_at` | datetime | UTC |
| `session_id` | bigint | FK → `ms_watch_sessions.id` |

**Indexes:** PRIMARY (`id`), UNIQUE (`video_id`, `user_id`, `milestone_pct`) — the unique constraint prevents double-recording the same milestone. The `INSERT IGNORE` in `MilestoneTracker` relies on this.

---

### `ms_playlist_items`

Ordered video items within a playlist CPT.

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint, PK, AUTO_INCREMENT | |
| `playlist_id` | bigint | `mediashield_playlist` CPT post ID |
| `video_id` | bigint | `mediashield_video` CPT post ID |
| `sort_order` | int | Display order (ascending) |
| `added_at` | datetime | UTC |

**Indexes:** PRIMARY (`id`), KEY on `(playlist_id, sort_order)`.

---

## Pro plugin tables (8)

### `ms_playback_events`

Granular playback event log. Raw input for heatmap aggregation.

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint, PK, AUTO_INCREMENT | |
| `session_id` | bigint | FK → `ms_watch_sessions.id` |
| `video_id` | bigint | CPT post ID |
| `user_id` | bigint | |
| `event_type` | varchar(20) | `play`, `pause`, `seek`, `buffer`, `complete` |
| `position` | int | Video position in seconds at event time |
| `created_at` | datetime | UTC |

**Indexes:** PRIMARY (`id`), KEY on `(video_id, created_at)`.

**Cleanup:** The `ms_heatmap_aggregation` cron job (hourly) reads raw events and writes aggregated buckets to `ms_heatmap_cache`. Old raw events are not automatically pruned — manage retention manually or via the export/delete API.

---

### `ms_platform_connections`

API credentials for external video platforms.

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint, PK, AUTO_INCREMENT | |
| `platform` | varchar(50) | `bunny`, `youtube`, `vimeo`, `wistia` |
| `label` | varchar(255) | Admin-facing display name |
| `api_key` | text | Encrypted with AES-256-CBC using `SECURE_AUTH_SALT` |
| `extra_config` | longtext (JSON) | Platform-specific fields (e.g. Bunny `library_id`, `pull_zone_hostname`, `cdn_token_key`) |
| `created_at` | datetime | UTC |

**Indexes:** PRIMARY (`id`).

---

### `ms_upload_queue`

Upload job tracking for platform uploads.

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint, PK, AUTO_INCREMENT | |
| `video_id` | bigint | CPT post ID (created before upload completes) |
| `platform` | varchar(50) | Target platform |
| `platform_connection_id` | bigint | FK → `ms_platform_connections.id` |
| `status` | varchar(20) | `pending`, `uploading`, `processing`, `complete`, `failed` |
| `progress_pct` | int | 0–100 |
| `error_message` | text | Set on failure |
| `created_at` | datetime | UTC |
| `updated_at` | datetime | UTC |

**Indexes:** PRIMARY (`id`), KEY on `(video_id, status)`.

---

### `ms_activity_alerts`

Suspicious viewing pattern alerts.

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint, PK, AUTO_INCREMENT | |
| `user_id` | bigint | WordPress user ID |
| `video_id` | bigint | CPT post ID |
| `alert_type` | varchar(30) | `multi_ip`, `devtools`, `rapid_seek`, `concurrent_stream`, `vpn_detected` |
| `context` | longtext (JSON) | Alert-specific data |
| `dismissed_at` | datetime | NULL = active, non-NULL = dismissed |
| `created_at` | datetime | UTC |

**Indexes:** PRIMARY (`id`), KEY on `(user_id, alert_type)`, KEY on `(dismissed_at)`.

**Cleanup:** `ms_alert_pruning` cron (daily) deletes dismissed alerts older than 90 days.

---

### `ms_drm_licenses`

DRM license records issued to users.

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint, PK, AUTO_INCREMENT | |
| `user_id` | bigint | WordPress user ID |
| `video_id` | bigint | CPT post ID |
| `key_id` | varchar(64) | References the key in `ms_drm_keys` |
| `license_type` | varchar(20) | `streaming` (24h) or `persistent` (30d) |
| `issued_at` | datetime | UTC |
| `expires_at` | datetime | UTC |
| `revoked_at` | datetime | NULL unless revoked |

**Indexes:** PRIMARY (`id`), KEY on `(user_id, video_id)`.

---

### `ms_heatmap_cache`

Aggregated heatmap data per video, written by `ms_heatmap_aggregation` cron.

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint, PK, AUTO_INCREMENT | |
| `video_id` | bigint | CPT post ID |
| `bucket_start` | int | Position in seconds (start of 10-second bucket) |
| `view_count` | int | Number of playback events in this bucket |
| `avg_duration` | float | Average seconds spent in bucket |
| `last_updated` | datetime | UTC |

**Indexes:** PRIMARY (`id`), UNIQUE (`video_id`, `bucket_start`).

**Refresh:** The `ms_heatmap_aggregation` cron job runs hourly via Action Scheduler. `ms_heatmap_last_aggregated` option records the last run timestamp.

---

### `ms_drm_keys`

Encrypted AES-128 content keys for DRM-packaged videos.

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint, PK, AUTO_INCREMENT | |
| `video_id` | bigint, UNIQUE | One key per video |
| `key_id` | varchar(64) | Hex key ID |
| `content_key` | text | AES-128 key, encrypted at rest with AES-256-CBC using `SECURE_AUTH_SALT` |
| `created_at` | datetime | UTC |

**Indexes:** PRIMARY (`id`), UNIQUE (`video_id`).

---

### `ms_email_captures`

Email gate submission records.

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint, PK, AUTO_INCREMENT | |
| `email` | varchar(255) | Submitted email address |
| `video_id` | bigint | CPT post ID |
| `ip_address` | varchar(45) | For spam review |
| `consent` | tinyint(1) | Whether the consent checkbox was ticked |
| `consent_text` | text | Exact wording shown to the visitor at submission time |
| `created_at` | datetime | UTC |

**Indexes:** PRIMARY (`id`), KEY on `(email)`, KEY on `(video_id)`.

**Cleanup:** `ms_email_capture_retention` cron (daily) deletes rows older than `ms_email_retention_months` option (default 12 months).
