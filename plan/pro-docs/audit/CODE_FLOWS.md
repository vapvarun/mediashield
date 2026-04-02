# MediaShield Pro - Code Flow Maps

**Plugin:** MediaShield Pro v1.0.0
**Generated:** 2026-04-01

---

## Flow 1: Plugin Bootstrap

```
mediashield-pro.php
  |-- Define constants (VERSION, DB_VERSION, FILE, PATH, URL, PLUGIN_FILE)
  |-- Require Composer autoloader (vendor/autoload.php)
  |-- Register EDD SL SDK via `edd_sl_sdk_registry` action
  |     |-- Sets item_id=98765, url=wbcomdesigns.com
  |     |-- Messenger class: Licensing\Messenger
  |
  |-- register_activation_hook -> Core\Activator::activate()
  |     |-- Check MEDIASHIELD_VERSION defined (wp_die if missing)
  |     |-- DB\Schema::create_tables() (8 tables via dbDelta)
  |     |-- update_option('ms_pro_db_version', MEDIASHIELD_PRO_DB_VERSION)
  |
  |-- register_deactivation_hook -> Core\Deactivator::deactivate()
  |     |-- wp_clear_scheduled_hook() for 5 cron hooks
  |     |-- update_option('ms_show_badge', true)
  |
  |-- add_action('plugins_loaded', ..., 20)
        |-- Check defined('MEDIASHIELD_VERSION') else admin_notices + return
        |-- Core\Migrator::run()
        |     |-- Compare ms_pro_db_version to MEDIASHIELD_PRO_DB_VERSION
        |     |-- If stale: Schema::create_tables() + update option
        |
        |-- Core\Plugin::instance() [singleton]
              |-- Access\RoleAccess::register()
              |-- Watermark\AdvancedConfig::register()
              |-- add_filter('mediashield_upload_drivers', register_upload_drivers)
              |-- Upload\FrontendUpload::register()
              |-- Analytics\SuspiciousActivity::register()
              |-- Cron\ProCleanup::register()
              |-- Milestones\AdvancedActions::register()
              |-- Admin\DRMSettings::register()
              |-- add_filter('mediashield_player_type', override_player_type)
              |-- DRM\OfflineManager::register()
              |-- add_action('mediashield_generate_pdf', PdfExporter::handle_async)
              |-- Access\EmailGate::register()
              |-- Reports\WeeklyDigest::register()
              |-- add_action('admin_enqueue_scripts', enqueue_pro_admin)
              |-- add_action('wp_enqueue_scripts', enqueue_pro_frontend)
              |-- add_action('rest_api_init', register_rest_routes)
              |-- do_action('mediashield_pro_loaded')
```

---

## Flow 2: Video Access Control Chain

```
Free plugin calls: apply_filters('mediashield_can_watch', $result, $video_id, $user_id)
  |
  |-- [Priority 15] Access\EmailGate::check()
  |     |-- If already denied -> pass through
  |     |-- Read _ms_access_type post meta
  |     |-- If not 'email_gate' -> pass through
  |     |-- If logged in (user_id > 0) -> pass through (bypass)
  |     |-- Check ms_email_gate cookie for valid email
  |     |-- If no cookie -> return {allowed: false, reason: 'email_gate_required'}
  |     |-- Frontend JS listens for 'mediashield:access-denied' event
  |           |-- email-gate.js shows overlay form
  |           |-- User submits email -> POST /email-gate/submit
  |           |-- Server stores in ms_email_captures, sets cookie, fires webhook
  |           |-- JS dispatches 'mediashield:email-gate-passed' event
  |
  |-- [Priority 20] Access\RoleAccess::check_role()
        |-- If already denied -> pass through
        |-- If admin (manage_options) -> pass through
        |-- Read _ms_access_role post meta
        |-- If empty -> pass through (no restriction)
        |-- If guest (no user_id) -> deny
        |-- Check user has required role via in_array($role, $user->roles)
        |-- If missing role -> deny with "You do not have permission"
```

---

