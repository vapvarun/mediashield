# MediaShield (FREE) — Architecture + Flow + Wiring Audit

**Plugin:** mediashield
**Version under audit:** 1.0.0 (plugin header) / 1.1.0-dev (manifest)
**Audit date:** 2026-05-12
**Ground truth:** manifest v2.2 (2026-05-11), wppqa baseline (2026-05-11), live source
**Auditor:** wp-plugin-onboard skill (code-explorer subagent)
**Release status:** BLOCKED — HIGH: nonce-no-cap (Menu.php:174), plus additional HIGHs found below

---

## §1. Architecture Map

```
Bootstrap (mediashield.php)
  ├─ constants: MEDIASHIELD_VERSION='1.0.0', MEDIASHIELD_DB_VERSION=2
  ├─ Composer autoloader
  ├─ register_activation_hook → Activator::activate
  ├─ register_deactivation_hook → Deactivator::deactivate
  ├─ plugins_loaded:10 → Migrator::run() → Plugin::instance()
  ├─ EDD SL SDK auto-update (edd_sl_sdk_registry)
  ├─ admin_init → license auto-activation (blocking wp_remote_post to wbcomdesigns.com)
  └─ WP-CLI: ScaleCommand
     │
     ↓ Plugin::instance() [includes/Core/Plugin.php]
     ├─ CPTs: VideoPostType::register(), PlaylistPostType::register(), Thumbnail::register()
     ├─ REST: rest_api_init → register_rest_routes() [8 controllers]
     ├─ Blocks: VideoBlock, PlaylistBlock, Shortcode, PlaylistShortcode, MyVideosBlock
     ├─ Player: PlayerWrapper::register() [output buffer on template_redirect]
     ├─ Assets: Assets::register() [wp_enqueue_scripts]
     ├─ Admin: Menu::register(), SetupWizard::register()
     ├─ Cron: Cleanup::register()
     ├─ Privacy: PrivacyExporter, PrivacyEraser
     ├─ single_template filter → video_template()
     └─ do_action('mediashield_loaded') ← PRO consumes this
```

### Layer Responsibilities

| Layer | Files | Owns | Depends on |
|---|---|---|---|
| Bootstrap | `mediashield.php` | Constants, WP hook wiring, autoloader | Composer, WP core |
| Core singleton | `Core/Plugin.php` | All subsystem registration | All subsystems |
| Data schema | `DB/Schema.php`, `Core/Activator.php`, `Core/Migrator.php` | 6 tables via dbDelta; option seeding | `Core/Settings` |
| Settings | `Core/Settings.php` | Schema, types, defaults, frontend payload, sanitization | `get_option()`, WP |
| CPTs | `CPT/VideoPostType.php`, `CPT/PlaylistPostType.php`, `CPT/Thumbnail.php` | mediashield_video + mediashield_playlist CPTs, meta boxes | WP register_post_type |
| REST (8) | `REST/*Controller.php` | HTTP surface for `mediashield/v1`; permission_callbacks | AccessControl, SessionManager, Settings, UploadManager |
| Access | `Access/AccessControl.php`, `Access/SessionManager.php` | can_watch decision; HMAC tokens; concurrent limits | `ms_watch_sessions`; Settings |
| Player | `Player/Renderer.php`, `Player/PlaylistRenderer.php`, `Player/PlayerWrapper.php`, `Player/Protection.php`, `Player/Watermark.php` | Protected player HTML; OB-based video detection; config builders | Assets, Settings, apply_filters chain |
| Block/Shortcode | `Block/*.php` | Gutenberg + classic editor embed surfaces | Player/Renderer, Player/PlaylistRenderer |
| Admin | `Admin/Menu.php`, `Admin/SetupWizard.php` | WP admin pages; React SPA host; AJAX | `build/admin/index.js`, Settings |
| Assets | `Core/Assets.php` | Asset registration, localization of `mediashieldConfig` | `Settings::frontend_config()`, Watermark, Protection |
| Milestones | `Milestones/MilestoneTracker.php` | 25/50/75/100% tracking; fires 2 action hooks | `ms_milestones` table |
| Upload | `Upload/UploadManager.php`, `Upload/Drivers/` | Driver registry; file dispatch | `apply_filters('mediashield_upload_drivers')` |
| Tags | `Tags/TagManager.php` | Tag CRUD | `ms_tags`, `ms_video_tags` |
| Cron | `Cron/Cleanup.php` | Hourly inactive cleanup; monthly session archive | `ms_watch_sessions`, `ms_watch_sessions_archive` |
| Privacy | `Privacy/PrivacyExporter.php`, `Privacy/PrivacyEraser.php` | GDPR export + erasure | Custom tables |

