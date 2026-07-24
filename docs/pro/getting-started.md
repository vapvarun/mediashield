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

1. Navigate to **MediaShield > Settings** and open the **License** tab.
2. Enter your license key (found in your purchase confirmation email).
3. Click **Activate License**.

A valid license is required for automatic updates and support. The license status is shown in the License tab.

For developers: if you need to bypass the license check in a local development environment, see [`docs/developer/hooks-filters-pro.md`](../developer/hooks-filters-pro.md#mediashield_pro_license_valid).

## What Pro Adds

MediaShield Pro extends the free plugin through WordPress hooks -- it never replaces free behavior. Here's what activating Pro unlocks:

### Admin pages (6 new sections)

| Page | Description |
|------|-------------|
| Platforms | Connect and manage video platform API credentials |
| Alerts | View and manage suspicious activity alerts |
| Heatmap | Per-video playback heatmap analytics |
| Realtime | Live active viewer monitoring |
| DRM | DRM configuration and license management |
| Export | CSV and PDF data export |

### New features

- **Advanced Watermark** -- 7 configurable text fields (username, email, IP, user ID, timestamp, site name, custom text)
- **Platform Connections** -- Browse and import videos from Bunny, YouTube, Vimeo, Wistia
- **DRM Encryption** -- ClearKey DRM via Bunny Stream or local packager
- **Heatmap Analytics** -- Per-video playback heatmaps with position buckets
- **Realtime Dashboard** -- Live viewer count with auto-refresh
- **Suspicious Activity** -- Multi-device, developer-tools, rapid seek detection with alerts
- **Milestone Actions** -- Tag user, send email, fire webhook at milestones
- **Data Export** -- CSV and PDF reports
- **Weekly Digest** -- Automated analytics summary email
- **Role-Based Access** -- Per-video role restriction
- **Frontend Upload** -- Upload shortcode for authorized users

## Database

Pro stores its data in 8 additional tables (you don't need to manage these -- they're created automatically when Pro activates and cleaned up if you uninstall):

| Table | What it stores |
|-------|---------------|
| Playback events | Granular viewer playback events (for heatmaps) |
| Platform connections | Your encrypted API credentials |
| Upload queue | Upload job progress tracking |
| Activity alerts | Suspicious viewing pattern flags |
| DRM licenses | Issued content licenses |
| Heatmap cache | Aggregated heatmap display data |
| DRM keys | Encrypted content keys |

## Deactivation vs Deletion

- **Deactivation** clears Pro background jobs but preserves all data. Free plugin features continue working normally.
- **Deletion** drops all Pro tables and removes all Pro settings.

## Next Steps

- [Connect a video platform](platform-connections.md)
- [Set up DRM](drm-setup.md)
- [Explore analytics](analytics.md)
