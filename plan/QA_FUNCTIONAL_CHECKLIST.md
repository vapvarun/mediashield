# MediaShield -- Functional QA Checklist (Free + Pro)

Organized by functional groups. Each group has **Backend** (PHP/REST/DB) and **Frontend** (JS/CSS/UX) sections.

---

## 1. Installation & Lifecycle

### Backend
- [ ] Fresh install on WP 6.5+ / PHP 8.1 creates all 6 free DB tables
- [ ] Pro activation creates all 8 Pro DB tables
- [ ] Pro shows admin notice when free plugin is missing or outdated
- [ ] All default options set correctly (free: 14 options, pro: 17 options)
- [ ] EDD license activation/deactivation works
- [ ] License expiry shows admin notice
- [ ] Deactivation clears crons without data loss
- [ ] Re-activation recovers existing data
- [ ] `mediashield_loaded` action fires on free plugin load
- [ ] `mediashield_pro_loaded` action fires after free plugin loads

### Frontend
- [ ] Setup wizard redirects on first activation
- [ ] Wizard steps save settings correctly (General → Platform → Watermark → First Video)
- [ ] Admin SPA loads without JS errors
- [ ] No console errors on any page

---

## 2. Video Management (CPT)

### Backend
- [ ] `mediashield_video` CPT registered with REST API support
- [ ] Meta fields save: `_ms_platform`, `_ms_platform_video_id`, `_ms_source_url`, `_ms_protection_level`, `_ms_access_role`, `_ms_duration`
- [ ] URL auto-detection resolves platform from YouTube/Vimeo/Bunny/Wistia URLs
- [ ] Video deletion cascade-deletes: sessions, milestones, tags, playlist items
- [ ] Milestone tags save per-video per-percentage with enable toggles

### Frontend
- [ ] Add New Video page renders meta boxes correctly
- [ ] URL paste auto-detects platform + ID, updates label in real-time
- [ ] Shortcode copy box copies `[mediashield id=X]` to clipboard
- [ ] PHP template code copy works
- [ ] Protection level and role restriction dropdowns save
- [ ] Player options save (autoplay, loop, muted, controls)
- [ ] Feature overrides save (speed, keyboard, resume, sticky, endscreen) with Default/On/Off
- [ ] Connected platforms status shown when Pro active
- [ ] Pro upsell shown when Pro not active

---

## 3. Video Playback & Player

### Backend
- [ ] `[mediashield id=X]` shortcode renders player via `Renderer.php`
- [ ] Output buffer detection wraps embedded videos on non-shortcode pages
- [ ] `mediashield_player_type` filter allows overriding player type
- [ ] `mediashield_player_html` filter allows modifying player output
- [ ] `mediashield_enqueue_frontend` filter can disable asset loading
- [ ] `mediashield_enable_output_buffer` filter can disable OB
- [ ] Assets only enqueue on pages with video content

### Frontend
- [ ] YouTube video plays with watermark overlay
- [ ] Vimeo video plays with watermark overlay
- [ ] Self-hosted video plays with watermark overlay
- [ ] Bunny Stream video plays with watermark overlay
- [ ] Wistia video plays with watermark overlay
- [ ] MediaShield Video block renders in Gutenberg editor + frontend
- [ ] MediaShield Playlist block renders correctly
- [ ] Fullscreen keeps watermark visible
- [ ] Login overlay shows for non-logged-in users (when require_login enabled)
- [ ] Login overlay has role=dialog, aria-modal, focus trap, Escape key

---

## 4. Player Controls

### Backend
- [ ] Global settings save via REST: speed, keyboard, resume, sticky, endscreen
- [ ] Per-video overrides stored as post meta, deleted on "Default"
- [ ] `data-player-overrides` JSON attribute rendered on both shortcode and OB paths

### Frontend
- [ ] Speed control menu opens/closes, changes rate (0.5x-2x) on self-hosted only
- [ ] Keyboard shortcuts: Space=play/pause, Left/Right=seek 5s, Up/Down=volume, M=mute, F=fullscreen
- [ ] Keyboard shortcuts only fire when player is focused
- [ ] Resume playback remembers position across page loads
- [ ] Sticky player floats when scrolling past playing video
- [ ] Sticky player close button dismisses, does NOT show when paused
- [ ] End screen shows after video finishes with CTA link + replay button
- [ ] Per-video overrides take precedence over global settings
- [ ] All player settings respect `prefers-reduced-motion`

