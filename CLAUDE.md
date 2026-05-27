# MediaShield - Developer Reference

> **Product Scope (read before triaging any feature / UX / QA card):** [`plan/PRODUCT_SCOPE.md`](plan/PRODUCT_SCOPE.md). MediaShield is a **player** for cloud video in member-gated courses/lessons — admin sees all analytics, member sees only their own watch experience. Share buttons, public view-count badges, and other distribution affordances are **wontfix** because they actively work against the gated-content model. Confirmed by the 2026-05-23 audit.

> **READ FIRST:** Load [`audit/manifest.summary.json`](audit/manifest.summary.json) first (~1.4 KB index) — drill into [`audit/manifest.json`](audit/manifest.json) only when a task touches a specific category. Manifest v2.2 — 23 REST routes (`mediashield/v1`, +`/wizard/complete`), 1 AJAX (`ms_dismiss_pro_notice`), 2 admin pages, 6 tables, 3 blocks, **3 shortcodes**, 2 cron jobs, 2 CPTs, 31 hooks (14 actions + 17 filters), 34 services, 24 settings, 1 capability (`upload_mediashield`), 1 WP-CLI command (`mediashield scale`). ✅ **Release-ready** per wppqa baseline 2026-05-26 — see [`audit/wppqa-baseline-2026-05-26/SUMMARY.md`](audit/wppqa-baseline-2026-05-26/SUMMARY.md) (all clean; the 2026-05-11 HIGH `nonce-no-cap` at `Menu.php:174` and the 2 MEDIUM inline-onclick findings on `VideoPostType.php` have all been resolved). See also [`audit/FEATURE_AUDIT.md`](audit/FEATURE_AUDIT.md), [`audit/CODE_FLOWS.md`](audit/CODE_FLOWS.md), [`audit/ROLE_MATRIX.md`](audit/ROLE_MATRIX.md), [`audit/derived/cross-plugin-coupling.json`](audit/derived/cross-plugin-coupling.json). Open `audit/graph.html` (`cd audit && python3 -m http.server 8765`) for an interactive Cytoscape view. Refresh via `/wp-plugin-onboard --refresh` after non-trivial changes.

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
    Migrator.php             DB version migration runner — calls Settings::seed_defaults() on every version bump. v3 backfills legacy `_ms_video_tags` user meta entries into `ms_tags` so installs that pre-date the milestone-tag unification gain tag rows without manual intervention.
    Settings.php             Single source of truth for free-plugin options — schema (type+default), get/get_all/seed_defaults/sanitize, frontend_config()
    Assets.php               Frontend JS/CSS enqueue — pulls localized config from Settings::frontend_config()
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
    AnalyticsController.php  /analytics/overview, milestones, users, my-videos. Overview payload includes `recent_milestones` + `site_timezone`; date grouping uses MySQL CONVERT_TZ() so daily counts honour the WP site timezone.
    ProtectionController.php /protection/devtools-event beacon
    StreamController.php     /stream/{video_id} signed-URL handoff
  Access/
    AccessControl.php        Permission checks — login gate, per-video `_ms_access_role`, allowed-domain whitelist (default-deny empty referer + `mediashield_allow_empty_referer` filter), `mediashield_can_watch` filter chain. Consumed by SessionController and StreamController.
    SessionManager.php       HMAC token generation, concurrent stream limits
  Block/
    VideoBlock.php           mediashield/video Gutenberg block
    PlaylistBlock.php        mediashield/playlist Gutenberg block
    MyVideosBlock.php        mediashield/my-videos block + shortcode
    Shortcode.php            [mediashield id=X] shortcode
  Player/
    PlayerWrapper.php        Output buffer video detection + wrapping. Sticky-player observer watches a 1px sentinel placed before the player (not the player itself) to avoid the position:fixed feedback flicker.
    Protection.php           Right-click disable, devtools detection
    Watermark.php            Dynamic watermark overlay
    Renderer.php             Shared .ms-protected-player container output for a single video (used by [mediashield] shortcode, video block, single template). Validates the CPT + source URLs and enqueues assets only when output will be produced. Emits per-video playback options (`data-autoplay`/`data-loop`/`data-muted`/`data-controls`) from `_ms_autoplay`/`_ms_loop`/`_ms_muted`/`_ms_show_controls` meta.
    PlaylistRenderer.php     Shared playlist player output (used by [mediashield_playlist] shortcode and the playlist Gutenberg block). Validates the playlist CPT + items and enqueues assets only when output will be produced.
  Milestones/
    MilestoneTracker.php     25/50/75/100% completion tracking. When a milestone with a configured tag fires, the tag is promoted into the unified `ms_tags` dictionary (via `TagManager::ensure()`) and the video↔tag link recorded in `ms_video_tags`. The per-user earn record stays in `_ms_video_tags` user meta but now carries `tag_id` alongside the display string for cross-reference.
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

