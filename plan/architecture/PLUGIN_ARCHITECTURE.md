# MediaShield -- Plugin Architecture

**Plugin:** MediaShield v1.0.0
**Generated:** 2026-04-01
**Namespace:** `MediaShield\`
**Autoload:** PSR-4 via Composer (`includes/` -> `MediaShield\`)

---

## High-Level Architecture

MediaShield follows a modular, namespace-organized PHP architecture with a singleton bootstrap pattern. The plugin separates concerns into 11 namespaced modules (directories under `includes/`), a vanilla JS player system, and a React admin SPA.

```
mediashield.php (entry point)
   |
   v
Core\Plugin (singleton)
   |
   +-- CPT\          (2 custom post types + thumbnail auto-fetch)
   +-- DB\           (schema creation via dbDelta)
   +-- REST\         (6 REST controllers, 22+ routes)
   +-- Access\       (HMAC sessions + access control)
   +-- Block\        (3 Gutenberg blocks + 2 shortcodes)
   +-- Player\       (output buffer wrapping + renderer + watermark + protection)
   +-- Milestones\   (completion tracking at configurable thresholds)
   +-- Upload\       (driver-based upload system)
   +-- Tags\         (custom tag CRUD on custom tables)
   +-- Cron\         (Action Scheduler cleanup + cascade delete)
   +-- Privacy\      (GDPR export + erasure)
   +-- Admin\        (admin menu + setup wizard)
```

---

## Module Dependency Graph

```
Core\Plugin
  |-- CPT\VideoPostType          (no deps)
  |-- CPT\PlaylistPostType       (no deps)
  |-- CPT\Thumbnail              (no deps, uses WP media functions)
  |-- REST\TagController         -> Tags\TagManager
  |-- REST\SessionController     -> Access\AccessControl, Access\SessionManager
  |-- REST\PlaylistController    (direct DB queries)
  |-- REST\UploadController      -> Upload\UploadManager
  |-- REST\SettingsController    (wp_options CRUD)
  |-- REST\AnalyticsController   (direct DB queries)
  |-- Block\VideoBlock           (no deps, register_block_type)
  |-- Block\PlaylistBlock        (no deps, register_block_type)
  |-- Block\MyVideosBlock        (no deps, register_block_type + shortcode)
  |-- Block\Shortcode            -> Player\Renderer
  |-- Player\PlayerWrapper       (output buffer, standalone)
  |-- Player\Renderer            (shared HTML renderer for player container)
  |-- Player\Watermark           (config provider)
  |-- Player\Protection          (config provider)
  |-- Core\Assets                -> Player\Watermark, Player\Protection
  |-- Admin\Menu                 (no deps)
  |-- Admin\SetupWizard          (no deps)
  |-- Cron\Cleanup               (direct DB queries, pro-table-aware)
  |-- Privacy\PrivacyExporter    (direct DB queries)
  |-- Privacy\PrivacyEraser      (direct DB queries, pro-table-aware)
  |-- Milestones\MilestoneTracker (direct DB queries)
  |-- Upload\UploadManager       -> Upload\Drivers\DriverInterface
  |-- Tags\TagManager            (direct DB queries)

Access\SessionManager -> Milestones\MilestoneTracker (heartbeat -> check milestones)
```

---

## Data Architecture

### Storage Strategy
- **CPT data:** WordPress posts + post_meta for video/playlist metadata
- **Session tracking:** Custom tables (ms_watch_sessions) for high-write analytics
- **Tags:** Custom tables (ms_tags, ms_video_tags) instead of WP taxonomies for flexibility
- **Milestones:** Custom table (ms_milestones) with unique triple constraint for dedup
- **Settings:** WordPress options (ms_* prefix)

### Table Relationships
```
mediashield_video (CPT)
  |-- 1:N -- ms_watch_sessions (video_id)
  |-- 1:N -- ms_milestones (video_id)
  |-- M:N -- ms_tags via ms_video_tags (video_id <-> tag_id)
  |-- M:N -- mediashield_playlist via ms_playlist_items (video_id <-> playlist_id)

mediashield_playlist (CPT)
  |-- 1:N -- ms_playlist_items (playlist_id)

ms_watch_sessions
  |-- has_archive: ms_watch_sessions_archive (same schema, 24-month rotation)
  |-- 1:N -- ms_milestones (session_id, informational FK)
