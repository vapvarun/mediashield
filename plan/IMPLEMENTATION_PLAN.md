# MediaShield Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build MediaShield — a WordPress video protection plugin (free + pro) with dynamic watermarking, multi-platform support, engagement analytics, milestone automation, upload hub, and Widevine DRM with offline playback.

**Architecture:** Monolith with modular internals. Two plugins: `mediashield` (free, wordpress.org) + `mediashield-pro` (pro add-on). Free handles: video detection/wrapping, basic watermark (username+IP), standard download prevention, login-required access, basic analytics, milestones (hooks only), tags, self-hosted upload. Pro adds: configurable watermark, role-based access, heatmaps, suspicious activity, real-time dashboard, platform upload drivers (Bunny/Vimeo/YouTube/Wistia), frontend upload, milestone admin UI + actions, Widevine DRM, PWA offline.

**Tech Stack:** PHP 8.1+, WordPress 6.5+ (Interactivity API), React (@wordpress/scripts) for admin SPA, WordPress Interactivity API for frontend, Shaka Player for video playback, @wordpress/components for admin UI, Action Scheduler for background jobs, Shaka Packager for DRM content encryption, OpenSSL AES-256-CBC for secrets, full i18n from day 1, multisite network-aware.

**Data Model:** Hybrid — Videos as CPT (`mediashield_video`), Playlists as CPT (`mediashield_playlist`), analytics/sessions in custom tables. Playlist items in `ms_playlist_items` relationship table.

**Admin UX:** Notion-style full-page React app with sidebar nav, @wordpress/components + polish, inline auto-save, toast notifications. Setup wizard on first activation.

**Player:** Shaka Player for self-hosted/Bunny, iframe wrapper for YouTube/Vimeo/Wistia. Gutenberg block with video picker modal + URL paste.

**Design Spec:** `docs/DESIGN_SPEC.md` (same directory)

---

## File Map

### mediashield (free)

```
wp-content/plugins/mediashield/
├── mediashield.php
├── uninstall.php
├── composer.json
├── package.json                             # @wordpress/scripts build toolchain
├── includes/
│   ├── Core/
│   │   ├── Plugin.php
│   │   ├── Activator.php
│   │   ├── Deactivator.php
│   │   ├── Migrator.php
│   │   └── Assets.php
│   ├── CPT/
│   │   ├── VideoPostType.php                # Register mediashield_video CPT
│   │   ├── PlaylistPostType.php             # Register mediashield_playlist CPT
│   │   └── Thumbnail.php                    # Auto-fetch thumbnails from platform APIs
│   ├── Block/
│   │   ├── VideoBlock.php                   # Register mediashield/video block
│   │   ├── PlaylistBlock.php                # Register mediashield/playlist block
│   │   └── block.json                       # Block metadata
│   ├── DB/
│   │   └── Schema.php
│   ├── Player/
│   │   ├── PlayerWrapper.php
│   │   ├── Watermark.php
│   │   └── Protection.php
│   ├── Access/
│   │   ├── AccessControl.php
│   │   └── SessionManager.php
│   ├── Analytics/
│   │   ├── Tracker.php
│   │   └── Reporter.php
│   ├── Milestones/
│   │   └── MilestoneTracker.php
│   ├── Upload/
│   │   ├── UploadManager.php
│   │   └── Drivers/
│   │       ├── DriverInterface.php
│   │       └── SelfHosted.php
│   ├── Tags/
│   │   └── TagManager.php
│   ├── REST/
│   │   ├── SessionController.php
│   │   ├── VideoController.php
│   │   ├── TagController.php
│   │   ├── AnalyticsController.php
│   │   ├── UploadController.php
│   │   └── SettingsController.php
│   └── Admin/
│       ├── Menu.php
│       ├── Settings.php
│       ├── Dashboard.php
│       └── VideoManager.php
├── assets/
│   ├── js/
│   │   ├── player-wrapper.js
│   │   ├── watermark.js
│   │   ├── tracker.js
│   │   ├── protection.js
│   │   └── admin/
│   │       ├── dashboard.js
│   │       └── settings.js
│   └── css/
│       ├── player.css
│       └── admin.css
├── templates/
│   └── login-overlay.php
└── tests/
    └── phpunit/
        ├── bootstrap.php
        └── Unit/
            ├── SessionManagerTest.php
            ├── AccessControlTest.php
            ├── MilestoneTrackerTest.php
            ├── TagManagerTest.php
            └── TrackerTest.php
```