### Protection
| Method | Route | Description |
|--------|-------|-------------|
| POST | `/protection/devtools-event` | Beacon endpoint for client-side devtools/right-click events (permission: `beacon_permission`) |

### Stream
| Method | Route | Description |
|--------|-------|-------------|
| GET | `/stream/{video_id}` | Authenticated streaming handoff (permission: `stream_permissions_check`); used to gate self-hosted media URLs |

---

## Gutenberg Blocks

| Block | Slug | Description |
|-------|------|-------------|
| Video | `mediashield/video` | Embed protected video with player wrapper |
| Playlist | `mediashield/playlist` | Playlist with autoplay/countdown |
| My Videos | `mediashield/my-videos` | Logged-in user's watch history |

---

## Shortcodes

- `[mediashield id=X]` -- Render protected video player for video CPT ID X (delegates to `Player\Renderer`)
- `[mediashield_playlist id=Y]` -- Render protected playlist for playlist CPT ID Y (delegates to `Player\PlaylistRenderer`)
- `[mediashield_my_videos]` -- Render current user's watched videos grid

Both video and playlist shortcodes return empty output and skip asset enqueue for invalid/missing/non-published IDs.

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
| `mediashield_upload_started` | $driver, $file_path, $options | Upload driver invoked, before upload runs |
| `mediashield_upload_complete` | $video_id, $driver_name, $result | Upload finished successfully |
| `mediashield_upload_failed` | $driver, $error, $options | Upload driver returned an error |
| `mediashield_privacy_before_erase` | $email, $user, $page, $counters | Fired before per-user GDPR erase begins so extensions can log/cleanup adjacent data |

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
| `mediashield_allow_empty_referer` | $allow | When the allowed-domain whitelist is active, decides whether to permit playback for requests with no Referer header. Default `false` (deny). |
| `mediashield_frontend_config` | $config | Frontend localized config payload emitted as `window.mediashieldConfig`. Pro hooks this to inject premium player options. |
| `mediashield_privacy_erase_result` | $result, $email, $user, $page | Final GDPR erase report shape — extensions can append `items_removed` or `messages` before WordPress consumes the result |

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

All free-plugin options are declared in **`Core\Settings::schema()`** — that array is the single source of truth for option name, scalar type, and default value. Activator, REST controller, Watermark, AccessControl, and Assets all derive from it.

Adding a new option:
1. Add the entry to `Settings::schema()` (with type + default).
2. Bump `MEDIASHIELD_DB_VERSION` in `mediashield.php` so existing installs pick it up via `Migrator::run()` → `Settings::seed_defaults()`.
3. (If exposed to JS) reference it from `Settings::frontend_config()`.

Schema covers: `ms_enabled`, `ms_default_protection`, `ms_require_login`, `ms_watermark_opacity`, `ms_watermark_color`, `ms_watermark_swap_interval`, `ms_allowed_domains`, `ms_max_concurrent_streams`, `ms_custom_url_patterns`, `ms_show_badge`, `ms_max_upload_size`, `ms_login_overlay_text`, `ms_login_button_text`, `ms_access_denied_text`, `ms_player_speed_control`, `ms_player_sticky`, `ms_player_keyboard`, `ms_player_resume`, `ms_player_endscreen`, `ms_player_endscreen_text`, `ms_player_endscreen_url`, `ms_block_right_click`, `ms_block_keyboard`, `ms_hide_source`, `ms_detect_devtools`, `ms_pause_on_devtools`, `ms_devtools_title`, `ms_devtools_message`.

Pro extends both the GET (`mediashield_settings_response`) and PUT (`mediashield_settings_update`) paths via filter callbacks; Pro callbacks `unset()` their own keys from `$data` so the free SettingsController loop ignores them.

---

## Local CI pipeline

`bin/local-ci.sh` is the single entry point for every quality + correctness check. It runs without GitHub Actions or any external infrastructure — designed to be runnable from a fresh clone.

