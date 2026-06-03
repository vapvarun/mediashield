# REST API Reference

## Free plugin - `mediashield/v1`

Base URL: `/wp-json/mediashield/v1/`

Authentication: standard WordPress REST API nonce via `X-WP-Nonce` header or cookie-based auth. All endpoints require `manage_options` unless noted.

---

### Settings

| Method | Route | Auth | Description |
|--------|-------|------|-------------|
| GET | `/settings` | manage_options | Retrieve all settings. Output filtered via `mediashield_settings_response`. |
| PUT | `/settings` | manage_options | Update settings. Partial updates supported. Unknown keys are ignored. |

PUT behavior: known keys are type-cast to their schema type. A value that fails validation (such as an invalid hex color or out-of-range protection level) causes that key to be skipped - the previously stored value is kept.

---

### Tags

| Method | Route | Auth | Description |
|--------|-------|------|-------------|
| GET | `/tags` | manage_options | List all tags. |
| POST | `/tags` | manage_options | Create a tag. Body: `{ name, slug, description }`. |
| GET | `/tags/{id}` | manage_options | Get a single tag. |
| PUT | `/tags/{id}` | manage_options | Update a tag. |
| DELETE | `/tags/{id}` | manage_options | Delete a tag and remove all video-tag associations for it. |
| GET | `/videos/{video_id}/tags` | manage_options | Tags assigned to a video. |
| POST | `/videos/{video_id}/tags` | manage_options | Assign a tag to a video. Body: `{ tag_id }`. |
| DELETE | `/videos/{video_id}/tags/{tag_id}` | manage_options | Remove a tag from a video. |

---

### Sessions

| Method | Route | Auth | Description |
|--------|-------|------|-------------|
| POST | `/session/start` | logged_in (or anonymous when `_ms_access_type` is set - see `mediashield_session_allow_anonymous_start`) | Start a watch session. Body: `{ video_id }`. Returns HMAC session token. |
| POST | `/session/heartbeat` | logged_in | Update position and progress. Body: `{ session_token, position, completion_pct }`. |
| POST | `/session/end` | logged_in | End session and finalize stats. Body: `{ session_token }`. |
| POST | `/session/revoke-user` | manage_options | Kill all active sessions for a user. Body: `{ user_id }`. |

Session tokens are HMAC-signed. Heartbeat interval: 30 seconds. Sessions without a heartbeat for 5 minutes are treated as expired.

---

### Stream

| Method | Route | Auth | Description |
|--------|-------|------|-------------|
| GET | `/stream/{video_id}` | valid active session | Authenticated streaming handoff for self-hosted videos. Returns a signed URL or proxies the file. |

---

### Playlists

| Method | Route | Auth | Description |
|--------|-------|------|-------------|
| GET | `/playlists/{playlist_id}/items` | manage_options | List items in a playlist in sort order. |
| POST | `/playlists/{playlist_id}/items` | manage_options | Add a video to a playlist. Body: `{ video_id }`. |
| DELETE | `/playlists/{playlist_id}/items/{item_id}` | manage_options | Remove a video from a playlist. |
| POST | `/playlists/{playlist_id}/items/reorder` | manage_options | Reorder items. Body: `{ ordered_ids: [int] }`. |

---

### Upload

| Method | Route | Auth | Description |
|--------|-------|------|-------------|
| POST | `/upload/init` | upload_mediashield capability | Initialize an upload (chunked supported). Body: `{ filename, file_size, platform? }`. Returns `upload_id`. |
| GET | `/upload/status/{upload_id}` | upload_mediashield capability | Check upload progress. Returns `{ status, progress_pct }`. |

Upload status values: `pending` - `uploading` - `processing` - `complete` (or `failed`).

The `upload_mediashield` capability is separate from `manage_options` to allow instructors or content managers to upload videos without full admin access.

---

### Analytics

| Method | Route | Auth | Description |
|--------|-------|------|-------------|
| GET | `/analytics/overview` | manage_options | Dashboard summary. Date filter: `?period=7d\|30d\|90d`. Includes total videos, sessions, avg completion, active viewers, activity chart, top videos, recent milestones. Daily counts use `CONVERT_TZ()` to respect the WP site timezone. |
| GET | `/videos/{id}/stats` | manage_options | Per-video statistics. |
| GET | `/analytics/milestones` | manage_options | Milestone completion data across all videos. |
| GET | `/analytics/users` | manage_options | User engagement list. |
| GET | `/analytics/users/{user_id}` | manage_options | Single user: every video watched, max position, completion percentage, milestone history. |
| GET | `/analytics/my-videos` | logged_in | Current user's watched videos. No admin capability required. |

---

### Protection

| Method | Route | Auth | Description |
|--------|-------|------|-------------|
| POST | `/protection/devtools-event` | logged_in (beacon_permission) | Beacon endpoint for client-side devtools detection events. Fires `mediashield_devtools_detected` action. |

---

### Wizard

| Method | Route | Auth | Description |
|--------|-------|------|-------------|
| POST | `/wizard/complete` | manage_options | Mark the setup wizard as complete. |
