# MediaShield Pro - Feature Audit Report

**Plugin:** MediaShield Pro v1.0.0
**Audited:** 2026-04-01
**Namespace:** `MediaShieldPro\`
**Dependency:** Requires free MediaShield plugin (`MEDIASHIELD_VERSION` constant)
**PHP:** >=8.1 | **WP:** >=6.5

---

## 1. Plugin Bootstrap

| Item | Detail |
|------|--------|
| Main file | `mediashield-pro.php` |
| Autoload | PSR-4 via Composer (`includes/` -> `MediaShieldPro\`) |
| Singleton | `Core\Plugin::instance()` |
| Load priority | `plugins_loaded` at priority 20 (after free at 10) |
| Dependency check | `defined('MEDIASHIELD_VERSION')` -- admin notice if missing |
| EDD licensing | `edd_sl_sdk_registry` action, item ID 98765 (placeholder) |
| License check | `Plugin::is_licensed()` reads `mediashield-pro_license` option |
| Activation | `Core\Activator::activate()` -- creates DB tables, sets `ms_pro_db_version` |
| Deactivation | `Core\Deactivator::deactivate()` -- clears crons, resets `ms_show_badge` |
| Uninstall | `uninstall.php` -- drops 8 tables, deletes 17 options, clears transients |
| Migration | `Core\Migrator::run()` -- compares `ms_pro_db_version` to `MEDIASHIELD_PRO_DB_VERSION` |

---

## 2. Database Tables (8 tables)

All tables use `{$wpdb->prefix}` prefix. Created via `DB\Schema::create_tables()` using `dbDelta()`.

| Table | Purpose | Key Columns |
|-------|---------|-------------|
| `ms_playback_events` | Granular playback events | session_id, event_type (ENUM: play/pause/seek/buffer/complete/focus_lost/focus_gained), position, metadata (JSON) |
| `ms_platform_connections` | Video platform API credentials | platform, api_key (encrypted), api_secret (encrypted), extra_config (JSON), is_active |
| `ms_upload_queue` | Upload job queue | video_id, file_path, target_platform, status (ENUM: pending/uploading/processing/complete/failed), progress |
| `ms_activity_alerts` | Suspicious activity alerts | user_id, video_id, alert_type (ENUM: multi_ip/devtools/rapid_seek/concurrent_stream/vpn_detected), severity, details (JSON), is_dismissed |
| `ms_drm_licenses` | DRM license records | video_id, user_id, license_type (ENUM: streaming/persistent), license_token, device_id, expires_at, revoked_at |
| `ms_heatmap_cache` | Aggregated heatmap buckets | video_id, position_bucket, view_count, avg_duration, last_aggregated |
| `ms_drm_keys` | Content encryption keys | video_id (UNIQUE), key_id, content_key_encrypted, iv |
| `ms_email_captures` | Email gate submissions | video_id, email (UNIQUE per video), name, consent_given, consent_text, ip_address, source |

---

## 3. REST API Endpoints

All routes under namespace `mediashield-pro/v1`. Permission defaults to `manage_options` unless noted.

### 3.1 Platform Management (`REST\PlatformController`)

| Method | Route | Permission | Description |
|--------|-------|------------|-------------|
| GET | `/platforms` | manage_options | List connected platforms (ID, name, status, connected_at) |
| POST | `/platforms` | manage_options | Connect platform (encrypts api_key/api_secret with AES-256-CBC) |
| DELETE | `/platforms/{id}` | manage_options | Disconnect/delete platform |

### 3.2 Heatmap Analytics (`REST\HeatmapController`)

| Method | Route | Permission | Description |
|--------|-------|------------|-------------|
| GET | `/analytics/heatmap/{id}` | manage_options | Get heatmap buckets for a video |
| GET | `/analytics/playlist-funnel/{playlist_id}` | manage_options | Cross-video drop-off funnel |
| GET | `/analytics/device-breakdown` | manage_options | Device/browser distribution (param: period) |

### 3.3 Suspicious Activity (`REST\SuspiciousController`)

| Method | Route | Permission | Description |
|--------|-------|------------|-------------|
| GET | `/analytics/suspicious` | manage_options | Paginated alert list (params: page, per_page) |
| PATCH | `/analytics/suspicious/{id}/dismiss` | manage_options | Dismiss an alert |
| POST | `/analytics/suspicious/safe-user` | manage_options | Whitelist a user |

### 3.4 Realtime Viewers (`REST\RealtimeController`)

| Method | Route | Permission | Description |
|--------|-------|------------|-------------|
| GET | `/realtime/viewers` | manage_options | Active viewer list with metadata |

### 3.5 Milestone Configuration (`REST\MilestoneConfigController`)

| Method | Route | Permission | Description |
|--------|-------|------------|-------------|
| GET | `/milestones/config` | manage_options | Get milestone action config |
| PUT | `/milestones/config` | manage_options | Save milestone action config (validates threshold 1-100, action types: tag/email/webhook) |

### 3.6 DRM Licensing (`REST\DRMController`)

| Method | Route | Permission | Description |
|--------|-------|------------|-------------|
| POST | `/drm/license` | logged_in | Issue streaming license |
| POST | `/drm/offline` | logged_in | Issue persistent/offline license |
| POST | `/drm/revoke` | manage_options | Revoke all licenses for user+video |

### 3.7 Data Export (`REST\ExportController`)

| Method | Route | Permission | Description |
|--------|-------|------------|-------------|
| GET | `/export/csv/{type}` | manage_options | Stream CSV (types: watch_sessions, milestones, users) |
| POST | `/export/pdf/report` | manage_options | Queue async PDF report (Action Scheduler) |
| GET | `/export/status/{job_id}` | manage_options | Check PDF job status |

### 3.8 Email Gate (`Access\EmailGate` -- registered separately)

| Method | Route | Permission | Description |
|--------|-------|------------|-------------|
| POST | `/email-gate/submit` | public | Submit email for gated video access |

---

## 4. Filters Consumed (from free plugin)

| Hook | Class | Priority | Purpose |
|------|-------|----------|---------|
| `mediashield_can_watch` | `Access\EmailGate` | 15 | Require email capture before video access |
| `mediashield_can_watch` | `Access\RoleAccess` | 20 | Deny if user lacks required WordPress role |
| `mediashield_watermark_config` | `Watermark\AdvancedConfig` | 10 | Build watermark text from configurable fields (username, email, ip, user_id, timestamp, site_name, custom_text) |
| `mediashield_upload_drivers` | `Core\Plugin` | 10 | Register Bunny/Vimeo/YouTube/Wistia upload drivers |
| `mediashield_player_type` | `Core\Plugin` | 10 | Override to `drm` when `_ms_protection_level` = drm and DRM method is not none |
| `mediashield_settings_response` | `Admin\DRMSettings` | 10 | Inject DRM settings into GET response |
| `mediashield_settings_response` | `Watermark\AdvancedConfig` | 10 | Inject watermark pro settings into GET response |
| `mediashield_settings_update` | `Admin\DRMSettings` | 10 | Save DRM settings from PUT request |
| `mediashield_settings_update` | `Watermark\AdvancedConfig` | 10 | Save watermark pro settings from PUT request |

---

## 5. Actions Consumed

| Hook | Class | Purpose |
|------|-------|---------|
| `mediashield_milestone_reached` | `Milestones\AdvancedActions` | Fire tag/email/webhook on milestone |
| `mediashield_session_started` | `Analytics\SuspiciousActivity` | Check multi-IP, concurrent streams |
| `mediashield_generate_pdf` | `Export\PdfExporter` | Async PDF report handler |
| `mediashield_fire_webhook` | `Milestones\AdvancedActions` | Fire webhook HTTP POST |

---

## 6. Actions Fired

| Hook | Parameters | Fired By |
|------|------------|----------|
| `mediashield_pro_loaded` | (none) | `Core\Plugin::__construct()` |
| `mediashield_fire_webhook` | $url, $payload | `Milestones\AdvancedActions::action_webhook()` via Action Scheduler |

---

## 7. Shortcodes

| Shortcode | Class | Capability | Description |
|-----------|-------|------------|-------------|
| `[mediashield_upload]` | `Upload\FrontendUpload` | `upload_mediashield` | Frontend video upload form with file picker, platform selector, progress bar |

---

## 8. Cron Jobs (Action Scheduler)

All scheduled via Action Scheduler. Group: `mediashield-pro`.

| Action Hook | Frequency | Class | Description |
|-------------|-----------|-------|-------------|
| `ms_heatmap_aggregation` | Hourly | `Cron\ProCleanup` | Aggregate `ms_playback_events` into `ms_heatmap_cache` (10-second buckets) |
| `ms_alert_pruning` | Daily | `Cron\ProCleanup` | Delete dismissed alerts older than 90 days |
| `ms_email_capture_retention` | Daily | `Cron\ProCleanup` | Delete email captures older than `ms_email_retention_months` (default 12) |
| `ms_weekly_digest` | Weekly | `Reports\WeeklyDigest` | Send analytics summary HTML email to admins |

---

## 9. Custom Post Types

None registered by Pro. Pro extends the free plugin's `mediashield_video` CPT via meta fields:
- `_ms_access_role` -- Required role for video access
- `_ms_access_type` -- Access type (e.g., `email_gate`)
- `_ms_platform` -- Upload platform (bunny, vimeo, youtube, wistia)
- `_ms_platform_video_id` -- Platform-specific video ID
- `_ms_protection_level` -- Protection level (standard, drm)
- `_ms_drm_enabled` -- DRM enabled flag
- `_ms_drm_method` -- DRM method used
- `_ms_drm_output_dir` -- Local Shaka Packager output directory
- `_ms_drm_packaged_at` -- DRM packaging timestamp
- `_ms_drm_packaging_status` -- Packaging job status
- `_ms_drm_packaging_action_id` -- Action Scheduler job ID
- `_ms_library_id` -- Bunny Stream library ID
- `_ms_wistia_numeric_id` -- Wistia numeric ID
- `_ms_source_url` -- Original source file path

---

## 10. wp_options Used

| Option Key | Default | Description |
|------------|---------|-------------|
| `ms_pro_db_version` | 0 | Pro DB schema version |
| `ms_pro_watermark_fields` | `['username','ip']` | Watermark text field components |
| `ms_pro_watermark_custom_text` | `''` | Custom watermark text |
| `ms_pro_watermark_font_size` | `'medium'` | Watermark font size (small/medium/large) |
| `ms_show_badge` | `true` | Show MediaShield badge (pro can disable) |
| `ms_pro_milestone_config` | `[]` | Milestone action config array |
| `ms_drm_method` | `'none'` | DRM method: cloud_bunny, cloud_aws, local_shaka, none |
| `ms_drm_shaka_path` | `'packager'` | Shaka Packager binary path |
| `ms_drm_license_duration_streaming` | `86400` | Streaming license duration (seconds, default 24h) |
| `ms_drm_license_duration_persistent` | `2592000` | Persistent license duration (seconds, default 30d) |
| `ms_drm_auto_package` | `false` | Auto-package videos with DRM |
| `ms_suspicious_sensitivity` | `'medium'` | Suspicious activity sensitivity (low/medium/high) |
| `ms_safe_users` | `[]` | Whitelisted user IDs |
| `ms_email_gate_webhook_url` | `''` | Webhook URL for email gate submissions |
| `ms_email_gate_cookie_duration` | `7` | Email gate cookie expiry (days) |
| `ms_email_retention_months` | `12` | Email capture retention period (months) |
| `ms_weekly_digest_enabled` | `true` | Enable weekly digest email |
| `ms_weekly_digest_email` | admin email | Weekly digest recipient |
| `ms_enabled` | `true` | Master enable (from free plugin, checked by pro) |
| `ms_heatmap_last_aggregated` | `'1970-01-01 00:00:00'` | Last heatmap aggregation timestamp |
| `mediashield-pro_license` | `[]` | EDD license data |
| `mediashield-pro_license_key` | n/a | EDD license key |

---

## 11. Admin Pages

No dedicated admin pages. Pro injects into the free plugin's `toplevel_page_mediashield` admin page via:
- `admin_enqueue_scripts` -- Enqueues `build/admin/index.js` + CSS on MediaShield admin page
- `mediashield_admin_routes` JS filter -- Adds 6 admin SPA routes (Platforms, Alerts, Heatmap, Realtime, DRM, Export)

---

## 12. Upload Platform Drivers

All implement `MediaShield\Upload\Drivers\DriverInterface` from the free plugin.

| Driver | Class | API | Upload Protocol | Credentials |
|--------|-------|-----|-----------------|-------------|
| Bunny Stream | `Upload\Drivers\BunnyStream` | `video.bunnycdn.com` | tus resumable (5MB chunks) | api_key + library_id (api_secret) |
| Vimeo | `Upload\Drivers\VimeoApi` | `api.vimeo.com` v3 | tus resumable (5MB chunks) | access_token |
| YouTube | `Upload\Drivers\YouTubeApi` | `googleapis.com` Data API v3 | Resumable upload (5MB chunks) | access_token (OAuth) |
| Wistia | `Upload\Drivers\WistiaApi` | `upload.wistia.com` | Multipart form upload | api_token |

All drivers: encrypt credentials with AES-256-CBC using `SECURE_AUTH_SALT`, create `mediashield_video` CPT posts on upload, support `upload()`, `get_status()`, `delete()`, `get_embed_url()` methods.

---

## 13. DRM Architecture

| Class | Responsibility |
|-------|----------------|
| `DRM\KeyServer` | Generate/store AES-128 content keys in `ms_drm_keys` (encrypted with AES-256-CBC) |
| `DRM\Packager` | Route packaging to cloud_bunny (auto), cloud_aws (stub), local_shaka (CLI), or none |
| `DRM\WidevineLicense` | Issue/revoke streaming/persistent licenses, validate access via `AccessControl::can_watch()` |
| `DRM\OfflineManager` | Register PWA service worker on singular `ms_video` pages, render "Save for Offline" button |

---

## 14. Security Features

| Feature | Implementation |
|---------|----------------|
| Credential encryption | AES-256-CBC with `SECURE_AUTH_SALT` as key (all platform API keys) |
| REST permissions | `manage_options` for admin endpoints, `is_user_logged_in` for DRM license endpoints, `__return_true` for email gate submit |
| Input sanitization | `sanitize_text_field`, `sanitize_email`, `sanitize_textarea_field`, `wp_kses_post`, `absint`, `esc_url_raw` throughout |
| Shell injection prevention | `escapeshellarg()` on all Shaka Packager CLI arguments |
| Cookie security | Email gate cookie uses `COOKIEPATH`, `COOKIE_DOMAIN`, `is_ssl()`, httpOnly=false (for JS access) |
| IP detection | Checks `HTTP_X_FORWARDED_FOR` then `REMOTE_ADDR`, validates with `FILTER_VALIDATE_IP` |

---

## 15. Composer Dependencies

| Package | Version | Purpose |
|---------|---------|---------|
| `easy-digital-downloads/edd-sl-sdk` | dev-main | EDD Software Licensing SDK |
| `dompdf/dompdf` | ^2.0 | PDF report generation |

---

## 16. JavaScript Modules

### Frontend (assets/js/)

| File | Purpose | Dependencies | Events |
|------|---------|--------------|--------|
| `email-gate.js` | Email capture overlay on gated videos | `mediashield-player-wrapper` | Listens: `mediashield:access-denied`; Fires: `mediashield:email-gate-passed` |
| `drm-player.js` | Shaka Player + Widevine EME integration | `mediashield-player-wrapper`, `shaka` | Listens: `mediashield:player-ready` |
| `frontend-upload.js` | Frontend upload form with XHR progress | `wp-api-fetch` | DOM: `#mediashield-upload-form` submit |
| `offline-sw.js` | Service Worker for offline DASH segment caching | none (standalone SW) | Messages: cache-segments, clear-cache, get-cache-size |

