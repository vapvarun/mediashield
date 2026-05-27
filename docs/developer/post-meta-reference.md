# Post Meta Reference

Every `_ms_*` post meta key for the `mediashield_video` and `mediashield_playlist` custom post types. These are registered via `register_post_meta` in the CPT classes and are readable/writable via the WordPress REST API (`/wp-json/wp/v2/mediashield-videos/{id}` and `/wp-json/wp/v2/mediashield-playlists/{id}`) in addition to the MediaShield admin SPA.

Customers configure these through the video editor UI. Developers can read or write them via the REST API or `get_post_meta` / `update_post_meta`.

---

## `mediashield_video` CPT

### Core fields

| Meta Key | Type | Default | Description |
|----------|------|---------|-------------|
| `_ms_platform` | string | `self` | Hosting platform: `self`, `youtube`, `vimeo`, `bunny`, `wistia`. |
| `_ms_platform_video_id` | string | `''` | External platform video ID (extracted from the URL). |
| `_ms_source_url` | string | `''` | Direct video URL. For self-hosted videos this is the uploaded file URL; for platform videos it is the original pasted URL. |
| `_ms_protection_level` | string | `standard` | Per-video protection level override: `none`, `basic`, `standard`, `strict`. Empty string means "inherit `ms_default_protection`". |
| `_ms_access_role` | string | `''` | Required WordPress role slug. Empty string or `any` disables the role gate. |
| `_ms_duration` | int | `0` | Video duration in seconds. |

### Playback options

| Meta Key | Type | Values | Description |
|----------|------|--------|-------------|
| `_ms_autoplay` | string | `'1'` / `'0'` | Autoplay on page load. |
| `_ms_loop` | string | `'1'` / `'0'` | Loop playback. YouTube uses the `playlist=<id>` workaround so loop actually loops. |
| `_ms_muted` | string | `'1'` / `'0'` | Start muted. |
| `_ms_show_controls` | string | `'1'` / `'0'` | Show native player controls. Self-hosted only. |

### Player feature overrides (tri-state)

All tri-state keys use empty string = inherit global setting, `'on'` = force enabled, `'off'` = force disabled.

| Meta Key | Inherits from option | Description |
|----------|---------------------|-------------|
| `_ms_player_speed` | `ms_player_speed_control` | Playback rate menu. |
| `_ms_player_keyboard` | `ms_player_keyboard` | Keyboard shortcuts (Space/Arrow/M/F). |
| `_ms_player_resume` | `ms_player_resume` | Resume from last position. |
| `_ms_player_sticky` | `ms_player_sticky` | Sticky player on scroll. |
| `_ms_player_endscreen` | `ms_player_endscreen` | End-screen CTA overlay. |
| `_ms_player_endscreen_text` | `ms_player_endscreen_text` | CTA text (empty = inherit global). |
| `_ms_player_endscreen_url` | `ms_player_endscreen_url` | CTA URL (empty = inherit global). |

### Pro-managed meta

These keys are written by the Pro plugin. They are listed here for completeness; the authoritative list is in [hooks-filters-pro.md](hooks-filters-pro.md#post-meta-pro-managed).

| Meta Key | Set by | Description |
|----------|--------|-------------|
| `_ms_access_type` | Pro editor | Access gate type, e.g. `email_gate`. Read by `mediashield_player_access_type` filter. |
| `_ms_library_id` | BunnyStream driver | Bunny library ID. |
| `_ms_wistia_numeric_id` | WistiaApi driver | Wistia numeric ID. |
| `_ms_drm_enabled` | Pro DRM packager | Whether DRM is enabled for this video. |
| `_ms_drm_method` | Pro DRM packager | DRM method: `cloud_bunny`, `local_shaka`. |
| `_ms_drm_output_dir` | Pro DRM packager | Absolute path to the Shaka Packager DASH output directory. |
| `_ms_drm_packaged_at` | Pro DRM packager | Timestamp of last successful packaging. |
| `_ms_drm_packaging_status` | Pro DRM packager | Current packaging job status. |
| `_ms_drm_packaging_action_id` | Pro DRM packager | Action Scheduler job ID for async packaging. |

---

## `mediashield_playlist` CPT

| Meta Key | Type | Default | Description |
|----------|------|---------|-------------|
| `_ms_autoplay` | bool | `false` | Autoplay the next video after the current one ends. |
| `_ms_countdown` | int | `5` | Seconds to show the countdown overlay between videos. |
| `_ms_loop` | bool | `false` | Loop back to the first video after the last one ends. |
| `_ms_shuffle` | bool | `false` | Randomize playback order on each page load. |

Playlist items (video ordering) are stored in the `ms_playlist_items` table, not in post meta. See [database-tables.md](database-tables.md#ms_playlist_items).
