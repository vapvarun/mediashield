# Background Jobs and WP-CLI

**Included in Free** - Pro runs its own jobs (export, webhooks, digest), documented in the Pro developer guide.

## Scheduled jobs

Jobs are registered on `init`. If Action Scheduler is loaded (it is bundled in `vendor/`), jobs go into its `mediashield` group; otherwise WP-Cron is used, with a `monthly` interval added through `cron_schedules`.

| Hook | When | What it does | Details |
|------|------|--------------|---------|
| `ms_cleanup_inactive_sessions` | Hourly, recurring | Sets `is_active = 0` on sessions with no heartbeat for 10 minutes. | Only flips the flag. Reads that count active sessions use a 5 minute window themselves. |
| `ms_archive_old_sessions` | Monthly, recurring | Moves sessions older than `ms_session_retention_months` to `ms_watch_sessions_archive`. | Does nothing while the option is 0 (the default). |
| `ms_restore_archived_sessions` | One-off, re-queues itself every minute | Moves archived rows back to the live table, 2000 per pass. | Queued by the 1.3.0 upgrade. Batch filter: `mediashield_restore_archive_batch_size` (100-20000). |
| `ms_protect_library_files` | One-off, re-queues itself every minute | Moves the Media Library file of each self-hosted video into `uploads/mediashield/`, makes its Media Library item private, and points the video at its stream URL. A file also used elsewhere (its path in post content, meta or options, plain or JSON-escaped as Elementor stores it; its Media Library id in a block, a featured image, an Elementor media control, an ACF field or the Video widget; a Learnomy lesson or course; or anything an extension reports through `mediashield_library_file_in_use`) is left in place, and so is a file whose check fails. | Queued by the 1.4.2 upgrade while `ms_library_files_pending` is set. Resumable: every checked video gets `_ms_library_file`. Batch filter: `mediashield_library_files_batch_size` (10-200, default 50). New and edited videos are checked when saved, including a moved video pointed at a different file (its old file goes back to the Media Library first). When it finishes, administrators see a notice listing what moved and what was left. |
| `ms_rename_id_posters` | One-off, re-queues itself every minute | Renames each platform video's poster when its file name, title, slug, GUID, caption or alt text contains the YouTube, Vimeo, Wistia or Bunny video ID: the file and every size move to `mediashield-poster-<hash>`, and the text is replaced with the video title. Posters with no ID in them, such as one you uploaded, are left alone. | Queued by the 1.4.2 upgrade while `ms_poster_names_pending` is set (it holds the last video checked, so an interrupted run resumes). Batch filter: `mediashield_poster_rename_batch_size` (10-200, default 50). Until a poster is renamed, a viewer who may not watch is not shown it. |
| `ms_claim_uploaded_files` | One-off, re-queues itself every minute | Writes `_ms_owned_file` for each video that has none, oldest video first. | Queued by the 1.4.0 upgrade while `ms_file_claims_pending` is set. Batch filter: `mediashield_claim_files_batch_size` (50-1000, default 200). |

The bundled EDD licensing SDK also schedules its own weekly licence check, which is not part of MediaShield's jobs.

Two things run on delete rather than on a schedule: `before_delete_post` cascades for videos and playlists (see [Database Tables](04-database-tables.md)), and the GDPR exporter and eraser run when WordPress asks for them.

To run a job by hand with WP-CLI:

```
wp action-scheduler run --group=mediashield
wp cron event run ms_cleanup_inactive_sessions
```

The first form works when Action Scheduler is the scheduler, the second when WP-Cron is.

## WP-CLI commands

Registered only when `WP_CLI` is defined. There are two command groups and four commands.

### wp mediashield repair bunny-urls

*Since 1.3.0.* Finds videos pasted from a Bunny dashboard URL that were saved as self-hosted with no platform video ID, and rewrites them to the Bunny shape (platform `bunny`, bare GUID, library ID, unsigned embed URL).

| Flag | Meaning |
|------|---------|
| (none) or `--dry-run` | Report what would change. This is the default. |
| `--execute` | Write the repairs. |

Bunny collection URLs are reported for manual fixing and never rewritten.

```
wp mediashield repair bunny-urls
wp mediashield repair bunny-urls --execute
```

### wp mediashield repair restore-library-files

*Since 1.4.2.* Moves Media Library files that MediaShield protected back to their old path, restores each Media Library item's old status, and points the video at the public file again. If the old name has been taken since, the file gets the next free name in the same folder and the video and Media Library item follow it; nothing is overwritten. A restored video is not moved again when saved while it still plays the file you moved back; point it at a different file, or upload one on the video, and that file is protected. Deleting a video also puts its moved file back, and so does uninstalling MediaShield.

| Flag | Meaning |
|------|---------|
| `--video=<id>` | Restore one video only. |

```
wp mediashield repair restore-library-files
wp mediashield repair restore-library-files --video=42
```

### wp mediashield scale

A development tool that checks hot-path query budgets against a synthetic dataset. Do not run it on a production site. Synthetic rows use user IDs from 1000000 up so they can be removed cleanly.

| Command | What it does |
|---------|--------------|
| `seed` | Inserts sessions and milestones. Flags: `--users` (default 10000), `--sessions-per-user` (default 10), `--milestones-per-session` (1-4, default 4), `--batch` (default 5000, minimum 100). |
| `benchmark` | Times the hot-path queries and exits with an error if any is over its budget. |
| `teardown` | Deletes every synthetic row and the synthetic video post. |

```
wp mediashield scale seed --users=10000 --sessions-per-user=10
wp mediashield scale benchmark
wp mediashield scale teardown
```
