# Every Admin Screen

**Free + Pro.** Free gives you the seven screens in the first table. Pro adds more entries to the same sidebar; each is listed in the second table.

Everything lives under one **MediaShield** menu in the WordPress sidebar. It opens a single app with its own left-hand menu. The address changes after a `#`, so you can bookmark any screen, for example `wp-admin/admin.php?page=mediashield#/settings`.

## Before you start

- Only administrators can open these screens.
- Without JavaScript the app shows a message asking you to turn it on.

## Screens in Free

| Screen | Address ending | What you do there |
|--------|----------------|-------------------|
| Dashboard | `#/dashboard` | See total videos, sessions, average completion and live viewers, an activity chart, Top Videos and Recent Milestones. Pick Today, 7, 30 or 90 days. A first-time welcome panel walks through adding a video. |
| Videos | `#/videos` | Search by title, page through the library, see platform, duration and protection level, preview a video, copy its shortcode, edit it, or add a new one. |
| Playlists | `#/playlists` | List playlists with badges for Autoplay, Loop and Shuffle. Create one, open **Manage items** to add, reorder and set playback options, view or edit it. |
| Viewers | `#/viewers` | Search by name or email. See videos watched, average completion and last active. Click a viewer for their watch history. The old address `#/students` still works. |
| Tags | `#/tags` | Create and delete tags. Each row shows name, slug and how many videos use it. |
| Milestones | `#/milestones` | A log of every milestone reached: viewer, video, percentage and time, newest first, 20 per page. |
| Settings | `#/settings` | All plugin options. See [Settings Overview](01-settings-overview.md). |

## Screens added by Pro

| Screen | Address ending | Purpose |
|--------|----------------|---------|
| Platforms | `#/platforms` | Connect Bunny, YouTube, Vimeo and Wistia accounts. |
| Bunny, YouTube, Vimeo, Wistia browsers | `#/bunny-browser` and so on | Browse a connected library and import videos. |
| Alerts | `#/alerts` | Review suspicious viewing flagged by detection. |
| Heatmap | `#/heatmap` | See where in a video viewers watch and drop off. |
| Realtime | `#/realtime` | See who is watching now. |
| DRM | `#/drm` | Encrypted player settings (experimental). |
| Export | `#/export` | Download CSV or PDF reports. |
| Upload Queue | `#/upload-queue` | Follow uploads to connected platforms. |
| Milestone Actions | `#/milestone-config` | Run an action when a viewer reaches a milestone. |
| License | `#/license` | Enter and check your Pro licence key. |

## Other places MediaShield appears

| Where | What |
|-------|------|
| Video edit screen | See [The Video Edit Screen](06-video-edit-screen.md). |
| Playlist edit screen | A Playlist Items panel with Playback options. |
| Setup wizard | `wp-admin/admin.php?page=mediashield-wizard`. See [Setup Wizard](../getting-started/03-setup-wizard.md). |
| Tools > Site Health | A check that tells you whether self-hosted video files can be downloaded directly. |
| Settings > Privacy | MediaShield adds itself to WordPress's personal data export and erase tools. See [Privacy, Retention and Uninstall](../using-mediashield/17-privacy-retention-and-uninstall.md). |
| Plugins page | A one-off "Unlock the full power of MediaShield" notice appears on the MediaShield screen a week after install, until dismissed. Without Pro only. |

## Next steps

- [Settings Overview](01-settings-overview.md)
- [Analytics and Milestones](../using-mediashield/02-analytics-and-milestones.md)
