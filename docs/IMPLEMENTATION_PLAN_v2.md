# MediaShield Implementation Plan v2

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build MediaShield — a WordPress video protection plugin (free + pro) with dynamic watermarking, multi-platform support, engagement analytics, milestone automation, upload hub, and Widevine DRM with offline playback.

**Architecture:** Monolith with modular internals. Two plugins: `mediashield` (free, wordpress.org) + `mediashield-pro` (pro add-on). Free handles: video CPT, playlist CPT, Gutenberg blocks (video + playlist), shortcode, video detection/wrapping, basic watermark (username+IP), standard download prevention, login-required access, basic analytics, milestones (hooks only), tags, self-hosted upload, setup wizard, full admin React SPA. Pro adds: configurable watermark, role-based access, heatmaps, suspicious activity, real-time dashboard, platform upload drivers (Bunny/Vimeo/YouTube/Wistia), frontend upload, milestone admin UI + actions, Widevine DRM, PWA offline, data export (CSV/PDF).

**Tech Stack:** PHP 8.1+, WordPress 6.5+ (Interactivity API), React (@wordpress/scripts) for admin SPA + Gutenberg block editor components, WordPress Interactivity API for frontend block rendering, vanilla JS for frontend player/watermark/tracker/protection, Shaka Player for video playback + DRM, @wordpress/components for admin UI, Chart.js for dashboard charts, Action Scheduler for background jobs, Shaka Packager for DRM content encryption, Dompdf for PDF export (pro), OpenSSL AES-256-CBC for secrets, full i18n from day 1, multisite network-aware.

**Data Model:** Hybrid — Videos as CPT (`mediashield_video`), Playlists as CPT (`mediashield_playlist`), analytics/sessions in custom tables. Playlist items in `ms_playlist_items` relationship table. **NO `ms_videos` custom table** — all `video_id` references throughout the codebase = the CPT post ID.

**Admin UX:** Notion-style full-page React app with sidebar nav (Dashboard, Videos, Playlists, Tags, Milestones, Settings), @wordpress/components + polish, inline auto-save, toast notifications, Chart.js dashboard. Setup wizard on first activation. **NO classic PHP settings pages, NO WP_List_Table.**

**Frontend JS:** Vanilla JS for player-wrapper, watermark, tracker, protection. Gutenberg blocks use Interactivity API for `render.php`. Block editor components (picker modal, URL paste) use React.

**Player:** Shaka Player for self-hosted/Bunny, iframe wrapper for YouTube/Vimeo/Wistia. Gutenberg block with video picker modal + URL paste.

**REST Namespaces:** `mediashield/v1` (free), `mediashield-pro/v1` (pro). Upload endpoints: `mediashield/v1/upload/*` for self-hosted (free), `mediashield-pro/v1/upload/*` for platform uploads (pro).

**Design Spec:** `docs/DESIGN_SPEC_v2.md` (same directory)

---

## Integration Patterns (Free ↔ Pro)

These 4 patterns ensure clean separation and zero half-cooked features:

### 1. Admin SPA Extension (Pro pages in Free SPA)
Free SPA exposes routes via `wp.hooks.addFilter('mediashield_admin_routes', ...)`. Pro JS bundle registers additional routes (Alerts, Platforms, DRM) via its own `addFilter` call. Free SPA works alone; Pro adds pages without modifying free code. Similar to WooCommerce admin extensibility.

### 2. Gutenberg Editor Extension (Pro sidebar in Free block)
Free's `edit.js` includes a `<Slot name="mediashield-video-access-controls" />` in the sidebar panel. Pro registers a `<Fill>` that adds: Access Type dropdown (Login/Email Gate/Role-Based/None), concurrent session limit, DRM toggle. Standard WordPress SlotFill pattern — zero modification to free's edit.js.

### 3. Settings REST Extension (Pro fields in Free endpoint)
Free's `SettingsController` applies: `apply_filters('mediashield_settings_response', $settings)` on GET and `apply_filters('mediashield_settings_update', $data)` on PUT. Pro hooks both filters to merge pro settings (watermark fields, font size, badge toggle, concurrent limit, sensitivity, etc.) into the single `/settings` endpoint.

### 4. Player Extension (Pro overrides Free player behavior)
Free's `PlayerWrapper` applies: `apply_filters('mediashield_player_type', 'standard', $video_id)`. Pro overrides this to `'drm'` for DRM-protected videos, triggering Shaka Player + EME instead of standard playback. If Pro deactivated, filter isn't registered → falls back to `'standard'`.

---

## Dependency DAG

```
Task 1 ─── Scaffold + CPTs + DB + npm/composer
  │
  ├─► Task 3 ─── Tags CRUD + REST
  ├─► Task 4 ─── Access Control + Sessions
  │     └─► Task 5 ─── Player + Watermark + Tracker + BUILD STEP
  │           └─► Task 6 ─── Milestones (wired into tracker)
  │                 └─► Task 7 ─── Video Block + Shortcode + Single Template
  ├─► Task 8 ─── Playlist Block + REST
  ├─► Task 9 ─── Self-Hosted Upload
  ├─► Task 10 ── Admin React SPA (needs T3,T4,T6 REST endpoints)
  │     └─► Task 11 ── Setup Wizard
  │
  ├─► Task 2 ─── Pro Scaffold + Pro DB
  │     ├─► Task 12 ── Role-Based Access
  │     ├─► Task 13 ── Advanced Watermark + Badge removal
  │     ├─► Task 14 ── Platform Drivers + Multisite
  │     │     └─► Task 15 ── Frontend Upload Form
  │     ├─► Task 16 ── Advanced Analytics
  │     │     └─► Task 26 ── Weekly Email Digest
  │     ├─► Task 17 ── Advanced Milestone Actions
  │     ├─► Task 18 ── Widevine DRM
  │     │     └─► Task 19 ── PWA Offline
  │     ├─► Task 20 ── Data Export
  │     └─► Task 25 ── Email Gate (uses SlotFill from T7)
  │
  ├─► Task 21 ── i18n (after all UI tasks)
  ├─► Task 22 ── Deletion Cascade + Crons
  ├─► Task 23 ── GDPR (needs T4)
  ├─► Task 24 ── Student My Videos (needs T4)
  ├─► Task 27 ── Documentation
  └─► Task 28 ── E2E Testing (after everything)
```

## Working State After Each Task

Every task must leave the plugin in a **fully functional state** — no broken features, no dangling references. Here is what works after each task:

| After Task | What Works | Verifiable? |
|------------|-----------|-------------|
| **T1** | Plugin activates. 2 CPTs registered. 5 tables created. Videos creatable via WP admin. `npm install` + `composer install` complete. | `wp plugin activate mediashield` ✓ |
| **T2** | Pro activates alongside free. 12 tables total. Pro dependency check works. Deactivate pro → free still works. | `wp plugin activate mediashield-pro` ✓ |
| **T3** | Tags CRUD works via REST API. Videos can be tagged. | `wp rest post mediashield/v1/tags` ✓ |
| **T4** | Session start/heartbeat/end works via REST. Access control gates non-logged-in users. Domain restriction active. Resume position returned. | `wp rest post mediashield/v1/session/start` ✓ |
| **T5** | **Full frontend protection working.** Visit any page with YouTube/Vimeo/video embed → wrapped, watermarked, tracked. Heartbeats fire. Right-click blocked. Shaka Player works for self-hosted. `npm run build` produces bundles. | Browser test ✓ |
| **T6** | Watch video to 100% → 4 milestones fire. Hooks callable by any plugin. | DB check ✓ |
| **T7** | Gutenberg video block works (picker + URL paste). Shortcode renders. Single video page at `/video/slug/`. Video SEO schema in page source. SlotFill slot available for Pro. | Browser test ✓ |
| **T8** | Playlist block works. Playlist player with sidebar, auto-play, countdown. Playlist REST CRUD works. | Browser test ✓ |
| **T9** | Upload a video file via REST → stored in protected directory → CPT created. | `wp rest post mediashield/v1/upload/init` ✓ |
| **T10** | **Full admin SPA working.** Dashboard, Videos (with protection badges + toggle), Playlists, Students, Tags, Milestones, Settings (with watermark preview + overlay customization). All inline auto-save. `addFilter` extension point for Pro routes. | Browser test ✓ |
| **T11** | First activation → wizard redirect → 4 steps → settings saved → redirect to dashboard. | Browser test ✓ |
| **T12** | Set `_ms_access_role=editor` on video → subscriber blocked, editor allowed. | Browser test ✓ |
| **T13** | Pro watermark fields (email, timestamp, custom). Badge removable via `ms_show_badge=false`. | Browser test ✓ |
| **T14** | Connect Bunny/Vimeo/YouTube/Wistia API → upload video → CPT created with platform_video_id. Multisite: network-wide connections. | REST test ✓ |
| **T15** | `[mediashield_upload]` shortcode → instructor uploads from frontend. | Browser test ✓ |
| **T16** | Heatmaps populate from playback events. Suspicious alerts with VPN sensitivity + dismiss. Real-time viewer panel. Playlist funnel. Device breakdown. Drop-off chart. | Admin dashboard ✓ |
| **T17** | Configure email/webhook/tag on 100% milestone → actions fire on completion. | Watch video → check email/webhook ✓ |
| **T18** | DRM-protected video plays via Shaka Player + EME. License proxy works. Keys stored encrypted. | Browser test (requires Widevine setup) ✓ |
| **T19** | "Save for Offline" → Service Worker caches segments → plays offline. | Browser test ✓ |
| **T20** | CSV export downloads. PDF report generates. REST API accessible. | Download test ✓ |
| **T21** | All strings wrapped in `__()`. `.pot` file generated and non-empty. | `wp i18n make-pot` ✓ |
| **T22** | Delete video → all related rows cleaned up. Crons registered and running (session cleanup, archival, heatmap aggregation, alert pruning). | DB check after delete ✓ |
| **T23** | WP Privacy export includes watch data. Erasure anonymizes IPs. IP anonymization setting works. | WP Tools → Export/Erase ✓ |
| **T24** | Student "My Videos" page shows progress bars, resume links, completed badges. | Browser test as student ✓ |
| **T25** | Email Gate: non-logged-in visitor enters email → video plays → email captured → webhook fires. SlotFill adds Access Type dropdown in editor. | Browser test ✓ |
| **T26** | Weekly digest email fires (manual trigger) with overview stats + top videos. | Email check ✓ |
| **T27** | CLAUDE.md for both plugins. Complete and accurate. | Read docs ✓ |
| **T28** | 43-point E2E verification passes. | Full test run ✓ |

**Rule: After every task, `wp plugin activate mediashield` (and `mediashield-pro` if task touches pro) must succeed with zero PHP errors. Features from previous tasks must still work. If anything breaks, fix before moving to the next task.**

---

## File Map

### mediashield (free)

