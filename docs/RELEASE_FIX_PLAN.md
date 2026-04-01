# MediaShield Release Fix Plan

**Created:** 2026-04-01
**Scope:** Both mediashield (free) and mediashield-pro
**Goal:** Fix all critical/high issues found during release readiness audit

---

## Phase 1: Critical Code Bugs (Blocks everything)

### 1.1 SuspiciousActivity hook params mismatch [CRITICAL]
- **File:** `mediashield-pro/includes/Analytics/SuspiciousActivity.php:43`
- **Problem:** Hook registered with `10, 2` but `mediashield_session_started` fires 4 params (`$session_id, $video_id, $user_id, $ip`). Method receives `$session_id` as `$user_id`.
- **Fix:** Change to `10, 4`, update method signature to `(int $session_id, int $video_id, int $user_id, string $ip)`.

### 1.2 SelfHosted videos return 403 [HIGH]
- **File:** `mediashield/includes/Upload/Drivers/SelfHosted.php:219-229`
- **Problem:** `.htaccess` denies ALL access. No PHP proxy to serve files.
- **Fix:** Create a streaming proxy endpoint in REST API that verifies access via `mediashield_can_watch` filter, then serves the file with proper Range header support.

### 1.3 Email gate overlay never shown [HIGH]
- **File:** `mediashield/assets/js/player-wrapper.js:353`
- **Problem:** Session start failure only logs to console, never dispatches `mediashield:access-denied` event.
- **Fix:** On 403 response with `email_gate_required` reason, dispatch CustomEvent.

### 1.4 Free uninstall destroys Pro config [HIGH]
- **File:** `mediashield/uninstall.php:29`
- **Problem:** `DELETE WHERE option_name LIKE 'ms_%'` kills all Pro options too.
- **Fix:** Use explicit option list for free plugin. Check `defined('MEDIASHIELD_PRO_VERSION')` before wildcard delete.

### 1.5 GDPR export/erase missing Pro tables [HIGH]
- **Files:** `mediashield/includes/Privacy/PrivacyExporter.php`, `PrivacyEraser.php`
- **Problem:** Missing `ms_email_captures`, `ms_drm_licenses`, `ms_playback_events`, user meta `ms_completed_*`.
- **Fix:** Add Pro privacy exporter/eraser in mediashield-pro that registers via WP hooks.

### 1.6 Pro cascade delete skips non-session tables [HIGH]
- **File:** `mediashield/includes/Cron/Cleanup.php:117`
- **Problem:** DRM keys, licenses, heatmap cache, alerts not cleaned when video has no sessions.
- **Fix:** Always delete by `video_id` for non-session tables, regardless of session existence.

### 1.7 Activity alerts schema mismatch [MEDIUM]
- **File:** `mediashield-pro/includes/DB/Schema.php:80` vs `SuspiciousActivity.php:180`
- **Problem:** Schema has `details JSON`, code writes to `message` column.
- **Fix:** Add `message VARCHAR(500)` to schema, or change code to use `details` with JSON.

### 1.8 Session archive not transactional [MEDIUM]
- **File:** `mediashield/includes/Cron/Cleanup.php:210-225`
- **Fix:** Wrap INSERT+DELETE in START TRANSACTION / COMMIT.

### 1.9 visibilitychange ends all sessions [MEDIUM]
- **File:** `mediashield/assets/js/tracker.js:105-121`
- **Fix:** Only end sessions on `beforeunload`. On `visibilitychange` hidden, pause heartbeat.

### 1.10 Output buffer embeds get video_id=0 [MEDIUM]
- **File:** `mediashield/includes/Player/PlayerWrapper.php:153`
- **Fix:** Query for matching `mediashield_video` CPT by `_ms_platform_video_id` meta.

---

## Phase 2: Bunny.net Integration Fixes

### 2.1 Add pull zone hostname field to platform connection [HIGH]
- **Files:** `mediashield-pro/includes/DB/Schema.php`, `REST/PlatformController.php`, admin `Platforms.js`
- **Fix:** Use `extra_config` JSON column to store `pull_zone_hostname` and `cdn_token_key`.

### 2.2 Generate direct HLS/DASH streaming URLs [HIGH]
- **File:** `mediashield-pro/includes/Upload/Drivers/BunnyStream.php`
- **Fix:** Add `get_streaming_url()` method returning `https://vz-{pullzone}.b-cdn.net/{videoGuid}/playlist.m3u8`. Store streaming URL in `_ms_source_url` instead of local path.

### 2.3 Implement signed/token-authenticated playback URLs [HIGH]
- **File:** `mediashield-pro/includes/Upload/Drivers/BunnyStream.php`
- **Fix:** Generate HMAC-SHA256 signed URLs using CDN token key from `extra_config`.

