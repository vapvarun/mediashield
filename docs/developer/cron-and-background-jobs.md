# Cron and Background Jobs

All background processing in MediaShield. Customer docs never mention these - everything operational (what it does for site owners) is covered in the admin UI. This page is for developers who need to debug, extend, or disable specific jobs.

Both plugins prefer Action Scheduler when it is available and fall back to WP-Cron when it is not. Free schedules into the `mediashield` group, Pro into `mediashield-pro`. Scheduling happens on `init` (`Cron\Cleanup::schedule_actions()` and `Cron\ProCleanup::schedule_actions()`), not on activation - Pro's `Activator` additionally seeds three of its recurring actions so a fresh activation does not have to wait for the next page load.

---

## Free plugin

### `ms_cleanup_inactive_sessions`

| Property | Value |
|----------|-------|
| Hook name | `ms_cleanup_inactive_sessions` |
| Interval | Hourly (recurring) |
| Group | `mediashield` (Action Scheduler), or the WP-Cron `hourly` schedule as fallback |
| Source | `includes/Cron/Cleanup.php` - `cleanup_inactive_sessions()` |
| Registered | Scheduled from `init`; `Deactivator::deactivate()` clears it from both schedulers |

**What it does:** one `UPDATE` that sets `is_active = 0` on every `ms_watch_sessions` row whose `last_heartbeat` is older than 10 minutes. It does **not** move rows anywhere.

This is the session expiry mechanism - it's what prevents phantom "active" sessions after a browser crash. Note the two different windows: the concurrent-stream check in `Access\SessionManager` only counts a session as live when its heartbeat is within **5** minutes, so a stale session stops counting against the limit well before this job flips the flag.

**Debug:**
```php
// Trigger manually from wp-cli or a must-use plugin:
do_action( 'ms_cleanup_inactive_sessions' );
```

Both the WP-Cron entry (`cron` option) and the Action Scheduler queue are re-seeded on `init`, so a missing schedule usually means `init` is never reached (a fatal earlier in the boot), not that activation failed.

---

### `ms_archive_old_sessions`

| Property | Value |
|----------|-------|
| Hook name | `ms_archive_old_sessions` |
| Interval | Monthly (recurring) |
| Group | `mediashield` (Action Scheduler), or the WP-Cron `monthly` schedule as fallback |
| Source | `includes/Cron/Cleanup.php` - `archive_old_sessions()` |

The `monthly` WP-Cron interval (`MONTH_IN_SECONDS`) is registered by `Cleanup::add_monthly_schedule()` on the `cron_schedules` filter, and only when no other plugin has already defined `monthly`.

**What it does:** moves `ms_watch_sessions` rows with `started_at` older than `ms_session_retention_months` into `ms_watch_sessions_archive` inside a transaction (`INSERT ... SELECT` then `DELETE`), rolling back if either statement fails.

**Opt-in.** `ms_session_retention_months` defaults to `0` and the job returns immediately when the value is below 1, so on a default install this job does nothing. Before 1.3.0 it archived at 24 months unconditionally into a table no read path queries, which silently emptied every report at month 25.

---

### `ms_restore_archived_sessions`

| Property | Value |
|----------|-------|
| Hook name | `ms_restore_archived_sessions` |
| Interval | One-off, self-rescheduling (+60 s while rows remain) |
| Group | `mediashield` |
| Source | `includes/Cron/Cleanup.php` - `restore_archived_sessions()`; queued by `Core\Migrator::run()` |
| Since | 1.3.0 |

**What it does:** walks rows back out of `ms_watch_sessions_archive` into the live table, so history stranded by the pre-1.3.0 unconditional archiving becomes visible to the reports again.

1. Returns silently if the archive table does not exist.
2. Reads a batch of ids (`SELECT id ... ORDER BY id ASC LIMIT`), default 2000, filterable via `mediashield_restore_archive_batch_size` and clamped to 100-20000.
3. `INSERT INTO ms_watch_sessions (<every column except id>) SELECT ...` - the id is deliberately dropped so the live table assigns fresh primary keys.
4. Deletes the moved rows **only** when the insert reported exactly the batch size.
5. Reschedules itself a minute out while rows remain.

**Debug:** compare `SELECT COUNT(*)` on both tables. If the archive count is stuck, the insert is failing and the delete is (correctly) not running.