---

## §2. Critical User-Flow Traces

### Flow 1 — Watch a Protected Video

```
Page load with [mediashield id=X] or mediashield/video block
  → Player/Renderer::render($video_id)                                    [Renderer.php:36]
    → validate CPT exists + post_status='publish'                         [line 39]
    → apply_filters('mediashield_player_type', 'standard', $video_id)     [Renderer.php:51]
    → Assets::enqueue()                                                   [line 58]
    → emit <div class="ms-protected-player" data-video-id="N" ...>

Assets previously localized window.mediashieldConfig:
  → Settings::frontend_config() → player options, messages, restUrl, nonce
  → Watermark::get_config() → opacity, color, swap_interval, display_name, ip
  → Protection::get_config() → block_right_click, detect_devtools, ...

JS: player-wrapper.js initializes
  → detects .ms-player-target → creates platform adapter
  → dispatches 'mediashield:player-ready' CustomEvent

JS: tracker.js hears 'mediashield:player-ready'
  → POST /mediashield/v1/session/start {video_id: N}

PHP: SessionController::start_session
  → permission: is_user_logged_in()                                        [line 148]
  → AccessControl::can_watch($video_id, $user_id)                          [line 186]
      → admin bypass (manage_options)                                      [AccessControl.php:34]
      → login gate: Settings::get('ms_require_login')                      [line 40]
      → role gate: _ms_access_role meta                                    [line 47]
      → domain gate: Settings::get('ms_allowed_domains')                   [line 53]
      → apply_filters('mediashield_can_watch', $result, $vid, $uid)        [line 75]
          PRO: RoleAccess(p20), EmailGate(p15), LMS adapters(p25)
  → if denied → 403 WP_Error
  → SessionManager::start($video_id, $user_id, $ip, $ua)
      → START TRANSACTION                                                  [SessionManager.php:51]
      → COUNT(*) active sessions FOR UPDATE                                [line 54]
      → SELECT existing session FOR UPDATE (dedup)                         [line 66]
      → if existing: COMMIT → return resumed session + token
      → if active_count >= max: ROLLBACK
          → do_action('mediashield_concurrent_limit_reached', ...)         [line 107]
          → return false → 429 response
      → INSERT new session row                                             [line 133]
      → COMMIT                                                             [line 162]
      → do_action('mediashield_session_started', ...)                      [line 176]
      → generate_token(id|vid_id|user_id|ts|HMAC-SHA256-AUTH_SALT)
  → build watermark_config via raw get_option() [DEVIATION — see F-02]    [line 205]
  → apply_filters('mediashield_watermark_config', $config, $vid, $uid)    [line 225]
  → return 200: {session_token, resume_position, watermark_config, video}

JS tracker receives token → setInterval(heartbeat, 30000)
JS watermark renders canvas overlay from watermark_config
JS protection starts devtools detection

Heartbeat (every 30s): POST /session/heartbeat {token, position, duration, playing, focused}
  → rate limit 4/min via transient                                         [SessionController.php:253]
  → SessionManager::heartbeat(token, pos, dur, playing, focused)
      → validate_token (HMAC recompute, no DB lookup)                      [line 197]
      → UPDATE ms_watch_sessions (last_heartbeat, total_seconds, max_position, completion_pct)
      → MilestoneTracker::check(video_id, user_id, completion_pct, session_id)
          → apply_filters('mediashield_milestone_thresholds', [25,50,75,100], $vid)
          → INSERT IGNORE ms_milestones (UNIQUE KEY dedup)
          → if new: do_action('mediashield_milestone_reached', user_id, vid, pct, sess_id)
                    do_action("mediashield_milestone_{$pct}", user_id, vid)
              PRO: webhooks, emails, role grants, LMS auto-complete

Page unload: sendBeacon → POST /session/end
  → SessionManager::end(token) → UPDATE ms_watch_sessions SET is_active=0
  → do_action('mediashield_session_ended', ...)
```

