# MediaShield -- Configuration Guide

All settings are managed from the **MediaShield > Settings** admin page, a React single-page application with auto-save.

---

## General Settings

| Setting | Option Key | Default | Description |
|---------|-----------|---------|-------------|
| Enable Protection | `ms_enabled` | `true` | Master toggle for all video protection features |
| Default Protection Level | `ms_default_protection` | `standard` | Baseline protection: `none`, `basic`, `standard`, `strict` |
| Require Login | `ms_require_login` | `false` | Force login before any video playback |
| Show Badge | `ms_show_badge` | `true` | Display "Protected by MediaShield" badge on player |

### Protection Levels

- **None** -- No protection applied, videos play as normal embeds.
- **Basic** -- Right-click disabled, source URL hidden.
- **Standard** -- Basic + dynamic watermark overlay + devtools detection.
- **Strict** -- Standard + keyboard shortcut blocking + fullscreen watermark.

---

## Watermark Settings

| Setting | Option Key | Default | Description |
|---------|-----------|---------|-------------|
| Opacity | `ms_watermark_opacity` | `0.3` | Watermark transparency (0.0 to 1.0) |
| Color | `ms_watermark_color` | `#ffffff` | Watermark text color (hex) |
| Swap Interval | `ms_watermark_swap_interval` | `30` | Seconds between watermark position changes |

The free watermark displays the logged-in user's display name and IP address. Pro adds 7 configurable fields (username, email, IP, user ID, timestamp, site name, custom text).

---

## Access Control

| Setting | Option Key | Default | Description |
|---------|-----------|---------|-------------|
| Max Concurrent Streams | `ms_max_concurrent_streams` | `2` | Maximum simultaneous video sessions per user |
| Allowed Domains | `ms_allowed_domains` | `''` | Comma-separated domain whitelist for embed access |
| Login Overlay Text | `ms_login_overlay_text` | `'Please log in to watch this video'` | Message shown to non-logged-in users |
| Login Button Text | `ms_login_button_text` | `'Log In'` | Login button label |
| Access Denied Text | `ms_access_denied_text` | `'You do not have permission to view this video'` | Shown when access is denied |

---

## Upload Settings

| Setting | Option Key | Default | Description |
|---------|-----------|---------|-------------|
| Max Upload Size | `ms_max_upload_size` | `500` | Maximum upload file size in MB |

Self-hosted uploads are stored in `wp-content/uploads/mediashield/` with `.htaccess` protection. A REST API proxy endpoint serves files after access verification.

---

## Custom URL Patterns

| Setting | Option Key | Default | Description |
|---------|-----------|---------|-------------|
| Custom URL Patterns | `ms_custom_url_patterns` | `''` | Additional URL patterns for the output buffer video detector |

Use this to match custom video embed URLs that MediaShield doesn't detect automatically. One pattern per line. Supports wildcards.

---

## Per-Video Settings

Each video (CPT `mediashield_video`) has its own meta settings that override global defaults:

| Meta Key | Description |
|----------|-------------|
| `_ms_platform` | Hosting platform (`self`, `youtube`, `vimeo`, `bunny`, `wistia`) |
| `_ms_platform_video_id` | External platform video ID |
| `_ms_source_url` | Direct video URL |
| `_ms_protection_level` | Per-video protection level override |
| `_ms_access_role` | Required WordPress role (empty = all roles) |
| `_ms_duration` | Video duration in seconds |

### Milestone Tags

Each video can have tags assigned per milestone percentage (25%, 50%, 75%, 100%). When a user reaches that milestone, the tag is assigned to their user meta. Configure these in the video editor sidebar.

### Player Options

Per-video player overrides (stored as post meta):

- Autoplay, loop, muted, show controls
- Speed control toggle (self-hosted only)
- Keyboard shortcuts toggle
- Sticky player toggle
- End screen CTA (title, description, button text, button URL)

---

## Settings REST API

Settings are managed via REST endpoints:

```
GET  /wp-json/mediashield/v1/settings   -- Retrieve all settings
PUT  /wp-json/mediashield/v1/settings   -- Update settings (partial updates supported)
```

Both endpoints require `manage_options` capability. Pro extends these via the `mediashield_settings_response` and `mediashield_settings_update` filters.
