# MediaShield Pro - Plugin Architecture

**Version:** 1.0.0
**Generated:** 2026-04-01

---

## 1. High-Level Architecture

MediaShield Pro is a free-to-pro add-on architecture. The Pro plugin extends the free MediaShield plugin through WordPress filters and actions, never replacing core behavior but layering additional capabilities on top.

```
+--------------------------+      filters/actions      +---------------------------+
|    MediaShield (Free)    | <-----------------------> |   MediaShield Pro         |
|                          |                           |                           |
|  - Video CPT             |  mediashield_can_watch    |  - Role-based access      |
|  - Player wrapper        |  mediashield_watermark_*  |  - Email gate             |
|  - Basic watermarks      |  mediashield_upload_*     |  - Advanced watermarks    |
|  - Session tracking      |  mediashield_player_type  |  - Platform drivers       |
|  - Basic milestones      |  mediashield_milestone_*  |  - DRM system             |
|  - Settings REST API     |  mediashield_settings_*   |  - Analytics (heatmap,    |
|  - Self-hosted upload    |  mediashield_session_*    |    realtime, suspicious)  |
|  - Admin SPA shell       |  mediashield_admin_routes |  - Export (CSV, PDF)      |
|                          |  (JS filter)              |  - Weekly digest email    |
+--------------------------+                           +---------------------------+
```

---

## 2. Namespace & Autoloading

```
MediaShieldPro\          PSR-4 root -> includes/
  Core\                  Plugin bootstrap, activation, deactivation, migration
  DB\                    Database schema (dbDelta)
  Access\                Access control filters (role, email gate)
  Watermark\             Watermark configuration enhancement
  Upload\                Frontend upload shortcode
    Drivers\             Platform-specific upload implementations
  Analytics\             Heatmap, realtime, suspicious activity
  Milestones\            Advanced milestone actions (tag, email, webhook)
  DRM\                   Key management, licensing, packaging, offline
  Export\                CSV and PDF data export
  Reports\               Scheduled email reports
  Licensing\             EDD SL SDK messenger
  Admin\                 Settings injection
  Cron\                  Scheduled cleanup tasks
  REST\                  7 REST API controllers
```

---

## 3. Data Flow Diagram

```
                        +-----------+
                        |  Visitor  |
                        +-----+-----+
                              |
                    [watches video]
                              |
                    +---------v---------+
                    | Access Control    |
                    | Chain             |
                    |                   |
                    | 1. EmailGate (15) |
                    | 2. RoleAccess(20) |
                    +---------+---------+
                              |
                     [if allowed]
                              |
               +--------------v--------------+
               |                             |
        [standard player]            [DRM player]
               |                             |
               v                             v
    Player Wrapper (free)          Shaka Player + EME
               |                             |
               v                             v
    Session tracking (free)       DRM License issuance
               |                    (WidevineLicense)
               |                             |
               v                             v
    Playback events              ms_drm_licenses table
    (ms_playback_events)
               |
    +----------v----------+
    |                     |
    v                     v
  Heatmap            Suspicious
  Aggregation        Activity
  (hourly cron)      Detection
    |                     |
    v                     v
  ms_heatmap_cache   ms_activity_alerts
```

---

## 4. Module Dependency Map

```
Core\Plugin (singleton)
  |
  |-- registers --> Access\RoleAccess
  |-- registers --> Access\EmailGate
  |-- registers --> Watermark\AdvancedConfig
  |-- registers --> Upload\FrontendUpload
  |-- registers --> Analytics\SuspiciousActivity
  |-- registers --> Cron\ProCleanup
  |                   |-- calls --> Analytics\Heatmap::aggregate()
  |-- registers --> Milestones\AdvancedActions
  |-- registers --> Admin\DRMSettings
  |-- registers --> DRM\OfflineManager
  |-- registers --> Reports\WeeklyDigest
  |
  |-- registers REST routes:
  |     |-- REST\PlatformController
  |     |-- REST\HeatmapController --> Analytics\Heatmap
  |     |-- REST\SuspiciousController --> Analytics\SuspiciousActivity
  |     |-- REST\RealtimeController --> Analytics\RealtimeDashboard
  |     |-- REST\MilestoneConfigController
  |     |-- REST\DRMController --> DRM\WidevineLicense
  |     |-- REST\ExportController --> Export\CsvExporter, Export\PdfExporter

DRM\KeyServer --> REST\PlatformController (encrypt/decrypt utility)
DRM\Packager --> DRM\KeyServer (get/generate keys)
DRM\WidevineLicense --> MediaShield\Access\AccessControl (free plugin)

Upload\Drivers\* --> MediaShield\Upload\Drivers\DriverInterface (free plugin)
Upload\Drivers\* --> REST\PlatformController (credential decryption)
```