### mediashield-pro

```
wp-content/plugins/mediashield-pro/
├── mediashield-pro.php
├── composer.json
├── includes/
│   ├── Core/
│   │   ├── Plugin.php
│   │   ├── Activator.php
│   │   └── Migrator.php
│   ├── DB/
│   │   └── Schema.php
│   ├── Watermark/
│   │   └── AdvancedConfig.php
│   ├── Access/
│   │   └── RoleAccess.php
│   ├── Analytics/
│   │   ├── Heatmap.php
│   │   ├── SuspiciousActivity.php
│   │   └── RealtimeDashboard.php
│   ├── Milestones/
│   │   └── AdvancedActions.php
│   ├── Upload/
│   │   └── Drivers/
│   │       ├── BunnyStream.php
│   │       ├── VimeoApi.php
│   │       ├── YouTubeApi.php
│   │       └── WistiaApi.php
│   ├── DRM/
│   │   ├── WidevineLicense.php
│   │   ├── Packager.php
│   │   ├── OfflineManager.php
│   │   └── KeyServer.php
│   ├── REST/
│   │   ├── HeatmapController.php
│   │   ├── SuspiciousController.php
│   │   ├── RealtimeController.php
│   │   ├── DRMController.php
│   │   ├── PlatformController.php
│   │   └── MilestoneConfigController.php
│   └── Admin/
│       ├── PlatformConnections.php
│       └── DRMSettings.php
├── assets/
│   ├── js/
│   │   ├── drm-player.js
│   │   ├── offline-sw.js
│   │   ├── frontend-upload.js
│   │   └── admin/
│   │       ├── heatmap.js
│   │       └── realtime.js
│   └── css/
│       ├── frontend-upload.css
│       └── admin-pro.css
├── templates/
│   └── frontend-upload.php
└── tests/
    └── phpunit/
        └── Unit/
            ├── RoleAccessTest.php
            ├── SuspiciousActivityTest.php
            └── HeatmapTest.php
```

---

## Task 1: Free Plugin Scaffold + Database Schema

**Files:**
- Create: `mediashield/mediashield.php`
- Create: `mediashield/composer.json`
- Create: `mediashield/includes/Core/Plugin.php`
- Create: `mediashield/includes/Core/Activator.php`
- Create: `mediashield/includes/Core/Deactivator.php`
- Create: `mediashield/includes/Core/Migrator.php`
- Create: `mediashield/includes/DB/Schema.php`
- Create: `mediashield/uninstall.php`

- [ ] **Step 1: Create composer.json with PSR-4 autoloading**

```json
{
    "name": "mediashield/mediashield",
    "description": "Video protection for WordPress",
    "type": "wordpress-plugin",
    "autoload": {
        "psr-4": { "MediaShield\\": "includes/" }
    },
    "require": { "php": ">=8.1" }
}
```

Run: `cd wp-content/plugins/mediashield && composer install`

- [ ] **Step 2: Create mediashield.php bootstrap**

Constants: `MEDIASHIELD_VERSION` (1.0.0), `MEDIASHIELD_FILE`, `MEDIASHIELD_PATH`, `MEDIASHIELD_URL`, `MEDIASHIELD_DB_VERSION` (1).

Activation hook → `Activator::activate()`. Deactivation → `Deactivator::deactivate()`. `plugins_loaded` → `Plugin::instance()`.

- [ ] **Step 3: Create DB/Schema.php**

5 free tables: `ms_videos`, `ms_tags`, `ms_video_tags`, `ms_watch_sessions`, `ms_milestones`. All column definitions per DESIGN_SPEC.md.

- [ ] **Step 4: Create Migrator.php**

Version tracking via `ms_db_version` option. Compares on `plugins_loaded`, runs `Schema::create_tables()` via `dbDelta` if stale.

- [ ] **Step 5: Create Activator.php**

