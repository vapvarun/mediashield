# MediaShield — Capabilities

What this plugin actually does for a site owner, in their language, verified against code.

**Last verified against code:** 2026-07-17 (branch `1.2.0`)
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
| Hide the video's source URL? | **PARTIAL** | JS strips `src` from the element — but the server already printed the same URL into `data-source-url`, so Ctrl+U reveals it. | `protection.js:77-84` vs `PlayerWrapper.php:210` |
| Protect a video I pasted straight into a post? | **NO** | **Only videos added to MediaShield are protected.** A raw embed passes through untouched, by design. | `PlayerWrapper.php` (CPT lookup, "not ours" early return) |
| Stop a determined person keeping a copy? | **NO** | The watermark is a **DOM overlay**, not burned into the file. Anyone with the source file has a clean copy. This is the honest ceiling of the product. | — |

**The honest summary:** MediaShield **deters casual copying** of videos in its library and identifies the viewer if a recording leaks. It does not prevent a determined copier.

---

## Controlling who can watch

| Can I…? | Status | How it works / the catch | Evidence |
|---|---|---|---|
| Require login before watching? | **YES** | Default on. | `Access/AccessControl.php:40` |
| Restrict a video to a role? | **YES** | Per-video `_ms_access_role`. | `AccessControl.php` |
| Restrict embeds to my domains? | **YES** | Allowed-domain whitelist, default-deny on empty referer. | `AccessControl.php` |
| Stop two people sharing one login? | **PARTIAL** | Concurrent stream limit (default 2) — for **logged-in** users. Every guest collapses into `user_id = 0`, so the 3rd anonymous viewer *site-wide* is refused as if they were the same person. | `Access/SessionManager.php:54-93` |
| Let guests watch by turning "Require login" off? | **BROKEN** | The player gates on `isLoggedIn` alone; `ms_require_login` is not in `frontend_config()` at all, so the setting has no effect on the client. | `player-wrapper.js:677`, `Core/Settings.php` |

---

## Knowing what happened

| Can I…? | Status | How it works / the catch | Evidence |
|---|---|---|---|
| See views, sessions, completion rates? | **YES** | Dashboard + per-video and per-user drill-down. Genuinely good. | `REST/AnalyticsController.php` |
| Trust completion % on self-hosted video? | **BROKEN** | Duration is **never auto-detected** — the owner hand-types it per video ("leave 0 if unknown"). At 0, completion divides against nothing and is silently wrong. | `CPT/VideoPostType.php` (Duration field) |
| Fire automation at 25/50/75/100%? | **YES** | Milestone actions + hooks. Deduped correctly. | `Milestones/MilestoneTracker.php` |
| Learn anything about a guest viewer? | **NO** | Heartbeat is logged-in-only; milestones require `user_id > 0`. | `REST/SessionController.php` |
| Export my watch data? | **PARTIAL** | CSV export **truncates at 50,000 rows with no warning**, and buffers all rows in memory despite claiming to stream. | `Export/CsvExporter.php:27,107` |
| Keep more than 2 years of history? | **NO** | Sessions older than 24 months move to an archive table that **no read path queries**. At month 25 every report silently loses history. | `Cron/Cleanup.php:354-418`; `grep UNION` = 0 |
| Honour a GDPR erasure request? | **PARTIAL** | The live table is anonymized; the **archive is not**. IPs and user agents survive erasure, and WordPress reports success. | `Privacy/PrivacyEraser.php:87-95` |

---

## Getting video in, and putting it on the page

| Can I…? | Status | How it works / the catch | Evidence |
|---|---|---|---|
| Upload a video file from the admin? | **NO** | **There is no file upload anywhere in the product.** You paste a URL. To self-host you upload via WP Media separately, then paste the URL back. `/upload/init` exists as a REST route but **no JavaScript calls it**. | `post-new.php?post_type=mediashield_video`; `/upload/init` has zero JS callers |
| Use my existing video host? | **YES** | YouTube, Vimeo, Bunny Stream, Wistia, or a direct file URL. This is the real strength — keep your hosting. | `Player/PlayerWrapper.php` |
| Place a video on a page? | **YES** | Gutenberg block or `[mediashield id=X]`. A wrong/unpublished ID now explains itself to editors. | `Block/`, `Player/Renderer.php` |
| Use a page builder (Elementor/Divi)? | **NO** | No builder integration, and the CPT is non-public so there's no URL fallback. | `VideoPostType.php:78,85` |
| Find a video in a large library? | **PARTIAL** | Videos list has real pagination + search. A full native WP list table also ships at `edit.php?post_type=mediashield_video` but **nothing links to it**. | `Videos.js`; `show_in_menu: false` |

