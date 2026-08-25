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
| `ms_require_login` | bool | `true` | Force login before any video playback. Honoured on **both** sides since 1.3.0 - it is emitted to the client as `requireLogin` and relaxes the `/session/start` permission callback when off. Before 1.3.0 turning it off had no effect: the client gated on `isLoggedIn` alone and the REST callback refused every anonymous request regardless. |
| `ms_show_badge` | bool | `true` | Display "Protected by MediaShield" badge on the player. |
| `ms_session_retention_months` | int | `0` | Months of watch history to keep in the live table before moving rows to `ms_watch_sessions_archive`. **`0` = keep everything, and that is the default on purpose.** Clamped 0-120. Before 1.3.0 archiving ran unconditionally at 24 months into a table no read path queried, so every report silently lost history at month 25. Rows already archived are walked back on upgrade by `ms_restore_archived_sessions`. |

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

Self-hosted uploads are stored in `wp-content/uploads/mediashield/`. A REST proxy endpoint (`/stream/{id}`) serves files after access verification, and that is the only path the player uses.

MediaShield also writes an `.htaccess` deny rule into that directory, but **treat it as best-effort, not protection**: `.htaccess` is an Apache feature and nginx ignores it entirely, so on nginx the files are served directly with no access check. Stored filenames carry a random token so addresses cannot be derived from a video title, and the Site Health check in `Admin\HealthCheck` fetches a real file to report whether the server is actually refusing them. Files stay inside `uploads/` because that is the only directory a distributed plugin can rely on being writable, backed up and multisite-aware.

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
