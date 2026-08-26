# Database Tables

MediaShield creates 6 tables in the free plugin. All table names use the `{$wpdb->prefix}` prefix (typically `wp_`). Tables are created via `dbDelta` on activation, re-run by `Core\Migrator` whenever `MEDIASHIELD_DB_VERSION` increases, and dropped on full uninstall (Plugins > Delete) - unless Pro is still installed, in which case they are left alone along with the settings and the video records.

---

## ms_tags

Tag dictionary for milestone tags and manual video tags.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint unsigned, PK, AUTO_INCREMENT | |
| name | varchar(200) | Display name |
| slug | varchar(200), UNIQUE | URL-safe identifier |
| description | text | Optional description |
| created_by | bigint unsigned | WordPress user ID of creator, 0 if unknown |
| created_at | datetime | UTC creation timestamp |

Indexes: PRIMARY (id), UNIQUE `uk_slug` (slug).

---

## ms_video_tags

Many-to-many join between videos and tags. There is no surrogate primary key; the unique pair is the identity.

| Column | Type | Notes |
|--------|------|-------|
| video_id | bigint unsigned | `mediashield_video` CPT post ID |
| tag_id | bigint unsigned | References ms_tags.id |
| tagged_by | bigint unsigned | WordPress user ID, 0 if unknown |
| tagged_at | datetime | UTC timestamp |

Indexes: UNIQUE `uk_video_tag` (video_id, tag_id), KEY `idx_tag_id` (tag_id).

---

## ms_watch_sessions

Watch session records, live and historical. The concurrent-stream check, the dashboard, the viewer reports, and the "My Videos" grid all read this table.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint unsigned, PK, AUTO_INCREMENT | |
| video_id | bigint unsigned | CPT post ID |
| user_id | bigint unsigned | WordPress user ID (0 for guests) |
| session_token | varchar(255) | Random token stored with the row; the token handed to the client is HMAC-derived |
| ip_address | varchar(45) | IPv4 or IPv6 |
| user_agent | varchar(500) | Truncated to 500 characters on write |
| device_type | varchar(20) | desktop, mobile, or tablet |
| browser | varchar(50) | |
| started_at | datetime | UTC |
| last_heartbeat | datetime | UTC, updated every 30 seconds while playing |
| total_seconds | int unsigned | Running total of watched seconds |
| max_position | float | Furthest position reached (seconds) |
| completion_pct | float | 0-100 |
| is_active | tinyint(1) | 1 while the session is live |

Indexes: PRIMARY (id), KEY `idx_video_user` (video_id, user_id), KEY `idx_active` (user_id, is_active, last_heartbeat), KEY `idx_user` (user_id), KEY `idx_started` (started_at).

All timestamps are written with `current_time( 'mysql', true )` (UTC), and every query that compares against "now" passes the same function's output as a prepared parameter rather than relying on MySQL's `NOW()`.

---

## ms_watch_sessions_archive

Same schema as `ms_watch_sessions`, including the same indexes.

Nothing reads this table. It exists only as a holding area for the optional retention job below, and no report or export queries it. That is the whole reason retention is opt-in: rows moved here disappear from every screen in the plugin.

Upgrading to 1.3.0 queues a one-off job that moves any rows already here back into the live table, in batches, until the archive is empty.

---

## ms_milestones

Per-user milestone completion records.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint unsigned, PK, AUTO_INCREMENT | |
| video_id | bigint unsigned | CPT post ID |
| user_id | bigint unsigned | WordPress user ID |
| milestone_pct | tinyint unsigned | 25, 50, 75, 100, or any custom threshold |
| reached_at | datetime | UTC |
| session_id | bigint unsigned | References ms_watch_sessions.id, 0 if unknown |

Indexes: PRIMARY (id), UNIQUE `uk_video_user_pct` (video_id, user_id, milestone_pct), KEY `idx_user_id` (user_id).

The UNIQUE constraint prevents double-recording the same milestone. `MilestoneTracker` uses `INSERT IGNORE` and treats "a row was inserted" as "this milestone is new", which is what gates the milestone actions.

---

## ms_playlist_items

Ordered video items within a playlist CPT.

| Column | Type | Notes |
|--------|------|-------|
| id | bigint unsigned, PK, AUTO_INCREMENT | |
| playlist_id | bigint unsigned | `mediashield_playlist` CPT post ID |
| video_id | bigint unsigned | `mediashield_video` CPT post ID |
| sort_order | int unsigned | Display order (ascending) |
| added_at | datetime | UTC |

Indexes: PRIMARY (id), KEY `idx_playlist` (playlist_id, sort_order), KEY `idx_video` (video_id).

---

## Scheduled jobs

Jobs are registered on `init` through Action Scheduler when it is available (it ships with the plugin), and fall back to WP-Cron otherwise. The monthly interval is registered on `cron_schedules` for the fallback path.

**`ms_cleanup_inactive_sessions` - hourly.** Sets `is_active = 0` on sessions whose `last_heartbeat` is more than **10 minutes** old. It only flips the flag; it does not move, archive, or delete anything. Note this is a different threshold from the 5 minutes used at read time by the concurrent-stream check and the Active Viewers card, which ignore stale sessions without waiting for the job.

**`ms_archive_old_sessions` - monthly.** Moves sessions older than the configured retention window into `ms_watch_sessions_archive` and deletes the originals, inside a transaction. **It returns immediately unless `ms_session_retention_months` is 1 or more, and the default is 0.**

Until 1.3.0 this job archived at 24 months unconditionally, into a table nothing reads, so long-running sites silently lost report history with nothing in the UI to say why. Retention is now something the owner opts into.

**`ms_restore_archived_sessions` - one-off, self-rescheduling.** Queued by the 1.3.0 migration. Moves archived rows back into the live table 2000 at a time (filter `mediashield_restore_archive_batch_size`, clamped 100-20000), re-queueing itself a minute later while rows remain. It re-keys rows on insert rather than carrying their old IDs, and only deletes a batch from the archive once the insert is confirmed.

There is no five-minute cron. If you need to react to session expiry sooner than the hourly job, read `last_heartbeat` yourself.

**Cascade delete.** Not a cron job: `before_delete_post` cleans up when a video or playlist is permanently deleted. Deleting a video removes its tag links (and any tag left with no videos), its sessions in both tables, its milestones, its playlist entries, and the matching entries in each user's earned-tag meta. For self-hosted videos it also deletes the stored file - that file is in this site's own uploads folder and nothing else references it. **For platform videos it does not touch the platform.** A master on Bunny, Vimeo, YouTube or Wistia is left exactly where it is, so the same video can be linked back at any time by importing it again. With Pro active it clears the Pro tables that reference the video or its sessions. Deleting a playlist removes its items.

Deactivating the plugin unschedules the recurring jobs and leaves all data in place. Deleting the plugin drops all six tables and permanently deletes every video and playlist post.
