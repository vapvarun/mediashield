# Cron and Background Jobs

All background processing in MediaShield. Customer docs never mention these — everything operational (what it does for site owners) is covered in the admin UI. This page is for developers who need to debug, extend, or disable specific jobs.

---

## Free plugin — WP-Cron

### `ms_session_cleanup`

| Property | Value |
|----------|-------|
| Hook name | `ms_session_cleanup` |
| Interval | Every 5 minutes (`wp_schedule_event` with a custom interval registered as `'mediashield_5min'`) |
| Source | `includes/Cron/Cleanup.php` |
| Registered | `Deactivator::deactivate()` clears it; `Activator::activate()` schedules it |

**What it does:**
1. Selects all rows in `ms_watch_sessions` where `last_heartbeat < NOW() - INTERVAL 5 MINUTE` and `is_active = 1`.
2. Copies them to `ms_watch_sessions_archive`.
3. Sets `is_active = 0` on the source rows.

This is the session expiry mechanism — it's what prevents phantom "active" sessions after a browser crash.

**Debug:**
```php
// Trigger manually from wp-cli or a must-use plugin:
do_action( 'ms_session_cleanup' );
```

Check `wp_options` for `cron` key to verify the job is scheduled. If missing after activation, deactivate and reactivate the plugin.

---

## Pro plugin — Action Scheduler

Pro uses Action Scheduler (bundled) rather than WP-Cron for reliability. All Pro jobs run in the `mediashield-pro` group.

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

**What it does:**
1. Reads raw rows from `ms_playback_events` since `ms_heatmap_last_aggregated` option timestamp.
2. Groups into 10-second position buckets per video.
3. Upserts aggregated counts and average durations into `ms_heatmap_cache`.
4. Updates `ms_heatmap_last_aggregated` to the current timestamp.

**Debug:** Check `ms_heatmap_last_aggregated` in `wp_options`. If it's stale, Action Scheduler may be backed up — check the queue in the admin.

---

### `ms_alert_pruning`

| Property | Value |
|----------|-------|
| Hook name | `ms_alert_pruning` |
| Interval | Daily (recurring) |
| Group | `mediashield-pro` |

**What it does:** Deletes rows from `ms_activity_alerts` where `dismissed_at IS NOT NULL` and `dismissed_at < NOW() - INTERVAL 90 DAY`.

---

### `ms_weekly_digest`

| Property | Value |
|----------|-------|
| Hook name | `ms_weekly_digest` |
| Interval | Weekly (recurring) |
| Group | `mediashield-pro` |

**What it does:** Generates and sends the weekly analytics HTML email to `ms_weekly_digest_email`. Summarizes total views, completions, average completion rate, top 5 videos by views, and alert count for the past 7 days.

**Disable without uninstalling:**
```php
update_option( 'ms_weekly_digest_enabled', false );
```

---

### `ms_generate_pdf` (async, one-time)

| Property | Value |
|----------|-------|
| Hook name | `mediashield_generate_pdf` |
| Interval | One-time (scheduled on demand) |
| Group | `mediashield-pro` |

**What it does:** Triggered by `POST /mediashield-pro/v1/export/pdf/report`. Action Scheduler queues one `mediashield_generate_pdf` action per request. The `PdfExporter` class runs, produces an A4 PDF, stores it as a 24-hour transient, and emails a download link to the requesting admin.

**Check status:** `GET /mediashield-pro/v1/export/status/{job_id}` or query `wp_actionscheduler_actions` by `args` containing the `job_id` value.

---

## Disabling jobs selectively

To disable a specific free cron job without deactivating the plugin:

```php
add_action( 'ms_session_cleanup', function() {}, 1 ); // No-op — runs but does nothing.
// Or remove the schedule on init:
add_action( 'init', function() {
    wp_clear_scheduled_hook( 'ms_session_cleanup' );
} );
```

To disable a Pro Action Scheduler job:

```php
// Cancel all pending ms_heatmap_aggregation actions:
add_action( 'mediashield_pro_loaded', function() {
    as_unschedule_all_actions( 'ms_heatmap_aggregation', [], 'mediashield-pro' );
} );
```
