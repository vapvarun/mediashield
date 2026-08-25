# REST API Reference

## Free plugin - `mediashield/v1`

Base URL: `/wp-json/mediashield/v1/`

Authentication: standard WordPress REST API nonce via the `X-WP-Nonce` header, or cookie-based auth. There is no single capability for the whole namespace - each group states its own below, and they range from "administrator" to "anyone with a valid nonce".

---

### Settings

| Method | Route | Auth | Description |
|--------|-------|------|-------------|
| GET | `/settings` | manage_options | Retrieve all settings. Output filtered via `mediashield_settings_response`. |
| PUT | `/settings` | manage_options | Update settings. Partial updates supported. Unknown keys are ignored. |

PUT behavior: known keys are type-cast to their schema type, then run through that key's validator. A value that fails validation (an invalid hex color, a protection level outside the allowed set, a malformed URL) is skipped and the previously stored value is kept. Numeric ranges are clamped rather than rejected - opacity to 0-1, retention months to 0-120, ad skip delay to 0-60.

---

### Tags

| Method | Route | Auth | Description |
|--------|-------|------|-------------|
| GET | `/tags` | logged in | List tags. Args: `page`, `per_page` (max 100, default 50), `search`. |
| POST | `/tags` | edit_posts | Create a tag. Body: `{ name, description? }`. The slug is derived from the name. |
| GET | `/tags/{id}` | logged in | Get a single tag. |
| PATCH | `/tags/{id}` | edit_posts | Update `name` and/or `description`. Note: PATCH, not PUT. |
| DELETE | `/tags/{id}` | edit_posts | Delete a tag and remove all video-tag associations for it. Returns 204. |
| GET | `/videos/{video_id}/tags` | logged in | Tags assigned to a video. |
| POST | `/videos/{video_id}/tags` | edit_posts | Assign a tag to a video. Body: `{ tag_id }`. |
| DELETE | `/videos/{video_id}/tags/{tag_id}` | edit_posts | Remove a tag from a video. |

---

### Sessions

| Method | Route | Auth | Description |
|--------|-------|------|-------------|
| POST | `/session/start` | logged in, or anonymous when allowed | Start a watch session. Body: `{ video_id }`. Returns the session token, resume position, watermark config, and video details. |
| POST | `/session/heartbeat` | logged in, or a valid session token | Update progress. Body: `{ token, position, duration?, playing?, focused? }`. |
| POST | `/session/end` | logged in, or a valid session token | End session and finalize stats. Body: `{ token }`. |
| POST | `/session/revoke-user` | manage_options | End all active sessions for a user. Body: `{ user_id }`. |

Anonymous access to `/session/start` is allowed when the `ms_require_login` setting is off, or when the video carries an `_ms_access_type` meta value, and can be adjusted with the `mediashield_session_allow_anonymous_start` filter. Reaching the handler is not permission to watch: `AccessControl::can_watch()` still runs and can refuse with a reason.

Heartbeat and end accept a signed token instead of a login, because a guest who was allowed to start a session must be able to finish it. The token is HMAC-signed and validated without a database lookup; anything unsigned or tampered with is rejected.

The parameter is named `token`, not `session_token`. Heartbeat interval is 30 seconds. A session with no heartbeat for 5 minutes stops counting toward the concurrent-stream limit, and an hourly job marks sessions inactive after 10 minutes.

Starting a session beyond the concurrent limit returns HTTP 429 with code `concurrent_limit`.

---

### Stream

| Method | Route | Auth | Description |
|--------|-------|------|-------------|
| GET | `/stream/{video_id}` | `mediashield_can_watch` for the resolved viewer | Streams a self-hosted video file with range support. |

This endpoint serves the file itself; it does not redirect to one. It runs the same access check as the player on every request, including every range request, so access revoked mid-playback is refused on the next seek.

A `<video>` element cannot send an `X-WP-Nonce` header, so the player appends a signed `ms_token` query parameter naming the viewer the URL was minted for. The token is identity, not authorisation - the access check still runs for that viewer. Stream tokens are valid for 6 hours.

---

### Playlists