---

## 5. Watermark & Protection

### Backend
- [ ] `mediashield_watermark_config` filter allows customizing watermark
- [ ] Watermark config returned in session start response
- [ ] Pro: all 7 fields configurable (username, email, IP, user_id, timestamp, site_name, custom_text)
- [ ] Badge visibility toggle saves and applies

### Frontend
- [ ] Watermark shows username + IP (free) or configured fields (pro)
- [ ] Watermark position swaps at configured interval
- [ ] Right-click disabled on player container
- [ ] Devtools detection fires (protection.js)
- [ ] Watermark canvas has `aria-hidden=true`
- [ ] Player container has visible focus ring via keyboard

---

## 6. Session Tracking & Analytics

### Backend
- [ ] Session start creates row in `ms_watch_sessions` with HMAC token
- [ ] Heartbeat updates `last_heartbeat`, `total_seconds`, `max_position`, `completion_pct`
- [ ] Session end sets `is_active=0`
- [ ] Concurrent stream limit enforced (SELECT FOR UPDATE + transaction)
- [ ] Expired sessions (no heartbeat 5min) marked inactive
- [ ] HMAC token validation works without DB lookup
- [ ] Token expiration enforced (24h default)
- [ ] `mediashield_session_started/ended` actions fire with correct params
- [ ] `mediashield_concurrent_limit_reached` action fires

### Frontend
- [ ] Session starts on video play (REST call succeeds)
- [ ] Heartbeat fires every 30 seconds
- [ ] Session ends on page unload via `sendBeacon`
- [ ] Tab switch pauses heartbeat (does NOT end session)
- [ ] Concurrent stream error message shown to user

---

## 7. Milestones

### Backend
- [ ] Milestone fires at 25/50/75/100% completion
- [ ] Per-video tags assigned to user meta at milestones
- [ ] `mediashield_milestone_reached` action fires with $user_id, $video_id, $pct, $session_id
- [ ] `mediashield_milestone_{pct}` action fires for specific percentages
- [ ] `mediashield_milestone_thresholds` filter allows custom thresholds
- [ ] Pro: tag user action assigns tag to user meta
- [ ] Pro: send email action sends email at milestone
- [ ] Pro: fire webhook action POSTs to configured URL
- [ ] Pro: multiple actions per milestone work

### Frontend
- [ ] Admin Milestones page shows milestone data with user/video
- [ ] Pro: Milestone Config page shows 25/50/75/100% cards with enable + action type
- [ ] Pro: Action type dropdown (tag/email/webhook) with relevant fields

---

## 8. Playlist

### Backend
- [ ] `mediashield_playlist` CPT registered with REST API support
- [ ] Meta fields save: `_ms_autoplay`, `_ms_countdown`, `_ms_loop`, `_ms_shuffle`
- [ ] REST: add/remove/reorder playlist items
- [ ] Playlist items stored in `ms_playlist_items` with sort_order

### Frontend
- [ ] Create playlist with title/description in admin
- [ ] Add/remove videos, drag-and-drop reorder
- [ ] Playlist block renders with video list + active player
- [ ] Autoplay advances to next video with countdown timer
- [ ] Shuffle mode randomizes order
- [ ] Loop mode restarts playlist after last video

---

## 9. Tags

### Backend
- [ ] CRUD via REST: create, read, update, delete tags
- [ ] Assign/unassign tags to videos via REST
- [ ] Tags stored in `ms_tags`, video assignments in `ms_video_tags`

### Frontend
- [ ] Admin Tags page: create, assign, delete tags
- [ ] Tag assignment UI works on video list

---

## 10. Access Control

### Backend
- [ ] `mediashield_can_watch` filter chain works (return WP_Error to deny)
- [ ] Role restriction blocks unauthorized users
- [ ] Domain whitelisting blocks embeds on unlisted domains
- [ ] Pro: email gate checks cookie before requiring email
- [ ] Pro: email gate rate limit: 5 attempts/min per IP

### Frontend
- [ ] Non-logged-in user sees login overlay (when enabled)
- [ ] Access denied message shows correctly
- [ ] Concurrent stream limit shows error message
- [ ] Pro: email gate overlay shows on gated video
- [ ] Pro: email submission succeeds, sets cookie, starts video
- [ ] Pro: returning visitor with cookie skips gate
- [ ] Pro: email gate overlay has role=dialog, focus trap, Escape key

