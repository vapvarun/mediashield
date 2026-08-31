# MediaShield — Capabilities

What this plugin actually does for a site owner, in their language, verified against code.

**Last verified against code:** 2026-08-31 (branch `1.3.0`)
**Scope:** MediaShield is a **player for cloud video in member-gated courses and lessons**. See [`plan/PRODUCT_SCOPE.md`](plan/PRODUCT_SCOPE.md).
**Pro capabilities:** [`../mediashield-pro/CAPABILITIES.md`](../mediashield-pro/CAPABILITIES.md)

> **Why this file exists.** The manifest counts routes and tables; it never says what the product *does* for a buyer. Without that, copy drifts from reality — the setup wizard shipped a promise ("videos from all platforms are automatically protected when embedded") that the code did not deliver, and nothing caught it. **This file is the source of truth for store copy, docs, and planning.** If a row here says `NO`, that is a planning input, not an oversight.
>
> Status is verified against code, never against `readme.txt`, `docs/`, or `plan/` — those have been wrong.

**Status key:** `YES` shipped and verified · `PARTIAL` works with a named limit · `NO` absent · `BROKEN` present in UI/docs but verifiably does not work

---

## Protecting my videos

| Can I…? | Status | How it works / the catch | Evidence |
|---|---|---|---|
| Put a viewer-identifying watermark on my videos? | **YES** | Name + IP overlaid, position swaps on an interval so it can't be cropped out. | `Player/Watermark.php`, `assets/js/watermark.js` |
| Stop right-click / keyboard download shortcuts? | **YES** | Deters casual copying. | `assets/js/protection.js:65,74` |
| Hide the video's source URL? | **PARTIAL** (1.3.0) | **Self-hosted only.** The server now emits an empty `data-source-url` and points playback at the permission-checked `/stream/{id}` endpoint, signed with a short-lived `ms_token`. Both render paths go through one helper (`Protection::filter_player_urls()`) so they cannot drift. **Not applicable to YouTube, Vimeo, Wistia or Bunny** — those embeds expose their own IDs by construction. Before 1.3.0 the setting did nothing: JS stripped `src` after the server had already printed the same URL into `data-source-url`. | `Player/Protection.php:86-115`; `Renderer.php:206`; `PlayerWrapper.php:190` |
| Protect a video I pasted straight into a post? | **PARTIAL** | **Only videos already in the MediaShield library are protected** — but they are protected wherever they appear. The output-buffer pass matches a platform embed on its platform video ID and a `<video>` tag on its `_ms_source_url`; a hit gets wrapped, a miss passes through untouched by design. So pasting the same YouTube URL into a post does work; pasting a video you never added does not. | `PlayerWrapper.php:165-175`, `find_video_post_id()` |
| Stop a determined person keeping a copy? | **NO** | The watermark is a **DOM overlay**, not burned into the file. Anyone with the source file has a clean copy. This is the honest ceiling of the product. | — |

**The honest summary:** MediaShield **deters casual copying** of videos in its library and identifies the viewer if a recording leaks. It does not prevent a determined copier.

---

## Controlling who can watch

| Can I…? | Status | How it works / the catch | Evidence |
|---|---|---|---|
| Require login before watching? | **YES** | Default on. | `Access/AccessControl.php:40` |
| Restrict a video to a role? | **YES** | Per-video `_ms_access_role`. | `AccessControl.php` |
| Restrict embeds to my domains? | **YES** | Allowed-domain whitelist, default-deny on empty referer. | `AccessControl.php` |
| Stop two people sharing one login? | **YES** | Concurrent stream limit (default 2), enforced per logged-in user under a row lock. Guests are deliberately exempt: they have no account to share, and until 1.3.0 they all collapsed into `user_id = 0`, so the 3rd anonymous viewer *site-wide* was refused and guest B could be handed guest A's session token and resume position. Both fixed — guests now take a dedicated `start_guest_session()` path. | `Access/SessionManager.php:47-131` |
| Let guests watch by turning "Require login" off? | **YES** (1.3.0) | Works in both directions now. `requireLogin` is in `frontend_config()`, the player reads it, and `session_permissions_check()` lets an anonymous `/session/start` through when the gate is off — so the guest gets a real session, not a 401 behind a hidden overlay. Their views and watch time are recorded. | `Core/Settings.php:408`; `player-wrapper.js:822`; `REST/SessionController.php:176-224` |

---

## Knowing what happened