Check PHP >= 8.1, WP >= 6.4. Run migrations. Set defaults: `ms_enabled=true`, `ms_default_protection=standard`, `ms_require_login=true`, `ms_watermark_opacity=0.3`, `ms_watermark_color=#ffffff`, `ms_watermark_swap_interval=20`. Add `upload_mediashield` cap to admin.

- [ ] **Step 6: Create Deactivator.php + uninstall.php**

Deactivator: `flush_rewrite_rules()`. Uninstall: drop `ms_*` tables, delete `ms_*` options, remove capability.

- [ ] **Step 7: Create Core/Plugin.php singleton**

Registers hooks: `rest_api_init`, `admin_menu`, `wp_enqueue_scripts`, `admin_enqueue_scripts`, `template_redirect`. Fires `mediashield_loaded`.

- [ ] **Step 8: Verify tables created**

```bash
wp --path="/Users/varundubey/Local Sites/forums/app/public" plugin activate mediashield
wp --path="/Users/varundubey/Local Sites/forums/app/public" db tables --all-tables | grep ms_
```
Expected: 5 tables.

- [ ] **Step 9: Commit**

```bash
git add wp-content/plugins/mediashield/
git commit -m "feat(mediashield): scaffold free plugin with DB schema and activation"
```

---

## Task 2: Pro Plugin Scaffold + Pro Database Schema

**Files:**
- Create: `mediashield-pro/mediashield-pro.php`
- Create: `mediashield-pro/composer.json`
- Create: `mediashield-pro/includes/Core/Plugin.php`
- Create: `mediashield-pro/includes/Core/Activator.php`
- Create: `mediashield-pro/includes/Core/Migrator.php`
- Create: `mediashield-pro/includes/DB/Schema.php`

- [ ] **Step 1: Create pro composer.json** (PSR-4 namespace `MediaShieldPro\\`)

- [ ] **Step 2: Create pro bootstrap with dependency check**

`plugins_loaded:20`. Check `mediashield/mediashield.php` active, else admin notice + bail.

- [ ] **Step 3: Create Pro DB/Schema.php**

5 pro tables: `ms_playback_events`, `ms_platform_connections`, `ms_upload_queue`, `ms_activity_alerts`, `ms_drm_licenses`. Per DESIGN_SPEC.md.

- [ ] **Step 4: Create Pro Activator + Migrator** (same pattern as free)

- [ ] **Step 5: Create Pro Core/Plugin.php**

Hooks: `mediashield_watermark_config` filter, `mediashield_upload_drivers` filter, `mediashield_can_watch` filter, `mediashield_milestone_reached` action, `mediashield_session_started` action.

- [ ] **Step 6: Verify 10 tables total**

```bash
wp --path="..." plugin activate mediashield-pro
wp --path="..." db tables --all-tables | grep ms_
```

- [ ] **Step 7: Commit**

```bash
git add wp-content/plugins/mediashield-pro/
git commit -m "feat(mediashield-pro): scaffold pro plugin with DB schema"
```

---

## Task 3: Video Registry + Tags — CRUD + REST

**Files:** `TagManager.php`, `VideoController.php`, `TagController.php`, tests

- [ ] **Step 1: Write TagManager tests** (create, duplicate slug, get, delete, assign, get_for_video)
- [ ] **Step 2: Run tests — fail**
- [ ] **Step 3: Implement TagManager.php** (CRUD + assign/unassign/get_for_video)
- [ ] **Step 4: Run tests — pass**
- [ ] **Step 5: Build VideoController.php** (GET/POST/PATCH/DELETE /videos, paginated)
- [ ] **Step 6: Build TagController.php** (GET/POST/PATCH/DELETE /tags, /videos/{id}/tags)
- [ ] **Step 7: Register routes in Plugin.php**
- [ ] **Step 8: Verify via WP-CLI REST**
- [ ] **Step 9: Commit**

---

## Task 4: Access Control + Session Manager

**Files:** `AccessControl.php`, `SessionManager.php`, `SessionController.php`, tests

