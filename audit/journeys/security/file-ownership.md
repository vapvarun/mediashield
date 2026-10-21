---
journey: file-ownership
plugin: mediashield
priority: critical
roles: [author, other-author, subscriber]
covers: [card:10426248216, stream-route, delete-handler, protected-folder]
prerequisites:
  - "Site reachable at $SITE_URL, Free active"
  - "Two Author accounts: author-a and author-b (neither is an admin)"
  - "author-a has uploaded a self-hosted video through Videos > Add New (capture A_VIDEO and its stored file name from the Platform Video ID field)"
estimated_runtime_minutes: 8
---

# One video's file is not another video's file

A site owner expects that what an Author uploads is theirs. Another Author, or a
careless edit, must not be able to play it past its rules or delete it.

## Setup
- `wp option get ms_file_claims_pending` returns nothing (the upgrade job has finished).
- `wp post meta get A_VIDEO _ms_owned_file` equals the stored file name.

## Steps
1. As author-b create a video, platform Self-hosted, and type A's stored file name into Platform Video ID. Save. Open it as a member. **Expect** the player does not start, and `GET /wp-json/mediashield/v1/stream/{B_VIDEO}` returns 404 `file_not_found`.
2. As author-b clear that field and paste `$SITE_URL/wp-content/uploads/mediashield/<A's file>` as the video address instead. **Expect** the same refusal.
3. Delete B's video permanently. **Expect** A's file is still in `wp-content/uploads/mediashield/`, and A's video still plays for a member.
4. As author-a, upload a second video, play it, delete it. **Expect** its file is removed and A's first video is untouched.
5. Paste a public image-folder video URL (outside `uploads/mediashield/`) into a video. **Expect** it still plays: pasted links to ordinary uploads keep working.

## Pass criteria
Steps 1 and 2 refused, 3 leaves the file and the original video playing, 4 removes only its own file, 5 plays.

## Fail diagnostics
| Symptom | Likely cause | File |
|---|---|---|
| B's video plays A's file | ownership check skipped | `includes/REST/StreamController.php::resolve_file` |
| A's file vanished after B's delete | delete not gated on the claim | `includes/Cron/Cleanup.php::handle_video_delete` |
| New upload refused | claim not written at upload | `includes/Upload/Drivers/SelfHosted.php::upload` |
