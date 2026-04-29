# MediaShield -- Code Flow Maps

**Plugin:** MediaShield v1.0.0
**Generated:** 2026-04-01

---

## Flow 1: Plugin Bootstrap

```
WordPress loads plugins
  |
  v
mediashield.php
  |-- define constants (VERSION, DB_VERSION, FILE, PATH, URL)
  |-- require vendor/autoload.php (PSR-4 for MediaShield\ namespace)
  |-- register_activation_hook -> Activator::activate()
  |-- register_deactivation_hook -> Deactivator::deactivate()
  |-- add_action('plugins_loaded') ->
        |-- Migrator::run()
        |     |-- get_option('ms_db_version')
        |     |-- if stale: Schema::create_tables() + update_option
        |
        |-- Plugin::instance() (singleton)
              |-- VideoPostType::register()       -> add_action('init', register_post_type + register_meta)
              |-- PlaylistPostType::register()     -> add_action('init', register_post_type + register_meta)
              |-- Thumbnail::register()            -> add_action('save_post_mediashield_video', maybe_fetch_thumbnail)
              |-- add_action('rest_api_init', register_rest_routes)
              |-- VideoBlock::register()           -> add_action('init', register_block_type)
              |-- PlaylistBlock::register()        -> add_action('init', register_block_type)
              |-- Shortcode::register()            -> add_shortcode('mediashield', render)
              |-- PlayerWrapper::register()        -> add_action('template_redirect', start_buffer)
              |-- Assets::register()               -> add_action('wp_enqueue_scripts', enqueue_frontend)
              |-- Menu::register()                 -> add_action('admin_menu' + 'admin_enqueue_scripts')
              |-- SetupWizard::register()          -> add_action('admin_init' + 'admin_menu' + 'admin_enqueue_scripts' + 'rest_api_init')
              |-- Cleanup::register()              -> add_action('before_delete_post' + 'init' for cron scheduling)
              |-- PrivacyExporter::register()      -> add_filter('wp_privacy_personal_data_exporters')
              |-- PrivacyEraser::register()        -> add_filter('wp_privacy_personal_data_erasers')
              |-- MyVideosBlock::register()         -> add_action('init') + add_shortcode('mediashield_my_videos')
              |-- add_filter('single_template', video_template)
              |-- do_action('mediashield_loaded')
```

---

## Flow 2: Plugin Activation

```
WordPress activates plugin
  |
  v
Activator::activate()
  |-- PHP version check (>= 8.1) -- wp_die on failure
  |-- WP version check (>= 6.5) -- wp_die on failure
  |-- Schema::create_tables() -- dbDelta for 6 tables
  |-- update_option('ms_db_version', 1)
  |-- Set default options (ms_enabled, ms_default_protection, etc.)
  |-- Grant 'upload_mediashield' cap to administrator role
  |-- If !ms_wizard_completed: set_transient('ms_activation_redirect', true, 30)
  |-- flush_rewrite_rules()
```

---

## Flow 3: Video Watch Session (Full Lifecycle)