- [ ] **Step 1: Write AccessControl tests** (logged-in OK, logged-out denied, filter override, admin bypass)
- [ ] **Step 2: Write SessionManager tests** (create returns 64-char token, validate, heartbeat updates, end marks inactive)
- [ ] **Step 3: Run tests — fail**
- [ ] **Step 4: Implement AccessControl.php** (login gate + `mediashield_can_watch` filter)
- [ ] **Step 5: Implement SessionManager.php** (HMAC token generation, `ms_watch_sessions` CRUD, heartbeat processing with completion_pct calculation)
- [ ] **Step 6: Run tests — pass**
- [ ] **Step 7: Build SessionController.php** (POST /session/start, /session/heartbeat, /session/end)
- [ ] **Step 8: Test session flow via REST**
- [ ] **Step 9: Commit**

---

## Task 5: Player Wrapper — Video Detection + DOM Wrapping

**Files:** `PlayerWrapper.php`, `Protection.php`, `Watermark.php`, `Assets.php`, all JS/CSS, `login-overlay.php`

- [ ] **Step 1: Create PlayerWrapper.php** — output buffer, regex patterns for YouTube/Vimeo/Bunny/Wistia/video tags, wrap each match in `.ms-protected-player` div with canvas overlay
- [ ] **Step 2: Create Watermark.php** — server-side config: `{username} . {ip}`, opacity, color, interval. Passed to JS via `wp_localize_script`
- [ ] **Step 3: Create Protection.php** — `oncontextmenu="return false"`, `controlsList="nodownload"`, move `src` to `data-ms-src`
- [ ] **Step 4: Create Assets.php** — register/enqueue `player-wrapper.js`, `watermark.js`, `tracker.js`, `protection.js` with `mediashieldConfig` localized data
- [ ] **Step 5: Create player-wrapper.js** — init sessions per video, wire watermark/tracker/protection
- [ ] **Step 6: Create watermark.js** — Canvas rendering, random position swap, MutationObserver anti-tamper, ResizeObserver
- [ ] **Step 7: Create tracker.js** — 30s heartbeat interval, sendBeacon on unload
- [ ] **Step 8: Create protection.js** — right-click block, Ctrl+S block, load `data-ms-src` into `src`
- [ ] **Step 9: Create player.css + login-overlay.php**
- [ ] **Step 10: Wire in Plugin.php** (template_redirect → output buffer, wp_enqueue_scripts → assets)
- [ ] **Step 11: Browser test** — YouTube video page, verify wrapper + watermark + tracking
- [ ] **Step 12: Commit**

---

## Task 6: Analytics — Tracker + Reporter + Dashboard

**Files:** `Tracker.php`, `Reporter.php`, `AnalyticsController.php`, `Menu.php`, `Dashboard.php`, `dashboard.js`, `admin.css`

- [ ] **Step 1: Implement Tracker.php** — `process_heartbeat()` updates session, calls MilestoneTracker. `mark_inactive_sessions()` hourly cron.
- [ ] **Step 2: Implement Reporter.php** — `get_overview()`, `get_top_videos()`, `get_video_stats()`, `get_user_history()`. All paginated, indexed.
- [ ] **Step 3: Build AnalyticsController.php** — GET /videos/{id}/stats, GET /milestones
- [ ] **Step 4: Build Admin Menu.php** — top-level MediaShield + subpages
- [ ] **Step 5: Build Dashboard.php** — overview cards, top videos, recent sessions
- [ ] **Step 6: Create dashboard.js** — Chart.js line/bar charts, period selector
- [ ] **Step 7: Create admin.css**
- [ ] **Step 8: Register cleanup cron**
- [ ] **Step 9: Browser test admin dashboard**
- [ ] **Step 10: Commit**

---

## Task 7: Milestone System

**Files:** `MilestoneTracker.php`, tests

- [ ] **Step 1: Write tests** (fires at 25%, fires at 100%, no duplicates, multiple fire on jump, custom thresholds via filter)
- [ ] **Step 2: Run tests — fail**
- [ ] **Step 3: Implement MilestoneTracker.php** — `INSERT IGNORE` for dedup, fire `mediashield_milestone_reached` + `mediashield_milestone_{pct}` actions
- [ ] **Step 4: Run tests — pass**
- [ ] **Step 5: Wire into Tracker.php heartbeat**
- [ ] **Step 6: Commit**

---

## Task 8: Settings Page