```bash
composer ci                      # full gate (static + arch + manifest + journeys + scale)
composer ci:quick                # lint + coding-rules only (~10s)
composer ci:no-journeys          # skip browser-dependent stages
composer install-hooks           # install bin/git-hooks/pre-push (one-time)
SKIP_LOCAL_CI=1 git push         # emergency bypass
```

Stages (each prints a tag like `1.1`, `2.2` so you can scroll back to the first red):

| Stage | What | When skipped |
|---|---|---|
| 1.1 | PHP lint (every changed source file) | never |
| 1.2 | WPCS via `composer phpcs` | `--quick` mode, or vendor missing |
| 1.3 | PHPStan via `composer phpstan` | `--quick` mode, or vendor missing |
| 2.1 | `bin/coding-rules-check.sh` | never |
| 2.2 | `bin/architecture-checks.sh` (parses `plan/INVARIANTS.yaml`) | never |
| 3.1 | Manifest freshness (`audit/manifest.json` < 30 days) | `--quick` mode |
| 4.1 | `bin/run-journeys.sh` against `$LOCAL_CI_SITE_URL` (default `http://mediashield.local`) | `--quick` / `--no-journeys`, or site unreachable |
| 5.1 | `wp mediashield scale benchmark` | `wp` not on PATH or command not registered |

### Architecture invariants (`plan/INVARIANTS.yaml`)

The canonical list of architectural rules lives at `plan/INVARIANTS.yaml`. `bin/architecture-checks.sh` parses it at runtime — adding an invariant there AND a matching `check_<id>` function in the script is a contract. The script refuses to run when they drift apart.

MediaShield-specific deviations from the universal U-set:
- **U1** — adapted: enforces "no `$wpdb` in REST/Admin/Block/Player layers" (allowed in `DB/`, `Access/`, `Milestones/`, `Tags/`, `Cron/`, `Privacy/`, `Upload/`, `CPT/`).
- **U2** — N/A. MediaShield uses CPTs + per-feature data accessors instead of an abstract Model base. `gate_function: null`, status `manual-review`. See `plan/INVARIANTS.yaml` comment block for the rationale.
- **U3** — adapted: enforces controllers under `includes/REST/*Controller.php` live in `MediaShield\REST` namespace and extend `WP_REST_Controller` (no Base_Controller exists yet).

Pre-existing `$wpdb` violations should be moved to the `baseline:` array in `plan/INVARIANTS.yaml` with an `eta` for fix, not silently allowed.

---

## Customer journeys

End-to-end customer flows live under `audit/journeys/<role>/`. Each is a self-contained markdown file with YAML frontmatter (`journey`, `priority`, `roles`, `covers`, `prerequisites`). A journey-aware agent (Playwright + curl + mysql) reads the file and executes every step against a live site.

```bash
composer journeys                 # all journeys (default site $LOCAL_CI_SITE_URL)
composer journeys:critical        # only priority: critical
composer journeys:dry-run         # list what would run, no exec
bash bin/run-journeys.sh --site http://staging.local --only customer/watch
```

Results land in `audit/journey-runs/{YYYY-MM-DD-HHMM}/{slug}.json` plus an aggregated `summary.json`. The local-CI gate stage 4.1 fails the run if the most recent prior run had any FAIL journeys (regression sentinel).

Critical journeys shipped 2026-05-11:
- `customer/watch-protected-video.md` — full happy path: log in → wrapper renders → watermark → heartbeat → 25% milestone fires.
- `admin/upload-and-publish-video.md` — upload via `/upload/init` → CPT publishes → renders via shortcode AND block.
- `security/concurrent-stream-limit.md` — third concurrent stream is denied with `mediashield_concurrent_limit_reached` hook fired.

When a new customer-facing feature lands, add one journey per feature. When a bug is fixed that wasn't journey-covered, add one as a regression sentinel. See `audit/journeys/README.md` for schema + execution model + when NOT to write a journey.

---

## Scale benchmark

`src/CLI/ScaleCommand.php` ships three WP-CLI commands that gate hot-path query budgets against a production-shape dataset.

```bash
composer scale:seed       # 10000 users × 10 sessions × 4 milestones (~400k rows)
composer scale:bench      # times 5 hot paths against budgets, exits non-zero on overage
composer scale:teardown   # drops every synthetic row (uid >= 1_000_000)
```

Hot paths gated:

| Key | What it measures | Budget |
|---|---|---|
| `session_heartbeat` | UPDATE `wp_ms_watch_sessions` SET last_heartbeat WHERE id=PK | 5ms |
| `access_can_watch` | COUNT(*) active sessions for user (concurrent-limit) | 5ms |
| `stream_signed_lookup` | SELECT row by `(session_token, video_id)` | 30ms |
| `milestone_record` | INSERT IGNORE on UNIQUE `(video_id, user_id, pct)` | 20ms |
| `analytics_overview` | totals + distinct users + completed-100% count | 30ms |

Budgets are calibrated for a typical Local-by-Flywheel MySQL 8 box. If a query exceeds budget, `composer scale:bench` exits 1 and the local-CI gate (stage 5.1) blocks the push. Teardown is idempotent — synthetic rows are keyed by `user_id >= 1000000` and a CPT title literal.

Tighten budgets when refactoring a hot path. Loosen only with a written rationale in `plan/INVARIANTS.yaml` (add a `B`-group invariant or a release note in `plan/RELEASE_FIX_PLAN.md`).

---

## Documentation

> **Doc audience split (1.1.0):** Customer-facing docs live in `docs/free/` and `docs/pro/` — UI labels, plain language, no option keys / class names / REST paths. Developer reference lives in `docs/developer/` — option keys, REST endpoints, hooks/filters, DB schema, post meta, cron, DRM internals, extension architecture. **Never leak developer content into customer docs.** When extracting new dev reference, add it under `docs/developer/` and link from the relevant customer doc's footer.

### Customer-Facing Docs (`docs/free/`, `docs/pro/`)

| Document | Path | Description |
|----------|------|-------------|
| Getting Started | `docs/free/getting-started.md` | Day 1 walkthrough — activation, wizard, first video, embed, protection, analytics, milestones, playlists |
| Installation | `docs/free/installation.md` | Requirements, install steps, first-time setup |
| Configuration | `docs/free/configuration.md` | Every settings page explained with UI labels, defaults, and when to change |
| Shortcodes & Blocks | `docs/free/shortcodes-blocks.md` | Shortcode attributes + Gutenberg block usage |
| FAQ | `docs/free/faq.md` | Common questions and troubleshooting |
| Troubleshooting | `docs/free/troubleshooting.md` | Symptom-first triage for playback, tracking, cache issues |
| Migration Guide | `docs/free/migration-guide.md` | Moving from Presto, WP-Vimeo, Easy Video Player, etc. |
| Protection Philosophy | `docs/free/protection-philosophy.md` | Honest threat model — what protection can and can't do |
| Hooks & Filters | `docs/free/hooks-filters.md` | Redirect stub → `docs/developer/hooks-filters-free.md` |
| Pro: Getting Started | `docs/pro/getting-started.md` | Pro activation, license, first steps |
| Pro: Platform Connections | `docs/pro/platform-connections.md` | Connect Bunny, YouTube, Vimeo, Wistia (customer setup) |
| Pro: DRM Setup | `docs/pro/drm-setup.md` | Three DRM methods, when to use each, glossary |
| Pro: DRM Types Explained | `docs/pro/drm-types-explained.md` | ClearKey vs Widevine L1 vs FairPlay — buyer-facing tradeoffs |
| Pro: Analytics | `docs/pro/analytics.md` | Heatmaps, realtime, alerts, export — what you see and how to read it |
| Pro: License Management | `docs/pro/license-management.md` | License unlocks updates + support; never gates features |
| Pro: Email Gate | `docs/pro/email-gate.md` | Email-capture gate setup, scopes, integrations |
| Pro: Hooks & Filters | `docs/pro/hooks-filters-pro.md` | Redirect stub → `docs/developer/hooks-filters-pro.md` |

### Developer Reference (`docs/developer/`)

