# REST API Reference

## Free plugin - `mediashield/v1`

Base URL: `/wp-json/mediashield/v1/`

Permissions vary per controller and are listed per route below - do not assume `manage_options`. Tags and playlists read at `is_user_logged_in()` and write at `edit_posts`; uploads use the custom `upload_mediashield` capability; analytics, settings, the wizard and session revocation use `manage_options`.

Nonce: standard `wp_rest` nonce (via `wp_create_nonce( 'wp_rest' )` or the `X-WP-Nonce` header). Two routes accept the credential in the query string instead, because the caller cannot set a header: `/stream/{id}` takes a signed `ms_token`, and Pro's `/export/csv/{type}` takes `_wpnonce`.

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
| GET | `/tags` | logged_in | List tags from `ms_tags`. Paginated: `per_page` (default 50, 1-100), `page` (default 1), `search` (default `''`). |
| POST | `/tags` | `edit_posts` | Create a tag. Body: `{ name (required), description }`. The slug is derived server-side; there is no `slug` param. |
| GET | `/tags/{id}` | logged_in | Single tag. |
| PATCH | `/tags/{id}` | `edit_posts` | Update tag. Body: `{ name, description }`. The route registers `PATCH`, not `PUT` - a `PUT` returns 404. |
| DELETE | `/tags/{id}` | `edit_posts` | Delete tag via `TagManager::delete()`, which also removes its `ms_video_tags` rows. Returns 204 with no body. |
| GET | `/videos/{video_id}/tags` | logged_in | Tags assigned to a video. |
| POST | `/videos/{video_id}/tags` | `edit_posts` | Assign a tag to a video. Body: `{ tag_id }`. |
| DELETE | `/videos/{video_id}/tags/{tag_id}` | `edit_posts` | Remove a tag from a video. |

Read permission is `is_user_logged_in()`; every write is `current_user_can( 'edit_posts' )`.

---

### Sessions

| Method | Route | Permission | Description |
|--------|-------|-----------|-------------|
| POST | `/session/start` | logged_in, or anonymous when `ms_require_login` is off or `_ms_access_type` is set - see `mediashield_session_allow_anonymous_start` | Start a watch session. Body: `{ video_id }` (required). Returns `{ session_token, resume_position, is_resumed, watermark_config, video: { id, title, platform, protection_level, duration, source_url } }`. `video.source_url` is populated only for DRM videos. 429 `concurrent_limit` when the stream limit is hit; 403 with the denial reason promoted to the error code otherwise. |
| POST | `/session/heartbeat` | logged_in, or anonymous with a non-empty `token` | Update position + progress. Body: `{ token (required), position (required, number), duration (required, number), playing (bool, default true), focused (bool, default true) }`. |
| POST | `/session/end` | logged_in, or anonymous with a non-empty `token` | End session, finalize stats. Body: `{ token }` (required). |
| POST | `/session/revoke-user` | `manage_options` | Kill all active sessions for a user. Body: `{ user_id }`. |

The body field is `token`, not `session_token` - `session_token` is what `/session/start` returns, and what the column is called. There is no `completion_pct` param; the server derives completion from `position` and `duration`.

Session tokens are HMAC-signed, and on heartbeat/end the token **is** the authentication: `SessionManager` verifies the signature and rejects anything it did not mint, which is why those two routes accept an anonymous caller carrying one. Revocation stays logged-in and `manage_options` regardless.

Heartbeat interval: 30 seconds, emitted as `interval: 30000` in the frontend config (adjustable via the `mediashield_frontend_config` filter). A session stops counting toward the concurrent-stream limit once its heartbeat is more than 5 minutes old; the hourly `ms_cleanup_inactive_sessions` job flips `is_active` at 10 minutes.

---

### Stream