## Flow 3: Platform Upload (Bunny Stream Example)

```
User uploads via [mediashield_upload] shortcode form
  |
  |-- frontend-upload.js: XHR POST to /mediashield/v1/upload/init (free plugin)
  |     |-- Free plugin resolves driver via mediashield_upload_drivers filter
  |     |-- Pro registers: bunny -> BunnyStream, vimeo -> VimeoApi, etc.
  |
  |-- BunnyStream::upload($file_path, $options)
        |
        |-- Validate file exists
        |-- get_credentials() -> decrypt from ms_platform_connections table
        |     |-- SELECT api_key, api_secret WHERE platform='bunny' AND status='active'
        |     |-- decrypt() using AES-256-CBC with SECURE_AUTH_SALT
        |
        |-- Step 1: POST to Bunny API to create video entry
        |     |-- URL: https://video.bunnycdn.com/library/{library_id}/videos
        |     |-- Returns video GUID
        |
        |-- Step 2: tus resumable upload
        |     |-- POST to create tus session -> get Location header
        |     |-- Loop: PATCH 5MB chunks with Upload-Offset header
        |     |-- Continue until offset >= file_size or error
        |
        |-- Step 3: Create mediashield_video CPT post
        |     |-- wp_insert_post(post_type='mediashield_video')
        |     |-- Set post meta: _ms_platform, _ms_platform_video_id,
        |     |                   _ms_source_url, _ms_protection_level,
        |     |                   _ms_library_id
        |
        |-- Return {success, video_id, platform_video_id, embed_url}
```

---

## Flow 4: DRM License Issuance

```
Client (drm-player.js via Shaka Player EME) requests license:
  POST /mediashield-pro/v1/drm/license
    |
    |-- DRMController::issue_streaming_license()
          |-- Get video_id, device_id from request
          |-- Get current user_id
          |
          |-- WidevineLicense::issue_license($video_id, $user_id, 'streaming', $device_id)
                |-- Validate license_type is 'streaming' or 'persistent'
                |-- AccessControl::can_watch($video_id, $user_id)
                |     |-- Runs full filter chain including EmailGate + RoleAccess
                |     |-- If denied -> return WP_Error with 403
                |
                |-- Generate license_token: uuid4 + 16 random bytes
                |-- Calculate expires_at from ms_drm_license_duration_streaming option (default 24h)
                |-- INSERT into ms_drm_licenses table
                |-- Return license data (id, token, expires_at)
```

---

## Flow 5: DRM Video Packaging (Local Shaka)

```
Admin configures DRM method = 'local_shaka'

DRM\Packager::package($video_id, $file_path)
  |-- get_method() -> read ms_drm_method option
  |-- Switch to package_local_shaka()
        |
        |-- Check that the CLI execution function is available
        |-- Validate source file exists and is readable
        |
        |-- Get or generate DRM key:
        |     |-- KeyServer::get_key($video_id)
        |     |     |-- SELECT from ms_drm_keys WHERE video_id
        |     |     |-- PlatformController::decrypt() the content_key_encrypted
        |     |
        |     |-- If no key: KeyServer::generate_key($video_id)
        |           |-- random_bytes(16) for key_id and content_key
        |           |-- PlatformController::encrypt() the content_key
        |           |-- INSERT/UPDATE ms_drm_keys table
        |
        |-- Create output directory: uploads/mediashield/drm/{video_id}/
        |
        |-- Build Shaka Packager CLI command:
        |     All arguments escaped with escapeshellarg() to prevent injection
        |     Produces: video.mp4, init.mp4, stream.mpd
        |
        |-- Execute CLI command
        |-- Verify stream.mpd output file exists
        |
        |-- Set post meta:
        |     _ms_drm_enabled=true, _ms_drm_method='local_shaka',
        |     _ms_drm_output_dir, _ms_drm_packaged_at
        |
        |-- Return {video_id, method, status, output_dir, mpd_file}
```

---

## Flow 6: Heatmap Aggregation (Cron)