| Document | Path | Description |
|----------|------|-------------|
| Developer README | `docs/developer/README.md` | Entry point — explains who these docs are for, links each reference |
| Hooks & Filters (Free) | `docs/developer/hooks-filters-free.md` | Every free-plugin hook: params, example, use case, priority chain |
| Hooks & Filters (Pro) | `docs/developer/hooks-filters-pro.md` | Pro hooks fired + free hooks Pro consumes; deprecation shims documented |
| Settings Reference | `docs/developer/settings-reference.md` | Every `ms_*` option key — type, default, validator, sanitizer, where used |
| REST API | `docs/developer/rest-api.md` | All `mediashield/v1` + `mediashield-pro/v1` endpoints — method, auth, request, response |
| Database Tables | `docs/developer/database-tables.md` | Schema for all 14 tables (6 free + 8 pro) — columns, indexes, cleanup behaviour |
| Post Meta Reference | `docs/developer/post-meta-reference.md` | Every `_ms_*` post meta key on the `mediashield_video` + `mediashield_playlist` CPTs |
| Extension Architecture | `docs/developer/extension-architecture.md` | Filter chain order, priorities, `mediashield_lms_adapters`, SlotFill, upload drivers, Free/Pro boot |
| Cron & Background Jobs | `docs/developer/cron-and-background-jobs.md` | Every cron hook + Action Scheduler job — interval, purpose, debug |
| DRM Internals | `docs/developer/drm-internals.md` | `DRM\KeyServer` + `DRM\Packager` mechanics; key storage; ClearKey license flow |

### Planning Docs (`plan/`)

| Document | Path | Description |
|----------|------|-------------|
| Release Fix Plan | `plan/RELEASE_FIX_PLAN.md` | Critical/high issues found during audit |
| QA Checklist (Free) | `plan/QA_CHECKLIST_FREE.md` | Manual QA checklist for free plugin |
| QA Checklist (Pro) | `plan/QA_CHECKLIST_PRO.md` | Manual QA checklist for pro plugin |
| QA Functional Checklist | `plan/QA_FUNCTIONAL_CHECKLIST.md` | Unified functional QA grouped by feature area |
| QA Buyer Expectations | `plan/QA_BUYER_EXPECTATIONS.md` | Buyer-perspective acceptance criteria |
| QA Release Prospect | `plan/QA_RELEASE_PROSPECT.md` | Pre-release prospect checklist |
| Design Spec | `plan/DESIGN_SPEC.md` | Original design specification |
| Design Spec v2 | `plan/DESIGN_SPEC_v2.md` | Updated design specification |
| Implementation Plan | `plan/IMPLEMENTATION_PLAN.md` | Original implementation plan |
| Implementation Plan v2 | `plan/IMPLEMENTATION_PLAN_v2.md` | Updated implementation plan |
| 1.1.0 Player Config Filter | `plan/1.1.0-player-config-filter.md` | FREE-slice plan for `mediashield_player_config` filter + stable JS event contract |
| Architecture | `plan/architecture/PLUGIN_ARCHITECTURE.md` | High-level architecture, module graph |
| Pro CLAUDE.md | `plan/PRO_CLAUDE.md` | Pro plugin developer reference (moved from pro) |
| Pro Docs | `plan/pro-docs/` | Pro plugin planning docs (moved from pro) |

> The canonical feature audit and code-flow maps now live in the top-level `audit/` directory (`audit/FEATURE_AUDIT.md`, `audit/CODE_FLOWS.md`, `audit/manifest.json`, `audit/ROLE_MATRIX.md`, `audit/graph.html`), not under `plan/audit/`.

---

## Recent Changes