```
wp-content/plugins/mediashield/
├── mediashield.php
├── uninstall.php
├── composer.json                            # PSR-4 autoload (MediaShield\\), require: action-scheduler
├── package.json                             # @wordpress/scripts, shaka-player, chart.js
├── includes/
│   ├── Core/
│   │   ├── Plugin.php
│   │   ├── Activator.php
│   │   ├── Deactivator.php
│   │   ├── Migrator.php
│   │   └── Assets.php
│   ├── CPT/
│   │   ├── VideoPostType.php                # Register mediashield_video CPT + meta
│   │   ├── PlaylistPostType.php             # Register mediashield_playlist CPT + meta
│   │   └── Thumbnail.php                    # Auto-fetch thumbnails from platform APIs
│   ├── Block/
│   │   ├── VideoBlock.php                   # Register mediashield/video block
│   │   ├── PlaylistBlock.php                # Register mediashield/playlist block
│   │   └── Shortcode.php                    # [mediashield id=123] shortcode handler
│   ├── DB/
│   │   └── Schema.php                       # ms_tags, ms_video_tags, ms_watch_sessions,
│   │                                        # ms_milestones, ms_playlist_items
│   ├── Player/
│   │   ├── PlayerWrapper.php                # Detect & wrap video embeds (double-wrap prevention)
│   │   ├── Watermark.php                    # Canvas overlay config & data injection
│   │   └── Protection.php                   # Anti-download measures (DOM manipulation)
│   ├── Access/
│   │   ├── AccessControl.php                # Login gate + mediashield_can_watch filter
│   │   └── SessionManager.php               # HMAC token: session_id + video_id + user_id + created_ts
│   ├── Analytics/
│   │   ├── Tracker.php                      # Receives heartbeat events, writes to DB
│   │   └── Reporter.php                     # Query aggregation for dashboard
│   ├── Milestones/
│   │   └── MilestoneTracker.php             # Check thresholds, INSERT IGNORE dedup, fire actions
│   ├── Upload/
│   │   ├── UploadManager.php                # Orchestrate upload flow, driver factory
│   │   └── Drivers/
│   │       ├── DriverInterface.php          # upload(), get_status(), delete(), get_embed_url()
│   │       └── SelfHosted.php               # Local wp-content/uploads/mediashield/ driver
│   ├── Tags/
│   │   └── TagManager.php                   # CRUD for ms_tags + ms_video_tags
│   ├── REST/
│   │   ├── SessionController.php            # /session/start, /session/heartbeat, /session/end
│   │   ├── TagController.php                # /tags, /videos/{id}/tags
│   │   ├── AnalyticsController.php          # /analytics/overview, /videos/{id}/stats, /milestones
│   │   ├── PlaylistController.php           # /playlists/{id}/items CRUD (add/remove/reorder)
│   │   ├── UploadController.php             # /upload/init, /upload/status/{id}
│   │   └── SettingsController.php           # GET/PUT /settings
│   ├── Privacy/
│   │   ├── PrivacyExporter.php              # WP privacy data exporter (GDPR)
│   │   └── PrivacyEraser.php                # WP privacy data eraser (GDPR)
│   ├── Admin/
│   │   ├── Menu.php                         # Top-level MediaShield menu, enqueue React SPA
│   │   └── SetupWizard.php                  # Redirect on first activation, wizard admin page
│   └── Cron/
│       └── Cleanup.php                      # Inactive session cleanup, session archival, pruning
├── src/
│   ├── blocks/
│   │   ├── video/
│   │   │   ├── block.json                   # mediashield/video block metadata
│   │   │   ├── edit.js                      # Editor component (picker modal + URL paste)
│   │   │   ├── render.php                   # Interactivity API frontend render
│   │   │   ├── view.js                      # viewScriptModule (Interactivity API store)
│   │   │   └── editor.css
│   │   ├── playlist/
│   │   │   ├── block.json                   # mediashield/playlist block metadata
│   │   │   ├── edit.js                      # Editor component (playlist picker)
│   │   │   ├── render.php                   # Interactivity API frontend render
│   │   │   ├── view.js                      # viewScriptModule (Interactivity API store)
│   │   │   └── editor.css
│   │   └── my-videos/
│   │       ├── block.json                   # mediashield/my-videos block metadata
│   │       ├── render.php                   # Student video library with progress
│   │       └── view.js                      # Filtering (All/In Progress/Completed)
│   ├── admin/
│   │   ├── index.js                         # Admin SPA entry point
│   │   ├── App.js                           # Router + sidebar layout
│   │   ├── components/
│   │   │   ├── Sidebar.js                   # NavigableMenu sidebar nav
│   │   │   ├── Toast.js                     # @wordpress/notices integration
│   │   │   └── VideoPickerModal.js          # Shared video picker (used in blocks + admin)
│   │   ├── pages/
│   │   │   ├── Dashboard.js                 # Overview cards + Chart.js charts
│   │   │   ├── Videos.js                    # Video list with sort/filter/pagination
│   │   │   ├── Playlists.js                 # Playlist management
│   │   │   ├── Tags.js                      # Tag CRUD
│   │   │   ├── Milestones.js                # Milestone overview
│   │   │   └── Settings.js                  # Inline auto-save settings
│   │   └── wizard/
│   │       ├── Wizard.js                    # 4-step setup wizard
│   │       └── steps/
│   │           ├── GeneralStep.js
│   │           ├── WatermarkStep.js
│   │           ├── PlatformStep.js
│   │           └── FirstVideoStep.js
│   └── admin.css                            # Admin SPA styles
├── assets/
│   ├── js/
│   │   ├── player-wrapper.js                # Vanilla JS: DOM scan, video detection, wrapping
│   │   ├── watermark.js                     # Vanilla JS: Canvas rendering, position swap
│   │   ├── tracker.js                       # Vanilla JS: 30s heartbeat, sendBeacon on unload
│   │   └── protection.js                    # Vanilla JS: right-click block, src hiding
│   └── css/
│       └── player.css                       # Protected player + login overlay styles
├── templates/
│   ├── login-overlay.php                    # "Login to watch" overlay
│   └── single-mediashield_video.php         # Single video page template
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
├── composer.json                            # PSR-4 (MediaShieldPro\\), require: dompdf/dompdf
├── includes/
│   ├── Core/
│   │   ├── Plugin.php                       # Pro init, hooks into free, dependency check
│   │   ├── Activator.php
│   │   └── Migrator.php
│   ├── DB/
│   │   └── Schema.php                       # ms_playback_events, ms_platform_connections,
│   │                                        # ms_upload_queue, ms_activity_alerts,
│   │                                        # ms_drm_licenses, ms_heatmap_cache, ms_drm_keys
│   ├── Watermark/
│   │   └── AdvancedConfig.php               # Hooks mediashield_watermark_config filter
│   ├── Access/
│   │   └── RoleAccess.php                   # Hooks mediashield_can_watch, checks _ms_access_role
│   ├── Analytics/
│   │   ├── Heatmap.php                      # Reads ms_heatmap_cache, aggregation cron
│   │   ├── SuspiciousActivity.php           # Writes ms_activity_alerts
│   │   └── RealtimeDashboard.php            # Active viewer queries
│   ├── Milestones/
│   │   └── AdvancedActions.php              # Email, webhook, tag actions
│   ├── Upload/
│   │   └── Drivers/
│   │       ├── BunnyStream.php              # Bunny Stream API (tus upload)
│   │       ├── VimeoApi.php                 # Vimeo API v3 (OAuth + tus)
│   │       ├── YouTubeApi.php               # YouTube Data API v3 (resumable upload)
│   │       └── WistiaApi.php                # Wistia Upload API
│   ├── DRM/
│   │   ├── KeyServer.php                    # 128-bit key pairs, stores in ms_drm_keys, AES-256-CBC
│   │   ├── Packager.php                     # Shaka Packager CLI, DASH+CENC, Action Scheduler job
│   │   ├── WidevineLicense.php              # License proxy, writes ms_drm_licenses
│   │   └── OfflineManager.php               # Service Worker registration, offline manifest
│   ├── Access/
│   │   └── EmailGate.php                    # Email capture overlay for lead gen
│   ├── Export/
│   │   ├── CsvExporter.php                  # Streaming CSV export
│   │   └── PdfExporter.php                  # Dompdf summary reports
│   ├── Reports/
│   │   └── WeeklyDigest.php                 # Weekly email summary report
│   ├── REST/
│   │   ├── HeatmapController.php            # /analytics/heatmap/{id}
│   │   ├── SuspiciousController.php         # /analytics/suspicious
│   │   ├── RealtimeController.php           # /realtime/viewers
│   │   ├── DRMController.php                # /drm/license, /drm/offline, /drm/revoke
│   │   ├── PlatformController.php           # GET/POST/DELETE /platforms
│   │   ├── MilestoneConfigController.php    # GET/PUT /milestones/config
│   │   └── ExportController.php             # /export/csv/{type}, /export/pdf/report
│   ├── Admin/
│   │   ├── PlatformConnections.php          # Connect/disconnect UI per platform
│   │   └── DRMSettings.php                  # Widevine config, Shaka path, license duration
│   └── Cron/
│       └── ProCleanup.php                   # Heatmap aggregation, alert pruning, upload cleanup
├── assets/
│   ├── js/
│   │   ├── drm-player.js                   # Shaka Player init, EME license filter, DASH loading
│   │   ├── offline-sw.js                    # Service Worker: cache encrypted DASH segments
│   │   ├── frontend-upload.js               # Instructor upload form JS
│   │   └── admin/
│   │       ├── heatmap.js                   # Heatmap visualization (Chart.js)
│   │       └── realtime.js                  # Active viewers 30s poll
│   └── css/
│       ├── frontend-upload.css
│       └── admin-pro.css
├── templates/
│   └── frontend-upload.php                  # [mediashield_upload] shortcode template
└── tests/
    └── phpunit/
        └── Unit/
            ├── RoleAccessTest.php
            ├── SuspiciousActivityTest.php
            └── HeatmapTest.php
```

---

## Database Tables Summary

### Free Tables (5 tables, created by mediashield)

| Table | Purpose |
|-------|---------|
| `ms_tags` | Tag definitions (name, slug, description) |
| `ms_video_tags` | Many-to-many: video CPT post ID <-> tag ID |
| `ms_watch_sessions` | Per-user watch sessions with heartbeat tracking |
| `ms_milestones` | Milestone completion records (deduplicated) |
| `ms_playlist_items` | Playlist <-> video ordering (playlist_id, video_id, sort_order) |

### Pro Tables (7 tables, created by mediashield-pro)

| Table | Purpose |
|-------|---------|
| `ms_playback_events` | Granular play/pause/seek events for heatmaps |
| `ms_platform_connections` | Platform API credentials (encrypted) |
| `ms_upload_queue` | Async upload job queue |
| `ms_activity_alerts` | Suspicious activity alerts |
| `ms_drm_licenses` | Widevine license records |
| `ms_heatmap_cache` | Pre-aggregated heatmap data (hourly cron) |
| `ms_drm_keys` | DRM content encryption keys (AES-256-CBC encrypted) |

**Total: 12 tables (5 free + 7 pro)**

---

## Task 1: Free Plugin Scaffold + CPT Registration + DB Schema + npm init

**Files:**
- Create: `mediashield/mediashield.php`
- Create: `mediashield/composer.json`
- Create: `mediashield/package.json`
- Create: `mediashield/includes/Core/Plugin.php`
- Create: `mediashield/includes/Core/Activator.php`
- Create: `mediashield/includes/Core/Deactivator.php`
- Create: `mediashield/includes/Core/Migrator.php`
- Create: `mediashield/includes/DB/Schema.php`
- Create: `mediashield/includes/CPT/VideoPostType.php`
- Create: `mediashield/includes/CPT/PlaylistPostType.php`
- Create: `mediashield/uninstall.php`

- [ ] **Step 1: Create composer.json with PSR-4 autoloading + Action Scheduler**

```json
{
    "name": "mediashield/mediashield",
    "description": "Video protection for WordPress",
    "type": "wordpress-plugin",
    "autoload": {
        "psr-4": { "MediaShield\\": "includes/" }
    },
    "require": {
        "php": ">=8.1",
        "woocommerce/action-scheduler": "^3.7"
    }
}
```

Run: `cd wp-content/plugins/mediashield && composer install`

- [ ] **Step 2: Create package.json with build toolchain**

```json
{
    "name": "mediashield",
    "scripts": {
        "build": "wp-scripts build",
        "start": "wp-scripts start"
    },
    "devDependencies": {
        "@wordpress/scripts": "^28.0"
    },
    "dependencies": {
        "shaka-player": "^4.8",
        "chart.js": "^4.4"
    }
}
```

Run: `cd wp-content/plugins/mediashield && npm install`

- [ ] **Step 3: Create mediashield.php bootstrap**

Constants: `MEDIASHIELD_VERSION` (1.0.0), `MEDIASHIELD_FILE`, `MEDIASHIELD_PATH`, `MEDIASHIELD_URL`, `MEDIASHIELD_DB_VERSION` (1).

Activation hook -> `Activator::activate()`. Deactivation -> `Deactivator::deactivate()`. `plugins_loaded` -> `Plugin::instance()`.

- [ ] **Step 4: Create DB/Schema.php**

5 free tables (NO `ms_videos` table):

- `ms_tags` — id, name, slug, description, created_by, created_at. UNIQUE on slug.
- `ms_video_tags` — video_id (= CPT post ID), tag_id, tagged_by, tagged_at. PRIMARY KEY (video_id, tag_id).
- `ms_watch_sessions` — id, video_id (= CPT post ID), user_id, session_token, ip_address, user_agent, device_type, browser, started_at, last_heartbeat, total_seconds, max_position, completion_pct, is_active. Indexes: idx_video_user, idx_active, idx_user, idx_started.
- `ms_milestones` — id, video_id (= CPT post ID), user_id, milestone_pct, reached_at, session_id. UNIQUE KEY (video_id, user_id, milestone_pct).
- `ms_playlist_items` — id, playlist_id (= playlist CPT post ID), video_id (= video CPT post ID), sort_order, added_at. Indexes: idx_playlist (playlist_id, sort_order), idx_video (video_id).

