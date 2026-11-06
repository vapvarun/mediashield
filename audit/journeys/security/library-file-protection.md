---
journey: library-file-protection
plugin: mediashield
priority: critical
roles: [guest, subscriber, contributor, enrolled-student, administrator]
covers: [card:10482094152, wp-v2-video-meta, wp-v2-media, protected-folder, upgrade-job, owner-notice, restore]
prerequisites:
  - "Site reachable at $SITE_URL, Free active (Learnomy optional for steps 6-7)"
  - "A self-hosted video VIDEO whose Video URL is a Media Library file (FILE_URL, attachment ATT), saved on 1.4.1 or earlier, or saved now"
  - "A second video SHARED whose Media Library file is also embedded in a published post"
  - "A Contributor account"
estimated_runtime_minutes: 10
---

# A paid video's file cannot be downloaded by someone who may not watch it

An owner sells a lesson whose video came from the Media Library. A visitor who
did not buy it must not find or fetch the file; buyers keep watching.

## Setup
- After an update from 1.4.1: `wp option get ms_library_files_pending` returns nothing once the job has run (`wp action-scheduler run --hooks=ms_protect_library_files` speeds it up).

## Steps
1. Logged out: `GET /wp-json/wp/v2/mediashield-videos?_fields=id,meta`. **Expect** no `meta` on any item.
2. As the Contributor (application password): same request, and `/wp/v2/mediashield-videos/VIDEO`. **Expect** `_ms_platform` present, no `_ms_source_url`, `_ms_platform_video_id`, `_ms_stream_url`.
3. Logged out: `GET /wp-json/wp/v2/media?search=<file name>` and `/wp/v2/media/ATT`. **Expect** ATT not listed, single returns 401.
4. Logged out: `curl -r 0-99 FILE_URL`. **Expect** 404. The new path under `uploads/mediashield/` returns 403 on Apache (on nginx, see Site Health for the rule).
5. As an administrator: Media Library shows ATT; Dashboard shows "MediaShield now keeps self-hosted video files in its protected folder" listing VIDEO as moved and SHARED as left with "also used in the content of ...". "Got it" clears it.
6. Learnomy: an enrolled student opens the paid lesson playing VIDEO. **Expect** it plays (stream returns 206). A logged-in non-buyer sees "Enrollment required" and the stream returns 403.
7. Learnomy: a guest opens a free-preview lesson whose video was moved. **Expect** it plays.
8. `wp mediashield repair restore-library-files --video=VIDEO`. **Expect** FILE_URL serves again, ATT is back to its old status, VIDEO plays from it.
9. Delete a video whose file was moved. **Expect** the file is back at its Media Library path, not deleted.

## Pass criteria
1-4 reveal nothing, 5 lists both groups, 6-7 play for the allowed viewers only, 8-9 put the file back.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| Contributor reads `_ms_source_url` | meta bar back to `edit_posts` | `includes/CPT/VideoPostType.php::hide_meta_from_viewers` |
| File still public, state `in_use` | a reference was found; read the notice | `includes/Upload/LibraryFiles.php::used_elsewhere` |
| Buyer gets 404 after the move | claim not written | `includes/Upload/LibraryFiles.php::protect` |
| File gone after deleting a video | restore not run before the driver delete | `includes/Cron/Cleanup.php::handle_video_delete` |
