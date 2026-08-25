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

On first activation, MediaShield redirects you to a setup wizard with four steps, in this order:

### Step 1: General Settings

- **Enable video protection** -- Turn MediaShield on/off globally.
- **Require login to watch** -- Whether logged-out visitors can watch videos.
- **Default protection level** -- The wizard offers the two levels most people want here: **Standard** (watermark + tracking) and **None**. Basic and Strict are available afterwards on the Settings page and per video.

### Step 2: Platform

- An overview of the platforms MediaShield works with (YouTube, Vimeo, Bunny Stream, Wistia).
- **This step is informational only.** There is nothing to select and nothing is saved. Self-hosted files need no setup here, and API connections to Bunny / YouTube / Vimeo / Wistia require Pro.

### Step 3: First Video

- Optionally create your first protected video by pasting a URL. The wizard detects the platform automatically.
- This step accepts a URL only. To upload a video file, use **Videos > Add New** after setup.

### Step 4: Watermark Configuration

- **Opacity** -- How visible the watermark overlay is, from 0.1 to 1.
- **Color** -- Watermark text color.
- **Position swap interval** -- How often the watermark position changes, from 5 to 120 seconds.

### If you don't finish the wizard

Each step saves when you press **Save & Continue**; **Skip this step** moves on without saving that step. Anything you already saved is kept.

The wizard has no menu item of its own and opens only once, on first activation. To return to it, go to `wp-admin/admin.php?page=mediashield-wizard` directly. Nothing is lost if you never do -- every setting the wizard covers is also on the **MediaShield > Settings** page.

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

- **Deactivation** clears scheduled background jobs but preserves all data. Deactivate freely -- nothing is destroyed.
- **Deletion** (via Plugins > Delete) is destructive and cannot be undone. It drops all 6 tables, **permanently deletes every MediaShield video and playlist entry**, removes MediaShield's settings, and takes the MediaShield upload capability back off your roles.

Two details worth knowing before you press Delete:

- **Having Pro installed does not protect your library.** The tables, videos, and playlists go regardless. What the Pro check spares is MediaShield's saved options and temporary data, so that deleting free while Pro is still active doesn't wipe Pro's configuration out from under it.
- **Uploaded video files are left behind.** Self-hosted videos live as ordinary files in `wp-content/uploads/mediashield/`, and deleting the plugin does not remove them. If you want the disk space back, delete that folder yourself afterwards. (Deleting a single video from inside the plugin *does* delete its file -- this only applies to deleting the whole plugin.)

Back up your database and that uploads folder before deleting if there is any chance you'll want the library again.

## Upgrading

MediaShield includes a built-in migration system. When you update the plugin, database schema changes are applied automatically on the next page load. No manual action is needed.
