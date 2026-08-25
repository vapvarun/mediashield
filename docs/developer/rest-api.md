# REST API Reference

## Free plugin — `mediashield/v1`

Base URL: `/wp-json/mediashield/v1/`

All endpoints require `manage_options` unless noted. Nonce: standard `wp_rest` nonce (via `wp_create_nonce( 'wp_rest' )` or the `X-WP-Nonce` header).

---

### Settings

| Method | Route | Permission | Description |
|--------|-------|-----------|-------------|
| GET | `/settings` | `manage_options` | Retrieve all settings (typed). Output filtered via `mediashield_settings_response`. |
| PUT | `/settings` | `manage_options` | Update settings. Partial updates supported; unknown keys silently ignored. Input filtered via `mediashield_settings_update`. |

**PUT behaviour:**
1. Unknown keys are silently ignored (Pro uses this to handle its own keys).
2. Known keys are type-cast (boolean / integer / float / string).
3. A validator returning `null` (junk hex colour, out-of-enum protection level) causes that key to be skipped -- the previously stored value is kept.

**Related option keys:** see [settings-reference.md](settings-reference.md).

---

### Tags

| Method | Route | Permission | Description |
|--------|-------|-----------|-------------|
| GET | `/tags` | `manage_options` | List all tags from `ms_tags`. |
| POST | `/tags` | `manage_options` | Create a tag. Body: `{ name, slug, description }`. |
| GET | `/tags/{id}` | `manage_options` | Single tag. |
| PUT | `/tags/{id}` | `manage_options` | Update tag. |
| DELETE | `/tags/{id}` | `manage_options` | Delete tag and remove all `ms_video_tags` rows for it. |
| GET | `/videos/{video_id}/tags` | `manage_options` | Tags assigned to a video. |
| POST | `/videos/{video_id}/tags` | `manage_options` | Assign a tag to a video. Body: `{ tag_id }`. |
| DELETE | `/videos/{video_id}/tags/{tag_id}` | `manage_options` | Remove a tag from a video. |

---

### Sessions

| Method | Route | Permission | Description |
|--------|-------|-----------|-------------|
| POST | `/session/start` | logged_in (or anonymous when `_ms_access_type` is set — see `mediashield_session_allow_anonymous_start`) | Start a watch session. Returns HMAC token. Body: `{ video_id }`. |
| POST | `/session/heartbeat` | logged_in | Update position + progress. Body: `{ session_token, position, completion_pct }`. |
| POST | `/session/end` | logged_in | End session, finalize stats. Body: `{ session_token }`. |
| POST | `/session/revoke-user` | `manage_options` | Kill all active sessions for a user. Body: `{ user_id }`. |

Session tokens are HMAC-signed. Heartbeat interval: 30 seconds (configurable via `mediashield_frontend_config` filter). Sessions without a heartbeat for 5 minutes are treated as expired.

---

### Stream

| Method | Route | Permission | Description |
|--------|-------|-----------|-------------|
| GET | `/stream/{video_id}` | `stream_permissions_check` | Streams a self-hosted file with HTTP range support, after `AccessControl::can_watch()`. Accepts an optional signed `ms_token` query arg (since 1.3.0) - a `<video src>` cannot send an `X-WP-Nonce` header, so WordPress ignores the session cookie and every viewer would otherwise arrive as user 0. The token names the viewer; `can_watch()` still runs per request, so revoked access is refused on the next range request. Mint one with `EmbedLink::token( $video_id, $user_id, EmbedLink::STREAM_TTL )`. |

---

### Playlists

| Method | Route | Permission | Description |
|--------|-------|-----------|-------------|
| GET | `/playlists/{playlist_id}/items` | `manage_options` | List items in a playlist (ordered). |
| POST | `/playlists/{playlist_id}/items` | `manage_options` | Add a video to a playlist. Body: `{ video_id }`. |
| DELETE | `/playlists/{playlist_id}/items/{item_id}` | `manage_options` | Remove a video from a playlist. |
| POST | `/playlists/{playlist_id}/items/reorder` | `manage_options` | Reorder items. Body: `{ ordered_ids: [int] }`. |

---

### Upload

| Method | Route | Permission | Description |
|--------|-------|-----------|-------------|
| POST | `/upload/init` | `upload_mediashield` capability | Upload a video file as `multipart/form-data`. Fields: `file` (required), `title`, `driver` (default `self_hosted`), and `video_id` (since 1.3.0). Returns `{ video_id, platform_video_id, embed_url, status }` with 201. Passing `video_id` fills in an **existing** video instead of creating one - the admin uploader needs this, since Add New Video is already editing a post. That path is authorised separately with `edit_post`: the capability says a user may upload, not that they may overwrite any video by id. |
| GET | `/upload/status/{upload_id}` | `upload_mediashield` capability | Check upload progress. Returns `{ status, progress_pct }`. |

