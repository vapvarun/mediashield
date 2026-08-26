# Post Meta Reference

Every `_ms_*` post meta key for the `mediashield_video` and `mediashield_playlist` custom post types.

**Only some of these are registered.** `VideoPostType::register_meta()` and `PlaylistPostType::register_meta()` call `register_post_meta( ..., 'show_in_rest' => true )` for the keys marked **registered** below; those are readable and writable through core's CPT routes (`/wp-json/wp/v2/mediashield-videos/{id}`, `/wp-json/wp/v2/mediashield-playlists/{id}`). Everything else is plain post meta written by the classic meta box's `$_POST` handler (`VideoPostType::save_meta_box()`) and is **not** exposed to REST - `get_post_meta()` / `update_post_meta()` is the only way in for those.

Registered scalar meta uses `auth_callback => current_user_can( 'edit_posts' )` and a `sanitize_callback` of `sanitize_text_field` for strings, `absint` for integers, `rest_sanitize_boolean` for playlist booleans.

---

## `mediashield_video` CPT

### Core fields (registered)

| Meta Key | Type | Default | Description |
|----------|------|---------|-------------|
| `_ms_platform` | string | `self` | Hosting platform: `self`, `youtube`, `vimeo`, `bunny`, `wistia`. |
| `_ms_platform_video_id` | string | `''` | External platform video ID (extracted from the URL). |
| `_ms_source_url` | string | `''` | Direct video URL. For self-hosted videos this is the uploaded file URL; for platform videos it is the original pasted URL. |
| `_ms_stream_url` | string | `''` | Resolved playback URL when it differs from the source (Pro's Bunny driver writes signed CDN URLs here). Feeds the `mediashield_video_stream_url` filter. |
| `_ms_protection_level` | string | `''` | Per-video protection level: `none`, `basic`, `standard`, `strict`, plus any slug added via `mediashield_protection_levels` (Pro adds `drm`). **The default is the empty string, meaning "inherit `ms_default_protection`"** - a literal default here would mask the global setting. |
| `_ms_access_role` | string | `''` | Required WordPress role slug. Empty string or `any` disables the role gate. Enforced by free's `AccessControl::check_role()`, and again by Pro's `Access\RoleAccess` at priority 20. |
| `_ms_duration` | int | `0` | Video duration in seconds. |

### Milestone tags (registered, object schema)

| Meta Key | Type | Default | Description |
|----------|------|---------|-------------|
| `_ms_milestone_tags` | object | `[]` | Map of `{ "<pct>": { enabled: bool, tag: string } }`. Registered with a custom REST schema and `sanitize_milestone_tags()` rather than the scalar loop, so the admin SPA can read and write it. Entries outside 1-100, or with an empty `tag`, are dropped. |

### Playback options (not registered - meta box only)

Stored as the strings `'1'` and `'0'`. A **missing/empty** value is a third state meaning "never saved, use the player adapter's own default" - `Renderer` only emits the `data-*` attribute when the stored value is exactly `'1'` or `'0'`.

| Meta Key | Values | Description |
|----------|--------|-------------|
| `_ms_autoplay` | `'1'` / `'0'` / unset | Autoplay on page load. |
| `_ms_loop` | `'1'` / `'0'` / unset | Loop playback. YouTube uses the `playlist=<id>` workaround so loop actually loops. |
| `_ms_muted` | `'1'` / `'0'` / unset | Start muted. |
| `_ms_show_controls` | `'1'` / `'0'` / unset | Show native player controls. Self-hosted only. |

### Player feature overrides (not registered - meta box only)

Tri-state: the key is **deleted** to mean "inherit the global setting", `'on'` forces enabled, `'off'` forces disabled. Anything else submitted is treated as inherit.

| Meta Key | Inherits from option | Description |
|----------|---------------------|-------------|
| `_ms_player_speed` | `ms_player_speed_control` | Playback rate menu. |
| `_ms_player_keyboard` | `ms_player_keyboard` | Keyboard shortcuts (Space/Arrow/M/F). |
| `_ms_player_resume` | `ms_player_resume` | Resume from last position. |
| `_ms_player_sticky` | `ms_player_sticky` | Sticky player on scroll. |
| `_ms_player_endscreen` | `ms_player_endscreen` | End-screen CTA overlay. |
| `_ms_player_prevent_forward_seek` | `ms_player_prevent_forward_seek` | Clamp forward seeking to the furthest point watched. |

The two end-screen text fields are plain strings, not tri-state - they are deleted when submitted empty, and an empty value inherits the global:

| Meta Key | Inherits from option | Description |
|----------|---------------------|-------------|
| `_ms_player_endscreen_text` | `ms_player_endscreen_text` | CTA text. Sanitised with `sanitize_text_field`. |
| `_ms_player_endscreen_url` | `ms_player_endscreen_url` | CTA URL. Sanitised with `esc_url_raw`. |

All six overrides come from one map, `Player\FeatureOverrides::map()`, which the edit screen, the save handler, `Player\Renderer` and `Player\PlayerWrapper` all read. Add a feature there and it appears in every one of them.

`_ms_player_prevent_forward_seek` was added in 1.3.0. It was the only player feature without a per-video override, because the map behind these was written out four times by hand and the other three copies were never updated. The `mediashield_player_overrides` filter lets an add-on register its own.

### Extension seam

| Meta Key | Written by | Description |
|----------|-----------|-------------|
| `_ms_access_type` | Third-party extensions | Free reads this in two places and never writes it: `Player\Renderer` emits it as `data-access-type` on the player container (via the `mediashield_player_access_type` filter), and `SessionController` uses a non-empty value as the default reason to permit an anonymous `/session/start`. It is **not** set by Pro - no Pro code references the key. |

### Pro-managed meta

Written by the Pro plugin. Listed here for completeness; the authoritative list is in [hooks-filters-pro.md](hooks-filters-pro.md#post-meta-pro-managed). None of these are registered for REST.

| Meta Key | Set by | Description |
|----------|--------|-------------|
| `_ms_library_id` | BunnyStream driver | Bunny library ID. |
| `_ms_wistia_numeric_id` | WistiaApi driver | Wistia numeric ID. |
| `_ms_bunny_encode_status` | BunnyWebhookController | Latest encode state reported by the Bunny webhook. |
| `_ms_drm_enabled` | _(writer removed in 1.3.0)_ | Whether DRM packaging has completed for this video. Note Pro's player-type override reads `_ms_protection_level === 'drm'`, not this key. |
| `_ms_drm_method` | _(writer removed in 1.3.0)_ | DRM method used: `cloud_bunny` or `local_shaka`. |
| `_ms_drm_output_dir` | _(writer removed in 1.3.0)_ | Absolute path to the Shaka Packager DASH output directory (local method only). |
| `_ms_drm_packaged_at` | _(writer removed in 1.3.0)_ | UTC timestamp of last successful packaging. |
| `_ms_linked_lesson` | LMS\LMSMetaBox | Lesson/topic post ID this video is bound to. Validated against the owning adapter's `owns_post()`. |
| `_ms_lms_require_enrollment` | LMS\LMSMetaBox | Per-video override of `ms_lms_enrollment_check`. |
| `_ms_lms_complete_pct` | LMS\LMSMetaBox | Per-video override of `ms_lms_complete_pct`. |
| `_ms_ad_mode` | Ads\VideoAdsMetaBox | How ads run on this video (inherit / off / custom). |
| `_ms_ad_ids` | Ads\VideoAdsMetaBox | Ad post IDs selected for this video. |
| `_ms_ad_preroll` | Ads\VideoAdsMetaBox | Per-video pre-roll override. |
| `_ms_ad_postroll` | Ads\VideoAdsMetaBox | Per-video post-roll override. |
| `_ms_ad_midroll_count` | Ads\VideoAdsMetaBox | Per-video mid-roll count override. |
| `_ms_ad_plan_custom` | Ads\VideoAdsMetaBox | Explicit break positions when the plan is hand-authored. |

`_ms_meta_nonce`, `_ms_lms_nonce` and `_ms_ad_nonce` are meta-box nonce field names, not stored meta.

---

## `mediashield_playlist` CPT

All four are registered and REST-exposed.

| Meta Key | Type | Default | Description |
|----------|------|---------|-------------|
| `_ms_autoplay` | bool | `false` | Autoplay the next video after the current one ends. |
| `_ms_countdown` | int | `5` | Seconds to show the countdown overlay between videos. |
| `_ms_loop` | bool | `false` | Loop back to the first video after the last one ends. |
| `_ms_shuffle` | bool | `false` | Randomize playback order on each page load. |

Playlist items (video ordering) are stored in the `ms_playlist_items` table, not in post meta. See [database-tables.md](database-tables.md#ms_playlist_items).

---

## User meta

Not post meta, but the one `_ms_*` key that lives on users and is easy to mistake for a post meta key:

| Meta Key | Description |
|----------|-------------|
| `_ms_video_tags` | A single serialised map per user, keyed `<video_id>_<pct>`, recording milestone tag awards. `Cron\Cleanup::handle_video_delete()` walks it and strips entries pointing at a deleted video so orphans cannot accumulate. |