```

### Session Token Architecture
HMAC-based tokens eliminate DB lookups for token validation:
```
Token: {session_id}|{video_id}|{user_id}|{unix_ts}|{hmac_sha256}
Key:   AUTH_SALT (WordPress constant)
TTL:   24 hours
```

---

## JavaScript Architecture

### Two-Tier System

**Tier 1: Vanilla JS (assets/js/)** -- No build step, loaded on frontend
- `player-wrapper.js` -- Platform adapter factory (YouTube, Vimeo, Wistia, Shaka/native)
- `watermark.js` -- Canvas overlay rendering with anti-tamper detection
- `tracker.js` -- 30-second heartbeat to REST API with sendBeacon fallback
- `protection.js` -- Right-click block, keyboard block, source hiding, badge

**Tier 2: React (src/)** -- Built via webpack to `build/`
- Admin SPA (`src/admin/`) -- HashRouter, 7 pages, 3 shared components, 4 wizard steps
- Gutenberg blocks (`src/blocks/`) -- Video + Playlist block editors and view scripts

### Adapter Pattern (player-wrapper.js)
Each platform adapter provides a uniform interface:
```javascript
{
  getPosition(),    // Current playback position in seconds
  getDuration(),    // Total video duration
  isPlaying(),      // Boolean playing state
  seekTo(seconds),  // Seek to position
  play(),           // Start playback
  pause(),          // Pause playback
  onReady(cb),      // Ready callback
  onEnded(cb),      // Ended callback
  destroy()         // Cleanup
}
```

Adapters are stored on `el._msAdapter` for cross-module access (tracker, watermark).

### Event Communication
```
CustomEvent 'mediashield:player-ready' (dispatched by player-wrapper.js)
  |-- Listened by: watermark.js, tracker.js, protection.js
  |-- Detail: { el, videoId, token, resumePosition, watermarkConfig, video, adapter }
```

### SDK Loading
Platform SDKs are lazy-loaded via a shared `loadSDK()` function with dedup:
- YouTube: `youtube.com/iframe_api` (global callback pattern)
- Vimeo: `player.vimeo.com/api/player.js`
- Wistia: `fast.wistia.com/assets/external/E-v1.js`
- Shaka: Bundled via npm (for HLS/DASH self-hosted/Bunny)

---

## Security Architecture

### Authentication Layers
| Resource | Capability | Notes |
|----------|-----------|-------|
| Tag read | `is_user_logged_in` | Any authenticated user |
| Tag write | `edit_posts` | Editor+ |
| Session management | `is_user_logged_in` | Own sessions only |
| Session revocation | `manage_options` | Admin kills other users' sessions |
| Upload | `upload_mediashield` | Custom capability, granted to admin on activation |
| Settings | `manage_options` | Admin only |
| Analytics (admin) | `manage_options` | Admin only |
| Analytics (self) | `is_user_logged_in` | Own watch history only |

### Anti-Piracy Measures
1. **Watermark overlay** -- Canvas-based, rotates position, anti-tamper (pauses video if canvas removed)
2. **Right-click blocking** -- contextmenu event prevention
3. **Keyboard blocking** -- Ctrl+S / Cmd+S prevention
4. **Source hiding** -- Move video src to data attribute, load via JS
5. **controlsList=nodownload** -- Native browser download button hidden
6. **Custom fullscreen** -- Container fullscreen (keeps watermark) instead of native video fullscreen
7. **Iframe replacement** -- Output buffer replaces raw iframes with adapter-based players (no exposed URLs in source)
8. **HMAC session tokens** -- Cryptographic session validation without DB lookup
9. **Concurrent stream limits** -- Configurable max simultaneous streams per user with row locking
10. **Domain restrictions** -- Embed whitelist prevents unauthorized embedding

### Rate Limiting
- Heartbeat: max 4 requests per minute per user (transient-based)

### File Upload Security
- MIME validation via `wp_check_filetype_and_ext`
- `.htaccess` denial in upload directory
- `index.php` for directory listing prevention
- File size limit (configurable, default 2 GB)

---

## Extension Architecture (Free/Pro Split)

### Filter-Based Extension Points

| Extension Point | Hook | Pro Use Case |
|----------------|------|-------------|
| Access control | `mediashield_can_watch` | Role-based access, email gating |
| Watermark config | `mediashield_watermark_config` | Email, timestamp, custom text in watermark |
| Upload drivers | `mediashield_upload_drivers` | Bunny, Vimeo, YouTube, Wistia upload drivers |
| Player type | `mediashield_player_type` | Override to `drm` for DRM-protected videos |
| Milestone thresholds | `mediashield_milestone_thresholds` | Custom percentages |
| Settings GET | `mediashield_settings_response` | Merge pro settings into response |
| Settings PUT | `mediashield_settings_update` | Handle pro-specific settings fields |
| Trusted IP headers | `mediashield_trusted_ip_headers` | Add CDN/proxy headers |
| Admin routes | `mediashield_admin_routes` | Add pages to admin SPA router |

### Pro Constant Detection
`defined('MEDIASHIELD_PRO_VERSION')` is checked in:
- `Player\Watermark::get_config()` -- Force badge visible if pro not active
- `Cron\Cleanup::handle_video_delete()` -- Cascade delete to pro tables
- `Privacy\PrivacyEraser::erase()` -- Delete from pro activity_alerts table

### Admin SPA Extension
Pro extends the admin SPA via:
1. `mediashield_admin_routes` filter (PHP) -- additional hash routes
2. `wp.hooks` (JS) -- SlotFill into existing pages

---

## Build System

### webpack.config.js
Extends `@wordpress/scripts` default config with custom entry points:
```
Entry points:
  blocks/video/index    -> build/blocks/video/index.js
  blocks/video/view     -> build/blocks/video/view.js
  blocks/playlist/index -> build/blocks/playlist/index.js
  blocks/playlist/view  -> build/blocks/playlist/view.js
  admin/index           -> build/admin/index.js + index.css