```
User visits page with [mediashield id=X] or mediashield/video block
  |
  v
Server-side render:
  Renderer::render($video_id)
  |-- get_post() + validate CPT type + publish status
  |-- Read meta: platform, platform_video_id, source_url, stream_url, protection_level, duration
  |-- apply_filters('mediashield_player_type', 'standard', $video_id)
  |-- do_action('mediashield_needs_shaka') if self/bunny
  |-- Output: .ms-protected-player > .ms-player-target + canvas + overlay + fullscreen btn
  |
  v
Frontend (Assets::enqueue_frontend):
  |-- Enqueue player-wrapper.js, watermark.js, tracker.js, protection.js, player.css
  |-- wp_localize_script -> mediashieldConfig (restUrl, nonce, userId, watermark config, etc.)
  |
  v
player-wrapper.js IIFE boots:
  |-- DOMContentLoaded -> init()
  |-- querySelectorAll('.ms-protected-player') -> forEach(initPlayer)
  |-- observeDynamicEmbeds() (MutationObserver for SPA-injected players)
  |
  v
initPlayer(el):
  |-- Check protectionLevel != 'none'
  |-- If !config.isLoggedIn -> showLoginOverlay(el) -> STOP
  |-- createAdapter(el) -- factory based on data-platform:
  |     |-- youtube  -> YouTubeAdapter.create() -> loads YouTube IFrame API -> new YT.Player
  |     |-- vimeo    -> VimeoAdapter.create() -> loads Vimeo Player SDK -> new Vimeo.Player
  |     |-- wistia   -> WistiaAdapter.create() -> loads E-v1.js -> _wq push
  |     |-- self     -> NativeAdapter.create() -> <video> element (+ Shaka for HLS/DASH)
  |     |-- bunny    -> NativeAdapter.create() -> <video> element (+ Shaka)
  |
  |-- adapter.onReady(cb) -> startSession(el, videoId, adapter)
  |
  v
startSession(el, videoId, adapter):
  |-- fetch POST /mediashield/v1/session/start { video_id }
  |
  v (server side)
  SessionController::start_session()
    |-- Validate video exists, is published, is mediashield_video CPT
    |-- AccessControl::can_watch($video_id, $user_id)
    |     |-- Admin bypass
    |     |-- Login gate (ms_require_login option)
    |     |-- Domain restriction (ms_allowed_domains)
    |     |-- apply_filters('mediashield_can_watch', $result, $video_id, $user_id)
    |
    |-- SessionManager::start($video_id, $user_id, $ip, $ua)
    |     |-- START TRANSACTION
    |     |-- Count active sessions (SELECT ... FOR UPDATE)
    |     |-- Check for existing session on same video (dedup)
    |     |   |-- If exists with heartbeat < 5 min ago: COMMIT, return existing token
    |     |-- Check concurrent limit (ms_max_concurrent_streams)
    |     |   |-- If exceeded: ROLLBACK, do_action('mediashield_concurrent_limit_reached'), return false
    |     |-- Get resume position from most recent session (any status)
    |     |-- Parse device_type + browser from user-agent
    |     |-- INSERT new session row
    |     |-- COMMIT
    |     |-- generate_token(session_id, video_id, user_id, created_ts) -> HMAC token
    |     |-- do_action('mediashield_session_started', ...)
    |     |-- return {session_token, session_id, resume_position, is_resumed}
    |
    |-- apply_filters('mediashield_watermark_config', $config, $video_id, $user_id)
    |-- Return JSON: session_token, resume_position, is_resumed, watermark_config, video meta
  |
  v (client side)
  el.dataset.sessionToken = data.session_token
  |-- If resume_position > 0: showResumePrompt() (seekTo + play or start over)
  |-- Dispatch CustomEvent 'mediashield:player-ready' with detail
  |
  v
watermark.js listens 'mediashield:player-ready':
  |-- initWatermark(el, wmConfig)
  |-- Canvas 2d rendering: text (username + IP) at rotating positions
  |-- setInterval for position swap (swap_interval * 1000 ms)
  |-- ResizeObserver for responsive re-render
  |-- MutationObserver: if canvas removed -> pause video (anti-tamper)
  |
  v
tracker.js listens 'mediashield:player-ready':
  |-- startTracking(el, token, video, adapter)
  |-- setInterval(sendHeartbeat, 30000)
  |
  v (every 30s)
  sendHeartbeat(session):
    |-- adapter.getPosition(), adapter.getDuration(), adapter.isPlaying()
    |-- document.hasFocus()
    |-- fetch POST /mediashield/v1/session/heartbeat { token, position, duration, playing, focused }
    |
    v (server)
    SessionController::heartbeat()
      |-- Rate limit check: max 4/min per user (transient-based)
      |-- SessionManager::heartbeat(token, position, duration, playing, focused)
      |     |-- validate_token(token) -- HMAC verification, 24h expiry check
      |     |-- UPDATE session: last_heartbeat, total_seconds += 30 (if playing), max_position = GREATEST, completion_pct
      |     |-- If rows > 0 && completion_pct > 0:
      |           MilestoneTracker::check(video_id, user_id, completion_pct, session_id)
      |             |-- apply_filters('mediashield_milestone_thresholds', [25,50,75,100], $video_id)
      |             |-- For each threshold <= completion_pct:
      |                   INSERT IGNORE into ms_milestones
      |                   If new row: do_action('mediashield_milestone_reached', ...)
      |                               do_action('mediashield_milestone_{pct}', ...)
  |
  v
protection.js initializes:
  |-- Block right-click (contextmenu event)
  |-- Add controlsList="nodownload" to all <video>
  |-- Move src to data-ms-src (source hiding)
  |-- Add "Protected by MediaShield" badge div
  |-- Block Ctrl+S / Cmd+S globally
  |
  v
On page unload (beforeunload / visibilitychange='hidden'):
  tracker.js -> endAllSessions()
    |-- clearInterval for all active sessions
    |-- navigator.sendBeacon POST /session/end?_wpnonce=... { token }
    |
    v (server)
    SessionController::end_session()
      |-- SessionManager::end(token)
      |     |-- validate_token
      |     |-- UPDATE is_active=0, last_heartbeat=now
      |     |-- do_action('mediashield_session_ended', ...)
```