**Flag F-02:** Watermark config in SessionController uses hardcoded defaults diverging from Settings schema.

---

### Flow 2 — Admin Uploads a Video

```
Admin SPA (#/videos) → Upload button → FormData POST
  → REST: POST /mediashield/v1/upload/init
  → Permission: current_user_can('upload_mediashield')                    [UploadController.php:87]
      Cap granted: Activator.php:59 (admin role only)
  → UploadManager::upload($tmp, $driver, $options)                        [line 122]
      → get_drivers() → apply_filters('mediashield_upload_drivers', ...)  [UploadManager.php:48]
          PRO: adds Bunny, YouTube, Vimeo, Wistia
      → SelfHosted::upload():
          → MIME + size validation vs ms_max_upload_size
          → move_uploaded_file → wp-content/uploads/mediashield/
          → wp_insert_post(post_type='mediashield_video', post_status='publish')
          → update_post_meta(_ms_platform='self', _ms_platform_video_id, _ms_source_url)
          → return {success, video_id, url, filename}
  → do_action('mediashield_upload_complete', $video_id, $driver, $result) [line 148]
  → return 200: {success, video_id, url, driver, title}

JS: Videos.js refreshes video list → GET mediashield-videos (WP CPT REST)
```

**Flag F-14:** `GET /upload/status/{id}` returns null for self-hosted uploads — SelfHosted never writes status.

---

### Flow 3 — Concurrent Stream Limit Reached

```
User tab 2: POST /session/start (different video, user already at max streams)
  → SessionManager::start()
      → START TRANSACTION
      → COUNT(*) = max_concurrent → ROLLBACK
      → do_action('mediashield_concurrent_limit_reached', ...) [consumed_by: null]
      → return false → SessionController → 429 WP_Error

JS tracker.js: res.ok = false → failCount++ → 3 failures → silent stop
→ No user-facing overlay or message displayed                              [Flag F-03]
```

---

### Flow 4 — Settings Change via Admin SPA

```
Admin SPA (#/settings) → Save → PUT /mediashield/v1/settings
  → Permission: current_user_can('manage_options')
  → $data = JSON body
  → apply_filters('mediashield_settings_update', $data)                   [SettingsController.php:112]
      PRO: ProSettings, DRMSettings, AdvancedConfig — handle + unset their keys
  → foreach $data: Settings::sanitize($key, $value) → update_option($key, $sanitized)
  → return get_settings() →
      Settings::get_all()
      apply_filters('mediashield_settings_response', $settings)           [line 84]
          PRO: merges pro keys back into response
  → 200: full settings map including PRO keys
```

**Round-trip spot-check (5 settings):**

| Setting | Schema | Saved? | Consumer | Notes |
|---|---|---|---|---|
| `ms_require_login` | ✓ Settings.php:35 | ✓ | AccessControl.php:40 via `Settings::get()` | OK |
| `ms_watermark_opacity` | ✓ Settings.php:38 | ✓ | Watermark::get_config() via `Settings::get()` | BUT SessionController:207 uses raw `get_option()` → DRIFT [F-02] |
| `ms_max_concurrent_streams` | ✓ Settings.php:44 | ✓ | SessionManager:48 via raw `get_option()` → DRIFT [F-07] | |
| `ms_block_right_click` | ✓ Settings.php:68 | ✓ | Protection::get_config():36 via raw `get_option()` → DRIFT [F-07] | |
| `ms_milestones` | ✗ NOT in schema | ✗ silently dropped | MilestoneTracker uses hardcoded fallback | MISSING [F-06] |

---

### Flow 5 — Cleanup Cron