**Files:** `Settings.php`, `SettingsController.php`, `settings.js`

- [ ] **Step 1: Build Settings.php** — WordPress Settings API: general section (enabled, protection, login), watermark section (opacity, color, interval)
- [ ] **Step 2: Build SettingsController.php** — GET/PUT /settings
- [ ] **Step 3: Create settings.js** — color picker, range slider
- [ ] **Step 4: Browser test settings page**
- [ ] **Step 5: Commit**

---

## Task 9: Video Manager (Admin List Table)

**Files:** `VideoManager.php`

- [ ] **Step 1: Build VideoManager extending WP_List_Table** — columns: ID, Title, Platform, Protection, Tags, Sessions, Avg Completion, Created. Sortable, filterable by platform, bulk delete, pagination (20/page).
- [ ] **Step 2: Browser test video manager**
- [ ] **Step 3: Commit**

---

## Task 10: Self-Hosted Upload Driver (Free)

**Files:** `DriverInterface.php`, `SelfHosted.php`, `UploadManager.php`, `UploadController.php`

- [ ] **Step 1: Create DriverInterface.php** — `upload()`, `get_status()`, `delete()`, `get_embed_url()` + value objects
- [ ] **Step 2: Implement SelfHosted.php** — uploads to `wp-content/uploads/mediashield/` with `.htaccess` deny
- [ ] **Step 3: Implement UploadManager.php** — driver factory via `mediashield_upload_drivers` filter, registers in `ms_videos`
- [ ] **Step 4: Build UploadController.php** — POST /upload/init, GET /upload/status/{id}
- [ ] **Step 5: Test upload**
- [ ] **Step 6: Commit**

---

## Task 11: Pro — Role-Based Access Control

**Files:** `RoleAccess.php`, tests

- [ ] **Step 1: Write tests** (correct role OK, wrong role denied, empty role allows all, admin bypass)
- [ ] **Step 2: Run — fail**
- [ ] **Step 3: Implement RoleAccess.php** — hooks `mediashield_can_watch`, checks `access_role` on `ms_videos`
- [ ] **Step 4: Run — pass**
- [ ] **Step 5: Commit**

---

## Task 12: Pro — Advanced Watermark Configuration

**Files:** `AdvancedConfig.php`

- [ ] **Step 1: Implement AdvancedConfig.php** — hooks `mediashield_watermark_config`, builds text from configured fields (username, email, ip, user_id, timestamp, site_name, custom_text)
- [ ] **Step 2: Add pro watermark settings fields** (ms_watermark_fields, ms_watermark_custom_text, ms_watermark_font_size)
- [ ] **Step 3: Commit**

---

## Task 13: Pro — Platform Upload Drivers

**Files:** `BunnyStream.php`, `VimeoApi.php`, `YouTubeApi.php`, `WistiaApi.php`, `PlatformController.php`, `PlatformConnections.php`

- [ ] **Step 1: Implement BunnyStream.php** — Bunny Stream API, tus upload
- [ ] **Step 2: Implement VimeoApi.php** — Vimeo API v3, OAuth + tus
- [ ] **Step 3: Implement YouTubeApi.php** — YouTube Data API v3, resumable upload
- [ ] **Step 4: Implement WistiaApi.php** — Wistia Upload API
- [ ] **Step 5: Register drivers via filter in Pro Plugin.php**
- [ ] **Step 6: Build PlatformController.php** — GET/POST/DELETE /platforms, encrypt credentials
- [ ] **Step 7: Build PlatformConnections.php admin page** — connect/disconnect UI per platform
- [ ] **Step 8: Commit**

---

## Task 14: Pro — Frontend Upload Form

**Files:** `frontend-upload.php`, `frontend-upload.js`, `frontend-upload.css`

- [ ] **Step 1: Register [mediashield_upload] shortcode** — capability check `upload_mediashield`
- [ ] **Step 2: Build template** — drag-drop zone, platform selector, title, tags, progress bar
- [ ] **Step 3: Build frontend-upload.js** — FormData + XMLHttpRequest chunked upload
- [ ] **Step 4: Browser test**
- [ ] **Step 5: Commit**

---

## Task 15: Pro — Advanced Analytics