All column definitions per DESIGN_SPEC.md. All `video_id` columns reference the `mediashield_video` CPT post ID.

- [ ] **Step 5: Create Migrator.php**

Version tracking via `ms_db_version` option. Compares on `plugins_loaded`, runs `Schema::create_tables()` via `dbDelta` if stale.

- [ ] **Step 6: Create Activator.php**

Check PHP >= 8.1, WP >= 6.5. Run migrations. Set defaults: `ms_enabled=true`, `ms_default_protection=standard`, `ms_require_login=true`, `ms_watermark_opacity=0.3`, `ms_watermark_color=#ffffff`, `ms_watermark_swap_interval=20`. Add `upload_mediashield` cap to admin. Flush rewrite rules.

- [ ] **Step 7: Register mediashield_video CPT in VideoPostType.php**

Public, `show_in_rest: true`, supports: title, editor, thumbnail, custom-fields. Labels, rewrite slug: `video`. Menu icon: `dashicons-video-alt3`.

Register post meta (all `show_in_rest: true`):
- `_ms_platform` — string (self, bunny, youtube, vimeo, wistia, iframe)
- `_ms_platform_video_id` — string
- `_ms_source_url` — string
- `_ms_protection_level` — string (none, standard, drm)
- `_ms_access_role` — string
- `_ms_duration` — integer

- [ ] **Step 8: Register mediashield_playlist CPT in PlaylistPostType.php**

Public, `show_in_rest: true`, supports: title, editor, thumbnail. Labels, rewrite slug: `playlist`.

Register playlist meta (all `show_in_rest: true`):
- `_ms_autoplay` — boolean
- `_ms_countdown` — integer (3/5/10)
- `_ms_loop` — boolean
- `_ms_shuffle` — boolean

All playlist features (autoplay, countdown, loop, shuffle) are in FREE.

- [ ] **Step 8b: Create Thumbnail.php — auto-fetch thumbnails from platform APIs**

On `save_post_mediashield_video` hook: if no featured image set, fetch thumbnail from platform API based on `_ms_platform` + `_ms_platform_video_id` meta:
- YouTube: `https://img.youtube.com/vi/{id}/maxresdefault.jpg`
- Vimeo: oEmbed API → `thumbnail_url`
- Bunny: Bunny Stream API → thumbnail URL
- Wistia: Wistia Data API → `thumbnail.url`
- Self-hosted: default placeholder (FFmpeg extraction deferred to roadmap)

Download image via `media_sideload_image()`, set as featured image. Admin can override manually at any time (standard WP featured image).

- [ ] **Step 9: Create Deactivator.php + uninstall.php**

Deactivator: `flush_rewrite_rules()`. Uninstall: drop `ms_*` tables, delete `ms_*` options, remove capability, delete CPT posts.

- [ ] **Step 10: Create Core/Plugin.php singleton**

Registers hooks: `init` (CPTs), `rest_api_init`, `admin_menu`, `wp_enqueue_scripts`, `admin_enqueue_scripts`, `template_redirect`. Fires `mediashield_loaded` action.

- [ ] **Step 11: Verify tables + CPTs created**

```bash
wp --path="/Users/varundubey/Local Sites/forums/app/public" plugin activate mediashield
wp --path="/Users/varundubey/Local Sites/forums/app/public" db tables --all-tables | grep ms_
wp --path="/Users/varundubey/Local Sites/forums/app/public" post-type list --format=table | grep mediashield
wp --path="/Users/varundubey/Local Sites/forums/app/public" rewrite flush
```

Expected: 5 tables + 2 CPTs registered.

- [ ] **Step 12: Commit**

```bash
git add wp-content/plugins/mediashield/
git commit -m "feat(mediashield): scaffold free plugin with CPTs, DB schema, composer + npm"
```

---

## Task 2: Pro Plugin Scaffold + Pro DB Schema

**Files:**
- Create: `mediashield-pro/mediashield-pro.php`
- Create: `mediashield-pro/composer.json`
- Create: `mediashield-pro/includes/Core/Plugin.php`
- Create: `mediashield-pro/includes/Core/Activator.php`
- Create: `mediashield-pro/includes/Core/Migrator.php`
- Create: `mediashield-pro/includes/DB/Schema.php`

- [ ] **Step 1: Create pro composer.json**

```json
{
    "name": "mediashield/mediashield-pro",
    "description": "MediaShield Pro — advanced video protection",
    "type": "wordpress-plugin",
    "autoload": {
        "psr-4": { "MediaShieldPro\\": "includes/" }
    },
    "require": { "php": ">=8.1" }
}
```

Note: Dompdf is added later in Task 20 when export is implemented.

Run: `cd wp-content/plugins/mediashield-pro && composer install`

- [ ] **Step 2: Create pro bootstrap with dependency check**

`plugins_loaded:20`. Check `mediashield/mediashield.php` active via `is_plugin_active()`, else admin notice + bail.

- [ ] **Step 3: Create Pro DB/Schema.php**

7 pro tables:

- `ms_playback_events` — id, session_id, event_type (ENUM: play, pause, seek, buffer, complete, focus_lost, focus_gained), position, timestamp, metadata (JSON). Indexes: idx_session, idx_position.
- `ms_platform_connections` — id, platform, api_key (TEXT, encrypted), api_secret (TEXT, encrypted), extra_config (JSON), is_active, connected_by, connected_at. Index: idx_platform.
- `ms_upload_queue` — id, video_id (= CPT post ID, nullable), file_path, target_platform, status (ENUM: pending, uploading, processing, complete, failed), progress, error_message, uploaded_by, created_at, completed_at. Index: idx_status.
- `ms_activity_alerts` — id, user_id, video_id (= CPT post ID), alert_type (ENUM: multi_ip, devtools, rapid_seek, concurrent_stream, vpn_detected), severity, details (JSON), created_at. Indexes: idx_user, idx_severity, idx_created.
- `ms_drm_licenses` — id, video_id (= CPT post ID), user_id, license_type (ENUM: streaming, persistent), license_token, device_id, expires_at, created_at, revoked_at. Indexes: idx_video_user, idx_expires.
- `ms_heatmap_cache` — id, video_id (= CPT post ID), position_bucket, view_count, avg_duration, last_aggregated. Index: idx_video_position.
- `ms_drm_keys` — id, video_id (= CPT post ID), key_id (VARCHAR 255), content_key_encrypted (TEXT, AES-256-CBC), iv (VARCHAR 32), created_at. UNIQUE KEY on video_id.

- [ ] **Step 4: Create Pro Activator + Migrator**

Same pattern as free: version tracking via `ms_pro_db_version`, `dbDelta` for table creation.

- [ ] **Step 5: Create Pro Core/Plugin.php**

Hooks: `mediashield_watermark_config` filter, `mediashield_upload_drivers` filter, `mediashield_can_watch` filter, `mediashield_milestone_reached` action, `mediashield_session_started` action.

- [ ] **Step 5b: Implement Pro Deactivator.php with graceful fallback logic**

On pro deactivation (NOT uninstall):
- **Tables preserved** — pro tables are NOT dropped. Data persists for re-activation.
- **DRM videos fall back** — free plugin's PlayerWrapper checks `function_exists('MediaShieldPro\\...')`. If pro absent and `protection_level=drm`, fall back to standard protection (watermark + tracking, no Shaka Player). Add `mediashield_player_type` filter in free plugin that pro overrides.
- **Watermark reverts** — pro filter removed from `mediashield_watermark_config`, free default (username+IP) takes over automatically.
- **Milestone hooks still fire** — `mediashield_milestone_reached` action fires normally, pro's `AdvancedActions` handler simply isn't registered, so nothing executes. No errors.
- **Pro admin menu items removed** — pro's `admin_menu` hooks are gone, subpages (Alerts, Platforms, DRM) disappear cleanly.
- **Pro crons unscheduled** — `register_deactivation_hook` calls `wp_clear_scheduled_hook()` for: `ms_heatmap_aggregation`, `ms_alert_pruning`, `ms_upload_cleanup`, `ms_weekly_digest`.
- **Badge stays** — `ms_show_badge` setting persists but if it was set to false (Pro white-label), it reverts to true (badge shown) since that's a Pro feature. Free plugin checks: `if (!defined('MEDIASHIELD_PRO_VERSION')) { force badge visible }`.
- **Platform connections preserved** — `ms_platform_connections` table stays. Platform-uploaded video embeds continue to work (URLs are static). But new uploads to platforms are disabled.

- [ ] **Step 6: Verify 12 tables total**

```bash
wp --path="/Users/varundubey/Local Sites/forums/app/public" plugin activate mediashield-pro
wp --path="/Users/varundubey/Local Sites/forums/app/public" db tables --all-tables | grep ms_
```

Expected: 12 tables (5 free + 7 pro).

- [ ] **Step 7: Commit**

```bash
git add wp-content/plugins/mediashield-pro/
git commit -m "feat(mediashield-pro): scaffold pro plugin with 7 DB tables"
```

---

## Task 3: Tags CRUD + REST API

**Files:**
- Create: `mediashield/includes/Tags/TagManager.php`
- Create: `mediashield/includes/REST/TagController.php`
- Create: `mediashield/tests/phpunit/bootstrap.php`
- Create: `mediashield/tests/phpunit/Unit/TagManagerTest.php`

`video_id` in `ms_video_tags` = CPT post ID. No `ms_videos` table involved.

- [ ] **Step 1: Write TagManager tests** (create, duplicate slug, get, delete, assign to video CPT, get_for_video)
- [ ] **Step 2: Run tests — fail**
- [ ] **Step 3: Implement TagManager.php** — CRUD on `ms_tags` + assign/unassign/get_for_video on `ms_video_tags`. `video_id` = CPT post ID.
- [ ] **Step 4: Run tests — pass**
- [ ] **Step 5: Build TagController.php** — `mediashield/v1` namespace. GET/POST/PATCH/DELETE `/tags`, GET/POST/DELETE `/videos/{id}/tags`.
- [ ] **Step 6: Register routes in Plugin.php**
- [ ] **Step 7: Verify via WP-CLI REST**

```bash
wp --path="..." rest get mediashield/v1/tags --user=1
```

- [ ] **Step 8: Commit**

```bash
git commit -m "feat(mediashield): tags CRUD with REST API (ms_tags + ms_video_tags)"
```

---

## Task 4: Access Control + Session Manager

**Files:**
- Create: `mediashield/includes/Access/AccessControl.php`
- Create: `mediashield/includes/Access/SessionManager.php`
- Create: `mediashield/includes/REST/SessionController.php`
- Create: `mediashield/tests/phpunit/Unit/AccessControlTest.php`
- Create: `mediashield/tests/phpunit/Unit/SessionManagerTest.php`

- [ ] **Step 1: Write AccessControl tests** (logged-in OK, logged-out denied when `ms_require_login=true`, `mediashield_can_watch` filter override, admin bypass)
- [ ] **Step 2: Write SessionManager tests** (create returns HMAC token, validate HMAC without DB lookup, heartbeat updates session, end marks inactive)
- [ ] **Step 3: Run tests — fail**
- [ ] **Step 4: Implement AccessControl.php**

Login gate based on `ms_require_login` option + `mediashield_can_watch` filter. Returns `{allowed: bool, reason: string}`.

**Domain restriction (free):** Check HTTP referer against `ms_allowed_domains` option (comma-separated list of allowed domains). If empty, allow all domains. If set, block playback from non-whitelisted domains. Prevents embedding protected videos on pirate/unauthorized sites. Applied in `can_watch()` before session start. Setting in admin: "Allowed Domains" textarea.
- [ ] **Step 5: Implement SessionManager.php**

HMAC token generation: `hash_hmac('sha256', "{session_id}:{video_id}:{user_id}:{created_ts}", AUTH_SALT)`. Token = `{session_id}:{video_id}:{user_id}:{created_ts}:{hmac}`.

Validation: split token, recompute HMAC, compare. **No DB lookup for validation.**