---

## The watch experience (what the student gets)

| Can they…? | Status | How it works / the catch | Evidence |
|---|---|---|---|
| Play, pause, seek, fullscreen? | **YES** | Plus a custom fullscreen button. | `player-wrapper.js:930` |
| Resume where they left off? | **YES** | Default on. | `ms_player_resume` |
| Change playback speed? | **PARTIAL** | Setting exists — but the custom fullscreen button **sits on top of Chrome's ⋮ menu**, which is where native speed *and* PiP live. We take away what the browser gave them free. | `ms_player_speed_control` |
| Keep the video visible while scrolling? | **YES** | Sticky player. | `ms_player_sticky` |
| **Turn on captions/subtitles?** | **NO** | **No `<track>`, no `.vtt`, no `textTrack` anywhere.** We cannot render captions even if the customer supplies a file. | exhaustive search of player JS |
| Pick a quality / watch on weak mobile data? | **NO** | No transcoding, no adaptive bitrate, no quality UI. One file, one bitrate. | — |
| Pop the video out (picture-in-picture)? | **NO** | No `requestPictureInPicture`, and our button covers the browser's own. | — |
| Jump to a chapter? | **NO** | No occurrence of "chapter" in either plugin. | — |
| Read the player in their own language? | **NO** | The admin is translated; the **student-facing player is not** — zero `wp.i18n`, never passed to `wp_set_script_translations()`. | `assets/js/player-wrapper.js` |
| Watch an HLS/DASH self-hosted video? | **PARTIAL** | Shaka is **never enqueued** (the `mediashield_needs_shaka` flag is set then discarded), so playback inherits raw browser support. HLS works on Safari/iOS and macOS Chromium; **fails on Firefox everywhere and Chrome on Windows/Android. DASH is dead everywhere.** | `Core/Assets.php:47` |

---

## What a buyer would reasonably expect and we do NOT have

Ranked by how likely a buyer is to *assume* it already exists.

1. **Captions / subtitles.** Every competitor has them; several auto-generate. We cannot accept a `.vtt` at all. The declared market is **courses** — WCAG 2.1 **Level A** 1.2.2 requires captions on prerecorded video, and the EAA has applied to EU digital products since June 2025. This fails an institutional procurement checklist before protection is ever discussed. **This is the single largest gap in the product.**
2. **Uploading a video from the admin.** "It's a video plugin, I'll upload my video" is the first thing anyone tries. There is no uploader. Compounded by no chunked upload, so the server's limit (typically 64–128 MB on shared hosting) is a hard ceiling a 20-minute lesson exceeds.
3. **Adaptive streaming / quality selector.** A 1080p lesson is unwatchable on weak mobile data with no lower rung to fall to.
4. **Picture-in-picture** — and our redundant fullscreen button actively occludes the browser's native PiP/speed menu. Cheapest fix on this list.
5. **Chapters / markers.** A 40-minute lesson without them is a scrub-hunt.
6. **Playlist autoplay / countdown.** The whole engine is written and works; **no UI writes the settings.**
7. **A translated player.** WordPress buyers are disproportionately non-English.
8. **Page-builder support.** A large share of WP course sites are Elementor or Divi.

---

## The pattern behind most of the BROKEN rows

Four-plus findings share one shape: **a control is rendered, a value is stored, and no consumer ever reads it.** `ms_default_upload_target`, `ms_lms_enrollment_check`, playlist autoplay, `data-ms-untracked` (removed), `ms_bunny_webhook_key`. Nothing in CI asserts that a rendered control has a live consumer — which is exactly what `/wp-contract-audit` catches and is evidently not gating this plugin.