**Files:** `Heatmap.php`, `SuspiciousActivity.php`, `RealtimeDashboard.php`, REST controllers, admin JS

- [ ] **Step 1: Implement Heatmap.php** — aggregate `ms_playback_events` into position buckets
- [ ] **Step 2: Hook heartbeat to record playback events** — play/pause/seek events batched with heartbeat
- [ ] **Step 3: Implement SuspiciousActivity.php** — multi-IP detection (5min window), rapid seek, DevTools flag
- [ ] **Step 4: Implement RealtimeDashboard.php** — query active sessions (last_heartbeat > 2min ago)
- [ ] **Step 5: Build REST controllers** — /analytics/heatmap/{id}, /analytics/suspicious, /realtime/viewers
- [ ] **Step 6: Build heatmap.js** — Chart.js bar chart
- [ ] **Step 7: Build realtime.js** — 30s poll, auto-refresh table
- [ ] **Step 8: Add Pro admin subpages** (Alerts, Platforms)
- [ ] **Step 9: Commit**

---

## Task 16: Pro — Advanced Milestone Actions

**Files:** `AdvancedActions.php`, `MilestoneConfigController.php`

- [ ] **Step 1: Implement AdvancedActions.php** — hooks `mediashield_milestone_reached`, executes: tag (user_meta), email (wp_mail), webhook (wp_remote_post non-blocking)
- [ ] **Step 2: Build MilestoneConfigController.php** — GET/PUT /milestones/config
- [ ] **Step 3: Add Milestones config admin page** — thresholds + per-threshold actions UI
- [ ] **Step 4: Commit**

---

## Task 17: Pro — Widevine DRM System

**Files:** `KeyServer.php`, `Packager.php`, `WidevineLicense.php`, `DRMController.php`, `DRMSettings.php`, `drm-player.js`

- [ ] **Step 1: Implement KeyServer.php** — generate 128-bit key pairs, encrypt content key with OpenSSL AES-256-CBC before storage
- [ ] **Step 2: Implement Packager.php** — Shaka Packager CLI wrapper, DASH + CENC packaging, runs via Action Scheduler background job
- [ ] **Step 3: Implement WidevineLicense.php** — license proxy: validate access → proxy to Widevine key server → return license. Streaming + persistent types. Records in `ms_drm_licenses`.
- [ ] **Step 4: Build DRMController.php** — POST /drm/license, /drm/offline, /drm/revoke
- [ ] **Step 5: Create drm-player.js** — Shaka Player init, EME license request filter, DASH manifest loading
- [ ] **Step 6: Build DRMSettings.php admin page**
- [ ] **Step 7: Commit**

---

## Task 18: Pro — PWA Offline Download

**Files:** `OfflineManager.php`, `offline-sw.js`

- [ ] **Step 1: Implement OfflineManager.php** — register Service Worker, provide offline segment manifest
- [ ] **Step 2: Create offline-sw.js** — cache encrypted DASH segments on demand, serve from cache when offline
- [ ] **Step 3: Add "Save for Offline" button** — requests persistent license, sends segments to SW, shows progress
- [ ] **Step 4: Commit**

---

## Task 19: E2E Integration Testing

- [ ] **Step 1: Test free full flow** — embed video, verify wrapper + watermark + tracking + milestones
- [ ] **Step 2: Test pro features** — extended watermark, role access, real-time dashboard
- [ ] **Step 3: Test access denial** — subscriber blocked from editor-only video
- [ ] **Step 4: Test milestone deduplication** — replay doesn't duplicate
- [ ] **Step 5: Commit**

---

## Task 20: Video + Playlist CPTs

**Files:** `VideoPostType.php`, `PlaylistPostType.php`, `Thumbnail.php`

