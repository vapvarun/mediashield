# Installation

## Requirements

- WordPress 6.5 or higher
- PHP 8.1 or higher
- A modern browser for the admin (Chrome, Firefox, Safari, Edge)

## Before you start

- You need administrator access to the site.
- Download the `mediashield` ZIP from the MediaShield download page on wbcomdesigns.com (the free plugin is not listed in the WordPress.org directory).

## Install the plugin

1. Go to **Plugins > Add New > Upload Plugin**.
2. Choose the ZIP file and click **Install Now**.
3. Click **Activate Plugin**.

After activation, MediaShield redirects you to the setup wizard.

Alternatively, unzip the file and upload the `mediashield` folder to `/wp-content/plugins/` via FTP, then activate from **Plugins**.

## What happens on activation

When MediaShield activates, it:

- Creates 6 database tables for sessions, milestones, tags, and playlists
- Seeds default settings
- Adds a single top-level **MediaShield** menu to the admin sidebar
- Gives the Administrator role the `upload_mediashield` capability
- Redirects to the setup wizard on first activation

The 6 tables are created automatically. You don't need to manage them - they're removed when you delete the plugin.

## After installation

MediaShield adds one item to the WordPress admin sidebar. Opening it loads a single-page admin app whose own sidebar has seven sections:

| Section | Purpose |
|---------|---------|
| Dashboard | Overview stats and activity chart |
| Videos | Your video library |
| Playlists | Grouped video sequences |
| Viewers | Per-user watch progress |
| Tags | Tag dictionary for organising videos |
| Milestones | Log of milestones viewers have reached |
| Settings | All plugin configuration |

Videos and playlists are WordPress custom post types, but they have no menu items of their own. You reach their edit screens from the Videos and Playlists sections.

The setup wizard launches automatically on first activation. Follow the [Setup Wizard guide](03-setup-wizard.md) to complete initial configuration.

### Check Site Health if you host video files yourself

If you plan to upload video files to your own server, open **Tools > Site Health** after installing. MediaShield adds a check that requests one of your own stored video files over HTTP and reports what the server actually did. On Apache the bundled `.htaccess` rule blocks that request; on nginx it does not, and the check gives you the rule to add. Videos hosted on YouTube, Vimeo, Wistia, or Bunny are unaffected.

## Upgrading

MediaShield includes an automatic migration system. When you update the plugin, database schema changes are applied on the next page load. No manual steps needed.

Upgrading to 1.3.0 also queues a one-off background job that moves previously archived watch sessions back into the live table, so history that earlier versions archived out of reach reappears in your reports. It runs in batches and reschedules itself until finished.

## Uninstalling

- **Deactivating** the plugin unschedules its background jobs but preserves all data, settings, videos, and playlists.
- **Deleting** the plugin (Plugins > Delete) is destructive. It drops all 6 tables, **permanently deletes every MediaShield video and playlist post and the uploaded video files**, removes the `upload_mediashield` capability from all roles, and clears MediaShield's options. If MediaShield Pro is still active, tables, posts, files and options are kept so Pro keeps working, and only the capability is removed. Export anything you want to keep before deleting. More in [Privacy, Retention and Uninstall](../using-mediashield/17-privacy-retention-and-uninstall.md).