### 2.4 Call driver delete() on video CPT deletion [HIGH]
- **File:** `mediashield/includes/Cron/Cleanup.php`
- **Fix:** Read `_ms_platform` and `_ms_platform_video_id` meta, instantiate driver, call `delete()`.

### 2.5 Persist tus upload state for resume [HIGH]
- **File:** `mediashield-pro/includes/Upload/Drivers/BunnyStream.php`
- **Fix:** Store `tus_location` and `offset` in `ms_upload_queue` table.

### 2.6 Add connection validation on platform connect [MEDIUM]
- **File:** `mediashield-pro/includes/REST/PlatformController.php`
- **Fix:** Call `GET /library/{libraryId}` to validate credentials before storing.

### 2.7 Implement Bunny thumbnail fetch [MEDIUM]
- **Files:** `mediashield/includes/CPT/Thumbnail.php`, `mediashield-pro/includes/Upload/Drivers/BunnyStream.php`
- **Fix:** Fetch `https://vz-{pullzone}.b-cdn.net/{videoGuid}/thumbnail.jpg`.

### 2.8 Fix tus AuthorizationSignature to use SHA256 [MEDIUM]
- **File:** `mediashield-pro/includes/Upload/Drivers/BunnyStream.php:120,157`
- **Fix:** `hash('sha256', $api_key)` instead of raw `$api_key`.

---

## Phase 3: Admin UX Fixes

### 3.1 Fix dashboard fake data [CRITICAL]
- **File:** `mediashield/src/admin/pages/Dashboard.js:113-118,362-371`
- **Fix:** Remove `generateDemoData()`. Show empty state message with CTA.

### 3.2 Fix wizard data integrity [CRITICAL]
- **Files:** `mediashield/src/admin/wizard/Wizard.js`, step files
- **Fix:** Use `url` instead of `path` for apiFetch. Load existing settings. Show error toasts. Fix settings key mismatch.

### 3.3 Add missing free settings UI [CRITICAL]
- **File:** `mediashield/src/admin/pages/Settings.js`
- **Fix:** Add "Login & Access Messages" section with login_overlay_text, login_button_text, access_denied_text fields.

### 3.4 Add missing Pro settings UI [CRITICAL]
- **Files:** Pro admin JS pages
- **Fix:** Build UI for: advanced watermark fields, milestone actions config, email gate config (webhook URL, cookie duration, retention), suspicious sensitivity, weekly digest toggle/email.

### 3.5 Add Pro upsell indicators in free version [HIGH]
- **File:** `mediashield/src/admin/pages/Settings.js`, `Sidebar.js`
- **Fix:** Show locked Pro menu items in sidebar. Add Pro badge sections in settings page.

### 3.6 Add protection level explanations [HIGH]
- **File:** `mediashield/src/admin/pages/Settings.js`
- **Fix:** Add description text for each level (None/Basic/Standard/Strict).

### 3.7 Fix sidebar nav i18n [HIGH]
- **Files:** `mediashield/src/admin/App.js`, Pro `index.js`
- **Fix:** Wrap all labels in `__()` from `@wordpress/i18n`.

### 3.8 Add DRM educational content [CRITICAL]
- **File:** Pro `src/admin/pages/DRM.js`
- **Fix:** Add intro text explaining what DRM is, requirements per method, browser compatibility.

---

## Phase 4: DRM Fixes

### 4.1 Fix Widevine license response format [HIGH]
- **File:** `mediashield-pro/includes/DRM/WidevineLicense.php:36-103`
- **Fix:** Implement ClearKey license response format for local_shaka. Document that only ClearKey is supported (not full Widevine proxy).

### 4.2 Fix Bunny DRM playback path [HIGH]
- **File:** `mediashield-pro/includes/DRM/Packager.php:70-81`, `assets/js/drm-player.js`
- **Fix:** Use pull zone HLS URL instead of local file path. Configure Shaka Player with correct manifest URL.

---

## Phase 5: Minor Fixes & Polish

### 5.1 Heatmap N+1 query fix [MEDIUM]
### 5.2 Email gate cookie set to HttpOnly [MEDIUM]
### 5.3 CSV export exclude session_token [MEDIUM]
### 5.4 Pro deactivator use as_unschedule_all_actions [LOW]
### 5.5 Archive cascade include ms_watch_sessions_archive [LOW]
### 5.6 Cron callbacks add try/catch error logging [MEDIUM]
### 5.7 Heartbeat retry logic in tracker.js [MEDIUM]
### 5.8 Bunny error response key fix (Message vs message) [MEDIUM]
### 5.9 Rate limit (429) retry with backoff for Bunny [MEDIUM]
### 5.10 Hardcoded video/mp4 in tus metadata [LOW]