```

### Commands
```bash
npm run build    # Production build to build/
npm run start    # Dev watch mode with hot reload
npm run lint:js  # ESLint via wp-scripts
npm run lint:css # Stylelint via wp-scripts
```

---

## File Inventory

### PHP Source Files (34, excluding vendor)

| Path | Class/Purpose |
|------|--------------|
| `mediashield.php` | Entry point, constants, bootstrap |
| `uninstall.php` | Clean uninstall handler |
| `includes/Core/Plugin.php` | Singleton, hook registration |
| `includes/Core/Activator.php` | Activation (schema, options, caps, flush) |
| `includes/Core/Deactivator.php` | Deactivation (flush rewrite rules) |
| `includes/Core/Migrator.php` | DB version migration runner |
| `includes/Core/Assets.php` | Frontend JS/CSS enqueue |
| `includes/CPT/VideoPostType.php` | mediashield_video CPT + 7 meta fields |
| `includes/CPT/PlaylistPostType.php` | mediashield_playlist CPT + 4 meta fields |
| `includes/CPT/Thumbnail.php` | Auto-fetch thumbnails from platform APIs |
| `includes/DB/Schema.php` | dbDelta for 6 tables |
| `includes/REST/TagController.php` | /tags CRUD + /videos/{id}/tags |
| `includes/REST/SessionController.php` | /session/start, heartbeat, end, revoke-user |
| `includes/REST/PlaylistController.php` | /playlists/{id}/items CRUD + reorder |
| `includes/REST/UploadController.php` | /upload/init, /upload/status/{id} |
| `includes/REST/SettingsController.php` | /settings GET + PUT |
| `includes/REST/AnalyticsController.php` | /analytics/overview, milestones, users, my-videos |
| `includes/Access/AccessControl.php` | Permission checks (mediashield_can_watch filter) |
| `includes/Access/SessionManager.php` | HMAC token gen, concurrent stream limits, heartbeat |
| `includes/Block/VideoBlock.php` | mediashield/video block registration |
| `includes/Block/PlaylistBlock.php` | mediashield/playlist block registration |
| `includes/Block/MyVideosBlock.php` | mediashield/my-videos block + shortcode |
| `includes/Block/Shortcode.php` | [mediashield id=X] shortcode |
| `includes/Player/PlayerWrapper.php` | Output buffer video detection + wrapping |
| `includes/Player/Renderer.php` | Shared player HTML renderer |
| `includes/Player/Protection.php` | Anti-download config |
| `includes/Player/Watermark.php` | Watermark config provider |
| `includes/Milestones/MilestoneTracker.php` | 25/50/75/100% completion tracking |
| `includes/Upload/UploadManager.php` | Driver registry |
| `includes/Upload/Drivers/DriverInterface.php` | Upload driver contract |
| `includes/Upload/Drivers/SelfHosted.php` | Local wp-content upload driver |
| `includes/Tags/TagManager.php` | Tag CRUD on ms_tags/ms_video_tags |
| `includes/Cron/Cleanup.php` | Session archival, cascade delete |
| `includes/Privacy/PrivacyExporter.php` | GDPR data export |
| `includes/Privacy/PrivacyEraser.php` | GDPR data erasure |
| `includes/Admin/Menu.php` | Admin menu + SPA asset enqueue |
| `includes/Admin/SetupWizard.php` | First-activation redirect + wizard |
| `templates/single-mediashield_video.php` | Single video page template |
| `templates/login-overlay.php` | Login overlay fallback template |
| `src/blocks/video/render.php` | Video block server render |
| `src/blocks/playlist/render.php` | Playlist block server render |
| `src/blocks/my-videos/render.php` | My Videos block server render |

### JavaScript Source Files (35)

| Path | Purpose |
|------|---------|
| `assets/js/player-wrapper.js` | Platform adapter system (vanilla) |
| `assets/js/watermark.js` | Canvas watermark renderer (vanilla) |
| `assets/js/tracker.js` | Heartbeat tracker (vanilla) |
| `assets/js/protection.js` | Anti-download measures (vanilla) |
| `src/admin/index.js` | Admin SPA entry point |
| `src/admin/App.js` | Route definitions, layout |
| `src/admin/components/Sidebar.js` | Navigation sidebar |
| `src/admin/components/Toast.js` | Notification toasts |
| `src/admin/components/VideoPickerModal.js` | Video selection modal |
| `src/admin/pages/Dashboard.js` | Overview stats + charts |
| `src/admin/pages/Videos.js` | Video CRUD list |
| `src/admin/pages/Playlists.js` | Playlist management |
| `src/admin/pages/Tags.js` | Tag management |
| `src/admin/pages/Students.js` | User watch progress |
| `src/admin/pages/Milestones.js` | Milestone config |
| `src/admin/pages/Settings.js` | Plugin settings form |
| `src/admin/wizard/Wizard.js` | Setup wizard container |
| `src/admin/wizard/steps/GeneralStep.js` | Site config step |
| `src/admin/wizard/steps/PlatformStep.js` | Platform selection step |
| `src/admin/wizard/steps/WatermarkStep.js` | Watermark setup step |
| `src/admin/wizard/steps/FirstVideoStep.js` | First video upload step |
| `src/blocks/video/index.js` | Video block editor registration |
| `src/blocks/video/edit.js` | Video block editor component |
| `src/blocks/video/view.js` | Video block frontend script |
| `src/blocks/playlist/index.js` | Playlist block editor registration |
| `src/blocks/playlist/edit.js` | Playlist block editor component |
| `src/blocks/playlist/view.js` | Playlist block frontend script |
| `src/blocks/my-videos/view.js` | My Videos block frontend script |

### CSS Files (6)

| Path | Purpose |
|------|---------|
| `assets/css/player.css` | Player container, watermark, overlays, responsive |
| `src/admin/admin.css` | Admin SPA styles |
| `src/blocks/video/editor.css` | Video block editor |
| `src/blocks/playlist/editor.css` | Playlist block editor |
| `build/admin/index.css` | Compiled admin CSS |
| `build/admin/index-rtl.css` | RTL admin CSS |

---

## Performance Considerations

1. **Output buffer** -- `PlayerWrapper` runs on every frontend page; fast regex exit if no video/iframe found
2. **HMAC tokens** -- Session validation without DB lookup (crypto-only)
3. **Row locking** -- `SELECT ... FOR UPDATE` prevents concurrent session race conditions
4. **INSERT IGNORE** -- Milestone dedup without SELECT+INSERT round trip
5. **Transient rate limiting** -- Heartbeat rate limit uses WordPress transients (fast)
6. **Lazy SDK loading** -- Platform SDKs only loaded when their embeds are present
7. **MutationObserver** -- Dynamic embed detection without polling
8. **sendBeacon** -- Session end on page unload without blocking navigation
9. **Action Scheduler** -- Hourly session cleanup and monthly archival via background processing
10. **Conditional asset loading** -- Admin SPA assets only on MediaShield admin pages; frontend assets only if ms_enabled
