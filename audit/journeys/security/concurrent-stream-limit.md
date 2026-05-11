---
journey: concurrent-stream-limit
plugin: mediashield
priority: critical
roles: [subscriber]
covers: [session-limit, hook-mediashield_concurrent_limit_reached, access-denied]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "MediaShield activated; ms_max_concurrent_streams = 2 (so the 3rd start is denied)"
  - "At least one published mediashield_video CPT (capture VIDEO_ID)"
  - "ms_require_login = 1 (so AccessControl actually evaluates the limit)"
estimated_runtime_minutes: 4
---

# Concurrent stream limit denies the 3rd session and fires the hook

The piracy/abuse-control safety net. With `ms_max_concurrent_streams = 2`,
the same user starting a third concurrent watch session must (a) be denied
with a WP_Error from `/session/start`, (b) cause
`mediashield_concurrent_limit_reached` to fire with the right args, and
(c) leave the existing 2 sessions undisturbed. If this regresses, paying
customers lose the only built-in defence against shared accounts.

## Setup

- Site: `$SITE_URL`
- Test user: `journey-subscriber` (autologin via `?autologin=journey-subscriber`)
- Settings (set before run; restore after):
  ```bash
  wp option update ms_max_concurrent_streams 2
  wp option update ms_require_login 1
  ```
- DB clean:
  ```sql
  DELETE FROM wp_ms_watch_sessions
    WHERE user_id = (SELECT ID FROM wp_users WHERE user_login = 'journey-subscriber');
  ```
- Hook recorder mu-plugin (drop into `wp-content/mu-plugins/journey-hook-recorder.php` for the run):
  ```php
  <?php
  add_action( 'mediashield_concurrent_limit_reached', function( $user_id, $video_id, $active_count, $max ) {
      file_put_contents(
          WP_CONTENT_DIR . '/journey-hook.log',
          json_encode( compact( 'user_id', 'video_id', 'active_count', 'max' ) ) . "\n",
          FILE_APPEND
      );
  }, 10, 4 );
  ```

## Steps

### 1. Resolve test user + nonce
- **Action**: `playwright_navigate $SITE_URL/?autologin=journey-subscriber`; extract REST nonce from page.
- **Capture**: `USER_ID`, `NONCE`.

### 2. Start session #1 — should succeed
- **Action**: `curl -X POST $SITE_URL/wp-json/mediashield/v1/session/start -H "X-WP-Nonce: $NONCE" -d '{"video_id":VIDEO_ID}'`
- **Expect**: HTTP 200; `session_token` returned. Row count for user in `wp_ms_watch_sessions WHERE is_active=1` = 1.

### 3. Start session #2 — should succeed
- **Action**: same, second client (or just second curl call).
- **Expect**: HTTP 200; second `session_token`. Active count = 2.

### 4. Start session #3 — must be denied
- **Action**: same curl, third invocation.
- **Expect**: HTTP 403 (or 429); body is a WP_Error with `code = 'mediashield_concurrent_limit_reached'` (or whatever the AccessControl chain returns — see code).
  ```json
  { "code": "...", "message": "...", "data": { "status": 403 } }
  ```
- **Expect**: active session count remains 2 (no row leaked from the failed start).
- **Expect**: `journey-hook.log` contains a JSON line with `user_id = USER_ID`, `video_id = VIDEO_ID`, `active_count = 2`, `max = 2`.

### 5. Cleanup
- **Action**: end one session via `POST /session/end` for `session_token` from step 2.
- **Action**: start a fresh session — should now succeed (count drops to 1, then back to 2).
- **Expect**: HTTP 200 on the retry, confirming the limit is dynamic, not a permanent ban.

## Pass criteria

ALL of the following hold:
1. First two `/session/start` calls return 200.
2. Third `/session/start` call returns a 4xx with a denial code.
3. Active session count for the user never exceeds 2.
4. `mediashield_concurrent_limit_reached` fires exactly once with `(USER_ID, VIDEO_ID, 2, 2)`.
5. After ending one session, a new start succeeds.

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| Third call returns 200 | Limit check missing or bypassed | `includes/Access/AccessControl.php::can_watch`, `includes/Access/SessionManager.php::active_count` |
| Third call denied but hook never fires | `do_action` skipped after early-return | `includes/Access/AccessControl.php` (action dispatch order) |
| Hook fires with wrong args | Signature drift | `includes/Access/AccessControl.php` (check `do_action` arg list against `mediashield_concurrent_limit_reached` callback) |
| Active count exceeds 2 | Race or missing index | `includes/Access/SessionManager.php::start_session`, `wp_ms_watch_sessions` index on `(user_id, is_active)` |
| Hook fires twice | Double dispatch in retry path | `includes/REST/SessionController.php::start_session` |
