# Post Meta and Options Reference

**Included in Free** - Pro adds its own options and meta, documented in the Pro developer guide.

Everything MediaShield stores outside its own tables, verified against the 1.4.0 source.

## Video post meta (`mediashield_video`)

Registered with `register_post_meta()` and `show_in_rest`, so they round-trip through `/wp/v2/mediashield-videos`. Writing them needs `edit_posts`.

| Meta key | Type | Default | Purpose |
|----------|------|---------|---------|
| `_ms_platform` | string | empty | `self`, `youtube`, `vimeo`, `wistia` or `bunny`. |
| `_ms_platform_video_id` | string | empty | The host's video ID. For `self` it is the stored file name. |
| `_ms_source_url` | string | empty | Source or file URL, `esc_url_raw`. |
| `_ms_protection_level` | string | empty | `none`, `basic`, `standard`, `strict`, or a level an add-on registers. Empty follows `ms_default_protection`. |
| `_ms_access_role` | string | empty | A role slug. Empty or `any` means any logged-in user. |
| `_ms_duration` | integer | 0 | Length in seconds. 0 means unknown, and the player then reports it. |
| `_ms_stream_url` | string | empty | Direct stream URL (for example a Bunny HLS playlist). |
| `_ms_milestone_tags` | object | empty | `{ "<pct>": { "enabled": bool, "tag": string } }`. Percentages 1-100 become extra milestone thresholds. |

## Video post meta written by the editor

Saved from the video edit screen. Not registered for REST.

| Meta key | Values | Purpose |
|----------|--------|---------|
| `_ms_autoplay`, `_ms_loop`, `_ms_muted`, `_ms_show_controls` | `1` or `0` | Per-video player options (self-hosted and Bunny only). |
| `_ms_player_speed`, `_ms_player_keyboard`, `_ms_player_resume`, `_ms_player_sticky`, `_ms_player_endscreen`, `_ms_player_prevent_forward_seek` | `on`, `off`, or absent | Per-video override of the matching site setting. Absent follows the site. The list is filterable with `mediashield_player_overrides`. |
| `_ms_player_endscreen_text`, `_ms_player_endscreen_url` | string | Per-video end screen message and link. |
| `_ms_library_id` | string | Bunny library ID, read from the pasted URL. |
| `_ms_owned_file` | string | File name this video owns in `uploads/mediashield/`. An empty string means "claimed, no file". The stream endpoint serves only the owner's file. |
| `_ms_access_type` | string | Not written by Free. An extension sets it to declare its own gate; a non-empty value lets anonymous visitors reach `POST /session/start`. |

The video's featured image is the thumbnail (`CPT\Thumbnail` sideloads the platform thumbnail).

## Playlist post meta (`mediashield_playlist`)

Registered with `show_in_rest`; edited through `/wp/v2/mediashield-playlists/{id}`.

| Meta key | Type | Default | Purpose |
|----------|------|---------|---------|
| `_ms_autoplay` | boolean | false | Play the next video automatically. |
| `_ms_countdown` | integer | 5 | Seconds before the next video starts. |
| `_ms_loop` | boolean | false | Start again after the last video. |
| `_ms_shuffle` | boolean | false | Play in random order. |

## User meta

| Meta key | Purpose |
|----------|---------|
| `_ms_video_tags` | Map of `{video_id}_{pct}` to `{ video_id, pct, tag, tag_id, earned_at }`: the milestone tags a user has earned. Included in the GDPR export and removed by the eraser. |

## Options in the settings schema

`Core\Settings::schema()` declares 35 options. Read one with `Settings::get( $key )` (typed, falls back to the default). Defaults are seeded on activation and on every database version bump, and `PUT /settings` validates each value before saving.

