---
journey: watch-protected-video
plugin: mediashield
priority: critical
roles: [subscriber, customer]
covers: [player-wrapper, watermark, heartbeat, milestone-tracking]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "MediaShield activated; ms_enabled = 1"
  - "At least one published mediashield_video CPT with a self-hosted source URL"
  - "ms_watermark_opacity > 0 so overlay is visible to the assertion"
  - "ms_max_concurrent_streams >= 1"
estimated_runtime_minutes: 5
---

# Customer watches a protected video end-to-end

The end-to-end happy path. A logged-in subscriber loads a single protected
video page; the player is wrapped, the watermark renders with their identity,
the tracker heartbeats to `/session/heartbeat` every ~30s, and crossing the
25% threshold writes a row to `wp_ms_milestones` and fires
`mediashield_milestone_25`. If any single link breaks, paying customers stop
getting "did this user actually watch it?" data.

## Setup

- Site: `$SITE_URL`
- Test user: `journey-subscriber` (autologin via `?autologin=journey-subscriber`)
- Fixtures needed:
  - One published `mediashield_video` post (capture `VIDEO_ID`) with
    `_ms_platform = self`, `_ms_source_url` set to a short MP4 (5-10s).
- DB clean (per-run idempotency):
  ```sql
  DELETE FROM wp_ms_watch_sessions WHERE user_id = (SELECT ID FROM wp_users WHERE user_login = 'journey-subscriber');
  DELETE FROM wp_ms_milestones     WHERE user_id = (SELECT ID FROM wp_users WHERE user_login = 'journey-subscriber');
  ```

## Steps

### 1. Authenticate and land on the video single
- **Action**: `playwright_navigate $SITE_URL/?autologin=journey-subscriber&p=<VIDEO_ID>`
- **Expect**: HTTP 200; DOM contains `<div class="ms-protected-player">` (player wrapper rendered).
- **Capture**: `USER_ID` ← from `<body class="… logged-in user-id-N">` or `wp_users` lookup.
- **On fail**: `includes/Player/PlayerWrapper.php` (output buffer detection broken) OR `includes/Player/Renderer.php` (validation killed the render).

### 2. Watermark overlay renders with user identity
- **Action**: same page; query `document.querySelector('.ms-watermark')`.
- **Expect**: element exists; its text content includes the user email or display name (per `mediashield_watermark_config` filter shape).
- **On fail**: `assets/js/watermark.js` (renderer) OR `includes/Player/Watermark.php` (server-side config) OR `Settings::frontend_config()` (localized payload missing watermark fields).

### 3. Tracker fires `/session/start`
- **Action**: monitor network in Playwright; wait up to 5s for `POST $SITE_URL/wp-json/mediashield/v1/session/start`.
- **Expect**: HTTP 200; JSON body has non-empty `session_token` and `session_id`.
- **Capture**: `SESSION_TOKEN`, `SESSION_ID`.
- **On fail**: `assets/js/tracker.js` (start call broken) OR `includes/REST/SessionController.php::start_session` OR `includes/Access/AccessControl.php::can_watch` returned WP_Error.

### 4. Heartbeat writes to `wp_ms_watch_sessions`
- **Action**: wait ~35s, observe `POST /wp-json/mediashield/v1/session/heartbeat` with `{session_token: SESSION_TOKEN, position: 3}`.
- **Expect**: HTTP 200; row in `wp_ms_watch_sessions` with `id = SESSION_ID` has `last_heartbeat` within last 5s and `total_seconds >= 30`.
  ```sql
  SELECT last_heartbeat, total_seconds, max_position, completion_pct, is_active
    FROM wp_ms_watch_sessions WHERE id = $SESSION_ID;
  ```
- **On fail**: `assets/js/tracker.js` (heartbeat loop) OR `includes/REST/SessionController.php::heartbeat` OR `includes/Access/SessionManager.php::touch_session`.

### 5. Crossing 25% writes a milestone row + fires the hook
- **Action**: jumpto 30% of video and wait 5s for the next heartbeat.
- **Expect**: row exists in `wp_ms_milestones` with `(video_id, user_id, milestone_pct) = (VIDEO_ID, USER_ID, 25)`. Hook listener (set up before navigation via mu-plugin or admin notice script) recorded `mediashield_milestone_25`.
  ```sql
  SELECT * FROM wp_ms_milestones
    WHERE video_id = $VIDEO_ID AND user_id = $USER_ID AND milestone_pct = 25;
  ```
- **On fail**: `includes/Milestones/MilestoneTracker.php::record` (insert/idempotency) OR thresholds filter (`mediashield_milestone_thresholds`) overrode to non-default.

## Pass criteria

ALL of the following hold:
1. `.ms-protected-player` renders on the single template.
2. `.ms-watermark` overlay renders with the user's identity in its text.
3. `/session/start` returns 200 with a session token.
4. `/session/heartbeat` updates `last_heartbeat` and `total_seconds`.
5. A `wp_ms_milestones` row exists for `(VIDEO_ID, USER_ID, 25)`.

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Bare `<video>` with no wrapper | Output buffer scan miss | `includes/Player/PlayerWrapper.php` |
| Wrapper but no watermark | JS payload missing watermark config | `includes/Core/Settings.php::frontend_config`, `assets/js/watermark.js` |
| `/session/start` returns 403 | AccessControl denied or capability fallback | `includes/Access/AccessControl.php::can_watch`, `mediashield_can_watch` filter |
| Heartbeat 200 but no DB write | SessionManager::touch_session no-op | `includes/Access/SessionManager.php` |
| No milestone row at 25% | Thresholds filter or PK collision | `includes/Milestones/MilestoneTracker.php` |