- [ ] **Step 1: Register mediashield_video CPT** — public, show_in_rest, supports: title, editor, thumbnail, custom-fields. Labels, rewrite slug: `video`. Menu icon: dashicons-video-alt3.
- [ ] **Step 2: Register post meta** — `_ms_platform`, `_ms_platform_video_id`, `_ms_source_url`, `_ms_protection_level`, `_ms_access_role`, `_ms_duration`. All registered with `show_in_rest: true`.
- [ ] **Step 3: Register mediashield_playlist CPT** — public, show_in_rest, supports: title, editor, thumbnail. Labels, rewrite slug: `playlist`.
- [ ] **Step 4: Register playlist meta** — `_ms_autoplay`, `_ms_countdown`, `_ms_loop`, `_ms_shuffle`. All show_in_rest.
- [ ] **Step 5: Create ms_playlist_items table** — add to Schema.php: playlist_id, video_id, sort_order, added_at
- [ ] **Step 6: Implement Thumbnail.php** — auto-fetch from YouTube oEmbed, Vimeo API, Bunny API, Wistia API. Set as featured image. Fire on video save.
- [ ] **Step 7: Flush rewrite rules on activation**
- [ ] **Step 8: Verify CPT appears in admin, REST API works** (`wp rest get wp/v2/mediashield_video --user=1`)
- [ ] **Step 9: Commit**

---

## Task 21: Gutenberg Video + Playlist Blocks

**Files:** `VideoBlock.php`, `PlaylistBlock.php`, `block.json`, `src/blocks/video/`, `src/blocks/playlist/`

- [ ] **Step 1: Setup @wordpress/scripts build** — create `package.json` with `@wordpress/scripts`, `@wordpress/blocks`, `@wordpress/components`, `@wordpress/block-editor`, `@wordpress/data` dependencies
- [ ] **Step 2: Create mediashield/video block** — `block.json` with attributes: `videoId`, `url`. Edit component: two insertion modes ("Choose from library" opens picker modal, "Paste URL" auto-detects). Preview shows thumbnail + platform badge.
- [ ] **Step 3: Build video picker modal** — React modal using @wordpress/components. Fetches from `/wp/v2/mediashield_video`. Searchable, filterable by tag/platform. Click to select.
- [ ] **Step 4: Build URL auto-detect** — paste handler: regex detects YouTube/Vimeo/Bunny/Wistia URL, auto-creates video CPT post via REST, sets block attributes
- [ ] **Step 5: Create mediashield/playlist block** — select playlist from picker, renders playlist player with sidebar
- [ ] **Step 6: Register blocks in PHP** — `register_block_type()` in VideoBlock.php + PlaylistBlock.php
- [ ] **Step 7: Build frontend render** — `render.php` for each block, outputs `.ms-protected-player` wrapper with Interactivity API directives
- [ ] **Step 8: Browser test** — insert video block in editor, verify preview + frontend playback
- [ ] **Step 9: Commit**

---

## Task 22: Notion-Style Admin SPA (React)

**Files:** `src/admin/`, admin React app

- [ ] **Step 1: Setup React admin entry point** — `src/admin/index.js`, register with `wp_enqueue_script` on `mediashield` admin pages. Uses `@wordpress/element`, `@wordpress/components`, `@wordpress/api-fetch`.
- [ ] **Step 2: Build sidebar navigation** — React component with sections: Dashboard, Videos, Playlists, Tags, Milestones, Settings. Uses `@wordpress/components` NavigableMenu. Renders selected section in right panel.
- [ ] **Step 3: Build Settings section** — inline auto-save on blur/toggle for each field. No "Save" button. Toast via `@wordpress/notices`. Sections: General, Watermark, Export.
- [ ] **Step 4: Build Dashboard section** — overview cards + Chart.js charts. Fetches from `/mediashield/v1/analytics`. Period selector (today/7d/30d).
- [ ] **Step 5: Build Videos section** — list table with sort/filter/pagination. Row actions: edit, view, delete. Uses `@wordpress/components` table patterns.
- [ ] **Step 6: Build custom CSS polish** — modern spacing, card styles, elevated from default WP admin look. Light/dark follows WP admin color scheme.
- [ ] **Step 7: Browser test** — navigate admin pages, verify inline save, toast notifications
- [ ] **Step 8: Commit**

---

## Task 23: Setup Wizard

**Files:** `includes/Admin/SetupWizard.php`, `src/admin/wizard/`