```
Action Scheduler fires 'ms_heatmap_aggregation' (hourly)
  |
  |-- Cron\ProCleanup::run_heatmap_aggregation()
        |-- Analytics\Heatmap::aggregate()
              |
              |-- Read ms_heatmap_last_aggregated option
              |-- SELECT from ms_playback_events WHERE created_at > last_run
              |     GROUP BY video_id, FLOOR(position/10)*10
              |     -> Produces 10-second position buckets
              |
              |-- For each aggregated row:
              |     |-- Check ms_heatmap_cache for existing (video_id, position_bucket)
              |     |-- If exists: UPDATE with weighted average avg_duration + sum view_count
              |     |-- If not: INSERT new cache row
              |
              |-- update_option('ms_heatmap_last_aggregated', now)
```

---

## Flow 7: Suspicious Activity Detection

```
Free plugin fires: do_action('mediashield_session_started', $user_id, $ip)
  |
  |-- Analytics\SuspiciousActivity::on_session_started($user_id, $ip)
        |-- Check is_user_safe() (reads ms_safe_users option)
        |-- If safe -> return
        |
        |-- check_multi_ip($user_id, $ip)
              |-- Read ms_suspicious_sensitivity option (low/medium/high)
              |     low:    10 IPs in 24h
              |     medium: 5 IPs in 12h (default)
              |     high:   3 IPs in 6h
              |
              |-- SELECT COUNT(DISTINCT ip_address) FROM ms_watch_sessions
              |     WHERE user_id = ? AND started_at > (now - window_hours)
              |
              |-- If count >= threshold:
                    create_alert($user_id, 0, 'multi_ip', 'warning', message)
                      |-- INSERT into ms_activity_alerts table
```

---

## Flow 8: Milestone Actions

```
Free plugin fires: do_action('mediashield_milestone_reached', $user_id, $video_id, $pct, $session_id)
  |
  |-- Milestones\AdvancedActions::handle_milestone()
        |-- Read ms_pro_milestone_config option
        |-- Loop config array, find matching threshold == $pct
        |-- For each matching milestone, loop its actions:
              |
              |-- 'tag' action:
              |     update_user_meta($user_id, "ms_completed_{video_id}_{pct}", timestamp)
              |
              |-- 'email' action:
              |     |-- Determine recipient: 'admin' -> admin_email, 'user' -> user email
              |     |-- Replace placeholders: {user_name}, {video_title}, {percentage}, {user_email}
              |     |-- wp_mail($to, $subject, $body)
              |
              |-- 'webhook' action:
                    |-- If Action Scheduler available:
                    |     as_enqueue_async_action('mediashield_fire_webhook', [$url, $payload])
                    |-- Else: fire_webhook() synchronously
                          |-- wp_remote_post($url, JSON payload)
                          |-- Payload: event, user_id, video_id, percentage, session_id, timestamp, site_url
```

---

## Flow 9: Email Gate Submission

```
email-gate.js: User fills email + consent checkbox on overlay
  |
  |-- POST /mediashield-pro/v1/email-gate/submit
  |     Body: {email, video_id, consent: 1}
  |
  |-- EmailGate::handle_submit()
        |-- Validate consent == 1 (else 400)
        |-- Validate video exists and is mediashield_video CPT (else 404)
        |
        |-- INSERT into ms_email_captures:
        |     email, video_id, consent_given=1, ip_address, created_at
        |
        |-- Set cookie:
        |     name: ms_email_gate
        |     value: email address
        |     duration: ms_email_gate_cookie_duration option (default 7 days)
        |     path: COOKIEPATH, domain: COOKIE_DOMAIN, secure: is_ssl()
        |
        |-- Fire webhook if configured:
        |     Read ms_email_gate_webhook_url option
        |     If valid URL: wp_remote_post(non-blocking, timeout=5)
        |     Payload: {email, video_id, title, time}
        |
        |-- Return {success: true, message: "Email submitted successfully."}
        |
  |-- JS: Remove overlay, restore player visibility
  |-- Dispatch 'mediashield:email-gate-passed' CustomEvent
```

---