---

## 5. Encryption Architecture

All sensitive credentials use AES-256-CBC encryption at rest.

```
+------------------+     encrypt()      +------------------------+
| Plaintext        | -----------------> | Base64(IV + Ciphertext)|
| (API keys,       |                    | Stored in DB           |
|  tokens)         |                    |                        |
+------------------+     decrypt()      +------------------------+
                    <-----------------

Key derivation: hash('sha256', SECURE_AUTH_SALT, binary=true)
IV: Random 16 bytes per encryption (openssl_random_pseudo_bytes)

Two implementations (should be consolidated):
  1. PlatformController::encrypt/decrypt -- Base64(IV + ciphertext) (no separator)
  2. Upload Drivers::decrypt -- Base64(IV:ciphertext) (colon separator)
```

Note: There is an inconsistency between the PlatformController encrypt format (concatenated IV+ciphertext) and the Upload Drivers decrypt format (colon-separated IV:ciphertext). This should be investigated and unified.

---

## 6. Admin UI Architecture

The free plugin provides a React-based Single Page Application (SPA) on the `toplevel_page_mediashield` admin page. Pro extends it via the `mediashield_admin_routes` JavaScript filter (using `@wordpress/hooks`).

```
Free Admin SPA
  |-- Dashboard
  |-- Videos
  |-- Sessions
  |                   <-- Pro inserts here via splice -->
  |-- [Platforms]     (Pro) REST: /platforms
  |-- [Alerts]        (Pro) REST: /analytics/suspicious
  |-- [Heatmap]       (Pro) REST: /analytics/heatmap, device-breakdown
  |-- [Realtime]      (Pro) REST: /realtime/viewers (15s polling)
  |-- [DRM]           (Pro) REST: /settings (shared), /drm/revoke
  |-- [Export]        (Pro) REST: /export/csv, /export/pdf
  |-- Settings

Script dependency chain:
  mediashield-admin (free) -> mediashield-pro-admin (pro)
  wp-hooks, wp-element, wp-components, wp-api-fetch, wp-i18n, wp-notices
```

---

## 7. Cron / Background Processing

