# Database Tables

MediaShield creates 6 tables in the free plugin. All table names use the `{$wpdb->prefix}` prefix (typically `wp_`). Tables are created via `dbDelta` on activation and dropped on full uninstall (Plugins > Delete).

---

## ms_tags

Tag taxonomy for milestone and manual video tags.

| Column | Type | Notes |
|--------|------|-------|
| id | int, PK, AUTO_INCREMENT | |
| name | varchar(255) | Display name |
| slug | varchar(255), UNIQUE | URL-safe identifier |
| description | text | Optional description |
| created_by | bigint | WordPress user ID of creator |
| created_at | datetime | UTC creation timestamp |

Indexes: PRIMARY (id), UNIQUE (slug).

---

## ms_video_tags

Many-to-many join between videos and tags.

| Column | Type | Notes |
|--------|------|-------|
| video_id | bigint | `mediashield_video` CPT post ID |
| tag_id | int | References ms_tags.id |
| tagged_by | bigint | WordPress user ID |
| tagged_at | datetime | UTC timestamp |

Indexes: UNIQUE (video_id, tag_id).

---

## ms_watch_sessions

Active and recent watch session records. The concurrent-stream limit check reads this table.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint, PK, AUTO_INCREMENT | |
| video_id | bigint | CPT post ID |
| user_id | bigint | WordPress user ID (0 for guests) |
| session_token | varchar(64) | HMAC-derived token for heartbeat authentication |
| ip_address | varchar(45) | IPv4 or IPv6 |
| user_agent | text | |
| device_type | varchar(20) | desktop, mobile, or tablet |
| browser | varchar(50) | |
| started_at | datetime | UTC |
| last_heartbeat | datetime | Updated every 30 seconds |
| total_seconds | int | Running total of watched seconds |
| max_position | int | Furthest position reached (seconds) |
| completion_pct | float | 0-100 |
| is_active | tinyint(1) | 1 while session is live |

Indexes: PRIMARY (id), KEY on (user_id, is_active), KEY on (session_token, video_id).

Cleanup: The `Cron\Cleanup` job archives rows with `last_heartbeat` older than 5 minutes into `ms_watch_sessions_archive` and sets `is_active = 0`.

---

## ms_watch_sessions_archive

Same schema as `ms_watch_sessions`. Receives rows moved from the active table by the cleanup job. Used for historical analytics queries.

No automatic pruning in the free plugin. Pro's export endpoints read both tables.

---

## ms_milestones

Per-user milestone completion records.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint, PK, AUTO_INCREMENT | |
| video_id | bigint | CPT post ID |
| user_id | bigint | WordPress user ID |
| milestone_pct | tinyint | 25, 50, 75, or 100 (or custom via filter) |
| reached_at | datetime | UTC |
| session_id | bigint | References ms_watch_sessions.id |

Indexes: PRIMARY (id), UNIQUE (video_id, user_id, milestone_pct).

The UNIQUE constraint prevents double-recording the same milestone. The MilestoneTracker class uses `INSERT IGNORE` to rely on this constraint.

---

## ms_playlist_items

Ordered video items within a playlist CPT.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint, PK, AUTO_INCREMENT | |
| playlist_id | bigint | `mediashield_playlist` CPT post ID |
| video_id | bigint | `mediashield_video` CPT post ID |
| sort_order | int | Display order (ascending) |
| added_at | datetime | UTC |

Indexes: PRIMARY (id), KEY on (playlist_id, sort_order).

---

## Cron cleanup

Two scheduled jobs maintain the tables:

**Session cleanup** - runs on the WordPress cron schedule. Moves stale session rows (no heartbeat for 5 minutes) from `ms_watch_sessions` to `ms_watch_sessions_archive` and marks them inactive.

**Cascade delete** - runs when a video or playlist CPT is deleted. Removes associated rows from all related tables to prevent orphaned records.

The cron job is registered as a standard WordPress cron event via `wp_schedule_event`. WP-Cron fallback is supported for hosts that do not use a real system cron.