Methods:
- `start($video_id, $user_id)` -> **Session dedup:** check for existing active session (same user + video, last heartbeat < 2 min ago) — reuse it instead of creating a new row. Otherwise create `ms_watch_sessions` row. Returns token + config + **`resume_position`** (the `max_position` from the last session for this user+video, or 0 if first watch). This enables "resume watching."
- `heartbeat($token, $position, $duration, $playing, $focused)` -> validates HMAC, updates session.
- `end($token)` -> marks `is_active = 0`.
- `completion_pct = (max_position / duration) * 100`.
- `revoke_user($user_id)` -> marks ALL active sessions for this user as inactive. For bulk access revocation when membership expires. Fires `mediashield_user_access_revoked` action.
- **Concurrent session limit (Pro):** On `start()`, count active sessions for this user (WHERE `user_id = X AND is_active = 1 AND last_heartbeat > NOW() - INTERVAL 2 MINUTE`). If count >= `ms_max_concurrent_streams` (default: 2, admin-configurable: 1-5), reject with `{allowed: false, reason: 'Too many active streams. Please close another video first.'}`. This is the #1 credential sharing blocker. Fires `mediashield_concurrent_limit_reached` action for admin alerts.

- [ ] **Step 6: Run tests — pass**
- [ ] **Step 7: Build SessionController.php** — `mediashield/v1` namespace.
  - POST `/session/start` — returns `{ session_token, resume_position, watermark_config, video }`
  - POST `/session/heartbeat`
  - POST `/session/end`
  - POST `/session/revoke-user` (admin only) — params: `user_id`. Calls `revoke_user()`. For membership/LMS integration.
- [ ] **Step 8: Test session flow via REST** — verify `resume_position` is returned, verify session dedup

```bash
wp --path="..." rest post mediashield/v1/session/start --user=1 '{"video_id": 123}'
```

- [ ] **Step 9: Commit**

```bash
git commit -m "feat(mediashield): access control + HMAC session manager with REST endpoints"
```

---

## Task 5: Player Wrapper + Watermark + Protection + Tracker

**All frontend JS is vanilla JS (NOT Interactivity API, NOT React).**

**Files:**
- Create: `mediashield/includes/Player/PlayerWrapper.php`
- Create: `mediashield/includes/Player/Watermark.php`
- Create: `mediashield/includes/Player/Protection.php`
- Create: `mediashield/includes/Core/Assets.php`
- Create: `mediashield/assets/js/player-wrapper.js`
- Create: `mediashield/assets/js/watermark.js`
- Create: `mediashield/assets/js/tracker.js`
- Create: `mediashield/assets/js/protection.js`
- Create: `mediashield/assets/css/player.css`
- Create: `mediashield/templates/login-overlay.php`

- [ ] **Step 1: Create PlayerWrapper.php**

Output buffer on `template_redirect`. Regex patterns for YouTube (`<iframe src="*youtube.com/embed/*">`), Vimeo (`<iframe src="*player.vimeo.com/*">`), Bunny (`<iframe src="*iframe.mediadelivery.net/*">`), Wistia (`<div class="wistia_embed*">`), `<video>` tags, generic iframe. Also detect `youtube-nocookie.com` variant.

**Double-wrap prevention:** Check for existing `.ms-protected-player` class before wrapping. Skip if already wrapped.

**Lazy iframe fallback:** Add a `MutationObserver` in `player-wrapper.js` that watches for dynamically injected iframes (lazy-loaded by page builders like Elementor/Divi). When a new iframe appears matching video patterns, wrap and protect it on the fly.

**Admin-configurable URL patterns:** Settings include a textarea for custom URL patterns (regex). Handles edge cases where themes/plugins use non-standard embed formats.

Each match: look up or auto-register as `mediashield_video` CPT post. Wrap in `.ms-protected-player` div with `data-video-id` (= CPT post ID), canvas overlay, protection overlay.

- [ ] **Step 2: Create Watermark.php**

Server-side config: `{display_name} . {ip_address}` (free). Opacity, color, swap interval from options. Passed to JS via `wp_localize_script('mediashield-player', 'mediashieldConfig', ...)`.

- [ ] **Step 3: Create Protection.php**

`oncontextmenu="return false"`, `controlsList="nodownload"`, move `src` to `data-ms-src` (loaded by JS).

- [ ] **Step 4: Create Assets.php**

Register/enqueue `player-wrapper.js`, `watermark.js`, `tracker.js`, `protection.js` (all vanilla JS) only when videos detected on page. Localize `mediashieldConfig` with REST URL, nonce, watermark config, user login status.

- [ ] **Step 5: Create player-wrapper.js (vanilla JS)**

`DOMContentLoaded` -> scan for `.ms-protected-player` elements. For each:
  - Check `data-protection-level` — if `none`, skip all protection (no watermark, no login gate, no session). Just render the video normally. This enables **free preview/trailer videos**.
  - Check `mediashieldConfig.isLoggedIn`, if false show login overlay.
  - POST `/session/start` — receive `resume_position`. If > 0, **seek player to that position** with a "Resume from X:XX?" toast prompt. This enables **resume watching**.
  - Init watermark, tracker, protection per video.

- [ ] **Step 6: Create watermark.js (vanilla JS)**

Canvas rendering: `ctx.font`, `ctx.fillStyle`, `ctx.globalAlpha`, `ctx.fillText()`. Random position swap via `setInterval(config.interval * 1000)`. MutationObserver: if canvas removed from DOM, pause video. ResizeObserver: re-render on container resize. **Mobile-responsive sizing:** on screens < 640px, reduce font size, truncate long text (show username only, drop IP/timestamp), reposition to bottom-right to avoid obstructing content.

- [ ] **Step 7: Create tracker.js (vanilla JS)**

30-second heartbeat interval: collect position, duration, playing, focused. POST `/session/heartbeat`. `navigator.sendBeacon()` on `beforeunload` for `/session/end`.

- [ ] **Step 8: Create protection.js (vanilla JS)**

Right-click block on `.ms-protected-player`, Ctrl+S/Cmd+S block, load `data-ms-src` into `src` via JS.

**"Protected by MediaShield" badge (free):** Small translucent badge in bottom-left corner of player. Links to mediashield.com (builds brand awareness + social proof). CSS-only, no JS. **Pro: badge removable** via setting `ms_show_badge = false` (white-label). This is the same model as Presto Player's branding and Wistia's free-tier badge.

- [ ] **Step 9: Create player.css + login-overlay.php**

Player styles: `.ms-protected-player` relative positioned, canvas overlay absolute, z-index layering. Login overlay: centered message with login button/link.

- [ ] **Step 9b: Implement server-side heartbeat batching in Tracker.php**

`Tracker::process_heartbeat()` — the PHP handler called by SessionController on each heartbeat POST:

**With object cache (Redis/Memcached):** detected via `wp_using_ext_object_cache()`. Queue heartbeat data into `wp_cache_set('ms_heartbeat_batch', ...)`. Accumulate up to 60 seconds of heartbeats. Background flush via Action Scheduler (every 60s): read batch from cache, bulk `UPDATE ms_watch_sessions SET ... CASE id WHEN X THEN Y ...`, call `MilestoneTracker::check()` for any sessions that crossed thresholds, clear cache.

**Without object cache (fallback):** Transient micro-batch. Collect up to 10 heartbeats in a transient (`set_transient('ms_heartbeat_batch', ...)`). Flush on the 10th heartbeat or after 60 seconds (whichever first). For < 100 concurrent viewers this is fine. For larger sites, admin sees a notice recommending Redis.

**Emergency fallback:** If both fail, direct DB write per heartbeat (no batching). Functional but not performant at scale.

- [ ] **Step 10: Wire in Plugin.php**

`template_redirect` -> PlayerWrapper output buffer. `wp_enqueue_scripts` -> Assets (conditional). `admin_enqueue_scripts` -> admin assets.

- [ ] **Step 11: Run npm build**

```bash
cd wp-content/plugins/mediashield && npm run build
```

Produces: `build/player-wrapper.js` (bundled with Shaka Player), `build/watermark.js`, `build/tracker.js`, `build/protection.js`. Assets.php enqueues from `build/` directory.

Verify: Self-hosted video plays via Shaka Player (not native `<video>`). YouTube/Vimeo iframes wrapped with overlay.

- [ ] **Step 12: Browser test**

Navigate to a page with YouTube/Vimeo embed -> verify `.ms-protected-player` wrapper, canvas watermark rendering, heartbeat network requests in DevTools, right-click blocked. Navigate to a self-hosted MP4 → verify Shaka Player loads. Test `protection_level=none` video → plays without watermark/login gate. Test resume: watch to 50%, leave page, return → "Resume from X:XX?" prompt.

- [ ] **Step 13: Commit**

```bash
git commit -m "feat(mediashield): player wrapper with watermark, protection, tracker, Shaka Player"
```

---

## Task 6: Milestone System

**Files:**
- Create: `mediashield/includes/Milestones/MilestoneTracker.php`
- Create: `mediashield/tests/phpunit/Unit/MilestoneTrackerTest.php`

- [ ] **Step 1: Write tests** (fires at 25%, fires at 100%, no duplicates on re-watch, multiple fire on jump from 20% to 60%, custom thresholds via `mediashield_milestone_thresholds` filter)
- [ ] **Step 2: Run tests — fail**
- [ ] **Step 3: Implement MilestoneTracker.php**

`check($video_id, $user_id, $completion_pct, $session_id)`:
- Get thresholds: `apply_filters('mediashield_milestone_thresholds', [25, 50, 75, 100])`
- For each threshold <= completion_pct: `INSERT IGNORE INTO ms_milestones` (dedup by UNIQUE key)
- If INSERT succeeded (not duplicate): fire `do_action('mediashield_milestone_reached', $user_id, $video_id, $pct)` + `do_action("mediashield_milestone_{$pct}", $user_id, $video_id)`
- Return array of newly fired milestones

- [ ] **Step 4: Run tests — pass**
- [ ] **Step 5: Wire into tracker heartbeat**

In `Tracker.php` (or `SessionManager::heartbeat`): after updating session completion_pct, call `MilestoneTracker::check()`.

- [ ] **Step 6: Commit**

```bash
git commit -m "feat(mediashield): milestone system with INSERT IGNORE dedup and action hooks"
```

---

## Task 7: Gutenberg Video Block + Shortcode

**Editor components use React. Frontend render uses Interactivity API.**

**Files:**
- Create: `mediashield/src/blocks/video/block.json`
- Create: `mediashield/src/blocks/video/edit.js`
- Create: `mediashield/src/blocks/video/render.php`
- Create: `mediashield/src/blocks/video/view.js`
- Create: `mediashield/src/blocks/video/editor.css`
- Create: `mediashield/src/admin/components/VideoPickerModal.js`
- Create: `mediashield/includes/Block/VideoBlock.php`
- Create: `mediashield/includes/Block/Shortcode.php`
- Create: `mediashield/templates/single-mediashield_video.php`

- [ ] **Step 1: Create block.json for mediashield/video**

Attributes: `videoId` (number), `url` (string). Supports: align, spacing. Category: media. Icon: video-alt3. `render` points to `render.php`. `viewScriptModule` points to view.js.

- [ ] **Step 2: Build edit.js (React, editor only)**

Two insertion modes:
- **"Choose from library"** button -> opens VideoPickerModal
- **"Paste URL"** input field -> regex auto-detect (YouTube/Vimeo/Bunny/Wistia), auto-creates video CPT post via REST (`/wp/v2/mediashield_video`), sets block `videoId` attribute

Preview: shows thumbnail + platform badge once video selected. Uses `@wordpress/components` (Button, TextControl, Placeholder, Modal) and `@wordpress/api-fetch`.

**Pro extension point:** Include `<Slot name="mediashield-video-access-controls" />` in the `InspectorControls` sidebar panel. Free renders: Protection Level selector (none/standard). Pro uses `<Fill>` to add: Access Type dropdown (Login/Email Gate/Role-Based/None), concurrent session limit input, DRM toggle. This is the standard WordPress SlotFill pattern — Pro never modifies free's edit.js.

- [ ] **Step 3: Build VideoPickerModal.js (React, shared component)**

Modal using `@wordpress/components` Modal. Fetches from `/wp/v2/mediashield_video`. Searchable, filterable by tag/platform. Click to select -> sets videoId on block.

- [ ] **Step 4: Build render.php (Interactivity API)**

Outputs `.ms-protected-player` wrapper with `data-wp-interactive="mediashield/video"` directives. Server-side: fetch video CPT post, get platform/source_url/protection meta. Render protected player HTML.

- [ ] **Step 5: Build view.js (viewScriptModule, Interactivity API store)**

`wp_interactivity_state('mediashield/video', ...)`. Handles player init, session start, watermark/tracker/protection wiring on the frontend.

- [ ] **Step 6: Register block in VideoBlock.php**

`register_block_type(__DIR__ . '/../../src/blocks/video')` in `init` hook.