```
init hook → Cleanup::schedule_actions()
  → Action Scheduler preferred, WP-Cron fallback
  → hourly: ms_cleanup_inactive_sessions
  → monthly: ms_archive_old_sessions  [manifest incorrectly says 'daily' — F-09]

HOURLY → Cleanup::cleanup_inactive_sessions():
  UPDATE ms_watch_sessions SET is_active=0
  WHERE is_active=1 AND last_heartbeat < NOW() - INTERVAL 10 MINUTE

MONTHLY → Cleanup::archive_old_sessions():
  1. Verify ms_watch_sessions_archive exists (information_schema check)
  2. START TRANSACTION
  3. INSERT INTO archive SELECT * FROM sessions WHERE started_at < NOW() - 24 MONTHS
  4. DELETE FROM sessions WHERE started_at < NOW() - 24 MONTHS
  5. COMMIT (ROLLBACK on failure → error_log, no retry, no admin notice)
```

---

## §3. Wiring Issues

### AJAX
- 1 handler: `ms_dismiss_pro_notice` at `Menu.php:33`.
- JS caller: inline `<script>` in `Menu::render_pro_notice()` at line 160–166 — WIRED.
- Gap: No `current_user_can()` in handler (F-01 — release blocker).

### REST Endpoint JS Coverage
All 22 endpoints have JS callers. No orphan endpoints.
- `/stream/{video_id}` is served as `<source src=...>` or `fetch()` URL — correct for streaming.
- `/upload/status/{id}` has a JS polling caller in Videos.js but SelfHosted driver never writes status (F-14).

### Hooks Fired vs Listened (25 hooks)
- **10 confirmed consumed by PRO** (verified by opening PRO source files).
- **2 manifest discrepancies**: `mediashield_session_started` and `mediashield_devtools_detected` list `consumed_by: null` but PRO subscribes to both (`WeeklyDigest.php:55`, `SuspiciousActivity.php:47+48`).
- **13 genuinely free-only**, including good extension points (`mediashield_before_player`, `mediashield_after_player`, `mediashield_shortcode_source_url`) currently unused by PRO.

### Capabilities
- 1 custom cap (`upload_mediashield`).
- 5 `manage_options` gates in REST controllers + admin pages.
- `edit_posts` used in `PlaylistController` and `TagController` (inconsistent granularity — editors can manage playlists but not view analytics).
- No fabricated JS caps unenforced server-side.

---

## §4. Architecture Violations vs INVARIANTS.yaml

### U1 — Zero raw $wpdb in REST/Admin/Block/Player layers

**3 baseline violations confirmed, no new drift.**

| File | $wpdb call sites | Baselined | ETA |
|---|---|---|---|
| `includes/REST/AnalyticsController.php` | 6 (lines 166, 244, 295, 355, 422, 484) | YES | v1.2 |
| `includes/REST/PlaylistController.php` | 5 (lines 163, 229, 240, 282, 319) | YES | v1.2 |
| `includes/Player/PlaylistRenderer.php` | 1 (line 147) | YES | v1.2 |

All other REST controllers (SessionController, SettingsController, TagController, UploadController, StreamController, ProtectionController) contain zero raw `$wpdb` — they delegate to data-layer classes.

**Proposed AnalyticsRepository:** `includes/Analytics/AnalyticsRepository.php` with public methods: `get_overview(string $period): array`, `get_video_stats(int $video_id): array`, `get_milestones(array $args): array`, `get_users(array $args): array`, `get_user_detail(int $user_id): array`, `get_my_videos(int $user_id): array`. Controller methods become 6-line formatters. ETA: v1.2.

**Proposed PlaylistRepository:** `includes/Playlist/PlaylistRepository.php` with methods: `get_items(int $playlist_id): array`, `add_item(int $playlist_id, int $video_id, int $sort_order): int|false`, `remove_item(int $item_id): bool`, `reorder_items(int $playlist_id, array $order): bool`. PlaylistRenderer gets `PlaylistRepository::get_items()` instead of inline SQL. ETA: v1.2.

### U2 — Models layer: N/A
CPTs + per-feature accessors confirmed appropriate. No action.

### U3 — REST controllers extend WP_REST_Controller: COMPLIANT
All 8 verified.

### U4 — Manifest freshness: PASS (1 day old at audit date)

