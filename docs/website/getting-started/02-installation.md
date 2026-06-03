# Installation

## Requirements

- WordPress 6.5 or higher
- PHP 8.1 or higher
- A modern browser for the admin (Chrome, Firefox, Safari, Edge)

## Install from WordPress.org

1. Go to **Plugins > Add New** in your WordPress admin.
2. Search for "MediaShield".
3. Click **Install Now**, then **Activate**.

That's it. After activation, MediaShield redirects you to the setup wizard.

## Manual upload

1. Download the `mediashield` ZIP from WordPress.org or your account.
2. Go to **Plugins > Add New > Upload Plugin**.
3. Choose the ZIP file and click **Install Now**.
4. Click **Activate Plugin**.

Alternatively, unzip the file and upload the `mediashield` folder to `/wp-content/plugins/` via FTP, then activate from **Plugins**.

## What happens on activation

When MediaShield activates, it:

- Creates 6 database tables for sessions, milestones, tags, and playlists
- Seeds default settings
- Registers the MediaShield admin menu (7 pages)
- Redirects to the setup wizard on first activation

The 6 tables are created automatically. You don't need to manage them - they're cleaned up on uninstall.

## After installation

After activation, you'll see **MediaShield** in the WordPress admin sidebar with seven sections:

| Page | Purpose |
|------|---------|
| Dashboard | Overview stats and activity chart |
| Videos | Your video library |
| Playlists | Grouped video sequences |
| Tags | Tag dictionary for milestones |
| Students | Per-user watch progress |
| Milestones | Completion percentage settings |
| Settings | All plugin configuration |

The setup wizard launches automatically on first activation. Follow the [Setup Wizard guide](03-setup-wizard.md) to complete initial configuration.

## Upgrading

MediaShield includes an automatic migration system. When you update the plugin, database schema changes are applied on the next page load. No manual steps needed.

## Uninstalling

- **Deactivating** the plugin clears scheduled background jobs but preserves all data and settings.
- **Deleting** the plugin (Plugins > Delete) drops all 6 tables, removes all MediaShield options, and cleans up role capabilities. If MediaShield Pro is still active, Pro data is preserved separately.