- [ ] **Step 7: Build Shortcode.php**

Register `[mediashield id=123]` shortcode. Renders same protected player HTML as the block's render.php. Calls PlayerWrapper to wrap the output.

- [ ] **Step 8: Create single-mediashield_video.php template**

Single video page for the CPT permalink (`/video/slug/`). Loads theme header/footer, renders protected player in content area. Register via `single_template` filter in VideoBlock.php.

- [ ] **Step 8b: Add Video SEO schema markup (JSON-LD)**

On single video pages and wherever the block renders, inject `<script type="application/ld+json">` with `VideoObject` schema: `name`, `description`, `thumbnailUrl`, `uploadDate`, `duration`, `contentUrl` (only for public/preview videos), `embedUrl`. Helps videos appear in Google Video search results. Hook into `wp_head` on single-mediashield_video pages.

- [ ] **Step 9: Run npm build and verify**

```bash
cd wp-content/plugins/mediashield && npm run build
```

- [ ] **Step 10: Browser test**

Insert video block in Gutenberg editor -> test picker modal + URL paste. Verify frontend render shows protected player. Test shortcode in classic content. Visit `/video/slug/` single page. Verify VideoObject JSON-LD in page source.

- [ ] **Step 11: Commit**

```bash
git commit -m "feat(mediashield): Gutenberg video block + [mediashield] shortcode + single template"
```

---

## Task 8: Gutenberg Playlist Block + Playlist REST

**Files:**
- Create: `mediashield/src/blocks/playlist/block.json`
- Create: `mediashield/src/blocks/playlist/edit.js`
- Create: `mediashield/src/blocks/playlist/render.php`
- Create: `mediashield/src/blocks/playlist/view.js`
- Create: `mediashield/src/blocks/playlist/editor.css`
- Create: `mediashield/includes/Block/PlaylistBlock.php`
- Create: `mediashield/includes/REST/PlaylistController.php`

- [ ] **Step 1: Build PlaylistController.php**

`mediashield/v1` namespace. CRUD for `ms_playlist_items`:
- GET `/playlists/{id}/items` — list videos in order
- POST `/playlists/{id}/items` — add video (video_id, sort_order)
- DELETE `/playlists/{id}/items/{item_id}` — remove video
- PUT `/playlists/{id}/items/reorder` — batch update sort_order

- [ ] **Step 2: Create block.json for mediashield/playlist**

Attributes: `playlistId` (number). Supports: align. Category: media.

- [ ] **Step 3: Build edit.js (React, editor only)**

Playlist picker: fetches from `/wp/v2/mediashield_playlist`, select playlist. Preview shows playlist title + video count + thumbnail grid.

- [ ] **Step 4: Build render.php (Interactivity API)**

Outputs playlist player HTML with sidebar video list + main player area. Server-side: fetch playlist items via PlaylistController logic, render video thumbnails/titles in sidebar.

- [ ] **Step 5: Build view.js (viewScriptModule, Interactivity API store)**

Playlist player frontend: click video in sidebar to load, auto-play next with countdown timer, loop/shuffle support. Reads playlist meta (`_ms_autoplay`, `_ms_countdown`, `_ms_loop`, `_ms_shuffle`).

All playlist features (autoplay, countdown, loop, shuffle) are in FREE.

- [ ] **Step 6: Register block in PlaylistBlock.php**

`register_block_type()` in `init` hook.

- [ ] **Step 7: Browser test**

Create playlist CPT, add videos via REST, insert playlist block, verify frontend playback with auto-play and countdown.

- [ ] **Step 8: Commit**

```bash
git commit -m "feat(mediashield): playlist block with auto-play, countdown, loop, shuffle (all free)"
```

---

## Task 9: Self-Hosted Upload Driver

**Files:**
- Create: `mediashield/includes/Upload/Drivers/DriverInterface.php`
- Create: `mediashield/includes/Upload/Drivers/SelfHosted.php`
- Create: `mediashield/includes/Upload/UploadManager.php`
- Create: `mediashield/includes/REST/UploadController.php`

Upload endpoints use `mediashield/v1` namespace (free).

- [ ] **Step 1: Create DriverInterface.php**

```php
interface DriverInterface {
    public function upload(string $file_path, array $options): UploadResult;
    public function get_status(string $platform_video_id): VideoStatus;
    public function delete(string $platform_video_id): bool;
    public function get_embed_url(string $platform_video_id): string;
}
```

Plus value objects: `UploadResult`, `VideoStatus`.

- [ ] **Step 2: Implement SelfHosted.php**

Uploads to `wp-content/uploads/mediashield/` with `.htaccess` deny direct access. Generates signed URLs for playback. Creates `mediashield_video` CPT post on successful upload.

- [ ] **Step 3: Implement UploadManager.php**

Driver factory: default `SelfHosted`, extensible via `mediashield_upload_drivers` filter (pro adds platform drivers). Routes upload to correct driver.

- [ ] **Step 4: Build UploadController.php**

`mediashield/v1` namespace:
- POST `/upload/init` — validate MIME, file size, user cap (`upload_mediashield`), save to temp, return upload ID
- GET `/upload/status/{id}` — return upload progress/status

- [ ] **Step 5: Test upload**

```bash
# Create a test video file and upload
wp --path="..." rest post mediashield/v1/upload/init --user=1
```

- [ ] **Step 6: Commit**

```bash
git commit -m "feat(mediashield): self-hosted upload driver with DriverInterface + REST endpoints"
```

---

## Task 10: Admin React SPA

**SINGLE task for ALL admin UI. Full-page React app. NO classic PHP settings pages, NO WP_List_Table.**

**Files:**
- Create: `mediashield/includes/Admin/Menu.php`
- Create: `mediashield/src/admin/index.js`
- Create: `mediashield/src/admin/App.js`
- Create: `mediashield/src/admin/components/Sidebar.js`
- Create: `mediashield/src/admin/components/Toast.js`
- Create: `mediashield/src/admin/pages/Dashboard.js`
- Create: `mediashield/src/admin/pages/Videos.js`
- Create: `mediashield/src/admin/pages/Playlists.js`
- Create: `mediashield/src/admin/pages/Tags.js`
- Create: `mediashield/src/admin/pages/Milestones.js`
- Create: `mediashield/src/admin/pages/Settings.js`
- Create: `mediashield/src/admin.css`
- Modify: `mediashield/includes/REST/AnalyticsController.php`
- Modify: `mediashield/includes/REST/SettingsController.php`

- [ ] **Step 1: Build Menu.php**

Top-level MediaShield menu (`dashicons-video-alt3`). Single admin page that renders a `<div id="mediashield-admin-root"></div>`. Enqueue React SPA bundle (`admin.js`) + `admin.css` only on MediaShield admin pages. Localize REST URL, nonce, current user, plugin version.

- [ ] **Step 2: Build admin entry point (src/admin/index.js)**

`import { render } from '@wordpress/element'`. Mount `<App />` into `#mediashield-admin-root`.

- [ ] **Step 3: Build App.js with sidebar layout**

Hash-based routing (`#/dashboard`, `#/videos`, `#/playlists`, `#/students`, `#/tags`, `#/milestones`, `#/settings`). Left sidebar + right content panel.

**Pro extension point:** Routes array passed through `wp.hooks.applyFilters('mediashield_admin_routes', routes)`. Pro JS bundle calls `wp.hooks.addFilter('mediashield_admin_routes', 'mediashield-pro', (routes) => [...routes, { path: '#/alerts', label: 'Alerts', component: AlertsPage }, ...])`. Sidebar reads the filtered routes array — Pro pages appear automatically when Pro is active.

- [ ] **Step 4: Build Sidebar.js**

`@wordpress/components` NavigableMenu. Sections: Dashboard, Videos, Playlists, Students, Tags, Milestones, Settings. Active state highlighting.

- [ ] **Step 5: Build Settings page**

Inline auto-save on blur/toggle for each field. **No "Save" button.** Toast via `@wordpress/notices` on success/failure.

Sections:
- **General** — enabled, protection level, require login
- **Watermark** — opacity, color, swap interval + **live watermark preview panel** (renders a sample video frame with the current watermark config overlaid in real-time as admin adjusts sliders — eliminates trial-and-error)
- **Overlays** — customizable "Login to watch" text + button text + redirect URL. Customizable "Access denied" message for role-restricted videos. Preview of each overlay.
- **Detection** — custom URL pattern textarea for regex matching (handles non-standard embed formats)

Uses `@wordpress/components` (ToggleControl, TextControl, RangeControl, ColorPicker, SelectControl, TextareaControl). Fetches/updates via `mediashield/v1/settings`.

- [ ] **Step 6: Build SettingsController.php**

`mediashield/v1` namespace.
- GET `/settings` — return all `ms_*` options via `apply_filters('mediashield_settings_response', $settings)`. Pro hooks this to merge pro settings.
- PUT `/settings` — update options. Data passed through `apply_filters('mediashield_settings_update', $data)`. Pro hooks this to handle pro fields. Admin only.

- [ ] **Step 7: Build Dashboard page**

Overview cards: total videos, total sessions (period), avg completion, active viewers. Chart.js line chart (sessions over time) + bar chart (top videos). Period selector: today / 7d / 30d. Fetches from `mediashield/v1/analytics/overview`.

- [ ] **Step 8: Build AnalyticsController.php**

`mediashield/v1` namespace:
- GET `/analytics/overview?period=7d` — overview stats
- GET `/videos/{id}/stats` — per-video stats
- GET `/milestones?page=1&per_page=20` — paginated milestones

- [ ] **Step 9: Build Videos page**

List table with sort/filter/pagination. Columns: Thumbnail, Title, Platform, **Protection Status** (green badge: "Protected", yellow: "Partial" for iframe-only platforms, grey: "None" for `protection_level=none`), Tags, Sessions, Avg Completion, Date. Row actions: edit (link to CPT edit), view (link to frontend), **quick toggle protection on/off**, delete. Uses `@wordpress/components` patterns. Server-side pagination via `/wp/v2/mediashield_video`.

- [ ] **Step 10: Build Playlists page**

List of playlist CPTs. Columns: Title, Videos Count, Autoplay, Created. Row actions: edit, view. Link to CPT editor for full playlist management.

- [ ] **Step 11: Build Tags page**

Tag CRUD inline. Create new tag (name -> auto-slug). Edit tag name. Delete tag. Shows video count per tag. Fetches from `mediashield/v1/tags`.

- [ ] **Step 12: Build Milestones page**

Recent milestones table: User, Video, Milestone %, Reached At. Paginated, server-side. Filter by video.

- [ ] **Step 12b: Build Students page**

**Per-user watch history view** — the #1 admin question is "Show me what Student X watched." List all WordPress users with at least 1 watch session. Columns: Name, Email, Videos Watched, Avg Completion, Last Active. Click a user → drill-down showing all their video sessions with: video title, completion %, total time, last watched date. Fetches from `mediashield/v1/analytics/users` (add to AnalyticsController). Searchable by name/email.

- [ ] **Step 13: Build Toast.js**

`@wordpress/notices` integration. Auto-dismiss success toasts after 3s. Error toasts persist until dismissed.

- [ ] **Step 14: Create admin.css**

Modern spacing, card styles, elevated from default WP admin look. Light/dark follows WP admin color scheme. Responsive sidebar collapse on narrow screens.

- [ ] **Step 15: Build and browser test**

```bash
cd wp-content/plugins/mediashield && npm run build
```

Navigate to WP Admin -> MediaShield. Test: sidebar nav works, settings auto-save, dashboard charts render, video list pagination, tag CRUD, milestones table.

- [ ] **Step 16: Commit**

```bash
git commit -m "feat(mediashield): full admin React SPA with dashboard, videos, playlists, tags, milestones, settings"
```

---

## Task 11: Setup Wizard

**Setup wizard is FREE (not pro).**

**Files:**
- Create: `mediashield/includes/Admin/SetupWizard.php`
- Create: `mediashield/src/admin/wizard/Wizard.js`
- Create: `mediashield/src/admin/wizard/steps/GeneralStep.js`
- Create: `mediashield/src/admin/wizard/steps/WatermarkStep.js`
- Create: `mediashield/src/admin/wizard/steps/PlatformStep.js`
- Create: `mediashield/src/admin/wizard/steps/FirstVideoStep.js`

- [ ] **Step 1: Build SetupWizard.php**

On first activation, check `ms_wizard_complete` option. If false, redirect to wizard page on next admin load. Register wizard admin page (no menu item — accessed via redirect only). Page renders `<div id="mediashield-wizard-root"></div>` and enqueues wizard bundle.

