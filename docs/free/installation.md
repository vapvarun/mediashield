# MediaShield -- Installation & Setup

## Requirements

- WordPress 6.5 or higher
- PHP 8.1 or higher
- A modern browser (Chrome, Firefox, Safari, Edge)

## Installation

### From WordPress Admin

1. Go to **Plugins > Add New** in your WordPress admin.
2. Search for "MediaShield".
3. Click **Install Now**, then **Activate**.

### Manual Upload

1. Download the `mediashield` plugin ZIP file.
2. Upload it to `/wp-content/plugins/` and extract.
3. Go to **Plugins** in your WordPress admin and activate **MediaShield**.

## First-Time Setup

On first activation, MediaShield redirects you to a setup wizard with four steps:

### Step 1: General Settings

- **Enable Protection** -- Turn MediaShield on/off globally.
- **Default Protection Level** -- Choose the baseline protection for all videos (None, Basic, Standard, Strict).
- **Require Login** -- Whether non-logged-in users can watch videos.

### Step 2: Platform Selection

- Choose which video platforms you use (Self-hosted, YouTube, Vimeo, Bunny Stream, Wistia).
- Platform connections are configured later in Settings (Pro required for Bunny/YouTube/Vimeo/Wistia API connections).

### Step 3: Watermark Configuration

- **Opacity** -- How visible the watermark overlay is (0-100%).
- **Color** -- Watermark text color.
- **Swap Interval** -- How often the watermark position changes (in seconds).

### Step 4: First Video

- Optionally create your first protected video by pasting a URL or uploading a file.
- The wizard detects the platform automatically from the URL.

## After Setup

- Navigate to **MediaShield > Dashboard** to see your analytics overview.
- Go to **MediaShield > Videos** to manage your protected videos.
- Visit **MediaShield > Settings** to fine-tune all options.

## Database Tables

MediaShield creates 6 database tables on activation (you don't need to manage these -- they're created automatically and cleaned up if you uninstall):

| Table | Purpose |
|-------|---------|
| Video tags | Tag taxonomy for milestone and manual tags |
| Video-to-tag relationships | Links tags to videos |
| Watch sessions | Active session tracking |
| Session archive | Completed session history |
| Milestone records | Completion percentage tracking |
| Playlist items | Playlist video ordering |

For the full schema including column names and indexes, see the [developer database reference](../developer/database-tables.md).

## Uninstalling

- **Deactivation** clears scheduled background jobs but preserves all data.
- **Deletion** (via Plugins > Delete) drops all 6 tables, removes all MediaShield settings, and cleans up role capabilities. If MediaShield Pro is still active, Pro data is preserved.

## Upgrading

MediaShield includes a built-in migration system. When you update the plugin, database schema changes are applied automatically on the next page load. No manual action is needed.
