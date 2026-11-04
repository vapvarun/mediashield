# MediaShield 1.4.1 - QA handoff (Ready for Testing)

Covers every card in **Ready for Testing** on the MediaShield board (Basecamp project 47045023, 30 cards on 2026-11-04). Each card below lists what changed, how to verify it as the site owner or member would, and the negative case that must still be refused. The dev proof is in the latest comment on each card. This file says how to repeat it.

## 1. Setup

| Item | Value |
|---|---|
| Free | `mediashield` branch **1.4.1** (head `522cdbd` or later) |
| Pro | `mediashield-pro` branch **1.4.1** (head `b3cbf1e` or later) |
| LMS | Learnomy Free + Pro branch **2.2.0** (the guest-preview and My Videos cards need it) |
| Theme matrix | Reign, Astra, Kadence |
| Viewports | 1440, 1280, 1024, 800, 390 (admin lists also 1200 and 782) |
| Roles | Administrator, Author (instructor), Subscriber (member), logged-out guest |

- `build/` and `vendor/` are committed, so the branch works as installed. There is no build step.
- **Install Free and Pro 1.4.1 together.** Pro 1.4.0's DRM player read a field Free 1.4.1 no longer sends.
- The version header still says **1.4.0**; the bump happens at release. Admin CSS is now versioned by file time, but **hard-reload once** after installing to drop cached 1.4.0 assets.
- **Upgrade path:** test on a site with 1.4.0 data by deactivating Pro, replacing both folders, then activating. Activation now runs the upgrade (DB version 5 → 6).
- Test data you need:
  - a few videos at Standard protection: a YouTube one, a self-hosted one, and a Bunny one if a library is available
  - a playlist with 3 or more items
  - a member with watch history
  - a Learnomy course with one free-preview lesson and one paid lesson using a MediaShield video

## 2. How to report

Per card: **PASS** with what you saw (URL, role, viewport), or **BOUNCE** with exact steps, expected versus actual, and a screenshot. Test the card's negative case as well; a card is not PASS on the happy path alone. A UX-preference bounce needs the reason a site owner or member would be hurt, and the viewport it was seen at.

---

## 3. Access and playback

### 10439179985: Require Login blocked guests from Learnomy free-preview lessons
**Changed:** new filter `mediashield_guest_can_watch`, checked before the login gate. Learnomy answers yes for any published, currently-available free-preview lesson, including on instructor courses. Role and Allowed Domains checks still run after it.
**Verify (guest, Settings > Require Login ON):**
1. Free-preview lesson using a *MediaShield* video: the player plays and there is no login overlay.
2. Free-preview lesson of type *Video* whose YouTube URL is also in the MediaShield library: plays, no overlay.
3. REST `GET /wp-json/learnomy/v1/lessons/{preview id}` as a guest: `mediashield.embed_url` is present and opens a playing player.
**Negative:**
- A paid lesson shows Enrollment Required with no player, and `POST /mediashield/v1/session/start` for its video returns 401.
- Give the preview video a role restriction: the guest is refused.
- A scheduled (future) preview, or a preview in a draft course, stays locked.
- A lesson locked by Pro WP Fusion tags keeps its video locked.

### 10426248216: [Security] An Author could stream another video's protected file
**Changed (1.4.0, carried):** each upload records its owner. Streaming and deletion honour only the owner, and an upgrade backfills owners.
**Verify:** as Author, create a video that names another video's uploaded file. The stream is refused. Delete it: the other video's file stays and still plays.
**Negative:** the original owner's video plays before and after.

### 10426248133: Bunny direct play printed a signed CDN address to viewers who may not watch
**Verify:** logged out, on a page with a gated Bunny or self-hosted CDN video, `data-stream-url` is empty. As a member who may watch, it plays.

### 10426177123: Heartbeat rate limit keyed on a client-supplied token
**Verify:** send about 10 POSTs to `/session/heartbeat` with made-up tokens. You get 429 from about the 5th, and at most one new `_transient_ms_rate_` row.
**Negative:** two real sessions still get 4 beats a minute each.

### 10426177151: Guest pages were cacheable and printed the first visitor's IP
**Verify:** `curl -s` a guest page with a protected YouTube player. The page's watermark config has no `ip` or `text`. While playing, the watermark still shows name and IP from the session.

### 10235750725: [Pro] Uploaded platform videos never played (temp path as source)
**Status:** held by owner decision; no live Bunny upload is required for 1.4.1.
**Verify:** an imported Bunny video plays through the embed address (no token in the iframe src).