| Method | Route | Permission | Description |
|--------|-------|-----------|-------------|
| GET | `/stream/{video_id}` | `stream_permissions_check` | Streams a self-hosted file with HTTP range support, after `AccessControl::can_watch()`. Accepts an optional signed `ms_token` query arg (since 1.3.0) - a `<video src>` cannot send an `X-WP-Nonce` header, so WordPress ignores the session cookie and every viewer would otherwise arrive as user 0. The token names the viewer; `can_watch()` still runs per request, so revoked access is refused on the next range request. Mint one with `EmbedLink::token( $video_id, $user_id, EmbedLink::STREAM_TTL )`. |

---

### Playlists

| Method | Route | Permission | Description |
|--------|-------|-----------|-------------|
| GET | `/playlists/{playlist_id}/items` | logged_in | List items in a playlist, ordered by `sort_order` then `id`. Each row carries `item_id`, `video_id`, `sort_order`, `added_at`, `video_title`, `video_status`, `platform`, `source_url`, `duration`. |
| POST | `/playlists/{playlist_id}/items` | `edit_posts` | Add a video to a playlist. Body: `{ video_id (required), sort_order (default 0) }`. |
| DELETE | `/playlists/{playlist_id}/items/{item_id}` | `edit_posts` | Remove a video from a playlist. |
| PUT | `/playlists/{playlist_id}/items/reorder` | `edit_posts` | Reorder items. Body: `{ order: [ { item_id, sort_order }, ... ] }` (required). The route registers `PUT` with an `order` array of objects - not `POST`, and not a flat id list. |

A playlist id that is missing or not a `mediashield_playlist` post returns 404 `not_found`.

---

### Upload

| Method | Route | Permission | Description |
|--------|-------|-----------|-------------|
| POST | `/upload/init` | `upload_mediashield` capability | Upload a video file as `multipart/form-data`. Fields: `file` (required), `title`, `driver` (default `self_hosted`), and `video_id` (since 1.3.0). Returns `{ video_id, platform_video_id, embed_url, status }` with 201. Passing `video_id` fills in an **existing** video instead of creating one - the admin uploader needs this, since Add New Video is already editing a post. That path is authorised separately with `edit_post`: the capability says a user may upload, not that they may overwrite any video by id. |
| GET | `/upload/status/{upload_id}` | `upload_mediashield` capability | Check upload progress via the driver's `get_status()`. Optional `driver` param (default `self_hosted`). Returns `{ status, progress, error }` - the key is `progress` (0-100), not `progress_pct`. |

Status values depend on the driver. `self_hosted` uploads are synchronous, so it only ever returns `complete` or `not_found`. Pro's platform drivers map the remote encoder's state onto `pending` / `uploading` / `processing` / `complete` / `failed`, falling back to `unknown` when the platform reports something unmapped. Pro's `ms_upload_queue` table uses the same five-value enum.

---

### Analytics

| Method | Route | Permission | Description |
|--------|-------|-----------|-------------|
| GET | `/analytics/overview` | `manage_options` | Dashboard summary: total videos, sessions, avg completion, active viewers, activity chart, top videos, recent milestones. Date filter: `?period=7d|30d|90d`. Daily counts use `CONVERT_TZ()` to honour WP site timezone. |
| GET | `/videos/{id}/stats` | `manage_options` | Per-video statistics. |
| GET | `/analytics/milestones` | `manage_options` | Milestone completion data across all videos. Params: `per_page` (default 20), `page` (default 1), `video_id` (default 0 = all). |
| GET | `/analytics/users` | `manage_options` | User engagement list. Params: `per_page` (default 20), `page` (default 1), `search` (default `''`). |
| GET | `/analytics/users/{user_id}` | `manage_options` | Single user detail: every video watched, max position, completion %, milestone history. |
| GET | `/analytics/my-videos` | logged_in | Current user's watched videos. No admin cap required. |

---

### Protection

| Method | Route | Permission | Description |
|--------|-------|-----------|-------------|
| POST | `/protection/devtools-event` | `beacon_permission` - a valid `wp_rest` nonce, from the `X-WP-Nonce` header or a `_wpnonce` param. No capability, and **not** logged-in-only: anonymous playback has to be able to report. | Beacon endpoint for client-side devtools detection. Body: `{ strategy (required, one of `size_delta` \| `debugger_timing`), url, ua, screen }`. Fires the `mediashield_devtools_detected` action. |

