# MediaShield -- Feature Audit

**Plugin:** MediaShield v1.0.0
**Audited:** 2026-04-01
**Files analyzed:** 249 total (34 PHP source, 35 JS source/built, 6 CSS, 3 block.json, 2 templates)

---

## 1. Custom Post Types

### mediashield_video
- **File:** `includes/CPT/VideoPostType.php`
- **Slug:** `video` (rewrite), `mediashield-videos` (REST base)
- **Supports:** title, editor, thumbnail, custom-fields
- **REST:** Enabled (`show_in_rest = true`)
- **Admin menu:** Hidden (`show_in_menu = false`) -- managed by custom admin SPA
- **Meta fields (7):**

| Meta Key | Type | Default | Sanitize |
|----------|------|---------|----------|
| `_ms_platform` | string | `self` | `sanitize_text_field` |
| `_ms_platform_video_id` | string | `''` | `sanitize_text_field` |
| `_ms_source_url` | string | `''` | `sanitize_text_field` |
| `_ms_protection_level` | string | `standard` | `sanitize_text_field` |
| `_ms_access_role` | string | `''` | `sanitize_text_field` |
| `_ms_duration` | integer | `0` | `absint` |
| `_ms_stream_url` | string | `''` | `sanitize_text_field` |

### mediashield_playlist
- **File:** `includes/CPT/PlaylistPostType.php`
- **Slug:** `playlist` (rewrite), `mediashield-playlists` (REST base)
- **Supports:** title, editor, thumbnail
- **REST:** Enabled
- **Admin menu:** Hidden
- **Meta fields (4):**

| Meta Key | Type | Default | Sanitize |
|----------|------|---------|----------|
| `_ms_autoplay` | boolean | `false` | `rest_sanitize_boolean` |
| `_ms_countdown` | integer | `5` | `absint` |
| `_ms_loop` | boolean | `false` | `rest_sanitize_boolean` |
| `_ms_shuffle` | boolean | `false` | `rest_sanitize_boolean` |

---

## 2. Database Tables (6 custom tables)

All created via `dbDelta` in `includes/DB/Schema.php`. Prefix: `{$wpdb->prefix}`.

| Table | Purpose | Key Indexes |
|-------|---------|-------------|
| `ms_tags` | Video tag taxonomy (custom, not WP taxonomy) | `uk_slug` (UNIQUE) |
| `ms_video_tags` | Many-to-many video-tag assignments | `uk_video_tag` (UNIQUE pair), `idx_tag_id` |
| `ms_watch_sessions` | Active/recent watch session tracking | `idx_video_user`, `idx_active`, `idx_user`, `idx_started` |
| `ms_watch_sessions_archive` | Archived sessions (>24 months old) | Same as ms_watch_sessions |
| `ms_milestones` | Completion milestone tracking (25/50/75/100%) | `uk_video_user_pct` (UNIQUE triple), `idx_user_id` |
| `ms_playlist_items` | Ordered video-playlist memberships | `idx_playlist`, `idx_video` |

---

## 3. REST API Endpoints (6 controllers, 22 routes)

**Namespace:** `mediashield/v1`

### TagController (`includes/REST/TagController.php`)
| Method | Route | Permission | Description |
|--------|-------|------------|-------------|
| GET | `/tags` | `is_user_logged_in` | List tags (paginated, searchable) |
| POST | `/tags` | `edit_posts` | Create tag |
| GET | `/tags/{id}` | `is_user_logged_in` | Get single tag |
| PATCH | `/tags/{id}` | `edit_posts` | Update tag |
| DELETE | `/tags/{id}` | `edit_posts` | Delete tag |
| GET | `/videos/{video_id}/tags` | `is_user_logged_in` | Tags for a video |
| POST | `/videos/{video_id}/tags` | `edit_posts` | Assign tag to video |
| DELETE | `/videos/{video_id}/tags/{tag_id}` | `edit_posts` | Remove tag from video |

### SessionController (`includes/REST/SessionController.php`)
| Method | Route | Permission | Description |
|--------|-------|------------|-------------|
| POST | `/session/start` | `is_user_logged_in` | Start/resume watch session (HMAC token returned) |
| POST | `/session/heartbeat` | `is_user_logged_in` | Track playback progress (rate-limited: 4/min) |
| POST | `/session/end` | `is_user_logged_in` | End session, finalize stats |
| POST | `/session/revoke-user` | `manage_options` | Kill all active sessions for a user |