- [ ] **Step 1: Build SetupWizard.php** — redirect to wizard on first activation (check `ms_wizard_complete` option). Register wizard admin page (no menu item — accessed via redirect only).
- [ ] **Step 2: Build 4-step React wizard** — Step 1: General (enable protection, require login). Step 2: Watermark (fields, opacity, color). Step 3: Connect platform (optional — Bunny/Vimeo API key). Step 4: Protect first video (paste URL or upload).
- [ ] **Step 3: Each step auto-saves** — POST to `/mediashield/v1/settings` on step completion
- [ ] **Step 4: "Skip" and "Back" buttons** on each step. "Finish" on step 4 sets `ms_wizard_complete = true`, redirects to dashboard.
- [ ] **Step 5: Browser test** — deactivate + reactivate plugin, verify wizard appears
- [ ] **Step 6: Commit**

---

## Task 24: Pro — Data Export (CSV + PDF + API)

**Files:** `mediashield-pro/includes/Export/CsvExporter.php`, `PdfExporter.php`, REST endpoints

- [ ] **Step 1: Implement CsvExporter.php** — methods for: export_watch_sessions(), export_milestones(), export_user_history(). Streams CSV directly (no temp file for large exports). Headers: Content-Type text/csv, Content-Disposition attachment.
- [ ] **Step 2: Implement PdfExporter.php** — uses Dompdf (composer require dompdf/dompdf). Generates summary report: overview stats, top videos chart (as SVG), user completion table.
- [ ] **Step 3: Add export REST endpoints** — GET /export/csv/{type} (watch_sessions|milestones|users), GET /export/pdf/report?period=30d. Admin only.
- [ ] **Step 4: Add export buttons to admin dashboard** — "Export CSV" and "Download PDF Report" buttons in Dashboard section
- [ ] **Step 5: Commit**

---

## Task 25: CLAUDE.md + Final Documentation

- [ ] **Step 1: Write mediashield/CLAUDE.md** — architecture, CPTs, tables, REST, hooks, blocks, testing
- [ ] **Step 2: Write mediashield-pro/CLAUDE.md** — pro features, dependency, DRM setup, platform drivers
- [ ] **Step 3: Commit**

---

## Task 26: E2E Integration Testing

- [ ] **Step 1: Test free full flow** — video CPT creation, block embed, watermark, tracking, milestones
- [ ] **Step 2: Test playlist** — create playlist, add videos, verify playback order and auto-play
- [ ] **Step 3: Test pro features** — extended watermark, role access, real-time dashboard
- [ ] **Step 4: Test Gutenberg block** — video picker, URL paste, preview in editor, frontend render
- [ ] **Step 5: Test setup wizard** — fresh activation → wizard → complete → dashboard
- [ ] **Step 6: Test data export (pro)** — CSV download, PDF report generation
- [ ] **Step 7: Test multisite** — activate on subsite, verify per-site tables and settings
- [ ] **Step 8: Commit**

---

## Verification Checklist

1. Activate free → custom tables created + CPTs registered
2. Activate pro → pro tables created
3. Deactivate pro → free works alone
4. Video CPT → create, edit, featured image auto-fetched
5. Playlist CPT → create, add videos, reorder
6. Gutenberg block → picker modal + URL paste both work
7. Single video page → /video/slug/ renders protected player
8. YouTube embed → wrapped + watermarked + tracked
9. Vimeo embed → same
10. Self-hosted video → Shaka Player + watermark + tracking
11. Watch 30s+ → heartbeat in `ms_watch_sessions`
12. Watch 100% → 4 milestones in `ms_milestones`
13. Log out → "Login required" overlay
14. Role restriction (pro) → subscriber blocked
15. Admin SPA → sidebar nav, inline save, toast notifications
16. Setup wizard → 4 steps, auto-save, redirect to dashboard
17. Dashboard → stats, charts, period selector
18. Real-time panel (pro) → shows active viewer
19. Playlist playback → auto-play next with countdown (pro)
20. Tags CRUD + filtering
21. Self-hosted upload → protected directory
22. Bunny connection (pro) → API test passes
23. Pro watermark → email + timestamp
24. Suspicious activity → multi-IP alert
25. CSV export (pro) → downloads correctly
26. PDF report (pro) → generates summary
27. DRM (pro) → Shaka Player plays encrypted
28. Offline (pro) → SW caches segments
29. Multisite → per-site activation works
30. i18n → strings extracted, .pot file generated