### 10441021684: REST viewer routes cacheable by page caches
**Verify:** `curl -sD - .../wp-json/mediashield/v1/...` (stream URL, playlist player, a Pro 401) returns `Cache-Control: no-store, private`.
**Negative:** `/wp-json/wp/v2/posts` does not get that header.

## 4. Pages, blocks and shortcodes

### 10451340466 + 10469975942: Playlist permalink was blank, and 404 on fresh activation
**Verify:**
1. `/playlist/<slug>/` shows the player and the video list. Check at 1440 and 390, on all three themes.
2. Fresh activation: deactivate both plugins, delete the `rewrite_rules` option (`wp option delete rewrite_rules`), activate. The playlist URL returns 200 without saving Permalinks.
3. A video title containing `:)` or `[x]` shows as typed.
**Negative:** a password-protected playlist shows only the password form, with no player markup, until the password is entered.

### 10458960602 + 10470022530: My Videos titles and Resume went to the home page
**Changed:** new filter `mediashield_video_watch_url`. Learnomy links the lesson the member can open, and Pro links the lesson chosen in the video's LMS box (LearnDash, Tutor, LifterLMS). With no answer, the title is plain text with no Resume link.
**Verify (member):**
1. Titles and Resume open the member's own enrolled lesson, not another course's free preview.
2. `[mediashield_my_videos]` prints the same as the block.
**Negative:**
- A video in a course the member is not enrolled in shows plain text with no link.
- A lesson locked by "lock next until complete" gets no link.

### 10472954530: My Videos loaded the whole history with no limit
**Verify (member with 13+ videos, or use the filter `mediashield_my_videos_per_page` set to 2):**
1. 12 per page, with Previous, "Page X of Y" and Next.
2. All / In Progress / Completed are links (`?ms_videos=`); each filter covers the whole history, not just the visible page.
3. `?ms_videos_page=999` shows the last page; `?ms_videos=bogus` shows All.
4. Styling: grid at 1440, one column at 390, 40px targets, no sideways scroll, on all three themes.
5. Logged out: "Please log in…" with a Log in link.

