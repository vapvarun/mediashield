# MediaShield - Developer Reference

Video protection for WordPress -- dynamic watermarking, multi-platform support, engagement analytics, and milestone automation.

- **Version:** 1.0.0
- **Requires:** PHP 8.1, WordPress 6.5
- **Text Domain:** mediashield
- **Namespace:** `MediaShield\`
- **Autoload:** PSR-4 via Composer (`includes/`)

---

## Architecture

Singleton bootstrap in `mediashield.php`:
1. Composer autoloader loads `MediaShield\` from `includes/`
2. `plugins_loaded` runs `Migrator::run()` then `Plugin::instance()`
3. `Plugin.php` is a singleton that registers all CPTs, REST routes, blocks, assets, cron, and privacy handlers
4. Fires `mediashield_loaded` action when complete

Constants: `MEDIASHIELD_VERSION`, `MEDIASHIELD_DB_VERSION`, `MEDIASHIELD_FILE`, `MEDIASHIELD_PATH`, `MEDIASHIELD_URL`

---

## File Structure

```
mediashield.php              Main plugin file
uninstall.php                Clean uninstall handler
composer.json                PSR-4 autoload config
package.json                 npm deps (chart.js, shaka-player)
webpack.config.js            Custom entry points extending @wordpress/scripts

includes/
  Core/
    Plugin.php               Singleton, registers all hooks
    Activator.php            Activation (DB schema, options, flush rewrite)
    Deactivator.php          Deactivation (clear crons)
    Migrator.php             DB version migration runner
    Assets.php               Frontend JS/CSS enqueue
  CPT/
    VideoPostType.php        mediashield_video CPT + meta
    PlaylistPostType.php     mediashield_playlist CPT + meta
    Thumbnail.php            Video thumbnail handling
  DB/
    Schema.php               dbDelta for all 6 free tables
  REST/
    TagController.php        /tags CRUD, /videos/{id}/tags
    SessionController.php    /session/start, heartbeat, end, revoke-user
    PlaylistController.php   /playlists/{id}/items CRUD + reorder
    UploadController.php     /upload/init, /upload/status/{id}
    SettingsController.php   /settings GET + PUT
    AnalyticsController.php  /analytics/overview, milestones, users, my-videos
  Access/
    AccessControl.php        Permission checks (mediashield_can_watch filter)
    SessionManager.php       HMAC token generation, concurrent stream limits
  Block/
    VideoBlock.php           mediashield/video Gutenberg block
    PlaylistBlock.php        mediashield/playlist Gutenberg block
    MyVideosBlock.php        mediashield/my-videos block + shortcode
    Shortcode.php            [mediashield id=X] shortcode
  Player/
    PlayerWrapper.php        Output buffer video detection + wrapping
    Protection.php           Right-click disable, devtools detection
    Watermark.php            Dynamic watermark overlay
  Milestones/
    MilestoneTracker.php     25/50/75/100% completion tracking
  Upload/
    UploadManager.php        Driver registry (mediashield_upload_drivers filter)
    Drivers/
      DriverInterface.php    Upload driver contract
      SelfHosted.php         Local wp-content upload driver
  Tags/
    TagManager.php           Tag CRUD helpers
  Cron/
    Cleanup.php              Session archival, cascade delete
  Privacy/
    PrivacyExporter.php      GDPR data export
    PrivacyEraser.php        GDPR data erasure
  Admin/
    Menu.php                 Admin menu + SPA asset enqueue
    SetupWizard.php          First-activation redirect + wizard

src/                         JS source (compiled to build/)
  admin/                     React admin SPA
    index.js                 Entry point, hash router
    App.js                   Route definitions, layout
    components/
      Sidebar.js             Navigation sidebar
      Toast.js               Notification toasts
      VideoPickerModal.js    Video selection modal
    pages/
      Dashboard.js           Overview stats + charts
      Videos.js              Video CRUD list
      Playlists.js           Playlist management
      Tags.js                Tag management
      Students.js            User watch progress
      Milestones.js          Milestone config
      Settings.js            Plugin settings form
    wizard/
      Wizard.js              Setup wizard container
      steps/
        GeneralStep.js       Site config
        PlatformStep.js      Platform selection
        WatermarkStep.js     Watermark setup
        FirstVideoStep.js    First video upload
  blocks/
    video/                   Video block (edit.js, index.js, view.js)
    playlist/                Playlist block (edit.js, index.js, view.js)

assets/
  js/
    player-wrapper.js        Vanilla JS player detection + wrapping
    watermark.js             Dynamic watermark rendering
    tracker.js               Watch session heartbeat + progress
    protection.js            Right-click block, devtools detection
  css/
    player.css               Player + watermark styles