## Flow 10: Weekly Digest Email

```
Action Scheduler fires 'ms_weekly_digest' (weekly)
  |
  |-- Reports\WeeklyDigest::send()
        |-- Check ms_weekly_digest_enabled option (default true)
        |-- Get recipient from ms_weekly_digest_email or admin_email
        |-- Validate is_email()
        |
        |-- Query analytics for last 7 days:
        |     |-- Total views: COUNT(*) from ms_watch_sessions
        |     |-- Total completions: COUNT(*) from ms_milestones WHERE pct=100
        |     |-- Avg completion: AVG(completion_pct) from ms_watch_sessions
        |     |-- Top 5 videos: GROUP BY video_id ORDER BY count DESC
        |     |-- Alert count: COUNT(*) from ms_activity_alerts
        |
        |-- Build HTML email:
        |     |-- 4-column stats grid (views, completions, avg completion, alerts)
        |     |-- Top 5 videos table
        |     |-- Footer with disable instructions
        |
        |-- wp_mail($recipient, subject, html, headers=['Content-Type: text/html'])
```

---

## Flow 11: CSV Export

```
Admin clicks "Download CSV" in Export page
  |
  |-- React Export.js: Creates hidden <a> link to REST URL, clicks it
  |     URL: /mediashield-pro/v1/export/csv/{type}?date_from=&date_to=&_wpnonce=
  |
  |-- ExportController::stream_csv()
        |-- Validate type is watch_sessions | milestones | users
        |-- Build filters from query params
        |-- Set HTTP headers for CSV download:
        |     Content-Type: text/csv
        |     Content-Disposition: attachment
        |
        |-- Switch on type:
        |     watch_sessions -> CsvExporter::export_watch_sessions()
        |     milestones -> CsvExporter::export_milestones()
        |     users -> CsvExporter::export_users()
        |
        |-- CsvExporter streams to php://output:
        |     |-- fputcsv() header row
        |     |-- SELECT with filters, LIMIT 50000
        |     |-- fputcsv() each result row
        |     |-- fclose()
        |
        |-- exit; (prevents WP from adding response headers)
```

---

## Flow 12: Async PDF Report

```
Admin clicks "Generate PDF" in Export page
  |
  |-- React Export.js: POST /mediashield-pro/v1/export/pdf/report
  |     Body: {period: '30d'}
  |
  |-- ExportController::enqueue_pdf_report()
        |-- Check Action Scheduler available
        |-- as_enqueue_async_action('mediashield_pro_pdf_report', {user_id, filters})
        |-- Return {job_id, status: 'queued'} with 202
  |
  |-- React polls GET /export/status/{job_id} every 5s
  |
  |-- Action Scheduler executes the queued action
        |
        |-- PdfExporter::generate_report($user_id, $filters)
              |-- Check Dompdf class exists
              |-- gather_report_data(): Query overview stats, top 10 videos, completions
              |-- build_report_html(): A4 HTML template with stats table
              |-- Render with Dompdf
              |-- Save to uploads/mediashield/exports/report-{hash}.pdf
              |-- Store download URL in transient (24h TTL)
              |-- wp_mail() admin notification with download link
              |-- Return {file_path, download_url, hash, expires_in}
```

---

## Flow 13: Watermark Configuration

```
Free plugin calls: apply_filters('mediashield_watermark_config', $config, $video_id, $user_id)
  |
  |-- [Priority 10] Watermark\AdvancedConfig::enhance_config()
        |-- Read ms_pro_watermark_fields option (default: ['username', 'ip'])
        |-- For each field, build text part:
        |     username -> user display_name or 'Guest'
        |     email -> user_email
        |     ip -> from base config
        |     user_id -> '#' + user ID
        |     timestamp -> current time H:i
        |     site_name -> get_bloginfo('name')
        |     custom_text -> ms_pro_watermark_custom_text option
        |
        |-- Join parts with middle dot separator
        |-- Set config['text'] = joined string
        |-- Set config['font_size'] from ms_pro_watermark_font_size option
        |-- Set config['show_badge'] from ms_show_badge option
        |-- Return enhanced config
```