- [ ] **Step 2: Build 4-step React wizard inside admin SPA**

The wizard is a React component rendered within the admin SPA framework.

- Step 1 (General): enable protection, require login, default protection level
- Step 2 (Watermark): choose display fields, opacity, color
- Step 3 (Platform): optional — enter API keys for Bunny/Vimeo/YouTube/Wistia (saved to options, not `ms_platform_connections` which is pro)
- Step 4 (First Video): paste a URL (auto-detect + create CPT) or upload a file

- [ ] **Step 3: Each step auto-saves**

POST to `mediashield/v1/settings` on step completion. Progress indicator shows current step.

- [ ] **Step 4: Navigation**

"Skip" and "Back" buttons on each step. "Finish" on step 4 sets `ms_wizard_complete = true`, redirects to admin SPA dashboard (`#/dashboard`).

- [ ] **Step 5: Browser test**

Deactivate + reactivate plugin, verify wizard redirect appears. Complete all 4 steps. Verify settings saved. Verify redirect to dashboard on finish.

- [ ] **Step 6: Commit**

```bash
git commit -m "feat(mediashield): 4-step setup wizard (free) with auto-save and skip navigation"
```

---

## Task 12: Pro — Role-Based Access Control

**Files:**
- Create: `mediashield-pro/includes/Access/RoleAccess.php`
- Create: `mediashield-pro/tests/phpunit/Unit/RoleAccessTest.php`

- [ ] **Step 1: Write tests** (correct role OK, wrong role denied, empty `_ms_access_role` meta allows all, admin always bypasses)
- [ ] **Step 2: Run tests — fail**
- [ ] **Step 3: Implement RoleAccess.php**

Hooks `mediashield_can_watch` filter. Reads `_ms_access_role` from video CPT post meta (NOT from any custom table). If meta is empty/not set, allow all. If set, check current user has that role. Admins always bypass.

- [ ] **Step 4: Run tests — pass**
- [ ] **Step 5: Commit**

```bash
git commit -m "feat(mediashield-pro): role-based access control via _ms_access_role post meta"
```

---

## Task 13: Pro — Advanced Watermark Configuration

**Files:**
- Create: `mediashield-pro/includes/Watermark/AdvancedConfig.php`

- [ ] **Step 1: Implement AdvancedConfig.php**

Hooks `mediashield_watermark_config` filter. Builds watermark text from admin-configured fields: `username`, `email`, `ip`, `user_id`, `timestamp`, `site_name`, `custom_text`. Reads `ms_pro_watermark_fields` option (array of field keys) and `ms_pro_watermark_custom_text` option.

- [ ] **Step 2: Add pro watermark settings fields**

Pro options: `ms_pro_watermark_fields` (array), `ms_pro_watermark_custom_text` (string), `ms_pro_watermark_font_size` (string: small/medium/large), **`ms_show_badge`** (bool, default true — Pro users can set to false for white-label, removing the "Protected by MediaShield" badge). These are surfaced in the admin SPA Settings page when pro is active (pro extends the settings REST response). Free plugin checks: `if (!defined('MEDIASHIELD_PRO_VERSION')) { force ms_show_badge = true }`.

- [ ] **Step 3: Commit**

```bash
git commit -m "feat(mediashield-pro): advanced watermark config (email, timestamp, custom text)"
```

---

## Task 14: Pro — Platform Upload Drivers

**Files:**
- Create: `mediashield-pro/includes/Upload/Drivers/BunnyStream.php`
- Create: `mediashield-pro/includes/Upload/Drivers/VimeoApi.php`
- Create: `mediashield-pro/includes/Upload/Drivers/YouTubeApi.php`
- Create: `mediashield-pro/includes/Upload/Drivers/WistiaApi.php`
- Create: `mediashield-pro/includes/REST/PlatformController.php`
- Create: `mediashield-pro/includes/Admin/PlatformConnections.php`

Upload endpoints use `mediashield-pro/v1` namespace (pro).

- [ ] **Step 1: Implement BunnyStream.php** — Bunny Stream API, tus protocol for resumable uploads. Implements `DriverInterface`.
- [ ] **Step 2: Implement VimeoApi.php** — Vimeo API v3, OAuth + tus upload. Implements `DriverInterface`.
- [ ] **Step 3: Implement YouTubeApi.php** — YouTube Data API v3, resumable upload. Implements `DriverInterface`.
- [ ] **Step 4: Implement WistiaApi.php** — Wistia Upload API. Implements `DriverInterface`.
- [ ] **Step 5: Register drivers via `mediashield_upload_drivers` filter in Pro Plugin.php**
- [ ] **Step 6: Build PlatformController.php**

`mediashield-pro/v1` namespace:
- GET `/platforms` — list connected platforms
- POST `/platforms` — connect platform (encrypt credentials with AES-256-CBC before storing in `ms_platform_connections`)
- DELETE `/platforms/{id}` — disconnect platform
- POST `/upload/init` — platform upload init (pro, routes to platform driver)
- GET `/upload/status/{id}` — platform upload status

- [ ] **Step 7: Build PlatformConnections.php**

Admin page component data — connect/disconnect UI per platform. OAuth flows where needed (Vimeo, YouTube). API key paste for Bunny, Wistia. Connection status indicator. "Test Connection" button.

- [ ] **Step 8: Multisite network-wide platform connections**

On multisite: platform connections stored via `get_site_option('ms_platform_connections')` instead of `get_option()` — shared across all subsites in the network. Network admin page registered via `network_admin_menu` hook (only on multisite). Subsite admins can READ connections but only network admins can ADD/REMOVE. Detection: `is_multisite()` gates the network behavior.

- [ ] **Step 9: Commit**

```bash
git commit -m "feat(mediashield-pro): platform upload drivers (Bunny, Vimeo, YouTube, Wistia)"
```

---

## Task 15: Pro — Frontend Upload Form

**Files:**
- Create: `mediashield-pro/templates/frontend-upload.php`
- Create: `mediashield-pro/assets/js/frontend-upload.js`
- Create: `mediashield-pro/assets/css/frontend-upload.css`

- [ ] **Step 1: Register `[mediashield_upload]` shortcode**

Capability check: `upload_mediashield` (granted to admin, editor, and custom "instructor" role). Non-capable users see nothing.

- [ ] **Step 2: Build template (frontend-upload.php)**

Drag-drop zone, platform selector (from connected platforms), title input, tags multi-select, progress bar.

- [ ] **Step 3: Build frontend-upload.js**

FormData + XMLHttpRequest for chunked upload. Progress bar updates. On complete, show link to new video.

- [ ] **Step 4: Browser test** — visit page with shortcode as admin, upload a file, verify CPT post created
- [ ] **Step 5: Commit**

```bash
git commit -m "feat(mediashield-pro): frontend upload form with [mediashield_upload] shortcode"
```

---

## Task 16: Pro — Advanced Analytics

**Files:**
- Create: `mediashield-pro/includes/Analytics/Heatmap.php`
- Create: `mediashield-pro/includes/Analytics/SuspiciousActivity.php`
- Create: `mediashield-pro/includes/Analytics/RealtimeDashboard.php`
- Create: `mediashield-pro/includes/REST/HeatmapController.php`
- Create: `mediashield-pro/includes/REST/SuspiciousController.php`
- Create: `mediashield-pro/includes/REST/RealtimeController.php`
- Create: `mediashield-pro/assets/js/admin/heatmap.js`
- Create: `mediashield-pro/assets/js/admin/realtime.js`
- Create: `mediashield-pro/includes/Cron/ProCleanup.php` (partial — heatmap aggregation cron)

- [ ] **Step 1: Implement Heatmap.php**

Reads from `ms_heatmap_cache` (pre-aggregated data). **Never aggregates `ms_playback_events` on the fly for responses.** Provides `get_heatmap($video_id)` method that returns position buckets with view density.

- [ ] **Step 2: Implement heatmap aggregation cron**

Hourly cron (Action Scheduler): aggregates `ms_playback_events` into `ms_heatmap_cache` position buckets. Groups events by video_id + position bucket (e.g., 10-second intervals). Writes view_count + avg_duration per bucket. Updates `last_aggregated` timestamp.

- [ ] **Step 3: Hook heartbeat to record playback events**

On `mediashield_session_started` action: start recording. In heartbeat processing: batch INSERT play/pause/seek events into `ms_playback_events`.

- [ ] **Step 4: Implement SuspiciousActivity.php**

Detection rules:
- Multi-IP: same user_id from different IP within configurable window
- Rapid seek: scrubbing through entire video in < 30 seconds
- DevTools flag: client-side detection signal sent with heartbeat

**VPN/proxy sensitivity controls** — admin setting: `ms_suspicious_sensitivity` (low/medium/high).
- Low: only flag 5+ different IPs in 1 hour (minimal false positives, misses some sharing)
- Medium: flag 3+ different IPs in 30 minutes (default — balanced)
- High: flag 2+ different IPs in 5 minutes (catches more sharing, more false positives)

**Dismiss/mark safe** — each alert has a "Dismiss" button and "Mark user as safe" (whitelists that user from future multi-IP alerts). Dismissed alerts are soft-deleted (kept for audit but hidden from feed).

Writes alerts to `ms_activity_alerts` with severity.

- [ ] **Step 5: Implement RealtimeDashboard.php**

Query active sessions: `WHERE is_active = 1 AND last_heartbeat > NOW() - INTERVAL 2 MINUTE`. Returns: user, video, progress, IP, device, duration. Sortable, filterable.

- [ ] **Step 6: Build REST controllers**

`mediashield-pro/v1` namespace:
- GET `/analytics/heatmap/{id}` — heatmap data for video
- GET `/analytics/suspicious?page=1&per_page=20` — paginated suspicious activity alerts
- PATCH `/analytics/suspicious/{id}/dismiss` — dismiss an alert
- POST `/analytics/suspicious/safe-user` — whitelist user from multi-IP
- GET `/realtime/viewers` — active viewer list
- GET `/analytics/playlist-funnel/{playlist_id}` — **cross-video funnel**: for a playlist, returns how many users started Video 1, how many reached 100% on Video 1 and started Video 2, etc. Essential course analytics.
- GET `/analytics/device-breakdown?period=30d` — device/browser/platform breakdown chart data (collected in ms_watch_sessions but never surfaced until now)

- [ ] **Step 7: Build heatmap.js** — Chart.js bar chart visualization of heatmap data + **drop-off line chart** (% of viewers remaining at each position in the video, derived from heatmap bucket data — shows exactly where students stop watching)
- [ ] **Step 8: Build realtime.js** — 30-second poll of `/realtime/viewers`, auto-refresh table

- [ ] **Step 9: Commit**

```bash
git commit -m "feat(mediashield-pro): advanced analytics — heatmaps, suspicious activity, realtime dashboard"
```

---

## Task 17: Pro — Advanced Milestone Actions

**Files:**
- Create: `mediashield-pro/includes/Milestones/AdvancedActions.php`
- Create: `mediashield-pro/includes/REST/MilestoneConfigController.php`

- [ ] **Step 1: Implement AdvancedActions.php**

Hooks `mediashield_milestone_reached` action. Executes configured actions per threshold:
- **tag**: `update_user_meta($user_id, "ms_completed_{$video_id}", $timestamp)`
- **email**: `wp_mail()` to admin or user (configurable template)
- **webhook**: `wp_remote_post($url, $payload)` non-blocking

Reads config from `ms_pro_milestone_config` option (JSON: array of `{threshold, actions: [{type, config}]}`).

- [ ] **Step 2: Build MilestoneConfigController.php**

`mediashield-pro/v1` namespace:
- GET `/milestones/config` — return milestone config
- PUT `/milestones/config` — update config (thresholds + per-threshold actions)

- [ ] **Step 3: Add milestones config UI**

Pro extends the admin SPA Milestones page with a config panel: add/remove thresholds, configure actions per threshold (tag, email, webhook with URL).

- [ ] **Step 4: Commit**

```bash
git commit -m "feat(mediashield-pro): advanced milestone actions (tag, email, webhook) with config UI"
```

---

## Task 18: Pro — Widevine DRM

**Files:**
- Create: `mediashield-pro/includes/DRM/KeyServer.php`
- Create: `mediashield-pro/includes/DRM/Packager.php`
- Create: `mediashield-pro/includes/DRM/WidevineLicense.php`
- Create: `mediashield-pro/includes/REST/DRMController.php`
- Create: `mediashield-pro/assets/js/drm-player.js`
- Create: `mediashield-pro/includes/Admin/DRMSettings.php`