---

## Flow 4: Video Upload (Self-Hosted)

```
Admin SPA -> POST /mediashield/v1/upload/init (multipart/form-data)
  |-- UploadController::init_upload()
  |     |-- Permission: current_user_can('upload_mediashield')
  |     |-- Validate file present in $_FILES['file']
  |     |-- Check PHP upload error codes
  |     |-- UploadManager::upload($tmp_name, $driver_name, $options)
  |           |-- get_driver($driver_name) -- factory from mediashield_upload_drivers filter
  |           |-- driver->upload($file_path, $options)
  |
  v (SelfHosted driver)
  SelfHosted::upload():
    |-- Validate MIME type (mp4, webm, mov, m4v)
    |-- Check file size against ms_max_upload_size
    |-- wp_unique_filename() for conflict-free name
    |-- copy() to wp-content/uploads/mediashield/
    |-- wp_insert_post() -- create mediashield_video CPT
    |-- update_post_meta (_ms_platform=self, _ms_platform_video_id, _ms_source_url, _ms_protection_level)
    |-- Return {success, video_id, platform_video_id, embed_url}
  |
  v
  do_action('mediashield_upload_complete', $video_id, $driver_name, $result)
  |
  v
  Return 201: { video_id, platform_video_id, embed_url, status: 'complete' }
```

---

## Flow 5: Output Buffer Video Detection (PlayerWrapper)

```
template_redirect hook (frontend, non-admin, non-AJAX, non-cron)
  |-- ms_enabled option check
  |-- ob_start(PlayerWrapper::process_buffer)
  |
  v (on output flush)
  process_buffer($html):
    |-- Quick regex check: does HTML contain <iframe, <video, or wistia?
    |   |-- No: return unmodified HTML
    |
    |-- wrap_platform() for each platform pattern:
    |     YouTube:  /<iframe.*youtube\.com\/embed.*/
    |     Vimeo:    /<iframe.*player\.vimeo\.com\/video.*/
    |     Bunny:    /<iframe.*iframe\.mediadelivery\.net.*/
    |     Wistia:   /<div.*wistia_embed\s+wistia_async_.*/
    |     Self:     /<video.*>.*<\/video>/
    |     Custom:   ms_custom_url_patterns option (per-line regex)
    |
    |-- wrap_platform($html, $pattern, $platform):
    |     |-- preg_replace_callback
    |     |-- Double-wrap prevention (check for ms-protected-player class)
    |     |-- extract_video_id() from embed URL
    |     |-- apply_filters('mediashield_player_type', 'standard', 0)
    |     |-- do_action('mediashield_needs_shaka') for self/bunny
    |     |-- Replace iframe/video with:
    |           .ms-protected-player > .ms-player-target + canvas + overlay + fullscreen btn
    |
    v
    Return processed HTML (all embeds wrapped with protection containers)
```

---

## Flow 6: Tag CRUD

```
Admin SPA Tags page -> REST API calls
  |
  |-- GET /tags?per_page=50&page=1&search=...
  |     TagController::get_items()
  |     -> TagManager::list($per_page, $page, $search)
  |     -> SELECT from ms_tags with LIKE search, LIMIT/OFFSET
  |     -> Response: items[] + X-WP-Total + X-WP-TotalPages headers
  |
  |-- POST /tags { name, description }
  |     TagController::create_item()
  |     -> TagManager::create($name, $description, $user_id)
  |     -> sanitize_title() for slug, check duplicate slug
  |     -> INSERT into ms_tags
  |     -> 201: created tag object
  |
  |-- PATCH /tags/{id} { name?, description? }
  |     TagController::update_item()
  |     -> TagManager::update($tag_id, $data)
  |     -> Check slug uniqueness (excluding self)
  |     -> UPDATE ms_tags
  |
  |-- DELETE /tags/{id}
  |     TagController::delete_item()
  |     -> TagManager::delete($tag_id)
  |     -> DELETE from ms_video_tags WHERE tag_id = ?
  |     -> DELETE from ms_tags WHERE id = ?
  |
  |-- POST /videos/{video_id}/tags { tag_id }
  |     TagController::assign_video_tag()
  |     -> TagManager::assign_to_video() -> INSERT IGNORE ms_video_tags
  |
  |-- DELETE /videos/{video_id}/tags/{tag_id}
       TagController::unassign_video_tag()
       -> TagManager::unassign_from_video() -> DELETE from ms_video_tags
```

---

## Flow 7: Playlist Management