---

## Flow 14: Admin SPA Route Injection

```
Free plugin renders React admin SPA on toplevel_page_mediashield
  |
  |-- Plugin::enqueue_pro_admin() (admin_enqueue_scripts)
  |     |-- Only on 'toplevel_page_mediashield'
  |     |-- Load build/admin/index.asset.php for deps
  |     |-- wp_enqueue_script('mediashield-pro-admin', deps include 'mediashield-admin')
  |     |-- wp_enqueue_style('mediashield-pro-admin')
  |
  |-- src/admin/index.js executes:
        |-- import { addFilter } from '@wordpress/hooks'
        |-- Define 6 PRO_ROUTES: Platforms, Alerts, Heatmap, Realtime, DRM, Export
        |-- addFilter('mediashield_admin_routes', 'mediashield-pro', callback)
        |     |-- Check for duplicate injection
        |     |-- Find #/settings index
        |     |-- splice PRO_ROUTES before Settings (or push if not found)
        |     |-- Return modified routes array
```

---

## Flow 15: DRM Player Initialization

```
Free plugin fires CustomEvent 'mediashield:player-ready'
  |
  |-- drm-player.js listener:
        |-- Check detail.el.dataset.playerType === 'drm'
        |-- If not DRM -> return
        |
        |-- initDRMPlayer(el, videoId, video)
              |-- Check shaka global exists
              |-- shaka.polyfill.installAll()
              |-- Check shaka.Player.isBrowserSupported()
              |
              |-- Find or create <video> element in .ms-player-inner
              |-- new shaka.Player(videoEl)
              |
              |-- Configure Widevine license server:
              |     drm.servers['com.widevine.alpha'] = REST URL + 'drm/license'
              |
              |-- Register license request filter:
              |     Add X-WP-Nonce and X-MS-Video-ID headers
              |
              |-- player.load(sourceUrl) -- loads DASH manifest
```

---

## Flow 16: Offline Video Caching (Service Worker)

```
OfflineManager::register_sw()
  |-- Only on singular('ms_video') with _ms_drm_enabled=true
  |-- Enqueue offline-sw.js as module
  |-- Localize: restUrl, nonce, videoId
  |
  |-- OfflineManager::get_save_button_html($video_id)
        |-- Renders "Save for Offline" button with data attributes
        |-- Inline script checks navigator.serviceWorker support
        |-- Shows button only in supported browsers

offline-sw.js (Service Worker):
  |-- install: self.skipWaiting()
  |-- activate: self.clients.claim()
  |
  |-- fetch handler:
  |     |-- Only intercepts DASH segments (.m4s, .mpd, .mp4 with 'mediashield' in URL)
  |     |-- Cache-first strategy: check cache -> fallback to network
  |     |-- Cache network responses on success
  |
  |-- message handler:
        |-- 'cache-segments': Fetch and cache array of URLs
        |-- 'clear-cache': Delete entire cache
        |-- 'get-cache-size': Calculate and report total cached bytes
```

---

## Flow 17: Uninstall Cleanup

```
User deletes plugin via WP admin -> uninstall.php executes
  |
  |-- Check WP_UNINSTALL_PLUGIN defined
  |
  |-- Drop 8 tables:
  |     ms_email_captures, ms_heatmap_cache, ms_drm_keys,
  |     ms_drm_licenses, ms_activity_alerts, ms_upload_queue,
  |     ms_platform_connections, ms_playback_events
  |
  |-- Delete 17 pro options (watermark, DRM, milestone, digest, etc.)
  |-- Delete license options (mediashield-pro_license_key, mediashield-pro_license)
  |
  |-- Unschedule 5 Action Scheduler crons:
  |     ms_heatmap_aggregation, ms_alert_pruning, ms_upload_cleanup,
  |     ms_weekly_digest, ms_email_capture_retention
  |
  |-- Clean up transients: DELETE FROM wp_options WHERE option_name LIKE '_transient_ms_pro_%'
```