Rate limited to one recorded event per user+IP per hour via a transient; a throttled call still returns 200 with `{ recorded: false, reason: 'rate_limited' }`.

---

### Wizard

| Method | Route | Permission | Description |
|--------|-------|-----------|-------------|
| POST | `/wizard/complete` | `manage_options` | Mark the setup wizard as complete. Sets the `ms_wizard_completed` option, returns `{ success: true }`. Registered inline by `Admin\SetupWizard`, not by a controller class. |

---

### Core CPT routes

The two custom post types are `show_in_rest` and therefore also reachable through WordPress core's own namespace, with core's `edit_posts` / `map_meta_cap` behaviour:

| Route | Notes |
|-------|-------|
| `/wp/v2/mediashield-videos[/{id}]` | Video CPT. Exposes the registered `_ms_*` meta - see [post-meta-reference.md](post-meta-reference.md) for which keys are actually registered. |
| `/wp/v2/mediashield-playlists[/{id}]` | Playlist CPT, plus core's `/autosaves` sub-routes. |

---

## Pro plugin - `mediashield-pro/v1`

Base URL: `/wp-json/mediashield-pro/v1/`

All Pro endpoints require `manage_options` unless noted. The exceptions are `/drm/license` (any logged-in user) and `/bunny/webhook` (public, authenticated on a URL token).

---

### Platforms

| Method | Route | Permission | Description |
|--------|-------|-----------|-------------|
| GET | `/platforms` | `manage_options` | List all platform connections (credentials redacted). |
| POST | `/platforms` | `manage_options` | Create a new connection. Body: `{ platform (required, one of `bunny` \| `vimeo` \| `youtube` \| `wistia`), api_key (required), api_secret (default `''`), extra_config (object, default `{}`) }`. Credentials encrypted with AES-256-CBC before storage in `ms_platform_connections`. |
| DELETE | `/platforms/{id}` | `manage_options` | Disconnect a platform. |

---

### DRM

| Method | Route | Permission | Description |
|--------|-------|-----------|-------------|
| POST | `/drm/license` | logged_in | Issue a streaming ClearKey license. Body: `{ video_id (required), device_id (default `''`) }`. Runs `AccessControl::can_watch()` and the revocation check before returning the key. Response is the raw ClearKey JWK set: `{ keys: [ { kty: 'oct', kid, k } ], type: 'temporary' }`. |
| POST | `/drm/revoke` | `manage_options` | Revoke all licenses for a user+video. Body: `{ video_id (required), user_id (required) }`. Sets `revoked_at`; returns `{ video_id, user_id, licenses_revoked }`. |

There is no `/drm/offline` route. Offline (persistent) licensing was removed in 1.2.0 - `ms_drm_licenses.license_type` still carries the `persistent` enum value for legacy rows, but nothing issues one, and `ms_drm_license_duration_persistent` is not a real option.

Content keys are stored in `ms_drm_keys` (encrypted at rest). See [drm-internals.md](drm-internals.md) for key generation and packaging details.

---

### Analytics (Pro)

| Method | Route | Permission | Description |
|--------|-------|-----------|-------------|
| GET | `/analytics/heatmap/{id}` | `manage_options` | Position bucket data with view counts and average duration per 10-second bucket. Served from `ms_heatmap_cache`. The path param is named `id`, not `video_id`. |
| GET | `/analytics/playlist-funnel/{playlist_id}` | `manage_options` | Drop-off between videos in a playlist. |
| GET | `/analytics/device-breakdown` | `manage_options` | `ms_watch_sessions` grouped by `device_type` + `browser` over a `?period=` window. Each row: `{ device_type, browser, session_count, unique_users, avg_completion }`. |
| GET | `/realtime/viewers` | `manage_options` | All sessions with `last_heartbeat` within the past 5 minutes. |
| GET | `/analytics/suspicious` | `manage_options` | List activity alerts (paginated). |
| PATCH | `/analytics/suspicious/{id}/dismiss` | `manage_options` | Mark an alert as reviewed (sets `is_dismissed = 1`). |
| POST | `/analytics/suspicious/safe-user` | `manage_options` | Mark a user as safe to suppress future alerts. Persisted to the `ms_safe_users` option. |

