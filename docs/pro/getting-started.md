# MediaShield Pro -- Getting Started

## Requirements

- WordPress 6.5 or higher
- PHP 8.1 or higher
- **MediaShield free plugin** installed and activated

## Installation

1. Download the `mediashield-pro` ZIP from your [Wbcom Designs account](https://wbcomdesigns.com/my-account/).
2. Go to **Plugins > Add New > Upload Plugin** in your WordPress admin.
3. Upload the ZIP file and click **Install Now**.
4. Activate **MediaShield Pro**.

If the free plugin is not active, you will see an admin notice prompting you to install it first.

## License Activation

1. Navigate to **MediaShield > Settings** (or the license tab if shown).
2. Enter your license key (found in your purchase confirmation email).
3. Click **Activate License**.

A valid license is required for automatic updates and support. The license status is stored in the `mediashield-pro_license` option.

**Developer override:** You can bypass the license check with:

```php
add_filter( 'mediashield_pro_license_valid', '__return_true' );
```

## What Pro Adds

MediaShield Pro extends the free plugin through WordPress hooks -- it never replaces core behavior. Here's what activating Pro unlocks:

### Admin SPA Pages (6 new routes)

| Page | Description |
|------|-------------|
| Platforms | Connect and manage video platform API credentials |
| Alerts | View and manage suspicious activity alerts |
| Heatmap | Per-video playback heatmap analytics |
| Realtime | Live active viewer monitoring |
| DRM | DRM configuration and license management |
| Export | CSV and PDF data export |

### New Features

- **Advanced Watermark** -- 7 configurable text fields (username, email, IP, user ID, timestamp, site name, custom text)
- **Platform Connections** -- Browse and import videos from Bunny, YouTube, Vimeo, Wistia
- **DRM Encryption** -- Widevine ClearKey via Bunny Stream or local Shaka Packager
- **Email Gate** -- Capture emails before video access with webhook integration
- **Heatmap Analytics** -- Per-video playback heatmaps with position buckets
- **Realtime Dashboard** -- Live viewer count with auto-refresh
- **Suspicious Activity** -- Multi-IP, devtools, rapid seek detection with alerts
- **Milestone Actions** -- Tag user, send email, fire webhook at milestones
- **Data Export** -- CSV streaming and async PDF reports
- **Weekly Digest** -- Automated analytics summary email
- **Role-Based Access** -- Per-video role restriction
- **Frontend Upload** -- `[mediashield_upload]` shortcode

## Database

Pro creates 8 additional database tables on activation:

| Table | Purpose |
|-------|---------|
| `ms_playback_events` | Granular playback event log |
| `ms_platform_connections` | Encrypted API credentials |
| `ms_upload_queue` | Upload job tracking |
| `ms_activity_alerts` | Suspicious activity alerts |
| `ms_drm_licenses` | DRM license records |
| `ms_heatmap_cache` | Aggregated heatmap data |
| `ms_drm_keys` | Encrypted content keys |
| `ms_email_captures` | Email gate submissions |

## Deactivation vs Deletion

- **Deactivation** clears Pro cron jobs but preserves all data. Free plugin features continue working normally.
- **Deletion** drops all 8 Pro tables and removes 17 Pro-specific options.

## Next Steps

- [Connect a video platform](platform-connections.md)
- [Set up DRM](drm-setup.md)
- [Configure the email gate](email-gate.md)
- [Explore analytics](analytics.md)
