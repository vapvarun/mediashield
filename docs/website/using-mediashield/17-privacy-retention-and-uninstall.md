# Privacy, Retention and Uninstall

**Included in Free.** Pro adds its own tables to the same export and erase tools.

## Why use it

You can answer a data request with WordPress's own tools, decide how long watch history is kept, and remove everything cleanly when you stop using the plugin.

## What MediaShield stores

For each watch session: user ID, video ID, IP address, browser details, device type, start time, last report time, total seconds watched, furthest position and completion percentage. It also stores milestones, tags and playlist items. All of it stays in your own WordPress database. The free plugin sends nothing to outside servers.

## Before you start

- Privacy tools are under **Tools > Export Personal Data** and **Tools > Erase Personal Data**.
- Whether your use meets the law in your country is your decision. MediaShield gives you the tools, not legal advice. Add a line about video watch tracking to your privacy policy.

## Export one person's data

1. Go to **Tools > Export Personal Data**, enter the email and send the request.
2. The export includes a "MediaShield Video Data" group with their sessions, milestones and earned tags. It also reads sessions that were moved to the archive.

## Erase one person's data

1. Go to **Tools > Erase Personal Data** and confirm the request.
2. MediaShield clears the IP address and browser details on their sessions, in the live table and the archive. The session rows remain without that detail so your totals stay correct. Milestone records and earned tags are deleted.

## Keep or move old history

**Settings > Analytics Retention** controls how long sessions stay in reports. 0 keeps everything and is the recommended value. Any other number moves older sessions into an archive each month, and reports stop counting them. See [Settings Overview](../configuration/01-settings-overview.md).

## Background jobs

| Job | When | What it does |
|-----|------|--------------|
| Session cleanup | Hourly | Marks sessions with no report for 10 minutes as finished. |
| Archive old sessions | Monthly, only when retention is above 0 | Moves older sessions to the archive. |
| Restore archived sessions | Once after upgrading to 1.3.0 or later | Moves sessions archived by old versions back into reports. |

## Deactivate and delete

- **Deactivating** stops the background jobs and keeps all your data.
- **Deleting** the plugin from the Plugins page removes the six MediaShield tables, every MediaShield video and playlist, the `upload_mediashield` capability and the plugin options. Video files in `wp-content/uploads/mediashield/` are deleted too.
- If MediaShield Pro is still active when you delete the free plugin, MediaShield keeps the tables, videos, playlists, files and options so Pro keeps working. Only the `upload_mediashield` capability is removed.

Export what you want to keep first. Deleting cannot be undone.

## Next steps

- [FAQ](../faq/01-faq.md)
- [Analytics and Milestones](02-analytics-and-milestones.md)