```php
// Force a batch now:
do_action( 'ms_restore_archived_sessions' );

// Smaller batches on a constrained host:
add_filter( 'mediashield_restore_archive_batch_size', function () { return 250; } );
```

---

## Pro plugin - Action Scheduler

Pro requires Action Scheduler for its jobs (`ProCleanup::schedule_actions()` returns early when `as_has_scheduled_action()` is undefined - there is no WP-Cron fallback on the Pro side). All Pro jobs run in the `mediashield-pro` group.

Check pending jobs from WP Admin: **Tools > Scheduled Actions** (if WooCommerce is active), or query directly:

```sql
SELECT hook, status, scheduled_date_gmt, args
FROM wp_actionscheduler_actions
WHERE `group` = 'mediashield-pro'
ORDER BY scheduled_date_gmt ASC;
```

---

### `ms_heatmap_aggregation`

| Property | Value |
|----------|-------|
| Hook name | `ms_heatmap_aggregation` |
| Interval | Hourly (recurring) |
| Group | `mediashield-pro` |
| Source | `includes/Cron/ProCleanup.php` -> `Analytics\Heatmap::aggregate()` |

**What it does:**
1. Reads raw rows from `ms_playback_events` with `timestamp >` the `ms_heatmap_last_aggregated` option (default `1970-01-01 00:00:00`), joined to `ms_watch_sessions` for the `video_id`.
2. Groups into 10-second position buckets per video (`FLOOR( position / 10 ) * 10`).
3. Upserts counts and average durations into `ms_heatmap_cache` (`ON DUPLICATE KEY UPDATE` against `uk_video_position`).
4. Updates `ms_heatmap_last_aggregated` to the current timestamp.

**Debug:** Check `ms_heatmap_last_aggregated` in `wp_options`. If it's stale, Action Scheduler may be backed up - check the queue in the admin.

---

### `ms_playback_event_retention`

| Property | Value |
|----------|-------|
| Hook name | `ms_playback_event_retention` |
| Interval | Daily (recurring) |
| Group | `mediashield-pro` |
| Since | 1.2.0 |

**What it does:** deletes `ms_playback_events` rows older than the retention window, using the `idx_timestamp` index.

The window comes from `apply_filters( 'mediashield_pro_playback_event_retention_days', 90 )` and is floored at 2 days so it can never overtake the hourly aggregation. Pruned rows have already been folded into the cumulative `ms_heatmap_cache`, so the heatmaps do not lose anything.

```php
// Keep two weeks of raw events instead of 90 days.
add_filter( 'mediashield_pro_playback_event_retention_days', function () { return 14; } );
```

This job is scheduled by `ProCleanup` on `init` only - Pro's `Activator` seeds the other three recurring actions, not this one.

---

### `ms_alert_pruning`

| Property | Value |
|----------|-------|
| Hook name | `ms_alert_pruning` |
| Interval | Daily (recurring) |
| Group | `mediashield-pro` |

**What it does:** Deletes rows from `ms_activity_alerts` where `is_dismissed = 1` and `created_at` is older than 90 days. The table has no `dismissed_at` column - age is measured from creation, not from dismissal.

---

### `ms_weekly_digest`

| Property | Value |
|----------|-------|
| Hook name | `ms_weekly_digest` |
| Interval | Weekly (recurring) |
| Group | `mediashield-pro` |
| Source | `includes/Reports/WeeklyDigest.php` |

**What it does:** Generates and sends the weekly analytics HTML email to `ms_weekly_digest_email` (falling back to `admin_email`). Summarizes total views, completions, average completion rate, top 5 videos by views, and alert count for the past 7 days.

**Disable without uninstalling:**
```php
update_option( 'ms_weekly_digest_enabled', false );
```

**Send a test to yourself:** `POST /mediashield-pro/v1/digest/send-test` (`manage_options`) runs the same generator and re-targets the mail at the requesting admin.

---

### `ms_vpn_lookup` (async, one-off)

| Property | Value |
|----------|-------|
| Hook name | `ms_vpn_lookup` |
| Interval | One-time, enqueued per uncached IP |
| Group | `mediashield-pro` |
| Args | `[ $ip, $user_id, $video_id ]` |
| Source | `includes/Analytics/VpnDetection.php` |