All background tasks use Action Scheduler (WooCommerce's job queue) instead of wp-cron.

| Task | Hook | Frequency | Handler |
|------|------|-----------|---------|
| Heatmap aggregation | `ms_heatmap_aggregation` | Hourly | `Cron\ProCleanup::run_heatmap_aggregation()` |
| Alert pruning | `ms_alert_pruning` | Daily | `Cron\ProCleanup::run_alert_pruning()` |
| Email retention | `ms_email_capture_retention` | Daily | `Cron\ProCleanup::run_email_capture_retention()` |
| Weekly digest | `ms_weekly_digest` | Weekly | `Reports\WeeklyDigest::send()` |
| Webhook dispatch | `mediashield_fire_webhook` | Async (one-off) | `Milestones\AdvancedActions::fire_webhook()` |
| PDF report | `mediashield_pro_pdf_report` | Async (one-off) | `Export\PdfExporter::handle_async()` |
| DRM packaging | `mediashield_pro_drm_package` | Async (one-off) | `DRM\Packager::package()` |

All recurring tasks are registered via `as_schedule_recurring_action()` in an `init` hook, guarded by `as_has_scheduled_action()` to prevent duplicates. Group: `mediashield-pro`.

---

## 8. DRM System Architecture

```
                    +------------------+
                    | Admin configures |
                    | ms_drm_method    |
                    +--------+---------+
                             |
              +--------------+--------------+
              |              |              |
        cloud_bunny    local_shaka    cloud_aws
        (auto-DRM)     (CLI binary)   (stub/future)
              |              |
              v              v
        Update meta    KeyServer::generate_key()
        (done)              |
                            v
                    Shaka Packager CLI
                    (escapeshellarg'd)
                            |
                            v
                    DASH/HLS output
                    (uploads/mediashield/drm/{id}/)
                            |
                            v
                    drm-player.js (Shaka Player)
                    EME + Widevine
                            |
                            v
                    License request ->
                    POST /drm/license ->
                    WidevineLicense::issue_license()
                            |
                            v
                    ms_drm_licenses table
```

---

## 9. Upload Driver Architecture

All 4 platform drivers implement `MediaShield\Upload\Drivers\DriverInterface` from the free plugin.

```
Interface methods:
  get_name(): string
  upload(string $file_path, array $options): array
  get_status(string $upload_id): array
  delete(string $platform_video_id): bool
  get_embed_url(string $platform_video_id): string

Common patterns across all drivers:
  1. get_credentials() - Read from ms_platform_connections, decrypt
  2. Create video entry on platform API
  3. Upload file (tus protocol or multipart)
  4. Create mediashield_video CPT post with meta
  5. Return standardized result array

Upload protocols:
  BunnyStream: tus 1.0.0 (POST create, PATCH chunks)
  VimeoApi:    tus 1.0.0 via /me/videos
  YouTubeApi:  Google resumable upload (POST init, PUT chunks)
  WistiaApi:   Multipart form (single POST, file_get_contents)

Chunk size: 5MB for all chunked uploads
```

---

## 10. Suspicious Activity Detection Architecture

```
Session starts (free plugin action)
  |
  v
SuspiciousActivity::on_session_started($user_id, $ip)
  |
  |-- Check whitelist (ms_safe_users option)
  |
  v
check_multi_ip()
  |
  |-- Read sensitivity config:
  |     low:    10 IPs / 24h
  |     medium: 5 IPs / 12h (default)
  |     high:   3 IPs / 6h
  |
  |-- COUNT(DISTINCT ip_address) from ms_watch_sessions
  |
  |-- If threshold exceeded:
        create_alert() -> INSERT ms_activity_alerts
              |
              v
        Admin sees in Alerts page (REST API)
              |
              +-- Dismiss alert (PATCH /dismiss)
              +-- Mark user safe (POST /safe-user)
                    -> Adds to ms_safe_users option
```

Additional detection (not yet wired to session hook):
- `check_rapid_seek()` -- Position jump >80% in <30 seconds

---

## 11. Known Architecture Notes

1. **PDF async action mismatch**: `ExportController` enqueues `mediashield_pro_pdf_report` but the handler is hooked to `mediashield_generate_pdf`. These are two different action hooks. The async PDF export may not execute unless the action names are aligned.

2. **Encryption format inconsistency**: `PlatformController::encrypt()` produces `Base64(IV + ciphertext)` (concatenated), but the Upload Drivers' `decrypt()` method expects `Base64('IV:ciphertext')` (colon-separated). Credentials encrypted by PlatformController may fail to decrypt in Upload Drivers.

3. **Driver credential query mismatch**: Upload Drivers query `WHERE status = 'active'` but the Schema defines the column as `is_active` (TINYINT). The WHERE clause should use `is_active = 1`.

4. **Heatmap aggregate query references columns not in schema**: The aggregate query references `video_id` and `duration` columns on `ms_playback_events`, but the schema only has `session_id`, `event_type`, `position`, `timestamp`, and `metadata`. The `video_id` and `duration` would need to come from a JOIN with `ms_watch_sessions` (free plugin table).

5. **OfflineManager checks `is_singular('ms_video')` but the CPT slug is `mediashield_video`** -- this may prevent the service worker from loading on video pages.

6. **Deactivator clears `ms_upload_cleanup` cron** but this hook is never scheduled by ProCleanup. It appears to be a leftover or planned feature.