### PlaylistController (`includes/REST/PlaylistController.php`)
| Method | Route | Permission | Description |
|--------|-------|------------|-------------|
| GET | `/playlists/{playlist_id}/items` | `is_user_logged_in` | List videos in order |
| POST | `/playlists/{playlist_id}/items` | `edit_posts` | Add video to playlist |
| DELETE | `/playlists/{playlist_id}/items/{item_id}` | `edit_posts` | Remove video |
| PUT | `/playlists/{playlist_id}/items/reorder` | `edit_posts` | Batch reorder |

### UploadController (`includes/REST/UploadController.php`)
| Method | Route | Permission | Description |
|--------|-------|------------|-------------|
| POST | `/upload/init` | `upload_mediashield` | Upload a video file |
| GET | `/upload/status/{upload_id}` | `upload_mediashield` | Check upload progress |

### SettingsController (`includes/REST/SettingsController.php`)
| Method | Route | Permission | Description |
|--------|-------|------------|-------------|
| GET | `/settings` | `manage_options` | Retrieve all settings |
| PUT | `/settings` | `manage_options` | Update settings (partial supported) |

### AnalyticsController (`includes/REST/AnalyticsController.php`)
| Method | Route | Permission | Description |
|--------|-------|------------|-------------|
| GET | `/analytics/overview` | `manage_options` | Dashboard summary stats (period filter) |
| GET | `/videos/{id}/stats` | `manage_options` | Per-video statistics |
| GET | `/analytics/milestones` | `manage_options` | Paginated milestone list |
| GET | `/analytics/users` | `manage_options` | User engagement list (searchable) |
| GET | `/analytics/users/{user_id}` | `manage_options` | Single user drill-down |
| GET | `/analytics/my-videos` | `is_user_logged_in` | Current user's watched videos |

### SetupWizard (inline route in `includes/Admin/SetupWizard.php`)
| Method | Route | Permission | Description |
|--------|-------|------------|-------------|
| POST | `/wizard/complete` | `manage_options` | Mark wizard as completed |

---

## 4. AJAX Handlers

**None.** The plugin uses REST API exclusively; no `wp_ajax_` handlers.

---

## 5. Shortcodes (2)

| Shortcode | File | Description |
|-----------|------|-------------|
| `[mediashield id=X]` | `includes/Block/Shortcode.php` | Render protected video player via `Renderer::render()` |
| `[mediashield_my_videos]` | `includes/Block/MyVideosBlock.php` | Render current user's watch history grid |

---

## 6. Gutenberg Blocks (3)

| Block | Slug | Editor Script | View Script | Render PHP | Attributes |
|-------|------|---------------|-------------|------------|------------|
| Video | `mediashield/video` | `src/blocks/video/index.js` | `src/blocks/video/view.js` | `src/blocks/video/render.php` | `videoId` (number), `url` (string) |
| Playlist | `mediashield/playlist` | `src/blocks/playlist/index.js` | `src/blocks/playlist/view.js` | `src/blocks/playlist/render.php` | `playlistId` (number) |
| My Videos | `mediashield/my-videos` | none (SSR only) | `src/blocks/my-videos/view.js` | `src/blocks/my-videos/render.php` | none |

---

## 7. Admin Pages

### Main Admin SPA
- **File:** `includes/Admin/Menu.php`
- **Hook:** `admin_menu` -> `add_menu_page`
- **Slug:** `mediashield`
- **Capability:** `manage_options`
- **Renders:** `<div id="mediashield-admin-root">` -- React SPA mounted here
- **React bundle:** `build/admin/index.js` with hash routing

### Setup Wizard (hidden page)
- **File:** `includes/Admin/SetupWizard.php`
- **Slug:** `mediashield-wizard` (hidden submenu, no parent)
- **Auto-redirect:** On first activation via `ms_activation_redirect` transient
- **Renders:** `<div id="mediashield-wizard-root">` -- Same React bundle with `isWizard: true`

### Admin SPA Routes (7 pages)
| Route | Component | File |
|-------|-----------|------|
| `#/dashboard` | Dashboard | `src/admin/pages/Dashboard.js` |
| `#/videos` | Videos | `src/admin/pages/Videos.js` |
| `#/playlists` | Playlists | `src/admin/pages/Playlists.js` |
| `#/tags` | Tags | `src/admin/pages/Tags.js` |
| `#/students` | Students | `src/admin/pages/Students.js` |
| `#/milestones` | Milestones | `src/admin/pages/Milestones.js` |
| `#/settings` | Settings | `src/admin/pages/Settings.js` |

