# MediaShield Pro - Developer Reference

Advanced video protection add-on -- configurable watermarks, role-based access, heatmaps, suspicious activity detection, multi-platform uploads, Widevine DRM, PWA offline, data export, email gate, and weekly digest.

- **Version:** 1.0.0
- **Requires:** PHP 8.1, WordPress 6.5, MediaShield free plugin active
- **Text Domain:** mediashield-pro
- **Namespace:** `MediaShieldPro\`
- **Autoload:** PSR-4 via Composer (`includes/`)

---

## Dependency

Pro requires the free MediaShield plugin. Checks `defined('MEDIASHIELD_VERSION')` on `plugins_loaded` at priority 20 (free loads at default 10). Shows admin notice if free plugin is missing.

Constants: `MEDIASHIELD_PRO_VERSION`, `MEDIASHIELD_PRO_DB_VERSION`, `MEDIASHIELD_PRO_FILE`, `MEDIASHIELD_PRO_PATH`, `MEDIASHIELD_PRO_URL`, `MEDIASHIELD_PRO_PLUGIN_FILE`

---

## Architecture

Singleton bootstrap in `mediashield-pro.php`:
1. Composer autoloader loads `MediaShieldPro\` from `includes/`
2. EDD SL SDK registration via `edd_sl_sdk_registry` action
3. `plugins_loaded` (priority 20) checks free plugin dependency, runs `Migrator::run()` then `Plugin::instance()`
4. `Plugin.php` hooks into free plugin filters/actions to extend functionality
5. Fires `mediashield_pro_loaded` action when complete

---

## Quick File Index

| File | Purpose |
|------|---------|
| `mediashield-pro.php` | Main plugin file (dependency check, EDD SDK, bootstrap) |
| `uninstall.php` | Drop 8 tables, delete 17 options, clear crons |
| `composer.json` | PSR-4 autoload + dompdf + edd-sl-sdk |
| `package.json` | Build config for admin React SPA |

### includes/Core/

| File | Purpose |
|------|---------|
| `Plugin.php` | Singleton, registers all pro hooks, REST routes, asset enqueuing |
| `Activator.php` | Activation: dependency check, create DB tables |
| `Deactivator.php` | Deactivation: clear crons, reset badge |
| `Migrator.php` | Pro DB version migration via dbDelta |

### includes/DB/

| File | Purpose |
|------|---------|
| `Schema.php` | dbDelta for all 8 pro tables, drop_tables() |

### includes/Access/

| File | Purpose |
|------|---------|
| `RoleAccess.php` | Filter `mediashield_can_watch` at priority 20. Checks `_ms_access_role` post meta |
| `EmailGate.php` | Filter `mediashield_can_watch` at priority 15. Email capture gate with REST route, cookie, webhook |

### includes/Watermark/

| File | Purpose |
|------|---------|
| `AdvancedConfig.php` | Filter `mediashield_watermark_config`. Builds watermark from fields (username, email, ip, user_id, timestamp, site_name, custom_text). Injects/saves pro settings |

### includes/Upload/

| File | Purpose |
|------|---------|
| `FrontendUpload.php` | `[mediashield_upload]` shortcode. Requires `upload_mediashield` capability |
| `Drivers/BunnyStream.php` | Bunny.net tus resumable upload driver |
| `Drivers/VimeoApi.php` | Vimeo API v3 tus upload driver |
| `Drivers/YouTubeApi.php` | YouTube Data API v3 resumable upload driver |
| `Drivers/WistiaApi.php` | Wistia multipart form upload driver |

### includes/Analytics/

| File | Purpose |
|------|---------|
| `Heatmap.php` | Aggregate playback events into 10s position buckets, read from cache |
| `RealtimeDashboard.php` | Query active viewers from ms_watch_sessions (heartbeat < 5min) |
| `SuspiciousActivity.php` | Multi-IP detection with configurable sensitivity, alert creation, user whitelisting |

### includes/Milestones/

| File | Purpose |
|------|---------|
| `AdvancedActions.php` | Hook `mediashield_milestone_reached`. Execute tag/email/webhook actions per config |

### includes/DRM/

| File | Purpose |
|------|---------|
| `KeyServer.php` | Generate/store AES-128 content keys in ms_drm_keys |
| `WidevineLicense.php` | Issue/revoke streaming/persistent licenses. Validates access via AccessControl |
| `Packager.php` | Route packaging: cloud_bunny (auto), cloud_aws (stub), local_shaka (CLI), none |
| `OfflineManager.php` | PWA service worker registration, "Save for Offline" button |

### includes/Export/

| File | Purpose |
|------|---------|
| `CsvExporter.php` | Stream CSV to php://output (watch_sessions, milestones, users). 50k row limit |
| `PdfExporter.php` | Dompdf A4 report. Async via Action Scheduler. 24h transient download URL |

### includes/Reports/

| File | Purpose |
|------|---------|
| `WeeklyDigest.php` | Weekly HTML email: views, completions, avg completion, top 5 videos, alerts |

### includes/Licensing/

| File | Purpose |
|------|---------|
| `Messenger.php` | EDD SL SDK messenger with custom translations |

### includes/Admin/

| File | Purpose |
|------|---------|
| `DRMSettings.php` | Inject/save DRM settings via `mediashield_settings_response`/`mediashield_settings_update` |

### includes/Cron/

| File | Purpose |
|------|---------|
| `ProCleanup.php` | Schedule & run: heatmap aggregation (hourly), alert pruning (daily), email retention (daily) |

### includes/REST/ (7 controllers)

| File | Purpose |
|------|---------|
| `PlatformController.php` | `/platforms` CRUD. Encrypts credentials with AES-256-CBC |
| `HeatmapController.php` | `/analytics/heatmap/{id}`, playlist-funnel, device-breakdown |
| `SuspiciousController.php` | `/analytics/suspicious` list, dismiss, safe-user |
| `RealtimeController.php` | `/realtime/viewers` active viewer list |
| `MilestoneConfigController.php` | `/milestones/config` GET/PUT with validation |
| `DRMController.php` | `/drm/license`, `/drm/offline`, `/drm/revoke` |
| `ExportController.php` | `/export/csv/{type}`, `/export/pdf/report`, `/export/status/{job_id}` |

### JavaScript (assets/js/)

| File | Purpose |
|------|---------|
| `email-gate.js` | Email capture overlay. Listens: `mediashield:access-denied`. Fires: `mediashield:email-gate-passed` |
| `drm-player.js` | Shaka Player + Widevine EME. Listens: `mediashield:player-ready` |
| `frontend-upload.js` | Upload form XHR with progress tracking |
| `offline-sw.js` | Service Worker for DASH segment caching (offline playback) |
| `admin/heatmap.js` | Chart.js bar + retention line chart |
| `admin/realtime.js` | 30s polling viewer table |

### React Admin SPA (src/admin/)

| File | Purpose |
|------|---------|
| `index.js` | Entry point. Adds 6 routes via `mediashield_admin_routes` JS filter |
| `pages/Platforms.js` | Platform connection CRUD |
| `pages/Alerts.js` | Suspicious activity table with dismiss/safe |
| `pages/Heatmap.js` | Per-video Chart.js heatmap + device breakdown |
| `pages/Realtime.js` | Live viewer count, 15s auto-refresh |
| `pages/DRM.js` | DRM config + license revocation |
| `pages/Export.js` | CSV download + async PDF generation |

### CSS (assets/css/)

| File | Purpose |
|------|---------|
| `admin-pro.css` | Admin SPA styles (heatmap, realtime, alerts, platforms, DRM) |
| `email-gate.css` | Email gate overlay (dark backdrop, form, consent) |
| `frontend-upload.css` | Upload form (fields, progress bar, messages) |

---

## Database Tables

All tables use `{$wpdb->prefix}` prefix. Created via `dbDelta` in `DB\Schema`.

| Table | Columns |
|-------|---------|
| `ms_playback_events` | id, session_id, event_type (play/pause/seek/buffer/complete/focus_lost/focus_gained), position, timestamp, metadata (JSON) |
| `ms_platform_connections` | id, platform, api_key, api_secret, extra_config (JSON), is_active, connected_by, connected_at |
| `ms_upload_queue` | id, video_id, file_path, target_platform, status (pending/uploading/processing/complete/failed), progress, error_message, uploaded_by, created_at, completed_at |
| `ms_activity_alerts` | id, user_id, video_id, alert_type (multi_ip/devtools/rapid_seek/concurrent_stream/vpn_detected), severity, details (JSON), is_dismissed, created_at |
| `ms_drm_licenses` | id, video_id, user_id, license_type (streaming/persistent), license_token, device_id, expires_at, created_at, revoked_at |
| `ms_heatmap_cache` | id, video_id, position_bucket, view_count, avg_duration, last_aggregated |
| `ms_drm_keys` | id, video_id (unique), key_id, content_key_encrypted, iv, created_at |
| `ms_email_captures` | id, video_id, email (unique per video), name, consent_given, consent_text, ip_address, source, created_at |

---

## REST API Endpoints

All routes under namespace `mediashield-pro/v1`. Require `manage_options` unless noted.

### Platforms
| Method | Route | Description |
|--------|-------|-------------|
| GET/POST | `/platforms` | List/create platform connections |
| DELETE | `/platforms/{id}` | Disconnect platform |

### Analytics
| Method | Route | Description |
|--------|-------|-------------|
| GET | `/analytics/heatmap/{id}` | Heatmap data for video |
| GET | `/analytics/playlist-funnel/{playlist_id}` | Drop-off funnel for playlist |
| GET | `/analytics/device-breakdown` | Device type distribution |
| GET | `/analytics/suspicious` | Suspicious activity alerts (paginated) |
| PATCH | `/analytics/suspicious/{id}/dismiss` | Dismiss an alert |
| POST | `/analytics/suspicious/safe-user` | Mark user as safe |
| GET | `/realtime/viewers` | Currently active viewer count |

### Milestones
| Method | Route | Description |
|--------|-------|-------------|
| GET/PUT | `/milestones/config` | Milestone action configuration |

### DRM
| Method | Route | Permission | Description |
|--------|-------|------------|-------------|
| POST | `/drm/license` | logged_in | Issue streaming DRM license |
| POST | `/drm/offline` | logged_in | Issue persistent/offline DRM license |
| POST | `/drm/revoke` | manage_options | Revoke all licenses for user+video |

### Export
| Method | Route | Description |
|--------|-------|-------------|
| GET | `/export/csv/{type}` | Download CSV (watch_sessions, milestones, users) |
| POST | `/export/pdf/report` | Queue async PDF report |
| GET | `/export/status/{job_id}` | Check export job status |

### Email Gate
| Method | Route | Permission | Description |
|--------|-------|------------|-------------|
| POST | `/email-gate/submit` | public | Submit email for gated video access |

---

## Hooks Into Free Plugin

### Filters consumed
| Hook | Pro Class | Priority | Purpose |
|------|-----------|----------|---------|
| `mediashield_can_watch` | `Access\EmailGate` | 15 | Require email capture before access |
| `mediashield_can_watch` | `Access\RoleAccess` | 20 | Deny if user lacks required role |
| `mediashield_watermark_config` | `Watermark\AdvancedConfig` | 10 | Add position, font, rotation, per-video config |
| `mediashield_upload_drivers` | `Core\Plugin` | 10 | Register Bunny/Vimeo/YouTube/Wistia drivers |
| `mediashield_player_type` | `Core\Plugin` | 10 | Override to `drm` for DRM-protected videos |
| `mediashield_settings_response` | `Admin\DRMSettings`, `Watermark\AdvancedConfig` | 10 | Inject pro settings into GET response |
| `mediashield_settings_update` | `Admin\DRMSettings`, `Watermark\AdvancedConfig` | 10 | Save pro settings from PUT request |

### Actions consumed
| Hook | Pro Class | Purpose |
|------|-----------|---------|
| `mediashield_milestone_reached` | `Milestones\AdvancedActions` | Fire webhook, send email, tag user |
| `mediashield_session_started` | `Analytics\SuspiciousActivity` | Check for multi-IP, concurrent streams |
| `mediashield_generate_pdf` | `Export\PdfExporter` | Async PDF report handler |

### Actions fired
| Hook | Parameters | Description |
|------|------------|-------------|
| `mediashield_pro_loaded` | (none) | Fired after pro plugin fully loaded |
| `mediashield_fire_webhook` | $url, $payload | Webhook dispatch from milestone actions |

---

## Cron Jobs (Action Scheduler)

All scheduled via Action Scheduler in `Cron\ProCleanup` and `Reports\WeeklyDigest`.

| Action Hook | Frequency | Description |
|-------------|-----------|-------------|
| `ms_heatmap_aggregation` | Hourly | Aggregate `ms_playback_events` into `ms_heatmap_cache` buckets |
| `ms_alert_pruning` | Daily | Delete dismissed alerts older than 90 days from `ms_activity_alerts` |
| `ms_email_capture_retention` | Daily | Delete expired email captures based on `ms_email_retention_months` option (default 12) |
| `ms_weekly_digest` | Weekly | Send analytics summary email to site admins |

Group: `mediashield-pro`

---

## wp_options Used

| Option Key | Default | Description |
|------------|---------|-------------|
| `ms_pro_db_version` | 0 | Pro DB schema version |
| `ms_pro_watermark_fields` | `['username','ip']` | Watermark text field components |
| `ms_pro_watermark_custom_text` | `''` | Custom watermark text |
| `ms_pro_watermark_font_size` | `'medium'` | Font size: small/medium/large |
| `ms_show_badge` | `true` | Show MediaShield badge |
| `ms_pro_milestone_config` | `[]` | Milestone action config |
| `ms_drm_method` | `'none'` | DRM method: cloud_bunny/cloud_aws/local_shaka/none |
| `ms_drm_shaka_path` | `'packager'` | Shaka Packager binary path |
| `ms_drm_license_duration_streaming` | `86400` | Streaming license (seconds, 24h) |
| `ms_drm_license_duration_persistent` | `2592000` | Persistent license (seconds, 30d) |
| `ms_drm_auto_package` | `false` | Auto-package with DRM |
| `ms_suspicious_sensitivity` | `'medium'` | Sensitivity: low/medium/high |
| `ms_safe_users` | `[]` | Whitelisted user IDs |
| `ms_email_gate_webhook_url` | `''` | Webhook for email gate |
| `ms_email_gate_cookie_duration` | `7` | Cookie expiry (days) |
| `ms_email_retention_months` | `12` | Email capture retention |
| `ms_weekly_digest_enabled` | `true` | Enable weekly digest |
| `ms_weekly_digest_email` | admin email | Digest recipient |
| `ms_heatmap_last_aggregated` | epoch | Last aggregation time |

---

## Post Meta (on mediashield_video CPT)

| Meta Key | Set By | Purpose |
|----------|--------|---------|
| `_ms_access_role` | Editor | Required role for video access |
| `_ms_access_type` | Editor | Access type (e.g., `email_gate`) |
| `_ms_platform` | Upload drivers | Platform name (bunny, vimeo, youtube, wistia) |
| `_ms_platform_video_id` | Upload drivers | Platform-specific video ID |
| `_ms_source_url` | Upload drivers | Original source file path |
| `_ms_protection_level` | Upload drivers | Protection level (standard, drm) |
| `_ms_library_id` | BunnyStream | Bunny library ID |
| `_ms_wistia_numeric_id` | WistiaApi | Wistia numeric ID |
| `_ms_drm_enabled` | Packager | DRM enabled flag |
| `_ms_drm_method` | Packager | DRM method used |
| `_ms_drm_output_dir` | Packager | Shaka output directory |
| `_ms_drm_packaged_at` | Packager | Packaging timestamp |
| `_ms_drm_packaging_status` | Packager | Job status (queued) |
| `_ms_drm_packaging_action_id` | Packager | Action Scheduler job ID |

---

## Licensing

Uses EDD Software Licensing SDK via `easy-digital-downloads/edd-sl-sdk` Composer package.

- **Store URL:** https://wbcomdesigns.com
- **Registration:** `edd_sl_sdk_registry` action in main plugin file
- **License check:** `Plugin::is_licensed()` reads `mediashield-pro_license` option
- **Filter:** `mediashield_pro_license_valid` allows overriding license status
- **Admin notices:** `Licensing\Messenger` class displays activation/expiry notices

---

## Security Notes

- All platform credentials encrypted with AES-256-CBC using `SECURE_AUTH_SALT`
- Shaka Packager CLI arguments escaped with `escapeshellarg()`
- REST permissions: `manage_options` for admin, `is_user_logged_in` for DRM, public for email gate
- Input sanitization throughout: `sanitize_text_field`, `sanitize_email`, `absint`, `wp_kses_post`, `esc_url_raw`
- UNIQUE constraints on `ms_drm_keys.video_id` and `ms_email_captures.(video_id, email)`

---

## Detailed Documentation

- Feature Audit: `docs/audit/FEATURE_AUDIT.md`
- Code Flow Maps: `docs/audit/CODE_FLOWS.md`
- Design Spec: `docs/DESIGN_SPEC_v2.md`
- Implementation Plan: `docs/IMPLEMENTATION_PLAN_v2.md`

---

## Recent Changes

| Date | Files | Summary |
|------|-------|---------|
| 2026-04-01 | docs/ | Full plugin onboard: architecture docs, feature audit, code flows |
| 2026-03-30 | Initial | v1.0.0 -- Full pro plugin implementation |