### U5 — plan/README.yaml: Not verified in this audit scope

### U6 — Asset handle prefixes: COMPLIANT
All 6 handles use `mediashield-*` prefix.

### U7 — Text domain: PASS on 10 random spot-checks
All use `'mediashield'`.

---

## §5. Pro-Readiness Contract Verification

| Extension point | FREE fire location (verified) | PRO consumer (verified) | Status |
|---|---|---|---|
| `mediashield_admin_routes` | `src/admin/App.js:98` via `applyFilters` (JS filter) | PRO `build/admin/index.js` adds 6 routes | WIRED |
| `mediashield_settings_response` | `SettingsController.php:84` | PRO `ProSettings.php:30`, `DRMSettings.php:28`, `AdvancedConfig.php:31` | WIRED. Manifest line (:142) is stale. |
| `mediashield_settings_update` | `SettingsController.php:112` | PRO `ProSettings.php:31`, `DRMSettings.php:29`, `AdvancedConfig.php:32` | WIRED. Manifest line (:169) is stale. |
| `mediashield_player_type` | `Renderer.php:51`, `PlayerWrapper.php:196`, `PlaylistRenderer.php:61` | PRO `Core/Plugin.php:67` returns 'drm' | WIRED. **Manifest `where` incorrectly lists `Protection.php:51`** (F-04) |
| `mediashield_can_watch` | `AccessControl.php:75` | PRO `RoleAccess.php:26(p20)`, `EmailGate.php:40(p15)`, LMS adapters(p25) | WIRED |
| `mediashield_upload_drivers` | `UploadManager.php:48` | PRO `Core/Plugin.php:49` | WIRED |
| `mediashield_watermark_config` | `SessionController.php:225` | PRO `Watermark/AdvancedConfig.php:30` | WIRED. **Manifest `where` incorrectly lists `Watermark.php:225`** (F-05) |

All 6 declared extension points are wired and functional. Two manifest `where` fields are wrong (F-04, F-05) but do not affect runtime behavior.

---

## §6. Findings Summary Table