| Date | Files | Summary |
|------|-------|---------|
| 2026-05-11 | audit/manifest.json, audit/manifest.summary.json, audit/derived/, audit/wppqa-baseline-2026-05-11/, audit/FEATURE_AUDIT.md | wp-plugin-onboard refresh: v1→v2.2 manifest (added category_sources, static_analysis, summary, derived/cross-plugin-coupling), wppqa baseline (1 HIGH nonce-no-cap blocking release), Phase 4.7 local-CI scaffold (separate commit) |
| 2026-05-11 | plan/1.1.0-player-config-filter.md | FREE-slice plan — `mediashield_player_config` filter + stable JS event contract for 1.1.0 |
| 2026-05-05 | audit/manifest.json, audit/graph.html, audit/ROLE_MATRIX.md | Canonical audit onboard — manifest, Cytoscape graph, role matrix |
| 2026-05-03 | dist/, languages/, Gruntfile.js | Plain-English docs + WPCS auto-fixes + Grunt dist excludes |
| 2026-05-01 | .github/, audit/ | Moved CI from GitHub Actions to local-only |
| 2026-04-30 | src/admin/pages/Dashboard.js | Dashboard onboarding hero for first-time admins |
| 2026-04-28 | docs/free/, docs/pro/ | Release must-haves — protection philosophy, troubleshooting, license, migration, expanded FAQ |
| 2026-04-27 | includes/Player/Protection.php, includes/REST/ProtectionController.php | DevTools detection + honest DRM terminology + release QA |
| 2026-04-26 | includes/Cron/Cleanup.php | WP-Cron fallback for session cleanup + WP 6.8+ component deprecations |
| 2026-04-25 | plan/QA_FUNCTIONAL_CHECKLIST.md | Unified functional QA checklist grouped by feature area |
| 2026-04-24 | includes/, src/admin/ | Accessibility + PHPStan fixes from QA audit |
| 2026-04-23 | includes/Core/, src/edd-sdk/ | EDD Software Licensing SDK for free plugin auto-updates |
| 2026-04-22 | src/admin/wizard/, src/admin/components/ | Setup wizard UX + dashicon overlap fixes |
| 2026-04-20 | includes/REST/SettingsController.php | Security — fix arbitrary `ms_` option write + remove `source_url` from session response |
| 2026-04-18 | .github/workflows/, phpcs.xml, phpstan.neon | CI — PHP lint, WPCS, PHPStan L5, PHPUnit (later moved local) |
| 2026-04-15 | languages/, Gruntfile.js, package.json | i18n + Grunt distribution — `.pot` files, `npm run dist` |
| 2026-04-12 | includes/, src/admin/ | Pro upsell system — 7 contextual touchpoints |
| 2026-04-10 | includes/Block/, src/admin/pages/Videos.js | Per-video player control overrides (global + per-video) |
| 2026-04-01 | docs/, plan/, README.md, readme.txt | Docs reorg: user-facing docs in docs/, planning in plan/, QA checklists, README rewrite |
| 2026-04-01 | audit/, plan/architecture/ | Full onboard: feature audit, code flow maps, architecture docs |
| 2026-03-30 | Initial | v1.0.0 -- Full plugin implementation |

---

## Basecamp — MediaShield Project

The MediaShield Basecamp project is the source of truth for bugs, UI issues, and the dev/test pipeline. IDs below are stable — use them directly with the `basecamp-mcp-server` rather than re-searching each time.

- **Account ID:** `5798509`
- **Project ID (bucket):** `47045023`
- **App URL:** https://3.basecamp.com/5798509/projects/47045023
- **Card Table ID:** `9827871758` (`Kanban::Board`)

### Card Table Columns

Snapshot taken 2026-05-11. `cards_count` is point-in-time — re-fetch via `mcp__basecamp__basecamp_list_cards` (or `GET cards_url`) before acting.

| Column Title | Column ID | Type | Pos | Color | Cards |
|---|---|---|---|---|---|
| Triage | `9827871761` | `Kanban::Triage` | (fixed) | — | 0 |
| Not now | `9827871762` | `Kanban::NotNowColumn` | (fixed) | — | 0 |
| Suggestion | `9827871768` | `Kanban::Column` | 1 | — | 5 |
| Bugs | `9827871763` | `Kanban::Column` | 3 | purple | 48 |
| UI issues | `9829137292` | `Kanban::Column` | 3 | — | 4 |
| Ready for Development | `9827871766` | `Kanban::Column` | 4 | — | 0 |
| In Development | `9827872480` | `Kanban::Column` | 5 | — | 0 |
| Ready for Testing | `9827871767` | `Kanban::Column` | 6 | yellow | 0 |
| In Testing | `9827872883` | `Kanban::Column` | 7 | — | 0 |
| Done | `9827871765` | `Kanban::DoneColumn` | (fixed) | — | 0 |

### Fetch examples (basecamp-mcp-server)

```text
# All Bugs cards
mcp__basecamp__basecamp_list_cards(project_id="47045023", column_id="9827871763")

# Single card
mcp__basecamp__basecamp_get_card(project_id="47045023", card_id=<id>)

# Re-list columns (live, bypassing index)
mcp__basecamp__basecamp_list_columns(project_id="47045023", table_id="9827871758")

# Raw cards URL pattern (for any column)
https://3.basecampapi.com/5798509/buckets/47045023/card_tables/lists/<column_id>/cards.json
```

### Workflow lanes

- **Inbound:** new tickets land in **Bugs** (purple, 48 open) or **UI issues** (4 open). **Suggestion** holds feature requests (5 open).
- **Dev pipeline:** `Ready for Development → In Development → Ready for Testing (yellow) → In Testing → Done`.
- **Triage / Not now / Done** are fixed Kanban roles — do not delete or rename.