---

## 11. Platform Connections (Pro)

### Backend
- [ ] Bunny: connect with API key + library ID, validates via API
- [ ] Bunny: credentials encrypted with AES-256-CBC
- [ ] Bunny: browse/import/upload via REST endpoints
- [ ] Bunny: tus resumable upload for large files
- [ ] Bunny: webhook validates signature
- [ ] YouTube: connect with API key + channel ID
- [ ] Vimeo: connect with access token
- [ ] Wistia: connect with API token
- [ ] All: disconnect removes connection, preserves imported videos
- [ ] All: multiple connections per platform supported

### Frontend
- [ ] Platform cards show connection status with help text
- [ ] Browse library lists videos with thumbnails
- [ ] Search with debounce, collection/folder/playlist filters
- [ ] Checkbox select + bulk import
- [ ] "Already imported" badge on imported videos
- [ ] Upload progress bar updates correctly
- [ ] Upload resume after network interruption

---

## 12. DRM (Pro)

### Backend
- [ ] DRM method saves: none/cloud_bunny/local_shaka
- [ ] Cloud Bunny: ClearKey license issued for authorized users, denied for unauthorized
- [ ] Local Shaka: key generated, encrypted in `ms_drm_keys`, DASH segments created
- [ ] License duration: streaming (hours→seconds) and persistent (days→seconds)
- [ ] License revocation marks `revoked_at`, prevents key serving
- [ ] Offline: service worker registered, DASH segments cached

### Frontend
- [ ] DRM-enabled videos use Shaka Player
- [ ] DRM playback works in Chrome, Firefox, Edge
- [ ] Safari/iOS fallback to standard protection
- [ ] Watermark visible on DRM-protected videos
- [ ] DRM admin page: method dropdown, Shaka path, license duration fields
- [ ] Offline: "Save for Offline" button, offline playback works

---

## 13. Analytics (Pro)

### Backend
- [ ] Playback events logged to `ms_playback_events`
- [ ] Heatmap aggregation cron creates buckets (10-second intervals)
- [ ] Realtime viewer query returns active sessions
- [ ] Suspicious activity: multi-IP, devtools, rapid seek, concurrent alerts
- [ ] Alert dismiss/safe-user whitelist works
- [ ] Weekly digest cron sends email with stats

### Frontend
- [ ] Dashboard stats show real data, empty state when no data
- [ ] Period selector works (7d, 30d, 90d)
- [ ] Top videos list, chart renders with session data
- [ ] User drill-down works (click user → their sessions)
- [ ] Heatmap chart renders with retention line
- [ ] Device breakdown chart renders
- [ ] Playlist funnel chart renders
- [ ] Realtime: active viewers list with 15s auto-refresh
- [ ] Alerts page: list, dismiss, safe-user actions

---

## 14. Data Export (Pro)

### Backend
- [ ] CSV export: watch_sessions, milestones, users with date range filter
- [ ] CSV excludes session_token (security), 50k row limit, UTF-8
- [ ] PDF report queued via Action Scheduler, 24h download transient
- [ ] PDF contains: overview stats, top 10 videos, completion data
- [ ] Admin email notification with download link

### Frontend
- [ ] Export page loads, CSV downloads in browser
- [ ] PDF generation status polling works
- [ ] PDF renders correctly (A4, no overflow)

---

## 15. LMS Integration (Pro)

### Backend
- [ ] LearnDash/Tutor/LifterLMS adapters auto-detect active LMS
- [ ] Video completion at configured % marks lesson complete
- [ ] Non-enrolled user blocked from linked video
- [ ] `mediashield_lms_lesson_completed` action fires
- [ ] Third-party adapter can register via `mediashield_lms_adapters_loaded`

### Frontend
- [ ] LMS meta box appears on video edit when LMS active
- [ ] Lesson dropdown grouped by course
- [ ] Completion % dropdown (per-video override)
- [ ] Settings → LMS section hidden when no LMS active

---

## 16. Upload