| # | Severity | Category | Where | Issue | Fix |
|---|---|---|---|---|---|
| F-01 | HIGH | security | `includes/Admin/Menu.php:173` | AJAX handler has nonce check but no `current_user_can()` — any logged-in user who can load an admin page can dismiss the Pro notice. **Release blocker.** | Add `if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( null, 403 ); }` before line 175. |
| F-02 | HIGH | wiring | `includes/REST/SessionController.php:205-212` | `start_session` builds watermark config with `get_option('ms_watermark_opacity', 0.3)` and `get_option('ms_watermark_swap_interval', 20)` — hardcoded defaults diverge from Settings schema (0.5, 30). Two incompatible watermark configs coexist. | Replace with `Settings::get('ms_watermark_opacity')`, `Settings::get('ms_watermark_color')`, `Settings::get('ms_watermark_swap_interval')`. |
| F-03 | HIGH | ux | `assets/js/tracker.js:68-94` | 429 concurrent-limit response is indistinguishable from network failure — silently stops after 3 retries. No user message. | Check `res.status === 429` before failCount increment; dispatch `mediashield:concurrent-limit` event; render overlay using `mediashieldConfig.messages.accessDenied`. |
| F-04 | HIGH | manifest | `audit/manifest.json` hooks_fired | `mediashield_player_type` `where` lists `Protection.php:51` (wrong — Protection.php fires `mediashield_protection_config`). Actual sites: `Renderer.php:51`, `PlayerWrapper.php:196`, `PlaylistRenderer.php:61`. | Correct manifest `where` field. |
| F-05 | HIGH | manifest | `audit/manifest.json` hooks_fired | `mediashield_watermark_config` `where` lists `Watermark.php:225` (wrong — Watermark.php is 62 lines, no `apply_filters`). Actual site: `SessionController.php:225`. | Correct manifest `where` field. |
| F-06 | MEDIUM | data-integrity | `includes/Core/Settings.php` | `ms_milestones` is in manifest settings array but absent from `Settings::schema()`. Any PUT /settings with `ms_milestones` silently discards it. MilestoneTracker uses hardcoded fallback. | Add `'ms_milestones' => ['type' => 'array', 'default' => [25,50,75,100]]` to `Settings::schema()` with array sanitization, OR remove from manifest and document as filter-only. |
| F-07 | MEDIUM | consistency | Multiple files | `ms_max_concurrent_streams`, `ms_block_right_click`, `ms_block_keyboard`, `ms_detect_devtools`, `ms_pause_on_devtools`, `ms_hide_source` are read with raw `get_option()` bypassing `Settings::get()` in `SessionManager.php:48` and `Protection::get_config()`. | Replace with `Settings::get()` at each call site. Add B1 invariant: "No raw `get_option()` for `ms_*` outside Core/Settings." |
| F-08 | MEDIUM | manifest | `audit/manifest.json` hooks_fired + `derived/cross-plugin-coupling.json` | `mediashield_session_started` and `mediashield_devtools_detected` list `consumed_by: null` but PRO hooks both (`WeeklyDigest.php:55`, `SuspiciousActivity:47+48`). | Update both files on next refresh. |
| F-09 | MEDIUM | manifest | `audit/manifest.json` cron | `mediashield_archive_old_sessions` interval listed `"daily"` — source schedules it `monthly` (`MONTH_IN_SECONDS` in `Cleanup.php:83,104`). | Update manifest interval to `"monthly"`. |
| F-10 | MEDIUM | manifest | `audit/manifest.json` settings | `ms_require_login` default listed as `false` in manifest but `true` in `Settings.php:35`. Multiple defaults likely diverge. | Regenerate settings section from `Settings::schema()` as source of truth. |
| F-11 | MEDIUM | debt/U1 | `includes/REST/AnalyticsController.php:166,244,295,355,422,484` | 6 raw `$wpdb` call sites in REST controller — baselined U1. | Extract `includes/Analytics/AnalyticsRepository.php` (6 methods). ETA v1.2. |
| F-12 | MEDIUM | debt/U1 | `includes/REST/PlaylistController.php`, `includes/Player/PlaylistRenderer.php` | 5+1 raw `$wpdb` call sites — baselined U1. | Extract `includes/Playlist/PlaylistRepository.php`. ETA v1.2. |
| F-13 | MEDIUM | a11y | `assets/css/player.css:398`, `src/admin/admin.css:209` | `outline: none` on `:focus` removes keyboard focus ring. | Replace with `:focus-visible { outline: 2px solid #2271b1; outline-offset: 2px; }` |
| F-14 | MEDIUM | wiring | `includes/REST/UploadController.php` | `GET /upload/status/{id}` returns null for self-hosted uploads — SelfHosted never writes progress. JS polling silently receives empty data. | SelfHosted should return `{status: 'complete', progress: 100}` immediately. Status polling is meaningful only for async pro drivers. |
| F-15 | MEDIUM | security/perf | `mediashield.php:87-119` | `admin_init` makes a blocking 15-second `wp_remote_post()` to `wbcomdesigns.com` for license activation. Until `mediashield_preset_activated` is set, every admin page load blocks on this call. | Move to `wp_schedule_single_event(time(), 'ms_activate_license')` from activation hook, not admin_init. |
| F-16 | LOW | a11y | `assets/css/player.css:179,202` | Tap targets 32px and 18px below 40px minimum. | Bump button height to 40px. |
| F-17 | LOW | code-quality | `includes/CPT/VideoPostType.php:382,396` | Inline `onclick="this.select();"` handlers fight CSP and Interactivity API. | Move to `addEventListener('click', e => e.target.select())` in non-inline script. |
| F-18 | LOW | version | `mediashield.php:24` | Plugin header `Version: 1.0.0` but manifest says `1.1.0-dev`. | Bump plugin header or establish dev versioning convention. |
| F-19 | LOW | security | `src/CLI/ScaleCommand.php` (multiple) | Raw `$wpdb->query()` without `prepare()` in CLI-only code (no user input path). | Use `prepare()` or `insert()/delete()` equivalents for consistency. |
| F-20 | LOW | performance | `uninstall.php:71` | `get_posts(['posts_per_page' => -1])` — unbounded query at uninstall. | Batch in chunks of 100 with offset loop. |
| F-21 | LOW | code-quality | `bin/qa-stub-gen.php` (many lines) | 119 phpcs failures in dev tooling pollute CI output. | Add `// phpcs:ignoreFile` or exclude `bin/` in `phpcs.xml`. |
| F-22 | INFO | wppqa-false-positive | `includes/Access/SessionManager.php:51,82,95,155,162` | wppqa flags `$wpdb->query('START TRANSACTION')` etc. as "without prepare()". Transaction control statements have no variables — `prepare()` is inapplicable. | Add targeted `// phpcs:ignore` with justification. |
| F-23 | INFO | cron-notification | `includes/Cron/Cleanup.php:261,326` | Archive/cleanup failures silently logged via `error_log()` — no admin notice or retry. | Consider `do_action('mediashield_cron_error', $e, 'archive_old_sessions')` to allow admin-notice hooks. |

