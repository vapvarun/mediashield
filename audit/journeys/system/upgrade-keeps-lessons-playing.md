---
journey: upgrade-keeps-lessons-playing
plugin: mediashield
priority: critical
roles: [admin, subscriber]
covers: [card:10426248216, migrator, background-job, upgrade]
prerequisites:
  - "A site on MediaShield 1.3.0 with at least 3 self-hosted videos, two of them naming the same stored file"
  - "Action Scheduler available (WooCommerce or the bundled copy), and a second run with WP-Cron only"
estimated_runtime_minutes: 10
---

# Updating never breaks a lesson a member is watching

The site owner clicks Update on a live site. Nothing they have built may stop
working, even for a minute, and nothing may need their attention afterwards.

## Steps
1. As a member, start a self-hosted lesson and leave it playing. Note the video ids.
2. As admin, update to 1.4.0 over the 1.3.0 install.
3. **Immediately** (before the job runs) reload the lesson and play every self-hosted video. **Expect** all play. `wp option get ms_db_version` is 6 and `ms_file_claims_pending` is the newest video id.
4. Open Tools > Scheduled Actions (or `wp cron event list`). **Expect** `ms_claim_uploaded_files` queued, then Complete within a few minutes, with no admin notice or action needed.
5. After it completes: `ms_file_claims_pending` is gone, each self-hosted video has `_ms_owned_file`, YouTube, Vimeo and Bunny videos have an empty one. The older of the two videos that shared a file keeps playing, the other is refused.
6. Create a video after the update that names another video's file. **Expect** it is refused even before the job ran (no grace for new videos).
7. Edit the file name on an old video while the job is still queued. **Expect** that video forfeits its claim and stops playing the other file.
8. Seed 2000 self-hosted videos (`wp eval` loop) and run the job. **Expect** batches of 200, each under a few seconds, no timeout, and an interrupted run resumes on the next tick.

## Pass criteria
No video stops playing at any point before step 5 except as steps 6 to 7 describe; the job finishes unattended; the flag clears.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Lessons 404 right after update | grace not applied | `includes/REST/StreamController.php::resolve_file` |
| Flag never clears | job not scheduled or erroring | `includes/Core/Migrator.php::claim_uploaded_files`, `includes/Cron/Cleanup.php::claim_uploaded_files` |
| Job times out | batch too large | filter `mediashield_claim_files_batch_size` |