### Wizard Steps (4 steps)
| Step | Component | File |
|------|-----------|------|
| General | GeneralStep | `src/admin/wizard/steps/GeneralStep.js` |
| Platform | PlatformStep | `src/admin/wizard/steps/PlatformStep.js` |
| Watermark | WatermarkStep | `src/admin/wizard/steps/WatermarkStep.js` |
| First Video | FirstVideoStep | `src/admin/wizard/steps/FirstVideoStep.js` |

---

## 8. Cron Jobs / Scheduled Tasks

Uses **Action Scheduler** (Composer dependency: `woocommerce/action-scheduler ^3.7`).

| Action Hook | Schedule | File | Description |
|-------------|----------|------|-------------|
| `ms_cleanup_inactive_sessions` | Hourly | `includes/Cron/Cleanup.php` | Mark sessions inactive if heartbeat > 10 min stale |
| `ms_archive_old_sessions` | Monthly | `includes/Cron/Cleanup.php` | Move sessions > 24 months old to archive table, delete originals |

---

## 9. WordPress Hooks

### Actions Fired (8)
| Hook | Parameters | File |
|------|------------|------|
| `mediashield_loaded` | (none) | `includes/Core/Plugin.php:93` |
| `mediashield_session_started` | `$session_id, $video_id, $user_id, $ip` | `includes/Access/SessionManager.php:145` |
| `mediashield_session_ended` | `$session_id, $video_id, $user_id` | `includes/Access/SessionManager.php:242` |
| `mediashield_concurrent_limit_reached` | `$user_id, $video_id, $active_count, $max` | `includes/Access/SessionManager.php:87` |
| `mediashield_user_access_revoked` | `$user_id, $count` | `includes/Access/SessionManager.php:274` |
| `mediashield_milestone_reached` | `$user_id, $video_id, $pct, $session_id` | `includes/Milestones/MilestoneTracker.php:84` |
| `mediashield_milestone_{pct}` | `$user_id, $video_id` | `includes/Milestones/MilestoneTracker.php:96` |
| `mediashield_upload_complete` | `$video_id, $driver_name, $result` | `includes/REST/UploadController.php:118` |
| `mediashield_needs_shaka` | (none) | `includes/Player/PlayerWrapper.php:140`, `includes/Player/Renderer.php:44` |

### Filters Provided (8)
| Hook | Parameters | File |
|------|------------|------|
| `mediashield_can_watch` | `$result, $video_id, $user_id` | `includes/Access/AccessControl.php:53` |
| `mediashield_watermark_config` | `$config, $video_id, $user_id` | `includes/REST/SessionController.php:183` |
| `mediashield_upload_drivers` | `$drivers` | `includes/Upload/UploadManager.php:37` |
| `mediashield_player_type` | `$type, $video_id` | `includes/Player/PlayerWrapper.php:136`, `includes/Player/Renderer.php:36` |
| `mediashield_milestone_thresholds` | `$thresholds, $video_id` | `includes/Milestones/MilestoneTracker.php:41` |
| `mediashield_settings_response` | `$settings` | `includes/REST/SettingsController.php:95` |
| `mediashield_settings_update` | `$data` | `includes/REST/SettingsController.php:119` |
| `mediashield_trusted_ip_headers` | `$headers` | `includes/REST/SessionController.php:281` |

### Hooks Consumed (WP Core)
| Hook | Type | File | Callback |
|------|------|------|----------|
| `plugins_loaded` | action | `mediashield.php:38` | Bootstrap (Migrator + Plugin) |
| `init` | action | `CPT/VideoPostType.php`, `CPT/PlaylistPostType.php`, `Cron/Cleanup.php` | CPT registration, cron scheduling |
| `rest_api_init` | action | `Core/Plugin.php:56`, `Admin/SetupWizard.php:26` | REST route registration |
| `admin_menu` | action | `Admin/Menu.php:19`, `Admin/SetupWizard.php:22` | Admin menu registration |
| `admin_enqueue_scripts` | action | `Admin/Menu.php:20`, `Admin/SetupWizard.php:23` | Admin asset enqueue |
| `wp_enqueue_scripts` | action | `Core/Assets.php:28` | Frontend asset enqueue |
| `template_redirect` | action | `Player/PlayerWrapper.php:19` | Output buffer start |
| `single_template` | filter | `Core/Plugin.php:86` | Custom single video template |
| `save_post_mediashield_video` | action | `CPT/Thumbnail.php:17` | Auto-fetch thumbnail |
| `before_delete_post` | action | `Cron/Cleanup.php:24-25` | Cascade delete |
| `wp_privacy_personal_data_exporters` | filter | `Privacy/PrivacyExporter.php:19` | GDPR export |
| `wp_privacy_personal_data_erasers` | filter | `Privacy/PrivacyEraser.php:19` | GDPR erasure |
| `admin_init` | action | `Admin/SetupWizard.php:21` | Wizard redirect |

