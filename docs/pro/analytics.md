# MediaShield Pro -- Analytics

MediaShield Pro extends the free plugin's basic analytics with heatmaps, realtime monitoring, suspicious activity detection, and data export.

---

## Playback Heatmaps

Heatmaps show where in a video users are watching, rewatching, or dropping off.

### How It Works

1. The frontend tracker logs granular playback events (play, pause, seek, buffer, complete) to `ms_playback_events`.
2. A scheduled cron job (`ms_heatmap_aggregation`, hourly) aggregates events into 10-second position buckets in `ms_heatmap_cache`.
3. The admin page renders a Chart.js bar chart with a retention line overlay.

### Reading the Heatmap

- **High bars** indicate frequently watched segments (hot spots).
- **Retention line** shows the percentage of viewers who reached that point.
- **Steep drops** indicate where viewers lose interest.
- **Spikes after drops** indicate sections viewers seek to (e.g., key content, Q&A).

### Device Breakdown

The heatmap page also shows a device type distribution chart:
- Desktop, mobile, tablet breakdown with percentages
- Useful for optimizing video format and player layout

### REST Endpoint

```
GET /mediashield-pro/v1/analytics/heatmap/{video_id}
```

Returns position bucket data with view counts and average duration per bucket.

### Playlist Funnel

For playlists, a funnel view shows drop-off between videos:

```
GET /mediashield-pro/v1/analytics/playlist-funnel/{playlist_id}
```

---

## Realtime Dashboard

Monitor currently active viewers across all videos.

### Features

- Live viewer count with 15-second auto-refresh
- Per-session details: user, video, device type, browser, duration, completion
- Active session identification (heartbeat within last 5 minutes)

### REST Endpoint

```
GET /mediashield-pro/v1/realtime/viewers
```

Returns all sessions with `last_heartbeat` within the past 5 minutes.

---

## Suspicious Activity Detection

MediaShield Pro monitors for suspicious viewing patterns and generates alerts.

### Alert Types

| Type | Description | Trigger |
|------|-------------|---------|
| `multi_ip` | User watching from multiple IPs | Same user, different IPs within session window |
| `devtools` | Developer tools opened | Devtools detection event fired |
| `rapid_seek` | Rapid seeking through video | Multiple seek events in short window |
| `concurrent_stream` | Too many simultaneous streams | Exceeds configured limit |
| `vpn_detected` | VPN/proxy detected | IP reputation check |

### Sensitivity Levels

| Level | Option Value | Behavior |
|-------|-------------|----------|
| Low | `low` | Only flag multi_ip with 3+ IPs |
| Medium | `medium` | Flag multi_ip with 2+ IPs, rapid_seek |
| High | `high` | Flag all types aggressively |

Configure via `ms_suspicious_sensitivity` option.

### Managing Alerts

From the **Alerts** admin page:

- **Dismiss** -- Mark an alert as reviewed (dismissed alerts are pruned after 90 days)
- **Safe User** -- Whitelist a user to suppress future alerts (stored in `ms_safe_users` option)

### REST Endpoints

| Method | Route | Description |
|--------|-------|-------------|
| GET | `/analytics/suspicious` | List alerts (paginated) |
| PATCH | `/analytics/suspicious/{id}/dismiss` | Dismiss an alert |
| POST | `/analytics/suspicious/safe-user` | Mark user as safe |

---

## Data Export

### CSV Export

Stream watch data as CSV downloads with date and filter support.

**Available exports:**
- `watch_sessions` -- All session data (excludes `session_token` for security)
- `milestones` -- Milestone completion records
- `users` -- Per-user aggregated stats

**Limits:** 50,000 rows per export.

**Endpoint:**
```
GET /mediashield-pro/v1/export/csv/{type}?from=2026-01-01&to=2026-03-31
```

### PDF Reports

Generate comprehensive analytics PDF reports asynchronously.

**Report contents:**
- Overview stats (total views, unique viewers, avg completion)
- Top 10 videos by views
- Completion rate chart
- User engagement summary
- Activity alerts summary

**Process:**
1. Admin clicks "Generate PDF Report" in the Export page.
2. `POST /mediashield-pro/v1/export/pdf/report` queues an Action Scheduler job.
3. Dompdf generates an A4 PDF.
4. A download URL (24-hour transient) is created.
5. Admin receives an email notification with the download link.

**Check status:**
```
GET /mediashield-pro/v1/export/status/{job_id}
```

---

## Weekly Digest

An automated weekly HTML email summarizing your video analytics.

### Contents

- Total views this week
- Total completions
- Average completion rate
- Top 5 videos by views
- Alert count

### Configuration

| Setting | Option Key | Default |
|---------|-----------|---------|
| Enable Digest | `ms_weekly_digest_enabled` | `true` |
| Recipient Email | `ms_weekly_digest_email` | Site admin email |

The digest is scheduled via Action Scheduler with the `ms_weekly_digest` hook.

---

## Cron Jobs

All Pro analytics cron jobs use Action Scheduler in the `mediashield-pro` group:

| Hook | Frequency | Description |
|------|-----------|-------------|
| `ms_heatmap_aggregation` | Hourly | Aggregate playback events into heatmap buckets |
| `ms_alert_pruning` | Daily | Delete dismissed alerts older than 90 days |
| `ms_email_capture_retention` | Daily | Delete expired email captures |
| `ms_weekly_digest` | Weekly | Send analytics digest email |