Upload status values: `pending` → `uploading` → `processing` → `complete` (or `failed`).

---

### Analytics

| Method | Route | Permission | Description |
|--------|-------|-----------|-------------|
| GET | `/analytics/overview` | `manage_options` | Dashboard summary: total videos, sessions, avg completion, active viewers, activity chart, top videos, recent milestones. Date filter: `?period=7d|30d|90d`. Daily counts use `CONVERT_TZ()` to honour WP site timezone. |
| GET | `/videos/{id}/stats` | `manage_options` | Per-video statistics. |
| GET | `/analytics/milestones` | `manage_options` | Milestone completion data across all videos. |
| GET | `/analytics/users` | `manage_options` | User engagement list. |
| GET | `/analytics/users/{user_id}` | `manage_options` | Single user detail: every video watched, max position, completion %, milestone history. |
| GET | `/analytics/my-videos` | logged_in | Current user's watched videos. No admin cap required. |

---

### Protection

| Method | Route | Permission | Description |
|--------|-------|-----------|-------------|
| POST | `/protection/devtools-event` | `beacon_permission` (low cap — any logged-in user) | Beacon endpoint for client-side devtools / right-click detection events. Fires `mediashield_devtools_detected` action. |

---

### Wizard

| Method | Route | Permission | Description |
|--------|-------|-----------|-------------|
| POST | `/wizard/complete` | `manage_options` | Mark the setup wizard as complete. Sets `ms_wizard_completed` option. |

---

## Pro plugin — `mediashield-pro/v1`

Base URL: `/wp-json/mediashield-pro/v1/`

All Pro endpoints require `manage_options` unless noted.

---

### Platforms

| Method | Route | Permission | Description |
|--------|-------|-----------|-------------|
| GET | `/platforms` | `manage_options` | List all platform connections (credentials redacted). |
| POST | `/platforms` | `manage_options` | Create a new connection. Body: `{ platform, api_key, ... }`. Credentials encrypted with AES-256-CBC before storage in `ms_platform_connections`. |
| DELETE | `/platforms/{id}` | `manage_options` | Disconnect a platform. |

---

### DRM

| Method | Route | Permission | Description |
|--------|-------|-----------|-------------|
| POST | `/drm/license` | logged_in | Issue a streaming ClearKey license. Validates via `mediashield_can_watch` before returning the key. |
| POST | `/drm/offline` | logged_in | Issue a persistent (offline PWA) license (default duration: 30 days). |
| POST | `/drm/revoke` | `manage_options` | Revoke all licenses for a user+video. Body: `{ user_id, video_id }`. Sets `revoked_at` timestamp. |

License keys are stored in `ms_drm_keys` (encrypted at rest). See [drm-internals.md](drm-internals.md) for key generation and packaging details.

---

### Analytics (Pro)

| Method | Route | Permission | Description |
|--------|-------|-----------|-------------|
| GET | `/analytics/heatmap/{video_id}` | `manage_options` | Position bucket data with view counts and average duration per 10-second bucket. Served from `ms_heatmap_cache`. |
| GET | `/analytics/playlist-funnel/{playlist_id}` | `manage_options` | Drop-off between videos in a playlist. |
| GET | `/realtime/viewers` | `manage_options` | All sessions with `last_heartbeat` within the past 5 minutes. |
| GET | `/analytics/suspicious` | `manage_options` | List activity alerts (paginated). |
| PATCH | `/analytics/suspicious/{id}/dismiss` | `manage_options` | Mark an alert as reviewed. |
| POST | `/analytics/suspicious/safe-user` | `manage_options` | Mark a user as safe to suppress future alerts. |

---

### Export

| Method | Route | Permission | Description |
|--------|-------|-----------|-------------|
| GET | `/export/csv/{type}` | `manage_options` | Stream CSV export. `{type}`: `watch_sessions`, `milestones`, `users`. Params: `?from=YYYY-MM-DD&to=YYYY-MM-DD`. No cap on watch-session and milestone exports since 1.3.0 - they page through and export in full. The user export keeps a 200,000-row ceiling and writes a notice into the CSV if it is reached. |
| POST | `/export/pdf/report` | `manage_options` | Queue an async PDF report via Action Scheduler. Returns `{ job_id }`. |
| GET | `/export/status/{job_id}` | `manage_options` | Check PDF generation status. |