---

## 10. Capabilities

| Capability | Granted To | File |
|------------|-----------|------|
| `upload_mediashield` | administrator (on activation) | `includes/Core/Activator.php:62` |

Removed from all roles on uninstall (`uninstall.php:34`).

---

## 11. Settings (wp_options, 14 keys)

All prefixed `ms_`. Managed by `SettingsController`.

| Option Key | Type | Default | Description |
|------------|------|---------|-------------|
| `ms_enabled` | boolean | `true` | Master on/off switch |
| `ms_default_protection` | string | `standard` | Default protection level |
| `ms_require_login` | boolean | `true` | Require login to watch |
| `ms_watermark_opacity` | float | `0.3` | Watermark opacity (0-1) |
| `ms_watermark_color` | string | `#ffffff` | Watermark text color |
| `ms_watermark_swap_interval` | integer | `20` | Seconds between position swaps |
| `ms_allowed_domains` | string | `''` | Comma-separated allowed embed domains |
| `ms_max_concurrent_streams` | integer | `2` | Max simultaneous video streams per user |
| `ms_custom_url_patterns` | string | `''` | Newline-separated regex patterns for custom embed detection |
| `ms_show_badge` | boolean | `true` | Show "Protected by MediaShield" badge |
| `ms_max_upload_size` | integer | `2 * GB_IN_BYTES` | Max upload file size |
| `ms_login_overlay_text` | string | `''` | Custom login prompt text |
| `ms_login_button_text` | string | `''` | Custom login button text |
| `ms_access_denied_text` | string | `''` | Custom access denied message |

**Internal options (not in Settings controller):**

| Option Key | Type | Description |
|------------|------|-------------|
| `ms_db_version` | integer | Current DB schema version |
| `ms_wizard_completed` | boolean | Setup wizard completed flag |

---

## 12. Templates (2)

| Template | File | Description |
|----------|------|-------------|
| Single Video | `templates/single-mediashield_video.php` | Custom single post template with Renderer output + JSON-LD VideoObject schema |
| Login Overlay | `templates/login-overlay.php` | Server-side fallback login overlay (primary is JS-rendered) |

Both templates support block themes (`wp_is_block_theme()` check, `block_template_part`).

---

## 13. Uninstall Cleanup

**File:** `uninstall.php`

Actions performed on plugin deletion:
1. Drop all 6 custom tables
2. Delete all `ms_%` options
3. Remove `upload_mediashield` capability from all roles
4. Delete all `mediashield_video` and `mediashield_playlist` posts (force delete)
5. Clean up `_transient_ms_%` entries

---

## 14. GDPR / Privacy

| Component | File | Description |
|-----------|------|-------------|
| Data Exporter | `includes/Privacy/PrivacyExporter.php` | Exports watch sessions + milestones (paginated) |
| Data Eraser | `includes/Privacy/PrivacyEraser.php` | Anonymizes IP/UA in sessions, deletes pro activity alerts |

---

## 15. Upload System

### Driver Architecture
- **Interface:** `includes/Upload/Drivers/DriverInterface.php` -- `upload()`, `get_status()`, `delete()`, `get_embed_url()`, `get_name()`
- **Manager:** `includes/Upload/UploadManager.php` -- Driver registry via `mediashield_upload_drivers` filter
- **Free driver:** `includes/Upload/Drivers/SelfHosted.php` -- Local upload to `wp-content/uploads/mediashield/`

### SelfHosted Driver Details
- Allowed MIME types: `mp4`, `webm`, `mov`, `m4v`
- Creates `.htaccess` to deny direct access
- Creates `index.php` to prevent directory listing
- Validates MIME via `wp_check_filetype_and_ext`
- Auto-creates `mediashield_video` CPT post on upload

---

## 16. Video Thumbnail Auto-Fetch

**File:** `includes/CPT/Thumbnail.php`

