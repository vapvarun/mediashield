# MediaShield Pro -- Analytics

MediaShield Pro extends the free plugin's basic analytics with heatmaps, realtime monitoring, suspicious activity detection, and data export.

---

## Playback Heatmaps

Heatmaps show where in a video viewers are watching, rewatching, or dropping off. MediaShield watches what your viewers do -- every play, pause, seek, and buffer -- and shows it back to you as a position-by-position bar chart so you can see exactly which moments hold attention and which ones lose it.

### Reading the Heatmap

- **High bars** indicate frequently watched segments (hot spots). These are your most valuable moments.
- **Retention line** shows the percentage of viewers who reached that point in the video.
- **Steep drops** indicate where viewers lose interest and stop watching.
- **Spikes after drops** indicate sections viewers seek back to (e.g., key content, Q&A, demos).

Use this to cut weak openings, shorten slow sections, and move the most-rewatched content earlier.

### Device Breakdown

The heatmap page also shows a device type distribution chart:
- Desktop, mobile, tablet breakdown with percentages.
- Useful for optimizing video format and player layout (e.g., if 70% of your audience is on mobile, keep your talking-head videos portrait-friendly).

---

## Realtime Dashboard

Monitor who's watching right now across all your videos.

### Features

- Live viewer count with 15-second auto-refresh.
- Per-session details: user, video, device type, browser, how long they've been watching, completion percentage.
- Sessions are "active" if a heartbeat was received in the last 5 minutes.

---

## Suspicious Activity Detection

MediaShield Pro monitors for viewing patterns that suggest credential sharing, scraping, or suspicious behavior, and generates alerts you can review from the **Alerts** page.

### Alert Types

| Type | What it means | What triggers it |
|------|-------------|-----------------|
| Multi-device | One account watching from multiple locations | Same user, different IPs within the session window |
| Developer tools | Viewer opened browser developer tools | Detection event received |
| Rapid seeking | Viewer scrubbing rapidly through the video | Multiple seek events in a short window |
| Concurrent stream limit | Too many simultaneous streams | Exceeds your configured maximum |
| VPN / proxy detected | Viewer may be masking their location | IP reputation check |

### Sensitivity Levels

Control how aggressively MediaShield flags activity in **MediaShield > Settings > Suspicious Activity**:

| Level | Behavior |
|-------|---------|
| Low | Only flags multi-device with 3 or more different IPs |
| Medium | Flags multi-device with 2 or more IPs, plus rapid seeking |
| High | Flags all types aggressively |

### Managing Alerts

From the **Alerts** admin page:

- **Dismiss** -- Mark an alert as reviewed. Dismissed alerts are removed after 90 days.
- **Safe User** -- Whitelist a user to suppress future alerts (e.g., an admin doing testing).

---

## Data Export

### CSV Export

Download your watch data as spreadsheets with date range filters.

**Available exports:**
- **Watch sessions** -- all session data.
- **Milestones** -- every completion record.
- **Users** -- per-user aggregated stats.

**Limits:** No cap on watch-session and milestone exports since 1.3.0 - they page through and export in full. The user export keeps a 200,000-row ceiling and writes a notice into the CSV if it is reached.

Go to **MediaShield > Export**, choose the export type, set a date range, and click Download.

### PDF Reports

Generate a comprehensive analytics report as a PDF.

**Report contents:**
- Overview stats (total views, unique viewers, avg completion).
- Top 10 videos by views.
- Completion rate chart.
- User engagement summary.
- Activity alerts summary.

To generate:

1. Go to **MediaShield > Export**.
2. Click **Generate PDF Report**.
3. MediaShield queues the report in the background.
4. You'll receive an email with a download link when it's ready (usually within a few minutes). The link is valid for 24 hours.

---

## Weekly Digest

An automated weekly email summarising your video analytics.

### What's in the digest

- Total views this week.
- Total completions.
- Average completion rate.
- Top 5 videos by views.
- Number of unresolved alerts.

### Configuration

Go to **MediaShield > Settings > Weekly Digest**:

- **Enable Digest** -- On by default. Turn off if you don't want the weekly email.
- **Recipient Email** -- Defaults to the site admin email. Enter any address you prefer.

---

For developers: REST endpoints, cron job hooks, and the database tables behind analytics are documented in [`docs/developer/`](../developer/README.md).