- [ ] **Step 1: Implement KeyServer.php**

Generate 128-bit key pairs (key_id + content_key). Encrypt content_key with OpenSSL AES-256-CBC (using `AUTH_SALT`) before storage in `ms_drm_keys`. Methods: `generate_key($video_id)`, `get_key($video_id)` (decrypts on read).

- [ ] **Step 2: Implement Packager.php**

Shaka Packager CLI wrapper. DASH + CENC packaging. Runs via Action Scheduler background job (not blocking). Takes video file path, packages to DASH segments + manifest (.mpd). Stores in `wp-content/uploads/mediashield/drm/{video_id}/`.

- [ ] **Step 3: Implement WidevineLicense.php**

License proxy: validate user access (AccessControl::can_watch) -> fetch content key from KeyServer -> proxy to Widevine key server -> return license blob. Supports `streaming` and `persistent` license types. Records in `ms_drm_licenses`.

- [ ] **Step 4: Build DRMController.php**

`mediashield-pro/v1` namespace:
- POST `/drm/license` — request streaming license (EME flow)
- POST `/drm/offline` — request persistent license for offline
- POST `/drm/revoke` — revoke license for user + video

- [ ] **Step 5: Create drm-player.js**

Shaka Player initialization. EME license request filter (intercepts license requests, routes to `/drm/license`). DASH manifest loading. Hooks into player-wrapper.js for DRM-protected videos.

- [ ] **Step 6: Build DRMSettings.php**

Admin config data for DRM settings: Shaka Packager binary path, license server URL, default license duration, auto-package on upload toggle. Surfaced in admin SPA Settings page when pro is active.

- [ ] **Step 7: Commit**

```bash
git commit -m "feat(mediashield-pro): Widevine DRM system — KeyServer, Packager, license proxy, drm-player.js"
```

---

## Task 19: Pro — PWA Offline Download

**Files:**
- Create: `mediashield-pro/includes/DRM/OfflineManager.php`
- Create: `mediashield-pro/assets/js/offline-sw.js`

- [ ] **Step 1: Implement OfflineManager.php**

Register Service Worker via `wp_enqueue_script`. Provide offline segment manifest endpoint. Generate "Save for Offline" button HTML for DRM-protected videos.

- [ ] **Step 2: Create offline-sw.js (Service Worker)**

Cache encrypted DASH segments on demand. Serve from cache when offline. Handle persistent license storage in IndexedDB.

- [ ] **Step 3: Add "Save for Offline" button**

Requests persistent license (`/drm/offline`), sends segments to SW cache, shows download progress bar. Only appears for DRM-enabled videos.

- [ ] **Step 4: Commit**

```bash
git commit -m "feat(mediashield-pro): PWA offline download with Service Worker + persistent DRM license"
```

---

## Task 20: Pro — Data Export

**Files:**
- Create: `mediashield-pro/includes/Export/CsvExporter.php`
- Create: `mediashield-pro/includes/Export/PdfExporter.php`
- Create: `mediashield-pro/includes/REST/ExportController.php`
- Modify: `mediashield-pro/composer.json` (add dompdf)

- [ ] **Step 1: Add dompdf to pro composer.json**

```bash
cd wp-content/plugins/mediashield-pro && composer require dompdf/dompdf
```

- [ ] **Step 2: Implement CsvExporter.php**

Methods: `export_watch_sessions()`, `export_milestones()`, `export_user_history()`. Streams CSV directly (no temp file for large exports). Headers: `Content-Type: text/csv`, `Content-Disposition: attachment`.

- [ ] **Step 3: Implement PdfExporter.php**

Uses Dompdf. Generates summary report: overview stats, top videos table, user completion table. Accepts period parameter.

- [ ] **Step 4: Build ExportController.php**

`mediashield-pro/v1` namespace. Admin only.
- GET `/export/csv/{type}` — types: `watch_sessions`, `milestones`, `users`
- GET `/export/pdf/report?period=30d` — PDF summary report

- [ ] **Step 5: Add export buttons to admin dashboard**

Pro extends the admin SPA Dashboard page with "Export CSV" dropdown and "Download PDF Report" button.

- [ ] **Step 6: Commit**

```bash
git commit -m "feat(mediashield-pro): data export — CSV streaming + PDF reports (dompdf)"
```

---

## Task 21: i18n

**Files:**
- Modify: all PHP files (wrap strings in `__()`)
- Modify: all JS files (wrap strings in `wp.i18n.__()`)
- Generate: `mediashield/languages/mediashield.pot`
- Generate: `mediashield-pro/languages/mediashield-pro.pot`

- [ ] **Step 1: Audit all PHP files**

Ensure every user-facing string is wrapped in `__('string', 'mediashield')` or `esc_html__()` / `esc_attr__()` as appropriate. Text domain: `mediashield` for free, `mediashield-pro` for pro.

- [ ] **Step 2: Audit all JS files**

Ensure every user-facing string uses `wp.i18n.__('string', 'mediashield')`. Import from `@wordpress/i18n`.

- [ ] **Step 3: Generate .pot files**

```bash
wp --path="/Users/varundubey/Local Sites/forums/app/public" i18n make-pot wp-content/plugins/mediashield wp-content/plugins/mediashield/languages/mediashield.pot
wp --path="/Users/varundubey/Local Sites/forums/app/public" i18n make-pot wp-content/plugins/mediashield-pro wp-content/plugins/mediashield-pro/languages/mediashield-pro.pot
```

- [ ] **Step 4: Register JS translations**

In Assets.php: `wp_set_script_translations('mediashield-admin', 'mediashield', MEDIASHIELD_PATH . 'languages')` for each enqueued script handle.

- [ ] **Step 5: Commit**

```bash
git commit -m "chore(mediashield): full i18n — all strings wrapped, .pot files generated"
```

---

## Task 22: Video Deletion Cascade + Pruning Crons

**Files:**
- Create: `mediashield/includes/Cron/Cleanup.php`
- Create: `mediashield-pro/includes/Cron/ProCleanup.php` (finalize — may have been partially created in Task 16)

- [ ] **Step 1: Implement `before_delete_post` cascade**

Hook `before_delete_post` for `mediashield_video` CPT. When a video CPT post is deleted, clean up:
- `ms_video_tags` rows WHERE video_id = post ID
- `ms_watch_sessions` rows WHERE video_id = post ID
- `ms_milestones` rows WHERE video_id = post ID
- `ms_playlist_items` rows WHERE video_id = post ID
- Pro (if active): `ms_playback_events` via session_ids, `ms_activity_alerts`, `ms_drm_licenses`, `ms_heatmap_cache`, `ms_drm_keys`, `ms_upload_queue` WHERE video_id = post ID

Hook `before_delete_post` for `mediashield_playlist` CPT:
- `ms_playlist_items` rows WHERE playlist_id = post ID

- [ ] **Step 2: Implement free crons in Cleanup.php**

Register via Action Scheduler:

- **Inactive session cleanup (hourly):** `UPDATE ms_watch_sessions SET is_active = 0 WHERE is_active = 1 AND last_heartbeat < NOW() - INTERVAL 10 MINUTE`
- **Session archival (monthly):** `INSERT INTO ms_watch_sessions_archive SELECT * FROM ms_watch_sessions WHERE started_at < NOW() - INTERVAL 24 MONTH; DELETE FROM ms_watch_sessions WHERE started_at < NOW() - INTERVAL 24 MONTH`

Note: `ms_watch_sessions_archive` table creation should be added to Schema.php (same schema as `ms_watch_sessions`).

- [ ] **Step 3: Implement pro crons in ProCleanup.php**

Register via Action Scheduler:

- **Playback events aggregation (hourly):** Aggregate `ms_playback_events` into `ms_heatmap_cache` position buckets. Delete raw events older than 90 days after aggregation.
- **Alert pruning (monthly):** `DELETE FROM ms_activity_alerts WHERE created_at < NOW() - INTERVAL 6 MONTH`
- **Upload queue cleanup (daily):** Delete completed entries older than 7 days, failed entries older than 30 days.

- [ ] **Step 4: Verify crons register on activation**

```bash
wp --path="..." action-scheduler list --status=pending
```

- [ ] **Step 5: Commit**

```bash
git commit -m "feat(mediashield): video deletion cascade + hourly/daily/monthly pruning crons"
```

---

## Task 23: GDPR Compliance + Privacy Tools

**Files:**
- Create: `mediashield/includes/Privacy/PrivacyExporter.php`
- Create: `mediashield/includes/Privacy/PrivacyEraser.php`
- Modify: `mediashield/includes/Core/Plugin.php` (register privacy hooks)
- Modify: `mediashield/includes/REST/SettingsController.php` (IP anonymization option)

- [ ] **Step 1: Implement PrivacyExporter.php**

Register via `wp_privacy_personal_data_exporters` filter. Exports for a given user email:
- All watch sessions (video title, started_at, total_seconds, completion_pct, IP — hashed if anonymized)
- All milestones (video title, milestone_pct, reached_at)
- All activity alerts involving the user

Format: structured arrays per WordPress privacy export spec.

- [ ] **Step 2: Implement PrivacyEraser.php**

Register via `wp_privacy_personal_data_erasers` filter. Erases for a given user email:
- Anonymizes `ms_watch_sessions`: set `ip_address = ''`, `user_agent = ''`
- Retains session rows for aggregate analytics but removes PII
- Removes user from `ms_activity_alerts`

- [ ] **Step 3: Add IP anonymization option**

Setting `ms_anonymize_ips` (bool, default false). When enabled:
- Store only first 3 octets of IPv4 (e.g., `192.168.1.xxx`)
- Store only first 3 groups of IPv6
- Watermark shows anonymized IP (or replaces IP with user ID)
- Applied at write time in SessionManager, not retroactively

- [ ] **Step 4: Add privacy policy text snippet**

Setting page includes a copyable privacy policy paragraph that site owners can add to their Privacy Policy page. Text covers: what data is collected (IP, watch history, device info), why (video protection), retention period, user rights.

- [ ] **Step 5: Verify via WP Privacy tools**

WordPress Admin -> Tools -> Export/Erase Personal Data. Submit a request for a test user. Verify MediaShield data appears in export and is erased on erasure request.

- [ ] **Step 6: Commit**

```bash
git commit -m "feat(mediashield): GDPR compliance — privacy exporter, eraser, IP anonymization"
```

---

## Task 24: Student "My Videos" Frontend Page

**Files:**
- Create: `mediashield/includes/Block/MyVideosBlock.php`
- Create: `mediashield/src/blocks/my-videos/block.json`
- Create: `mediashield/src/blocks/my-videos/render.php`
- Create: `mediashield/src/blocks/my-videos/view.js`
- Create: `mediashield/includes/Block/MyVideosShortcode.php`
- Modify: `mediashield/includes/REST/AnalyticsController.php` (add /analytics/my-videos endpoint)

- [ ] **Step 1: Build REST endpoint /analytics/my-videos**

Returns all videos the current user has watched with: video_id, title, thumbnail, completion_pct, total_seconds, last_watched. Only returns videos the user has access to. Paginated. Requires authentication (current user).

- [ ] **Step 2: Build mediashield/my-videos Gutenberg block**

Renders a student-facing video library grid showing:
- Video thumbnail + title
- Progress bar (completion %)
- "Resume" button (links to single video page, player auto-seeks to `max_position`)
- "Completed" badge for 100% videos
- Filterable: All / In Progress / Completed

Uses Interactivity API for `render.php`. Server-side renders initial state, client-side handles filtering.

- [ ] **Step 3: Register [mediashield_my_videos] shortcode**

Classic editor alternative. Same output as the block's render.php.

- [ ] **Step 4: Browser test**

As a logged-in user who has watched some videos, visit a page with the block/shortcode. Verify: progress bars match DB data, "Resume" links work, completed badges appear.

- [ ] **Step 5: Commit**

```bash
git commit -m "feat(mediashield): student My Videos page with progress tracking and resume"
```

---

## Task 25: Pro — Email Gate / Lead Capture

**Files:**
- Create: `mediashield-pro/includes/Access/EmailGate.php`
- Create: `mediashield-pro/assets/js/email-gate.js`
- Create: `mediashield-pro/assets/css/email-gate.css`
- Modify: `mediashield/includes/Player/PlayerWrapper.php` (add filter hook for custom overlays)

- [ ] **Step 1: Implement EmailGate.php**