| Option | Type | Default | Notes |
|--------|------|---------|-------|
| `ms_enabled` | boolean | `true` | Master switch for protection. |
| `ms_default_protection` | string | `standard` | One of none, basic, standard, strict. Other values are rejected. |
| `ms_require_login` | boolean | `true` | Login gate. Off lets guests start sessions. |
| `ms_watermark_opacity` | float | `0.5` | Clamped to 0-1. |
| `ms_watermark_color` | string | `#ffffff` | 3 or 6 digit hex, stored lower case. |
| `ms_watermark_swap_interval` | integer | `30` | Seconds, minimum 1. |
| `ms_allowed_domains` | string | (empty) | Hosts separated by spaces, commas or new lines; a pasted URL is reduced to its host. |
| `ms_max_concurrent_streams` | integer | `2` | Minimum 1. Logged-in users only. |
| `ms_session_retention_months` | integer | `0` | Clamped to 0-120. 0 keeps everything. |
| `ms_show_badge` | boolean | `true` | "Protected by MediaShield" badge. |
| `ms_login_overlay_text` | string | `Please log in to watch this video` | Translated while it is still the default. |
| `ms_login_button_text` | string | `Log In` | Same. |
| `ms_access_denied_text` | string | `You do not have access to this video` | Same. |
| `ms_player_speed_control` | boolean | `true` | Speed menu on self-hosted and Bunny. |
| `ms_player_prevent_forward_seek` | boolean | `false` | Blocks seeking past the furthest point watched. |
| `ms_player_sticky` | boolean | `false` | Floating player on scroll. |
| `ms_player_keyboard` | boolean | `true` | Keyboard shortcuts. |
| `ms_player_resume` | boolean | `true` | Resume prompt. |
| `ms_player_endscreen` | boolean | `false` | End screen overlay. |
| `ms_player_accent_color` | string | (empty) | Hex or empty. Empty follows the theme colour. |
| `ms_player_endscreen_text` | string | (empty) | End screen message. |
| `ms_player_endscreen_url` | string | (empty) | URL or empty; an invalid URL is rejected. |
| `ms_ads_enabled` | boolean | `true` | Master switch for ad breaks. |
| `ms_ads_require_full_view` | boolean | `false` | Hides Skip; overrides the skip delay. |
| `ms_ads_skip_after` | integer | `5` | Seconds, clamped to 0-60. |
| `ms_ads_preroll` | boolean | `true` | Pre-roll break. |
| `ms_ads_midroll_count` | integer | `3` | Clamped to 0-10. |
| `ms_ads_show_markers` | boolean | `true` | Break markers on the seek bar. |
| `ms_block_right_click` | boolean | `true` | Block the context menu. |
| `ms_block_keyboard` | boolean | `false` | Block Ctrl+S / Cmd+S. |
| `ms_hide_source` | boolean | `true` | Self-hosted only: serve through the signed stream endpoint. |
| `ms_detect_devtools` | boolean | `true` | Devtools detection beacon. |
| `ms_pause_on_devtools` | boolean | `false` | Pause and show the overlay. |
| `ms_devtools_title` | string | `Developer Tools Detected` | Overlay title. |
| `ms_devtools_message` | string | `Please close developer tools to continue watching this video.` | Overlay message. |

Options in the schema are deleted on uninstall automatically. Do not add your own option names there.

## Options outside the schema

| Option | Written by | Purpose |
|--------|-----------|---------|
| `ms_db_version` | Activator, Migrator | Installed database version (6 at 1.4.0). |
| `ms_wizard_completed` | `POST /wizard/complete` | Setup wizard finished. |
| `ms_activated_at` | Activator, admin notice | Activation time (Unix), used to delay the Pro notice by 7 days. |
| `ms_pro_notice_dismissed` | `ms_dismiss_pro_notice` AJAX | Pro upsell notice dismissed. |
| `ms_file_claims_pending` | Migrator, cleanup job | Highest video ID the 1.4.0 ownership backfill must still cover; deleted when the job finishes. |
| `ms_default_upload_target` | Pro (settings filter) | `auto`, `self`, or a driver name. Free reads it in `UploadManager::resolve_default_driver()`; without Pro it is unset and behaves as `auto`. |

## Transients

`ms_rate_<hash>` (heartbeat rate limit, 60 seconds), `ms_devtools_rl_<hash>` (devtools beacon limit, 1 hour) and `ms_activation_redirect` (setup wizard redirect, 30 seconds). Uninstall deletes every transient whose name starts with `ms_`.

## Capability

`upload_mediashield` lets a user upload video files from the admin and through `POST /upload/init`. Activation grants it to Administrator only; grant it to other roles with `$role->add_cap( 'upload_mediashield' )`. Everything else in the admin needs `manage_options`.