```
Admin SPA Playlists page -> REST API calls
  |
  |-- GET /playlists/{id}/items
  |     PlaylistController::get_items()
  |     -> Validate playlist CPT exists
  |     -> JOIN query: ms_playlist_items + posts + postmeta (platform, source_url, duration)
  |     -> Include thumbnail URL via get_the_post_thumbnail_url()
  |
  |-- POST /playlists/{id}/items { video_id, sort_order? }
  |     PlaylistController::add_item()
  |     -> Validate playlist + video exist
  |     -> Auto-assign sort_order = MAX(sort_order) + 1 if not provided
  |     -> INSERT into ms_playlist_items
  |
  |-- DELETE /playlists/{id}/items/{item_id}
  |     PlaylistController::remove_item()
  |     -> DELETE from ms_playlist_items WHERE id = ? AND playlist_id = ?
  |
  |-- PUT /playlists/{id}/items/reorder { order: [{item_id, sort_order}] }
       PlaylistController::reorder_items()
       -> Loop: UPDATE ms_playlist_items SET sort_order = ? WHERE id = ? AND playlist_id = ?
```

---

## Flow 8: Analytics Dashboard

```
Admin SPA Dashboard page -> GET /analytics/overview?period=7d
  |
  v
AnalyticsController::get_overview():
  |-- period_to_interval('7d') -> '7 DAY'
  |-- Total published videos: wp_count_posts('mediashield_video')
  |-- Total sessions in period: COUNT(*) from ms_watch_sessions WHERE started_at >= DATE_SUB
  |-- Avg completion in period: AVG(completion_pct) WHERE completion_pct > 0
  |-- Active viewers: COUNT(DISTINCT user_id) WHERE is_active=1 AND last_heartbeat >= 5 MIN ago
  |-- Sessions per day chart: GROUP BY DATE(started_at)
  |-- Top 5 videos by session count: JOIN posts, GROUP BY video_id, ORDER BY session_count DESC
  |
  v
  Return: total_videos, total_sessions, avg_completion, active_viewers, sessions_chart[], top_videos[]
```

---

## Flow 9: Milestone Tracking

```
SessionManager::heartbeat() updates completion_pct
  |
  v
MilestoneTracker::check($video_id, $user_id, $completion_pct, $session_id):
  |-- apply_filters('mediashield_milestone_thresholds', [25, 50, 75, 100], $video_id)
  |-- sort thresholds ascending
  |-- For each threshold:
  |     |-- If completion_pct < threshold: break (no more reachable)
  |     |-- INSERT IGNORE into ms_milestones (dedup via UNIQUE KEY: video_id + user_id + milestone_pct)
  |     |-- If insert succeeded (new milestone):
  |           |-- do_action('mediashield_milestone_reached', $user_id, $video_id, $pct, $session_id)
  |           |-- do_action('mediashield_milestone_{pct}', $user_id, $video_id)
  |
  v
  Return array of newly-fired milestone percentages
```

---

## Flow 10: Cascade Delete (Video/Playlist)

```
WordPress permanently deletes a post
  |
  v
before_delete_post hook
  |
  |-- Cleanup::handle_video_delete($post_id):
  |     |-- Check post_type == 'mediashield_video'
  |     |-- DELETE from ms_video_tags WHERE video_id = $post_id
  |     |-- Collect session IDs: SELECT id FROM ms_watch_sessions WHERE video_id = $post_id
  |     |-- DELETE from ms_watch_sessions WHERE video_id = $post_id
  |     |-- DELETE from ms_milestones WHERE video_id = $post_id
  |     |-- DELETE from ms_playlist_items WHERE video_id = $post_id
  |     |-- If MEDIASHIELD_PRO_VERSION defined && session_ids not empty:
  |           |-- DELETE from ms_playback_events WHERE session_id IN (...)
  |           |-- DELETE from ms_activity_alerts WHERE video_id = $post_id
  |           |-- DELETE from ms_drm_licenses WHERE video_id = $post_id
  |           |-- DELETE from ms_heatmap_cache WHERE video_id = $post_id
  |           |-- DELETE from ms_drm_keys WHERE video_id = $post_id
  |
  |-- Cleanup::handle_playlist_delete($post_id):
        |-- Check post_type == 'mediashield_playlist'
        |-- DELETE from ms_playlist_items WHERE playlist_id = $post_id
```

---

## Flow 11: Cron Cleanup

```
Action Scheduler runs hourly:
  ms_cleanup_inactive_sessions
    -> Cleanup::cleanup_inactive_sessions()
    -> UPDATE ms_watch_sessions SET is_active=0
       WHERE is_active=1 AND last_heartbeat < 10 min ago

Action Scheduler runs monthly:
  ms_archive_old_sessions
    -> Cleanup::archive_old_sessions()
    -> Check ms_watch_sessions_archive table exists
    -> INSERT INTO archive SELECT * FROM sessions WHERE started_at < 24 months ago
    -> DELETE FROM sessions WHERE started_at < 24 months ago
```