### Backend
- [ ] Free: self-hosted upload via REST, `.htaccess` in upload dir
- [ ] Free: proxy endpoint serves video (not direct URL)
- [ ] Free: file size limit enforced, supported formats: MP4, WebM, MOV, M4V
- [ ] Pro: frontend upload creates CPT on completion with capability check

### Frontend
- [ ] Upload progress tracking
- [ ] Pro: `[mediashield_upload]` renders form for authorized users
- [ ] Pro: drag-and-drop, platform target selection, progress bar

---

## 17. Admin SPA

### Backend
- [ ] REST endpoints return proper error responses for invalid input
- [ ] `manage_options` required for all admin endpoints
- [ ] Nonce validation works on all endpoints
- [ ] Settings auto-save with debounce

### Frontend
- [ ] Sidebar loads all menu items at once (no flash/delay)
- [ ] Error boundary catches crashes, shows fallback UI
- [ ] Pro locked items shown with PRO badge when Pro not active
- [ ] All Pro upsell elements hidden when Pro is active
- [ ] Pages: Dashboard, Videos, Playlists, Viewers, Tags, Milestones, Settings
- [ ] Pro pages: Platforms, Alerts, Heatmap, Realtime, DRM, Export
- [ ] Navigation between pages works without full reload
- [ ] No admin notices rendered on MediaShield pages

---

## 18. Uninstall & GDPR

### Backend
- [ ] Free deactivation clears crons
- [ ] Free uninstall drops 6 tables, removes free `ms_*` options, removes capabilities
- [ ] Free uninstall does NOT delete Pro options when Pro active
- [ ] Pro deactivation clears Pro crons, resets badge
- [ ] Pro deletion drops 8 Pro tables, removes 17 Pro options
- [ ] Pro deletion does NOT affect free plugin data
- [ ] GDPR exporter: sessions, milestones, tags (free), email captures, DRM licenses, playback events (pro)
- [ ] GDPR eraser: anonymize IP/UA, delete milestones/tags, delete email captures, revoke DRM licenses

### Frontend
- [ ] Free plugin continues working after Pro deletion

---

## 19. Security

### Backend
- [ ] All REST routes have `permission_callback`
- [ ] Platform credentials encrypted with AES-256-CBC
- [ ] Session token uses HMAC (no DB lookup for validation)
- [ ] Session start response does NOT include `source_url`
- [ ] Email gate rate limit uses `REMOTE_ADDR` only
- [ ] Shaka Packager args escaped with `escapeshellarg()`
- [ ] DRM license endpoint validates user access
- [ ] Export endpoint requires `manage_options`
- [ ] Settings PUT only accepts defined keys
- [ ] No direct file access to PHP files (ABSPATH check)
- [ ] All DB queries use `$wpdb->prepare()` for user input

### Frontend
- [ ] No sensitive data exposed in JS console or DOM
- [ ] HMAC token not visible in network response body

---

## 20. Performance & Compatibility

### Backend
- [ ] No N+1 queries in admin list pages
- [ ] Cron jobs complete without timeout (heatmap <30s, alerts <10s)
- [ ] CSV export streams without memory exhaustion
- [ ] PDF generation async via Action Scheduler
- [ ] Platform API calls use proper timeouts

### Frontend
- [ ] Admin SPA loads in under 3 seconds
- [ ] Assets (JS/CSS) only load on pages with video content
- [ ] Session heartbeat does not degrade page performance
- [ ] Output buffer scanning adds no visible latency
- [ ] Responsive at 782px (tablet) and 480px (mobile)
- [ ] Works with: LearnDash, BuddyBoss, Elementor, WooCommerce
- [ ] Works with caching plugins: WP Super Cache, W3TC, LiteSpeed
- [ ] Player works in Gutenberg editor preview
- [ ] No PHP notices/warnings in debug.log
- [ ] No deprecated function calls

---

## 21. i18n & Accessibility

### Backend
- [ ] All user-facing strings wrapped in translation functions
- [ ] POT file generated and up to date
- [ ] Text domain consistent (`mediashield` / `mediashield-pro`)

### Frontend
- [ ] No hardcoded English strings in JS
- [ ] RTL layout not broken (basic check)
- [ ] All form inputs have labels or `aria-label`
- [ ] Focus indicators visible on all interactive elements
- [ ] Player container has focus ring via `:focus-visible`
- [ ] Watermark canvas has `aria-hidden=true`
- [ ] Overlays (login, email gate) have focus trap