```

---

## Custom Post Types

### mediashield_video
- **Slug:** `video`
- **REST base:** `mediashield-videos`
- **Supports:** title, editor, thumbnail, custom-fields
- **Meta fields:**
  - `_ms_platform` (string, default `self`) -- hosting platform
  - `_ms_platform_video_id` (string) -- external platform video ID
  - `_ms_source_url` (string) -- direct video URL
  - `_ms_protection_level` (string, default `standard`) -- standard/drm
  - `_ms_access_role` (string) -- required role slug
  - `_ms_duration` (integer) -- video duration in seconds

### mediashield_playlist
- **Slug:** `playlist`
- **REST base:** `mediashield-playlists`
- **Supports:** title, editor, thumbnail
- **Meta fields:**
  - `_ms_autoplay` (boolean, default false)
  - `_ms_countdown` (integer, default 5) -- seconds between videos
  - `_ms_loop` (boolean, default false)
  - `_ms_shuffle` (boolean, default false)

---

## Database Tables

All tables use `{$wpdb->prefix}` prefix. Created via `dbDelta` in `DB\Schema`.

| Table | Columns |
|-------|---------|
| `ms_tags` | id, name, slug (unique), description, created_by, created_at |
| `ms_video_tags` | video_id, tag_id (unique pair), tagged_by, tagged_at |
| `ms_watch_sessions` | id, video_id, user_id, session_token, ip_address, user_agent, device_type, browser, started_at, last_heartbeat, total_seconds, max_position, completion_pct, is_active |
| `ms_watch_sessions_archive` | Same schema as ms_watch_sessions |
| `ms_milestones` | id, video_id, user_id, milestone_pct (unique triple), reached_at, session_id |
| `ms_playlist_items` | id, playlist_id, video_id, sort_order, added_at |

---

## REST API Endpoints

All routes under namespace `mediashield/v1`. Require `manage_options` unless noted.

### Tags
| Method | Route | Description |
|--------|-------|-------------|
| GET | `/tags` | List all tags |
| POST | `/tags` | Create tag |
| GET/PUT/DELETE | `/tags/{id}` | Single tag CRUD |
| GET | `/videos/{video_id}/tags` | Tags for a video |
| POST | `/videos/{video_id}/tags` | Assign tag to video |
| DELETE | `/videos/{video_id}/tags/{tag_id}` | Remove tag from video |

### Sessions
| Method | Route | Description |
|--------|-------|-------------|
| POST | `/session/start` | Start watch session, returns HMAC token |
| POST | `/session/heartbeat` | Update position + progress |
| POST | `/session/end` | End session, finalize stats |
| POST | `/session/revoke-user` | Kill all active sessions for user |

### Playlists
| Method | Route | Description |
|--------|-------|-------------|
| GET/POST | `/playlists/{playlist_id}/items` | List/add playlist items |
| DELETE | `/playlists/{playlist_id}/items/{item_id}` | Remove item |
| POST | `/playlists/{playlist_id}/items/reorder` | Reorder items |

### Upload
| Method | Route | Description |
|--------|-------|-------------|
| POST | `/upload/init` | Initialize upload (chunked supported) |
| GET | `/upload/status/{upload_id}` | Check upload progress |

### Settings
| Method | Route | Description |
|--------|-------|-------------|
| GET | `/settings` | Retrieve all settings (filterable) |
| PUT | `/settings` | Update settings (filterable) |

### Analytics
| Method | Route | Description |
|--------|-------|-------------|
| GET | `/analytics/overview` | Dashboard summary stats |
| GET | `/videos/{id}/stats` | Per-video statistics |
| GET | `/analytics/milestones` | Milestone completion data |
| GET | `/analytics/users` | User engagement list |
| GET | `/analytics/users/{user_id}` | Single user detail |
| GET | `/analytics/my-videos` | Current user's watched videos |

---

## Gutenberg Blocks

| Block | Slug | Description |
|-------|------|-------------|
| Video | `mediashield/video` | Embed protected video with player wrapper |
| Playlist | `mediashield/playlist` | Playlist with autoplay/countdown |
| My Videos | `mediashield/my-videos` | Logged-in user's watch history |

---

## Shortcodes

- `[mediashield id=X]` -- Render protected video player for video CPT ID X
- `[mediashield_my_videos]` -- Render current user's watched videos grid

---

## WordPress Hooks

### Actions
| Hook | Parameters | Description |
|------|------------|-------------|
| `mediashield_loaded` | (none) | Fired after core plugin fully loaded |
| `mediashield_session_started` | $session_id, $video_id, $user_id, $ip | New watch session created |
| `mediashield_session_ended` | $session_id, $video_id, $user_id | Watch session finalized |
| `mediashield_concurrent_limit_reached` | $user_id, $video_id, $active_count, $max | Too many concurrent streams |
| `mediashield_user_access_revoked` | $user_id, $count | All sessions killed for user |
| `mediashield_milestone_reached` | $user_id, $video_id, $pct, $session_id | Any milestone hit |
| `mediashield_milestone_{pct}` | $user_id, $video_id | Specific milestone (25/50/75/100) |
| `mediashield_upload_complete` | $video_id, $driver_name, $result | Upload finished |

### Filters
| Hook | Parameters | Description |
|------|------------|-------------|
| `mediashield_can_watch` | $result, $video_id, $user_id | Access control gate (return WP_Error to deny) |
| `mediashield_watermark_config` | $config, $video_id, $user_id | Watermark overlay settings |
| `mediashield_upload_drivers` | $drivers | Registered upload driver classes |
| `mediashield_player_type` | $type, $video_id | Player type string (standard/drm) |
| `mediashield_milestone_thresholds` | $thresholds, $video_id | Array of milestone percentages |
| `mediashield_settings_response` | $settings | GET /settings output |
| `mediashield_settings_update` | $data | PUT /settings input |
| `mediashield_trusted_ip_headers` | $headers | IP detection header names |

---

## JavaScript Architecture

### Vanilla JS (assets/js/) -- no build required
- **player-wrapper.js** -- Detects video/iframe elements, wraps with protection container
- **watermark.js** -- Renders dynamic user watermark overlay (email/name + timestamp)
- **tracker.js** -- Sends heartbeat POST to /session/heartbeat every 30s
- **protection.js** -- Disables right-click context menu, detects devtools open

### React (src/) -- built via webpack
- **Admin SPA** (src/admin/) -- React app with hash routing (`#/dashboard`, `#/videos`, etc.)
- **Blocks** (src/blocks/) -- Gutenberg block edit + view scripts