### Admin (assets/js/admin/)

| File | Purpose | Dependencies |
|------|---------|--------------|
| `heatmap.js` | Chart.js bar chart + retention line for heatmap data | `chart.js` |
| `realtime.js` | Polls `/realtime/viewers` every 30s, renders active viewer table | none (vanilla JS) |

### Admin SPA (src/admin/)

| File | Purpose | Framework |
|------|---------|-----------|
| `index.js` | Entry point -- adds 6 routes via `mediashield_admin_routes` filter | `@wordpress/hooks` |
| `pages/Platforms.js` | Platform CRUD UI | React + `@wordpress/components` |
| `pages/Alerts.js` | Suspicious activity alerts table with dismiss/safe-user | React + `@wordpress/components` |
| `pages/Heatmap.js` | Per-video heatmap Chart.js + device breakdown table | React + Chart.js |
| `pages/Realtime.js` | Live viewer count with 15s auto-refresh | React + `@wordpress/components` |
| `pages/DRM.js` | DRM config (method, license durations, revoke) | React + `@wordpress/components` |
| `pages/Export.js` | CSV download + async PDF generation | React + `@wordpress/components` |

---

## 17. CSS Files

| File | Purpose |
|------|---------|
| `assets/css/admin-pro.css` | Admin SPA styles for pro pages (heatmap chart, realtime table, alert items, platform cards, DRM selector) |
| `assets/css/email-gate.css` | Email gate overlay styles (dark overlay, form, consent, submit button) |
| `assets/css/frontend-upload.css` | Frontend upload form styles (fields, progress bar, success/error messages) |
| `src/admin/admin-pro.css` | Source admin CSS (imported by index.js for build) |