### 10469915576: Playlist block editor UI unstyled, no standard controls
**Verify (block editor):**
1. Insert MediaShield Playlist. The preview is styled: header with count, numbered rows, thumbnails and platform badges, with no run-together text.
2. The sidebar has **Playlist** (picker; changing it updates the canvas) and **Playback** (the playlist's own Autoplay / Countdown / Loop / Shuffle, read-only, with a link to edit the playlist).
3. The MediaShield Video block preview is also styled.
**Design note:** per-block playback toggles were deliberately not added; the settings belong to the playlist. Bounce only if a real owner workflow needs a per-page override.

## 5. Admin UI (Free and Pro)

### 10469913731: Unmapped icons showed "?" glyphs
**Verify:** visit every admin route (Dashboard, Videos, Playlists, Viewers, Tags, Milestones, Platforms, Alerts, Heatmaps, Realtime, Export, Upload Queue, Milestone Actions, License, Settings). No "?" icon anywhere: sidebar, Dashboard KPI cards, the 6 Settings section headers, Upload Queue empty state. Milestones (flag) and Milestone Actions (zap) differ.

### 10469921870: Mobile sidebar had empty accessible names and duplicate icons
**Verify at 390:** every sidebar link has an accessible name (screen reader or accessibility tree) and a title, and is at least 40px. No two icons look alike.
**Regression:** at 1440 the labels are visible.

### 10469923957 + 10470023349: Actions buttons clipped (Videos "Copy shortcode", Playlists "View")
**Verify at 1440:** Videos (Edit / Preview / Copy shortcode) and Playlists (Manage items / Edit / View) fit on one line, fully clickable. Playlist setting badges (Autoplay / Loop / Shuffle) wrap rather than cut off.

### 10473957874: Admin lists cramped at tablet widths; phones scrolled sideways
**Verify on all 9 lists** (Videos, Playlists, Viewers + viewer detail, Tags, Milestones, Alerts, Heatmaps, Realtime, Upload Queue):
- **1440 / 1366 / 1300:** full sidebar with labels, nothing clipped.
- **1280 / 1200 / 1024 / 900 / 800:** the sidebar is an icon rail with tooltips and a visible focus ring, and nothing is clipped. Viewers and Alerts may scroll sideways inside their card; the page itself never scrolls sideways.
- **782 / 600 / 390:** every list stacks into cards with a label on each field and full-width 40px action buttons (Tags delete included). The empty-state row is centred.
- Dashboard and Settings at tablet widths: nothing overlaps.

### 10472954719: [Pro] Bunny Read-Only API key needed a reconnect
**Verify (Platforms, connected Bunny card):** "Read-Only API Key: not added" → enter a key → **Save key** → "saved", with Replace and Remove. Remove returns it to "not added". No reconnect needed.
**Negative:**
- `GET /mediashield-pro/v1/platforms` never returns the key, only `has_read_only_api_key`.
- `PATCH /platforms/{id}` returns 403 as a subscriber, 400 for a Vimeo connection, 404 for an unknown id.

### 10441021633: [Pro] Bunny webhook signature and URL
**Verify:** the connected Bunny card shows the webhook URL with Copy. A POST with a valid `X-BunnyStream-Signature` (HMAC-SHA256 of the raw body with the Read-Only key, plus `X-BunnyStream-Signature-Version: v1` and `X-BunnyStream-Signature-Algorithm: hmac-sha256`) returns 200. The `?ms_token=` URL still works.
**Negative:** a wrong signature, tampered body, or missing version returns 403.

## 6. Pro features

### 10458960510 + 10458960426: DRM hidden; DRM and Email Gate wording removed
**Verify:**
1. No DRM menu, level, setting or copy anywhere (admin, setup wizard, video meta box, readmes, docs). Copy says "use your host's DRM".
2. `POST /mediashield-pro/v1/drm/license` returns 404 `rest_no_route`.
3. Protection levels: none / basic / standard / strict.
4. **Upgrade:** a 1.4.0 site with a video at `drm`, updated by deactivate → replace → activate. The video is now Strict, and `ms_pro_db_version` is 6.
5. Deleting a video on a fresh 1.4.1 install logs no database error (no DRM tables exist).
6. The privacy export works with and without the old DRM tables.

### 10458960824: [Pro] Milestone email had no message body
**Owner decision:** milestone actions start **off**.
**Verify:**
1. A fresh install, and an upgraded 1.4.0 site with the old default, both show 25/50/75/100% Disabled.
2. Enable 50% with Send Email, a subject and an Email Message (placeholders): it saves and reloads.
3. Reaching 50% sends that message with the placeholders filled.
**Negative:** a config the owner saved before 1.4.1 is kept.

### 10458960766: [Pro] Weekly Digest not on Monday
**Verify:** WooCommerce > Status > Scheduled Actions (or `wp action-scheduler`) shows exactly one pending `ms_weekly_digest` at the next **Monday 08:00 site time**. That includes after an update by deactivate → activate from a 1.4.0 site that had a different weekday.

### 10458960697: [Pro] Suspicious Activity
**Verify:** the Alerts table shows each alert's message. Resuming a video mid-way raises no rapid-seek alert. Guest traffic from several IPs raises no multi-IP alert.
**Negative:** a real jump to about 88% is still flagged, and a member on 6 IPs is still flagged.

### 10450937415: [Pro] Heatmap labels 10x too large
**Verify:** bars are labelled in seconds matching the playback position. Events at 25s, 95s and 172s show as 20s, 90s and 170s, not 200s.

### 10458960656: [Pro] Watermark font size ignored
**Verify:** set Pro watermark font size to Small / Medium / Large. The text draws at 12/16/22px on desktop and 10/12/16px on mobile. An unknown value falls back to Medium.

## 7. Tooling, packaging and docs

### 10472954359: WP-CLI scale and repair missing from the release zip
**Verify on a zip build** (`grunt dist` or the release zip, not the git checkout):
- `wp help mediashield` lists `repair` and `scale`.
- `wp mediashield repair bunny-urls` runs (dry run).
- `wp help mediashield-pro scale` works.

### 10472954853: templates/login-overlay.php never loaded
**Verify:** the file is gone. The login overlay for a gated video still appears for a logged-out viewer with Require Login on.

### 10453477579: Docs gap for features in the tutorial videos
**Verify** in `docs/website` (Free and Pro) that each page matches the 1.4.1 UI:
- protect videos you already host, My Videos (paging and filters), end screen, stream limit and allowed domains
- Pro: Alerts, retention view, Bunny webhook (signature + Read-Only key on the card), DRM (auto-moved to Strict), Milestone Actions (Email Message, off by default), Weekly Digest (Monday 08:00)

## 8. Known, not in scope for these cards

- `.pot` files still contain removed strings (DRM, Email Gate). They are regenerated at release.
- The version bump to 1.4.1 and the changelog happen at release.
- On sites without a persistent object cache, My Videos costs about 9 queries per video in Learnomy's lesson check. That is filed on the Learnomy board (10473957896), not a MediaShield bug.