Hooks into `mediashield_can_watch` filter. For videos with `_ms_access_type = 'email_gate'` (new post meta value):
- Non-logged-in visitors see an email capture overlay instead of "Login to watch"
- User enters email → stored in `ms_email_captures` table (new pro table) or connected to email marketing via webhook
- Sets a cookie/session granting temporary access (configurable: 24h, 7d, 30d)
- After email submitted, video plays with watermark showing the captured email

This is the lead generation feature that Presto Player offers. Critical for course creators using free videos as lead magnets.

- [ ] **Step 2: Create ms_email_captures table**

```sql
ms_email_captures (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    video_id BIGINT UNSIGNED NOT NULL,
    email VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NOT NULL DEFAULT '',
    captured_at DATETIME NOT NULL,
    KEY idx_video (video_id),
    KEY idx_email (email)
)
```

Add to pro Schema.php.

- [ ] **Step 3: Build email-gate.js overlay**

Overlay form: email input + "Watch Now" button. Submit → POST to `mediashield-pro/v1/email-gate/submit`. On success → hide overlay, start video session.

- [ ] **Step 4: Add admin setting per video**

In video CPT editor, add a meta box / sidebar panel: "Access Type" dropdown: Login Required (default) / Email Gate / Role-Based / None (public).

- [ ] **Step 5: Add webhook integration**

Setting `ms_email_gate_webhook_url`. On each email capture, POST JSON `{ email, video_id, video_title, captured_at }` to the webhook. Integrates with Mailchimp, ConvertKit, ActiveCampaign, Zapier.

- [ ] **Step 6: Commit**

```bash
git commit -m "feat(mediashield-pro): email gate / lead capture with webhook integration"
```

---

## Task 26: Pro — Weekly Email Digest Reports

**Files:**
- Create: `mediashield-pro/includes/Reports/WeeklyDigest.php`
- Create: `mediashield-pro/templates/email/weekly-digest.php`
- Modify: `mediashield-pro/includes/Cron/ProCleanup.php` (add weekly digest cron)

- [ ] **Step 1: Implement WeeklyDigest.php**

Generates and sends a weekly summary email to admin (configurable recipient). Content:

- **Overview:** total views this week, new completions, avg completion rate, change from last week (↑↓)
- **Top 5 videos** by views this week
- **Alert summary:** X suspicious activity alerts this week (if any)
- **Students highlight:** X new students started watching, Y completed all videos in a playlist
- **Low performers:** videos with < 30% avg completion (action needed)

Uses `wp_mail()` with HTML template.

- [ ] **Step 2: Create email template (weekly-digest.php)**

Clean HTML email template. Responsive. MediaShield branding. Includes "View Full Dashboard" CTA button linking to admin SPA.

- [ ] **Step 3: Register weekly cron via Action Scheduler**

Runs every Monday at 9:00 AM (site timezone). Setting `ms_weekly_digest_enabled` (bool, default true for pro). Setting `ms_weekly_digest_email` (defaults to admin_email).

- [ ] **Step 4: Add settings UI**

In Pro admin section: toggle weekly digest, set recipient email, day of week preference.

- [ ] **Step 5: Commit**

```bash
git commit -m "feat(mediashield-pro): weekly email digest reports with performance highlights"
```

---

## Task 27: CLAUDE.md + Documentation

**Files:**
- Create: `mediashield/CLAUDE.md`
- Create: `mediashield-pro/CLAUDE.md`

- [ ] **Step 1: Write mediashield/CLAUDE.md**

Cover: architecture overview, CPTs (mediashield_video, mediashield_playlist), post meta fields, custom tables (5), REST API endpoints (mediashield/v1/*), WordPress hooks (actions + filters), Gutenberg blocks (mediashield/video, mediashield/playlist), shortcode, admin SPA structure, JS architecture (vanilla frontend, React admin, Interactivity API blocks), build commands, testing.

- [ ] **Step 2: Write mediashield-pro/CLAUDE.md**

Cover: pro features, dependency on free plugin, pro tables (7), REST API endpoints (mediashield-pro/v1/*), DRM setup (Shaka Packager path, Widevine config), platform drivers, export, cron jobs, hooks into free plugin.

- [ ] **Step 3: Commit**

```bash
git commit -m "docs(mediashield): CLAUDE.md for both free and pro plugins"
```

---

## Task 28: E2E Integration Testing

**Single comprehensive task. 30-point verification checklist.**

- [ ] **Step 1: Free plugin activation flow**
  - Activate mediashield free -> verify 5 custom tables created
  - Verify 2 CPTs registered (mediashield_video, mediashield_playlist)
  - Verify rewrite rules flushed (`/video/`, `/playlist/` work)
  - Verify setup wizard redirect on first activation

- [ ] **Step 2: Pro plugin activation flow**
  - Activate mediashield-pro -> verify 7 pro tables created (12 total)
  - Deactivate pro -> verify free works alone without errors

- [ ] **Step 3: Video CPT lifecycle**
  - Create video CPT post, set platform meta, verify featured image auto-fetch
  - Edit video, update meta, verify REST API reflects changes
  - Delete video -> verify cascade cleanup (tags, sessions, milestones, playlist items)

- [ ] **Step 4: Playlist lifecycle**
  - Create playlist CPT, add videos via REST, reorder, verify sort_order
  - Delete playlist -> verify ms_playlist_items cleaned up

- [ ] **Step 5: Gutenberg blocks**
  - Insert mediashield/video block -> test picker modal + URL paste both work
  - Insert mediashield/playlist block -> verify playlist selection
  - Frontend: verify blocks render protected player HTML

- [ ] **Step 6: Shortcode**
  - `[mediashield id=123]` in classic content -> verify protected player renders

- [ ] **Step 7: Single video page**
  - Visit `/video/slug/` -> verify single-mediashield_video.php template loads
  - Verify protected player with watermark + tracking

- [ ] **Step 8: Player wrapper (auto-detection)**
  - Page with YouTube embed -> verify wrapped + watermarked + tracked
  - Page with Vimeo embed -> verify same
  - Double-wrap prevention -> verify no nested `.ms-protected-player` divs

- [ ] **Step 9: Session + tracking flow**
  - Watch video 30s+ -> verify heartbeat in `ms_watch_sessions`
  - Watch video to 100% -> verify 4 milestones in `ms_milestones` (25, 50, 75, 100)
  - Re-watch same video -> verify no duplicate milestones

- [ ] **Step 10: Access control**
  - Log out -> verify "Login required" overlay
  - Pro: set `_ms_access_role=editor` on video -> subscriber blocked, editor allowed

- [ ] **Step 11: Tags CRUD**
  - Create tag via REST, assign to video, retrieve, delete
  - Verify ms_video_tags uses CPT post IDs

- [ ] **Step 12: Self-hosted upload**
  - Upload video file via REST -> verify protected directory with .htaccess
  - Verify CPT post created with correct meta

- [ ] **Step 13: Setup wizard**
  - Deactivate + reactivate -> wizard appears
  - Complete 4 steps -> verify settings saved + redirect to dashboard

- [ ] **Step 14: Admin SPA**
  - Navigate all sections: Dashboard, Videos, Playlists, Tags, Milestones, Settings
  - Settings: change watermark opacity -> verify auto-save (no Save button)
  - Dashboard: verify Chart.js charts render, period selector works
  - Toast notifications on save success

- [ ] **Step 15: Pro watermark**
  - Set pro watermark fields (email + timestamp) -> verify watermark text includes them

- [ ] **Step 16: Pro analytics**
  - Real-time panel -> verify shows active viewer during playback
  - Heatmap -> verify data populates from ms_heatmap_cache
  - Suspicious activity -> verify multi-IP alert created

- [ ] **Step 17: Pro platform connections**
  - Connect Bunny API key -> verify stored encrypted in ms_platform_connections
  - API test endpoint -> verify connection validates

- [ ] **Step 18: Pro milestone actions**
  - Configure email action on 100% milestone -> verify wp_mail fires

- [ ] **Step 19: Pro data export**
  - CSV export -> verify downloads correctly with correct headers
  - PDF report -> verify generates summary

- [ ] **Step 20: DRM (pro)**
  - DRM-enabled video -> verify Shaka Player plays encrypted content
  - License proxy flow -> verify ms_drm_licenses record created

- [ ] **Step 21: Offline (pro)**
  - "Save for Offline" -> verify Service Worker caches segments
  - Verify persistent license stored

- [ ] **Step 22: Pruning crons**
  - Verify crons registered (inactive session cleanup, session archival, alert pruning, upload queue cleanup, heatmap aggregation)

- [ ] **Step 23: Playlist playback**
  - Auto-play next video with countdown
  - Loop + shuffle behavior
  - All playlist features work in free (no pro required)

- [ ] **Step 24: i18n**
  - Verify .pot files generated and non-empty
  - Verify JS translations registered

- [ ] **Step 25: Resume watching**
  - Watch video to 50%, leave page, return -> verify player auto-seeks to last position with "Resume from X:XX?" prompt

- [ ] **Step 26: GDPR compliance**
  - WP Admin -> Tools -> Export Personal Data -> submit for test user -> verify MediaShield data in export
  - Erase Personal Data -> verify IPs anonymized in ms_watch_sessions
  - Enable `ms_anonymize_ips` -> new sessions store truncated IPs

- [ ] **Step 27: Student My Videos page**
  - Visit page with [mediashield_my_videos] as logged-in user -> verify progress bars, resume links

- [ ] **Step 28: Email gate (pro)**
  - Set video access type to "email_gate" -> visit as logged-out user -> email overlay appears -> submit -> video plays

- [ ] **Step 29: Weekly digest (pro)**
  - Trigger digest manually -> verify email received with overview stats

- [ ] **Step 30: Multisite**
  - Activate on subsite -> verify per-site tables and settings

- [ ] **Step 31: Commit**

```bash
git commit -m "test(mediashield): complete E2E integration testing — 25-point verification"
```

---

## Verification Checklist (Summary)

1. Activate free -> 5 custom tables + 2 CPTs registered
2. Activate pro -> 7 pro tables (12 total)
3. Deactivate pro -> free works alone
4. Video CPT -> create, edit, featured image auto-fetched
5. Playlist CPT -> create, add videos, reorder
6. Gutenberg video block -> picker modal + URL paste both work
7. Gutenberg playlist block -> select playlist, renders player
8. Shortcode `[mediashield id=123]` -> renders protected player
9. Single video page -> `/video/slug/` renders protected player
10. YouTube embed -> wrapped + watermarked + tracked
11. Vimeo embed -> same
12. Self-hosted video -> Shaka Player + watermark + tracking
13. Watch 30s+ -> heartbeat in `ms_watch_sessions`
14. Watch 100% -> 4 milestones in `ms_milestones`
15. Log out -> "Login required" overlay
16. Role restriction (pro) -> subscriber blocked from editor-only video
17. Admin React SPA -> sidebar nav, inline save, toast notifications
18. Setup wizard (free) -> 4 steps, auto-save, redirect to dashboard
19. Dashboard -> stats, Chart.js charts, period selector
20. Real-time panel (pro) -> shows active viewer
21. Playlist playback -> auto-play with countdown (free)
22. Tags CRUD + REST API
23. Self-hosted upload -> protected directory
24. Bunny connection (pro) -> API test passes
25. Pro watermark -> email + timestamp in watermark text
26. Suspicious activity (pro) -> multi-IP alert
27. CSV export (pro) -> downloads correctly
28. PDF report (pro) -> generates summary
29. DRM (pro) -> Shaka Player plays encrypted content
30. Offline (pro) -> SW caches segments
31. Resume watching -> return to video, auto-seeks to last position
32. Protection none -> free preview video plays without watermark/login
33. GDPR -> WP privacy export includes watch history, erasure anonymizes IPs
34. IP anonymization -> enable setting, verify truncated IPs stored
35. Student "My Videos" page -> progress bars, resume links, completed badges
36. Watermark preview -> settings panel shows live preview as config changes
37. VPN sensitivity -> set to low, verify fewer false positive alerts
38. Email gate (pro) -> non-logged-in user enters email, video plays, email captured
39. Weekly digest (pro) -> verify email sent, contains overview + top videos
40. Cross-video funnel (pro) -> playlist analytics shows start-to-finish drop-off
41. Domain restriction -> set allowed domains, embed on unauthorized domain = blocked
42. Concurrent session limit (pro) -> open 3 tabs as same user, 3rd blocked with "too many streams"
43. "Protected by MediaShield" badge -> visible on free, removable in pro settings