Queued from `mediashield_session_started` (priority 20) when VPN detection is on and the IP has no cached verdict. Falls back to an inline lookup when Action Scheduler is unavailable. The provider endpoint is filterable via `mediashield_vpn_lookup_url`.

---

### `mediashield_pro_drm_package` (async, one-off)

| Property | Value |
|----------|-------|
| Hook name | `mediashield_pro_drm_package` |
| Interval | One-time (`as_enqueue_async_action`) |
| Group | `mediashield-pro` |
| Args | `[ 'video_id' => int, 'file_path' => string ]` |
| Source | **Removed in 1.3.0** - `Packager::schedule_packaging()` was deleted with the rest of the packaging code |

No longer enqueued by anything. `Packager::schedule_packaging()` was the only producer and it had no callers, so this job never ran; both it and the `_ms_drm_packaging_status` / `_ms_drm_packaging_action_id` meta keys went with the deletion in 1.3.0.

---

### `mediashield_generate_pdf` (async, one-off)

| Property | Value |
|----------|-------|
| Hook name | `mediashield_generate_pdf` |
| Interval | One-time (scheduled on demand) |
| Group | `mediashield-pro` |
| Args | `[ 'user_id' => int, 'filters' => array, 'report_id' => string ]` |
| Handler | `Export\PdfExporter::handle_async()`, registered in `includes/Core/Plugin.php` |

**What it does:** Triggered by `POST /mediashield-pro/v1/export/pdf/report`. Action Scheduler queues one `mediashield_generate_pdf` action per request. `PdfExporter` runs, produces an A4 portrait PDF via dompdf, stores the download URL in a transient keyed by `report_id` for `DAY_IN_SECONDS`, and emails a download link to the requesting admin.

`report_id` is a `wp_generate_uuid4()` minted at enqueue time and threaded through the args precisely so the worker and the status poller agree on the transient key.

**Check status:** `GET /mediashield-pro/v1/export/status/{job_id}` or query `wp_actionscheduler_actions` by `args` containing the `job_id` value.

---

### `mediashield_fire_webhook` (async, one-off)

| Property | Value |
|----------|-------|
| Hook name | `mediashield_fire_webhook` |
| Interval | One-time (`as_enqueue_async_action`) |
| Group | `mediashield-pro` |
| Args | `[ $url, $payload ]` |
| Handler | `Milestones\AdvancedActions::fire_webhook()` |

Enqueued by a milestone action configured with a webhook URL. Falls back to a synchronous POST when Action Scheduler is unavailable. See [hooks-filters-pro.md](hooks-filters-pro.md#mediashield_fire_webhook).

---

## Disabling jobs selectively

`Cleanup::schedule_actions()` and `ProCleanup::schedule_actions()` both run on `init` at the default priority and will re-add anything cleared earlier, so unschedule at a later priority:

```php
add_action( 'init', function() {
    wp_clear_scheduled_hook( 'ms_archive_old_sessions' );
    if ( function_exists( 'as_unschedule_all_actions' ) ) {
        as_unschedule_all_actions( 'ms_archive_old_sessions', array(), 'mediashield' );
    }
}, 20 );
```

For `ms_archive_old_sessions` specifically, leaving `ms_session_retention_months` at `0` is the supported way to keep it inert - the callback returns before touching the database.

To disable a Pro Action Scheduler job:

```php
add_action( 'init', function() {
    as_unschedule_all_actions( 'ms_heatmap_aggregation', array(), 'mediashield-pro' );
}, 20 );
```

Hooking the job with a no-op callback does not help: `add_action( 'ms_alert_pruning', '__return_null', 1 )` adds a listener, it does not replace the plugin's.

---

## WP-CLI

Not cron, but the other place maintenance work runs.

| Command | What it does |
|---------|--------------|
| `wp mediashield repair bunny-urls` | Finds videos saved as self-hosted from a Bunny **dashboard** URL, extracts the video GUID and rewrites `_ms_platform` / `_ms_platform_video_id`. Dry run by default; pass `--execute` to write. Collection URLs are reported but never rewritten, because a collection GUID is not a video GUID. Source: `src/CLI/RepairCommand.php` (since 1.3.0). |