### Build
```bash
npm run build    # Production build to build/
npm run start    # Dev watch mode
```

webpack.config.js entry points:
- `blocks/video/index` + `blocks/video/view`
- `blocks/playlist/index` + `blocks/playlist/view`
- `admin/index`

Dependencies: chart.js (analytics charts), shaka-player (DRM playback)

---

## Admin SPA

Hash-routed React application rendered in a single WP admin page.

### Pages (7 routes)
| Route | Component | Description |
|-------|-----------|-------------|
| `#/dashboard` | Dashboard.js | Overview stats, charts |
| `#/videos` | Videos.js | Video CRUD list |
| `#/playlists` | Playlists.js | Playlist management |
| `#/tags` | Tags.js | Tag management |
| `#/students` | Students.js | User watch progress |
| `#/milestones` | Milestones.js | Milestone configuration |
| `#/settings` | Settings.js | Plugin settings |

### Components
- Sidebar.js -- Navigation sidebar with route links
- Toast.js -- Success/error notifications
- VideoPickerModal.js -- Reusable video selector

---

## Key Classes

| Class | Purpose |
|-------|---------|
| `Core\Plugin` | Singleton entry point, registers all hooks |
| `Access\SessionManager` | HMAC token generation/validation, concurrent stream enforcement |
| `Access\AccessControl` | `mediashield_can_watch` filter chain |
| `Milestones\MilestoneTracker` | Detects 25/50/75/100% completion, fires hooks |
| `Player\PlayerWrapper` | Output buffer scan for videos, wraps with protection container |
| `Upload\UploadManager` | Driver registry via `mediashield_upload_drivers` filter |
| `Core\Migrator` | Runs DB schema upgrades based on version comparison |

---

## Extension Points for Pro

The free plugin provides 4 extension patterns for the Pro add-on:

1. **Admin SPA routes** -- `mediashield_admin_routes` filter adds pages to the hash router
2. **SlotFill** -- Admin SPA uses `wp.hooks` for Pro to inject UI into existing pages
3. **Settings REST** -- `mediashield_settings_response` and `mediashield_settings_update` filters let Pro add/save settings
4. **Player type** -- `mediashield_player_type` filter lets Pro override to `drm` for DRM-protected videos

---

## Settings (wp_options)

Key option names used by `SettingsController`:
`ms_enabled`, `ms_default_protection`, `ms_require_login`, `ms_watermark_opacity`, `ms_watermark_color`, `ms_watermark_swap_interval`, `ms_allowed_domains`, `ms_max_concurrent_streams`, `ms_custom_url_patterns`, `ms_show_badge`, `ms_max_upload_size`, `ms_login_overlay_text`, `ms_login_button_text`, `ms_access_denied_text`

---

## Documentation

| Document | Path | Description |
|----------|------|-------------|
| Feature Audit | `docs/audit/FEATURE_AUDIT.md` | Complete feature inventory: CPTs, DB tables, REST endpoints, hooks, settings, blocks, shortcodes, cron jobs, capabilities, templates, GDPR |
| Code Flows | `docs/audit/CODE_FLOWS.md` | 16 detailed code flow maps covering bootstrap, session lifecycle, upload, output buffer, cascade delete, milestone tracking, access control, HMAC tokens |
| Architecture | `docs/architecture/PLUGIN_ARCHITECTURE.md` | High-level architecture, module dependency graph, data architecture, JS adapter pattern, security model, extension architecture, build system, file inventory |
| Design Spec | `docs/DESIGN_SPEC.md` | Original design specification |
| Design Spec v2 | `docs/DESIGN_SPEC_v2.md` | Updated design specification |
| Implementation Plan | `docs/IMPLEMENTATION_PLAN.md` | Original implementation plan |
| Implementation Plan v2 | `docs/IMPLEMENTATION_PLAN_v2.md` | Updated implementation plan |

---

## Recent Changes

| Date | Files | Summary |
|------|-------|---------|
| 2026-04-01 | docs/audit/, docs/architecture/ | Full onboard: feature audit, code flow maps, architecture docs |
| 2026-03-30 | Initial | v1.0.0 -- Full plugin implementation |