---

## Flow 12: Setup Wizard

```
First activation:
  Activator::activate() -> set_transient('ms_activation_redirect', true, 30)
  |
  v
Next admin page load:
  SetupWizard::maybe_redirect()
    |-- get_transient('ms_activation_redirect') -- if true:
    |-- delete_transient
    |-- Skip if AJAX, cron, CLI, bulk activate, or wizard already completed
    |-- wp_safe_redirect(admin.php?page=mediashield-wizard)
  |
  v
Wizard page renders:
  <div id="mediashield-wizard-root">
  |-- Same React admin bundle loaded with mediashieldAdmin.isWizard = true
  |-- Wizard.js renders 4 steps:
  |     1. GeneralStep.js (site config)
  |     2. PlatformStep.js (platform selection)
  |     3. WatermarkStep.js (watermark setup)
  |     4. FirstVideoStep.js (first video upload)
  |-- Each step auto-saves via PUT /mediashield/v1/settings
  |-- On finish: POST /mediashield/v1/wizard/complete
        -> update_option('ms_wizard_completed', true)
```

---

## Flow 13: HMAC Session Token

```
Token Generation (SessionManager::generate_token):
  payload = "{session_id}|{video_id}|{user_id}|{unix_timestamp}"
  hmac = hash_hmac('sha256', payload, AUTH_SALT)
  token = "{payload}|{hmac}"
  -> 5 pipe-delimited segments

Token Validation (SessionManager::validate_token):
  |-- Split by '|' -- expect exactly 5 parts
  |-- Extract: session_id, video_id, user_id, ts, hmac
  |-- Check expiry: (time() - ts) <= 86400 (24 hours)
  |-- Recompute: expected_hmac = hash_hmac('sha256', "{session_id}|{video_id}|{user_id}|{ts}", AUTH_SALT)
  |-- hash_equals(expected_hmac, hmac)
  |-- Return parsed parts or false
```

---

## Flow 14: Access Control Chain

```
AccessControl::can_watch($video_id, $user_id):
  |
  |-- 1. Admin bypass: user_can($user_id, 'manage_options') -> ALLOW
  |
  |-- 2. Login gate: ms_require_login option && !$user_id -> DENY
  |
  |-- 3. Domain restriction: ms_allowed_domains option
  |     |-- check_domain():
  |     |   |-- Get wp_get_referer()
  |     |   |-- No referer = allow (direct access)
  |     |   |-- Same domain as home_url = allow
  |     |   |-- Check against comma-separated whitelist (exact match or subdomain)
  |     |   |-- No match -> DENY "Playback is not allowed from this domain"
  |
  |-- 4. Pro filter: apply_filters('mediashield_can_watch', $result, $video_id, $user_id)
  |     (Pro adds: role-based access, email gating, etc.)
  |
  v
  Return: { allowed: bool, reason: string }
```

---

## Flow 15: Frontend Asset Enqueue

```
wp_enqueue_scripts hook (non-admin only):
  Assets::enqueue_frontend()
    |-- Check ms_enabled option
    |-- Enqueue 4 vanilla JS scripts:
    |   mediashield-player-wrapper (no deps)
    |   mediashield-watermark (depends on player-wrapper)
    |   mediashield-tracker (depends on player-wrapper)
    |   mediashield-protection (depends on player-wrapper)
    |
    |-- Enqueue player.css
    |
    |-- Build mediashieldConfig:
    |   restUrl, nonce, isLoggedIn, userId, loginUrl, interval (30000),
    |   watermark (Watermark::get_config()), protection (Protection::get_config())
    |
    |-- wp_localize_script('mediashield-player-wrapper', 'mediashieldConfig', $config)
```

---

## Flow 16: Single Video Page Template

```
is_singular('mediashield_video')
  |
  v
Plugin::video_template() -> single_template filter
  |-- Return templates/single-mediashield_video.php
  |
  v
Template renders:
  |-- Detect block theme (wp_is_block_theme)
  |-- Block theme: full HTML doc with block_template_part('header'/'footer')
  |-- Classic theme: get_header() / get_footer()
  |-- the_loop:
  |   |-- Renderer::render($video_id) -- protected player
  |   |-- the_content() -- any additional post content
  |-- JSON-LD VideoObject schema:
      @type: VideoObject, name, description, uploadDate, duration (PTxS), thumbnailUrl
```