| Can I…? | Status | How it works / the catch | Evidence |
|---|---|---|---|
| See views, sessions, completion rates? | **YES** | Dashboard + per-video and per-user drill-down. Genuinely good. | `REST/AnalyticsController.php` |
| Trust completion % on self-hosted video? | **BROKEN** | Duration is **never auto-detected** — the owner hand-types it per video ("leave 0 if unknown"). At 0, completion divides against nothing and is silently wrong. | `CPT/VideoPostType.php` (Duration field) |
| Fire automation at 25/50/75/100%? | **YES** | Milestone actions + hooks. Deduped correctly. | `Milestones/MilestoneTracker.php` |
| Learn anything about a guest viewer? | **PARTIAL** (1.3.0) | Sessions, views and watch time **are** recorded for guests: heartbeat and end authenticate on the HMAC session token rather than on being logged in. **Milestones still do not fire for guests** (`MilestoneTracker::check()` returns early on `user_id <= 0`), and every guest is `user_id = 0`, so there is no per-person guest history. | `REST/SessionController.php:148-170`; `Milestones/MilestoneTracker.php:42` |
| Export my watch data? | **PARTIAL** (Pro only) | CSV export is a **Pro** feature; free has no export UI. As of 1.3.0 the watch-session and milestone exports are **uncapped** and keyset-paged rather than buffered. The users aggregate cannot be keyset-paged (it GROUPs and sorts on a computed column) so it keeps a **200,000-row ceiling — which now writes a truncation note into the file itself** instead of quietly handing over a short one. The old "50,000 rows, no warning" behaviour is gone. | `../mediashield-pro/includes/Export/CsvExporter.php:50,355-370` |
| Keep more than 2 years of history? | **YES by default** (1.3.0) | Archiving is now **opt-in**: `ms_session_retention_months` defaults to `0` = keep everything, and rows archived by the old automatic 24-month rule are walked back into the live table on upgrade. **The catch if you do opt in:** no analytics read path queries the archive, so anything archived still leaves every report. Only GDPR export and erase read it. | `Cron/Cleanup.php:482-497`, `restore_archived_sessions()`; `grep watch_sessions_archive` in `REST/` = 0 |
| Honour a GDPR erasure request? | **YES** (1.3.0) | Both the live table and the archive are anonymized, and the data export folds the archive in via `UNION ALL` when it exists. Before 1.3.0 IPs and user agents survived erasure in the archive while WordPress reported success. | `Privacy/PrivacyEraser.php:97-130`; `Privacy/PrivacyExporter.php:143-170` |

---

## Getting video in, and putting it on the page

| Can I…? | Status | How it works / the catch | Evidence |
|---|---|---|---|
| Upload a video file from the admin? | **YES** (1.3.0) | Videos > Add New has a file picker with progress. Before 1.3.0 there was none, and `/upload/init` rejected every real upload anyway - it typed the file from PHP's temp path, which has no extension. Both fixed. | `CPT/VideoPostType.php`; `Upload/Drivers/SelfHosted.php` |
| Stop a self-hosted file being downloaded by its address? | **DEPENDS ON THE SERVER** | MediaShield writes an `.htaccess` deny rule into the uploads folder. That is Apache-only - **nginx ignores it**, and on nginx the file is served with no access check at all (measured: HTTP 200 for the file, 403 for the gated endpoint). Filenames now carry a random token so addresses cannot be guessed, and a Site Health check tells the owner which situation they are in and supplies the nginx rule. Neither is access control. | `Upload/Drivers/SelfHosted.php`; `Admin/HealthCheck.php` |
| Use my existing video host? | **YES** | YouTube, Vimeo, Bunny Stream, Wistia, or a direct file URL. This is the real strength — keep your hosting. | `Player/PlayerWrapper.php` |
| Place a video on a page? | **YES** | Gutenberg block or `[mediashield id=X]`. A wrong/unpublished ID now explains itself to editors. | `Block/`, `Player/Renderer.php` |
| Use a page builder (Elementor/Divi)? | **PARTIAL** | There is **no builder widget or element** — nothing appears in the Elementor/Divi panel, and the CPT is non-public so there is no URL fallback. But a builder that outputs a normal `<video>` or platform iframe for a video already in your library **is** picked up by the output-buffer pass and wrapped, matched on platform video ID or `_ms_source_url`. Workable, not integrated. | `VideoPostType.php:78,85`; `PlayerWrapper.php:130-175` |
| Find a video in a large library? | **PARTIAL** | The SPA Videos list has real pagination + search. The CPT is `show_in_menu: false`, so the native WP list table at `edit.php?post_type=mediashield_video` is still unlinked; the SPA links only to `post-new.php` (Add New). | `Videos.js:252`; `VideoPostType.php:84` |