---

## §7. Architecture Verdict

The MediaShield free plugin achieves its stated goal — a clean paired free/pro video protection plugin — with an architecture that is sound at its foundation. The HMAC session token design is correct and efficient (no DB lookup on heartbeat validation). The filter-based PRO extension contract is well-engineered: PRO uses a separate REST namespace, hooks only into declared extension points, and the admin SPA uses the `wp.hooks` JS filter pattern rather than PHP-land monkey-patching. The Settings class as single source of truth for option schema is the right pattern. The 3 U1 violations (AnalyticsController, PlaylistController, PlaylistRenderer) are pre-existing, baselined, and bounded — no new drift was found.

**The single biggest risk surface is Settings layer inconsistency.** Multiple consumers bypass `Settings::get()` with raw `get_option()` calls and hardcoded defaults that diverge from the schema. The most dangerous instance is in `SessionController::start_session` where the watermark config returned to the client uses different defaults than the watermark config localized to `window.mediashieldConfig`. Both configs can be active simultaneously on a single page load, producing inconsistent watermark behavior. This is a low-complexity fix (~15 call sites) with high correctness payoff.

**The single biggest opportunity for cleanup is `AnalyticsController` extraction.** The class is 500+ lines of SQL co-located with HTTP response formatting — the hardest module to test, refactor, or extend. Extracting `AnalyticsRepository` (6 methods) brings U1 compliance, enables unit testing, and unblocks PRO's heatmap/realtime analytics from needing to touch free controller code.

### Recommended refactors

1. **B1 invariant: "No raw `get_option()` for `ms_*` keys outside Core/Settings" (ETA: v1.1.1, ~15 call sites)** — Add to INVARIANTS.yaml as `id: B1, group: data-flow, severity: error, gate_function: check_B1`. Implement `check_B1` in `bin/architecture-checks.sh` to grep for `get_option\(\s*'ms_` outside `includes/Core/Settings.php`. This closes F-02 and F-07 permanently.

2. **AnalyticsRepository extraction (ETA: v1.2)** — Already baselined. Create `includes/Analytics/AnalyticsRepository.php` with 6 public static methods. AnalyticsController becomes 6 thin formatters calling the repository. Removes U1 baseline entry, enables mock-based unit tests.

3. **PlaylistRepository extraction (ETA: v1.2)** — Already baselined. Create `includes/Playlist/PlaylistRepository.php` with 4 methods. Resolves PlaylistController + PlaylistRenderer U1 violations simultaneously. Pattern already established by TagManager — copy-paste-adapt.

---

*Audit generated 2026-05-12. Ground truth: manifest v2.2, wppqa baseline 2026-05-11, live source.*
*Essential files: `includes/Core/Plugin.php`, `includes/Core/Settings.php`, `includes/Access/SessionManager.php`, `includes/Access/AccessControl.php`, `includes/REST/SessionController.php`, `includes/REST/SettingsController.php`, `includes/REST/AnalyticsController.php`, `includes/Player/Renderer.php`, `includes/Cron/Cleanup.php`, `assets/js/tracker.js`, `audit/manifest.json`, `plan/INVARIANTS.yaml`*
