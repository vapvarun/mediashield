# Settings Reference

All MediaShield free-plugin options are declared in `Core\Settings::schema()`. That array is the single source of truth for option name, scalar type, and default value. `Activator`, `REST\SettingsController`, `Player\Watermark`, `Access\AccessControl`, and `Core\Assets` all derive from it.

Adding a new option:
1. Add the entry to `Settings::schema()` (with type + default).
2. Bump `MEDIASHIELD_DB_VERSION` in `mediashield.php` so existing installs pick it up via `Migrator::run()` → `Settings::seed_defaults()`.
3. If exposed to JS, reference it from `Settings::frontend_config()`.

Pro settings are listed separately at the bottom of [hooks-filters-pro.md](hooks-filters-pro.md#pro-settings-wp_options).

---

## General

| Option Key | Type | Default | Description |
|-----------|------|---------|-------------|
| `ms_enabled` | bool | `true` | Master toggle. All protection and session tracking requires this to be on. |
| `ms_default_protection` | string | `standard` | Baseline protection level for all videos: `none`, `basic`, `standard`, `strict`. Used when a video has no per-video override. Values outside this enum are rejected. |
| `ms_require_login` | bool | `true` | Force login before any video playback. |
| `ms_show_badge` | bool | `true` | Display "Protected by MediaShield" badge on the player. |

---

## Watermark

| Option Key | Type | Default | Validation |
|-----------|------|---------|------------|
| `ms_watermark_opacity` | float | `0.5` | Clamped to `[0, 1]`. |
| `ms_watermark_color` | string | `#ffffff` | Hex regex `/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/`, normalised to lowercase. |
| `ms_watermark_swap_interval` | int | `30` | Minimum 1 second. |

---

## Access control

| Option Key | Type | Default | Validation |
|-----------|------|---------|------------|
| `ms_max_concurrent_streams` | int | `2` | Minimum 1. Enforced with row locking in `Access\SessionManager`. |
| `ms_allowed_domains` | string | `''` | Comma-separated domain whitelist for embed access. Empty string disables the check. |
| `ms_login_overlay_text` | string | `"Please log in to watch this video"` | Sanitised via `sanitize_textarea_field`. |
| `ms_login_button_text` | string | `"Log In"` | Sanitised via `sanitize_textarea_field`. |
| `ms_access_denied_text` | string | `"You do not have access to this video"` | Sanitised via `sanitize_textarea_field`. |

When `ms_allowed_domains` is non-empty, the request's HTTP Referer must match the whitelist or originate from the same host. Empty-Referer requests deny by default; opt in with `add_filter( 'mediashield_allow_empty_referer', '__return_true' )`.

---

## Upload

| Option Key | Type | Default | Validation |
|-----------|------|---------|------------|

Self-hosted uploads are stored in `wp-content/uploads/mediashield/` with `.htaccess` protection. A REST proxy endpoint serves files after access verification.

---

## Custom URL patterns

| Option Key | Type | Default | Description |
|-----------|------|---------|-------------|
| `ms_custom_url_patterns` | string | `''` | Additional URL patterns for the output buffer video detector. One pattern per line. Supports wildcards. |

---

## Player controls

Global defaults for the player UI. Each can be overridden per-video via the video editor sidebar (tri-state: on / off / inherit). Per-video meta keys mirror these names with the `_` prefix — see [post-meta-reference.md](post-meta-reference.md).

| Option Key | Type | Default | Description |
|-----------|------|---------|-------------|
| `ms_player_speed_control` | bool | `true` | Show the playback-rate menu (self-hosted / Bunny only). |
| `ms_player_sticky` | bool | `false` | Pin the player to a corner when scrolled off-screen during playback. |
| `ms_player_keyboard` | bool | `true` | Allow Space/Arrow/M/F shortcuts on the player. |
| `ms_player_resume` | bool | `true` | Resume from the last reached position when re-opening a video. |
| `ms_player_endscreen` | bool | `false` | Render the end-screen CTA when the video ends. |
| `ms_player_endscreen_text` | string | `''` | CTA text shown on the end-screen card (global fallback when a video doesn't set its own). |
| `ms_player_endscreen_url` | string | `''` | CTA URL. Empty allowed; otherwise must survive `esc_url_raw` (rejects `javascript:` URLs). |

---

## Protection controls

Server-side toggles consumed by `assets/js/protection.js` and `Player\Protection`.

| Option Key | Type | Default | Description |
|-----------|------|---------|-------------|
| `ms_block_right_click` | bool | `true` | Disable the browser context menu over protected players. |
| `ms_block_keyboard` | bool | `false` | Disable Ctrl/Cmd+S, Ctrl+U, F12 and similar capture shortcuts on player pages. |
| `ms_hide_source` | bool | `true` | Strip `src=` attributes from rendered video elements; the JS adapter reads them via `dataset` instead. |
| `ms_detect_devtools` | bool | `true` | Detect open browser devtools and dispatch a beacon to `/protection/devtools-event`. |
| `ms_pause_on_devtools` | bool | `false` | When devtools open, pause playback in addition to showing the overlay. |
| `ms_devtools_title` | string | `"Developer Tools Detected"` | Heading shown on the devtools overlay. |
| `ms_devtools_message` | string | `"Please close developer tools to continue watching this video."` | Body copy below the heading. |

---

## Wizard state

These options are set by the setup wizard and the plugin internals. You should not write to them directly.

| Option Key | Type | Notes |
|-----------|------|-------|
| `ms_wizard_completed` | bool | Flips to `true` when the setup wizard is finished. Controls the one-shot redirect. |
| `ms_activation_redirect` | bool | One-shot transient set on activation, consumed on the next admin page load. |