Hooks `save_post_mediashield_video`. When a video is saved without a featured image, fetches thumbnail from platform API:

| Platform | Source |
|----------|--------|
| YouTube | `img.youtube.com/vi/{id}/maxresdefault.jpg` |
| Vimeo | Vimeo oEmbed API |
| Wistia | Wistia oEmbed API |
| Bunny | Not supported in free (requires API key) |

Uses `media_sideload_image` to download and set as featured image.

---

## 17. JavaScript Architecture

### Vanilla JS (4 files, no build step)

| File | Handle | Dependencies | Purpose |
|------|--------|--------------|---------|
| `assets/js/player-wrapper.js` | `mediashield-player-wrapper` | none | Platform adapter system (YouTube, Vimeo, Wistia, Shaka/native) |
| `assets/js/watermark.js` | `mediashield-watermark` | player-wrapper | Canvas overlay rendering with position swap |
| `assets/js/tracker.js` | `mediashield-tracker` | player-wrapper | 30s heartbeat to `/session/heartbeat` |
| `assets/js/protection.js` | `mediashield-protection` | player-wrapper | Right-click block, Ctrl+S block, source hiding, badge |

### Platform Adapters (in player-wrapper.js)
Each adapter implements: `getPosition()`, `getDuration()`, `isPlaying()`, `seekTo()`, `play()`, `pause()`, `onReady()`, `onEnded()`, `destroy()`

| Adapter | Platform | SDK Loaded |
|---------|----------|------------|
| YouTubeAdapter | youtube | YouTube IFrame API |
| VimeoAdapter | vimeo | Vimeo Player SDK |
| WistiaAdapter | wistia | Wistia E-v1.js |
| NativeAdapter | self, bunny | Shaka Player (HLS/DASH) or native `<video>` |

### React (built via webpack)

| Bundle | Entry | Purpose |
|--------|-------|---------|
| `build/admin/index.js` | `src/admin/index.js` | Admin SPA (hash router) |
| `build/blocks/video/index.js` | `src/blocks/video/index.js` | Video block editor |
| `build/blocks/video/view.js` | `src/blocks/video/view.js` | Video block frontend |
| `build/blocks/playlist/index.js` | `src/blocks/playlist/index.js` | Playlist block editor |
| `build/blocks/playlist/view.js` | `src/blocks/playlist/view.js` | Playlist block frontend |

### Localized Config Objects
- `mediashieldConfig` (frontend) -- REST URL, nonce, user info, watermark/protection config
- `mediashieldAdmin` (admin) -- REST URLs, nonce, version, admin URL, isProActive flag

---

## 18. CSS Files

| File | Purpose |
|------|---------|
| `assets/css/player.css` | Player container, watermark canvas, login overlay, resume toast, badge, fullscreen, responsive |
| `src/admin/admin.css` | Admin SPA styles (compiled to `build/admin/index.css`) |
| `src/blocks/video/editor.css` | Video block editor styles |
| `src/blocks/playlist/editor.css` | Playlist block editor styles |

---

## 19. Dependencies

### PHP (Composer)
- `woocommerce/action-scheduler ^3.7` -- Scheduled task runner for cron jobs

### JavaScript (npm)
- `@wordpress/scripts ^28.0` -- Build tooling (webpack)
- `chart.js ^4.4` -- Dashboard analytics charts
- `shaka-player ^4.8` -- HLS/DASH video playback

---

## 20. Pro Extension Points

The free plugin provides 4 extension patterns:

| Pattern | Hook/Mechanism | Description |
|---------|---------------|-------------|
| Admin SPA routes | `mediashield_admin_routes` filter | Pro adds pages to hash router |
| SlotFill | `wp.hooks` (JS) | Pro injects UI into existing admin pages |
| Settings REST | `mediashield_settings_response` + `mediashield_settings_update` | Pro adds/saves pro settings |
| Player type | `mediashield_player_type` filter | Pro overrides to `drm` for DRM-protected videos |

### Pro-Aware Cascade Delete
`Cron/Cleanup.php` contains cascade delete logic for pro tables when `MEDIASHIELD_PRO_VERSION` is defined:
- `ms_playback_events`
- `ms_activity_alerts`
- `ms_drm_licenses`
- `ms_heatmap_cache`
- `ms_drm_keys`

### Pro-Aware Privacy Eraser
`Privacy/PrivacyEraser.php` deletes from `ms_activity_alerts` when pro is active.