---

### Platform browsers and import

One trio per connected platform. The `videos` / `projects` / `folders` / `playlists` routes proxy the platform API using the stored connection; the `import` routes create a `mediashield_video` CPT pointing at an existing remote video. All `manage_options`.

| Method | Route | Description |
|--------|-------|-------------|
| GET | `/bunny/videos` | Browse the Bunny Stream library. |
| GET | `/bunny/collections` | Bunny collections (folders). |
| POST | `/bunny/import` | Import selected Bunny videos. |
| GET | `/youtube/videos` | Browse the connected YouTube channel. |
| GET | `/youtube/playlists` | YouTube playlists. |
| POST | `/youtube/import` | Import selected YouTube videos. |
| GET | `/vimeo/videos` | Browse the connected Vimeo account. |
| GET | `/vimeo/folders` | Vimeo folders. |
| POST | `/vimeo/import` | Import selected Vimeo videos. |
| GET | `/wistia/videos` | Browse the connected Wistia account. |
| GET | `/wistia/projects` | Wistia projects. |
| POST | `/wistia/import` | Import selected Wistia videos. |

---

### Uploads, webhook, milestones, digest, license

| Method | Route | Permission | Description |
|--------|-------|-----------|-------------|
| GET | `/uploads` | `manage_options` | Read the `ms_upload_queue` table (platform upload jobs and their status). |
| POST | `/bunny/webhook` | `__return_true` (public) | Bunny Stream encode callback. Bunny sends no auth header, so the `permission_callback` is open and the handler authenticates instead: it accepts either a valid request signature or an `ms_token` query arg matching the auto-generated `ms_bunny_webhook_key` option. `ms_bunny_webhook_url` in the settings response is the read-only, token-bearing URL the owner pastes into Bunny. Fires `mediashield_bunny_encoded` or `mediashield_bunny_failed`. |
| GET | `/milestones/config` | `manage_options` | Read the milestone action configuration (`ms_pro_milestone_config`). |
| POST | `/milestones/test-action` | `manage_options` | Fire one milestone action against the current admin, for testing. Body: `{ type (required), config (object), threshold (int, default 100) }`. Returns 400 `invalid_action_type` for an unknown `type`. |
| POST | `/digest/send-test` | `manage_options` | Run the weekly-digest generator now and mail it to the requesting admin. Registered by `Reports\WeeklyDigest`, not a REST controller class. Returns `{ sent, to, subject, body_bytes }`. |
| GET | `/license` | `manage_options` | EDD license status for the Pro plugin. |

---

### Export

| Method | Route | Permission | Description |
|--------|-------|-----------|-------------|
| GET | `/export/csv/{type}` | `manage_options` **plus** a `wp_rest` nonce passed as the `_wpnonce` query param - a browser download navigation cannot send `X-WP-Nonce` | Stream CSV export. `{type}`: `watch_sessions`, `milestones`, `users`. Params: `date_from`, `date_to`, `user_id`, `video_id` (the date params are `date_from` / `date_to`, not `from` / `to`). No cap on watch-session and milestone exports since 1.3.0 - they keyset-page through and export in full. The user export is an aggregate query and keeps a 200,000-row ceiling, writing a trailing notice into the CSV if it is reached. |
| POST | `/export/pdf/report` | `manage_options` | Queue an async PDF report via Action Scheduler. Body: `{ period }`, one of `7d` \| `30d` \| `90d` \| `all` (default `30d`). Returns 202 `{ job_id, status: 'queued', message }`. 500 `export_no_scheduler` if Action Scheduler is absent. |
| GET | `/export/status/{job_id}` | `manage_options` | Check PDF generation status. |