---

## The watch experience (what the student gets)

| Can they…? | Status | How it works / the catch | Evidence |
|---|---|---|---|
| Play, pause, seek, fullscreen? | **YES** | Plus a custom fullscreen button. | `player-wrapper.js:930` |
| Resume where they left off? | **YES** | Default on. | `ms_player_resume` |
| Change playback speed? | **PARTIAL** | Setting exists — but the custom fullscreen button **sits on top of Chrome's ⋮ menu**, which is where native speed *and* PiP live. We take away what the browser gave them free. | `ms_player_speed_control` |
| Keep the video visible while scrolling? | **YES** | Sticky player. | `ms_player_sticky` |
| Be stopped from skipping ahead? | **YES** (1.3.0) | Seeking past the furthest point watched is blocked; rewinding still works. Site-wide setting plus a per-video override, like every other player feature. The behaviour shipped in an earlier version with no way to switch it on. | `ms_player_prevent_forward_seek`; `Player/FeatureOverrides.php:57`; `player-wrapper.js:622` |
| **Turn on captions/subtitles?** | **NO** | **No `<track>`, no `.vtt`, no `textTrack` anywhere.** We cannot render captions even if the customer supplies a file. | exhaustive search of player JS |
| Pick a quality / watch on weak mobile data? | **NO** | No transcoding, no adaptive bitrate, no quality UI. One file, one bitrate. | — |
| Pop the video out (picture-in-picture)? | **NO** | No `requestPictureInPicture`, and our button covers the browser's own. | — |
| Jump to a chapter? | **NO** | No occurrence of "chapter" in either plugin. | — |
| Read the player in their own language? | **NO** | The admin is translated; the **student-facing player is not** — zero `wp.i18n`, never passed to `wp_set_script_translations()`. | `assets/js/player-wrapper.js` |
| Watch an HLS/DASH self-hosted video? | **PARTIAL** (1.3.0) | **HLS now works everywhere.** `hls.js` 1.5.20 is bundled (`assets/vendor/hls.min.js`), registered separately, and enqueued both from the `mediashield_needs_shaka` action and from `Assets::enqueue()` — the flag used to be set one step too late, so no streaming library ever loaded and HLS played only in Safari. **DASH is still dead everywhere**: nothing ships a DASH player. | `Core/Assets.php:44-58,149-155,190-197` |

---

## What a buyer would reasonably expect and we do NOT have

Ranked by how likely a buyer is to *assume* it already exists.

1. **Captions / subtitles.** Every competitor has them; several auto-generate. We cannot accept a `.vtt` at all. The declared market is **courses** — WCAG 2.1 **Level A** 1.2.2 requires captions on prerecorded video, and the EAA has applied to EU digital products since June 2025. This fails an institutional procurement checklist before protection is ever discussed. **This is the single largest gap in the product.**
2. ~~**Uploading a video from the admin.**~~ **Fixed in 1.3.0** - Videos > Add New now has a file picker. Still no chunked upload, so the server's limit (typically 64-128 MB on shared hosting) remains a hard ceiling that a 20-minute lesson can exceed; the error now names the actual limit rather than a number nobody configured.
3. **Adaptive streaming / quality selector.** HLS *playback* works from 1.3.0, but we do not transcode and there is no quality UI — so a single 1080p file is still unwatchable on weak mobile data with no lower rung to fall to. Owners get rungs only if their host (Bunny) made them.
4. **Picture-in-picture** — and our redundant fullscreen button actively occludes the browser's native PiP/speed menu. Cheapest fix on this list.
5. **Chapters / markers.** A 40-minute lesson without them is a scrub-hunt.
6. **Playlist autoplay / countdown.** The whole engine is written and works; **no UI writes the settings.**
7. **A translated player.** WordPress buyers are disproportionately non-English.
8. **Page-builder support.** A large share of WP course sites are Elementor or Divi.

---

## The pattern behind most of the BROKEN rows

Several findings share one shape: **a control is rendered, a value is stored, and no consumer ever reads it.** Still live at 1.3.0: `ms_default_upload_target` (Pro) and `ms_lms_enrollment_check` (Pro), plus playlist autoplay/countdown — the engine is written and the admin only *reads* the flags, no UI writes them. Closed since: `data-ms-untracked` (removed), `ms_bunny_webhook_key` (now self-provisioning and actually verified), `ms_custom_url_patterns` and `ms_max_upload_size` (both removed). Nothing in CI asserts that a rendered control has a live consumer — which is exactly what `/wp-contract-audit` catches and is evidently not gating this plugin.