| Method | Route | Auth | Description |
|--------|-------|------|-------------|
| GET | `/playlists/{playlist_id}/items` | logged in | List items in a playlist in sort order. |
| POST | `/playlists/{playlist_id}/items` | edit_posts | Add a video to a playlist. Body: `{ video_id }`. |
| DELETE | `/playlists/{playlist_id}/items/{item_id}` | edit_posts | Remove a video from a playlist. |
| PUT | `/playlists/{playlist_id}/items/reorder` | edit_posts | Reorder items. Body: `{ order: [ { item_id, sort_order }, ... ] }`. |

Note the reorder route is `PUT` and takes objects, not a flat list of IDs.

Playlist playback options (`_ms_autoplay`, `_ms_countdown`, `_ms_loop`, `_ms_shuffle`) are post meta and are read and written through the WordPress core route for the CPT, `/wp/v2/mediashield-playlists/{id}`.

---

### Upload

| Method | Route | Auth | Description |
|--------|-------|------|-------------|
| POST | `/upload/init` | upload_mediashield | Upload a video file. |
| GET | `/upload/status/{upload_id}` | upload_mediashield | Ask a driver about an upload. Args: `driver` (default `self_hosted`). Returns `{ status, progress, error }`. |

`/upload/init` takes a **multipart form POST** with the file in a field named `file`, plus optional `title`, `driver` (default `self_hosted`), and `video_id`. It is a single request; there is no chunking and no separate initialization step.

Passing `video_id` attaches the file to an existing video instead of creating a new one, which is what the admin uploader does. That path additionally requires `edit_post` on the target video: `upload_mediashield` says you may upload, not that you may overwrite any video on the site by ID.

On success it returns 201 with `{ video_id, platform_video_id, embed_url, status: "complete" }`. Failures return 400 (no file, or a PHP upload error), 404 (unknown `video_id`), 403 (cannot edit that video), or 422 (the driver refused the file).

The `upload_mediashield` capability is separate from `manage_options` so instructors or content managers can upload videos without full admin access. Activation grants it to Administrator only.

---

### Analytics

| Method | Route | Auth | Description |
|--------|-------|------|-------------|
| GET | `/analytics/overview` | manage_options | Dashboard summary. Arg: `period` = `today` \| `7d` (default) \| `30d` \| `90d`. Returns total videos, sessions, avg completion, active viewers, activity chart, top videos, recent milestones, and the site timezone. Daily counts use `CONVERT_TZ()` to respect the WP site timezone. |
| GET | `/videos/{id}/stats` | manage_options | Per-video statistics. |
| GET | `/analytics/milestones` | manage_options | Milestone log. Args: `page`, `per_page` (default 20), `video_id`. Total counts come back in the `X-WP-Total` and `X-WP-TotalPages` headers. |
| GET | `/analytics/users` | manage_options | Viewer list. Args: `page`, `per_page` (default 20), `search`. |
| GET | `/analytics/users/{user_id}` | manage_options | One viewer: their 100 most recent sessions with video title, completion, watch time, furthest position, and timestamps. Milestone history, IP, and device type are not included in this payload. |
| GET | `/analytics/my-videos` | logged in | Current user's watched videos. No admin capability required. |

`period` values other than the four listed fall back to 7 days. "Today" means the last 24 hours, not the calendar day.

---

### Protection

| Method | Route | Auth | Description |
|--------|-------|------|-------------|
| POST | `/protection/devtools-event` | valid `wp_rest` nonce (guests allowed) | Beacon endpoint for client-side devtools detection. Args: `strategy`, `url`, `ua`, `screen`. Writes to the error log and fires `mediashield_devtools_detected`. |

Rate limited to one recorded event per user per hour per IP.

---

### Wizard

| Method | Route | Auth | Description |
|--------|-------|------|-------------|
| POST | `/wizard/complete` | manage_options | Mark the setup wizard as complete. |

---

## Core routes for the custom post types

Videos and playlists are custom post types with `show_in_rest` enabled, so the standard WordPress routes apply and are where you create, update, and delete them:

- `/wp/v2/mediashield-videos` and `/wp/v2/mediashield-videos/{id}`
- `/wp/v2/mediashield-playlists` and `/wp/v2/mediashield-playlists/{id}`

Video and playlist meta (`_ms_platform`, `_ms_source_url`, `_ms_protection_level`, `_ms_duration`, the player overrides, the playlist playback options) is registered with `show_in_rest`, so it round-trips through the `meta` object on those routes. This is how the block editor creates a video from a pasted URL.
