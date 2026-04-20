# MediaShield -- Release QA Walkthrough (Prospect + Admin)

**Audience:** Release gatekeeper running a fresh-install dry run before tagging.
**Complements:** `QA_CHECKLIST_FREE.md` (192 cases) and `QA_CHECKLIST_PRO.md` (275 cases) which cover feature depth. This doc is the **end-to-end narrative** — what a first-time buyer/admin sees within 10 minutes of clicking "Download".
**Owner:** @varun
**Release target:** week of 2026-04-20

---

## How to run this

1. Spin up a throwaway site (Local, or `wp site empty` on staging).
2. WP 6.5+ / PHP 8.1. No pre-existing MediaShield options.
3. Work through each section **in order** — stop on first failure and file a Basecamp card.
4. Tick the top-of-section Verdict when every box is checked.

---

## SECTION A — Prospect Golden Path (Free Only)

**Verdict:** `[ ] PASS   [ ] FAIL`

The "just bought this, let's see what it does" flow. Must complete in under 10 minutes with zero code.

### A1. Install & Activate
- [ ] Upload `mediashield-1.0.0.zip` via Plugins → Add New → Upload
- [ ] Activation succeeds with **no PHP notices** in `wp-content/debug.log`
- [ ] Redirected to Setup Wizard automatically on first activation
- [ ] 6 DB tables created (`wp db tables 'wp_ms_*'` shows all 6)
- [ ] Admin menu "MediaShield" appears at position 30

### A2. Setup Wizard
- [ ] Wizard loads without JS console errors
- [ ] Step 1 (General) saves site defaults
- [ ] Step 2 (Platform) selection saves
- [ ] Step 3 (Watermark) preview shows current user's name
- [ ] Step 4 (First Video) allows adding a YouTube URL
- [ ] "Finish" lands on Dashboard with a real stat card, not empty placeholder

### A3. First Video Playing
- [ ] Add New Video → paste a YouTube URL → platform auto-detected
- [ ] Save → Embed meta box shows shortcode with click-to-copy working
- [ ] Paste shortcode into a new page → publish
- [ ] Front-end: video plays, watermark visible with username+IP
- [ ] Right-click context menu blocked on player
- [ ] Fullscreen keeps watermark on top

### A4. Evidence That It's Working
- [ ] Refresh Dashboard — stat card increments (1 view)
- [ ] Viewers page shows the test user's display name (not "Unknown")
- [ ] Milestones page shows at least one milestone row after watching 25%+

### A5. No Broken Windows
- [ ] Every admin page loads without white-screen or React error boundary
- [ ] No `wp_die` / fatal anywhere
- [ ] No unstyled content flash (FOUC) on admin SPA load
- [ ] Dashicons and React chrome render crisply (no overlap — regression from `2237315`)

---

## SECTION B — Admin UX Polish Sweep (Free Only)

**Verdict:** `[ ] PASS   [ ] FAIL`

Reference bar: **Jetonomy settings page** (`/wp-admin/admin.php?page=jetonomy-settings`). Nothing in MediaShield should look more basic than Jetonomy.

For each admin page below, confirm:
- (a) Real content loads (not "Loading…" forever)
- (b) Stat cards have icons + values, empty states have icons + helpful copy
- (c) Table column widths are uniform (fixed layout)
- (d) Every button has hover/focus state
- (e) Mobile (≤782px) layout does not break

- [ ] **Dashboard** — Chart.js chart renders with real data OR well-designed empty state
- [ ] **Videos** — list, thumbnail, status badges, preview lightbox, pagination
- [ ] **Playlists** — CRUD modal, drag-reorder preview OK
- [ ] **Viewers** — avatars, progress bars per user
- [ ] **Tags** — create + assign flow, no duplicate-name silent fail
- [ ] **Milestones** — per-video list with user + % + timestamp
- [ ] **Settings** — card-wrapped sections, icons, section descriptions (Jetonomy-level)
- [ ] Sidebar: all 7 routes visible on first load (no flash-in)
- [ ] Error boundary catches a deliberately thrown JS error (test by injecting `throw 1` in console on a page) → shows friendly fallback, not white screen

---

## SECTION C — Pro Upsell Visibility (Free Only)

**Verdict:** `[ ] PASS   [ ] FAIL`

Free must clearly preview what Pro adds without feeling crippled.

- [ ] Dashboard shows 4 Pro feature cards + gradient "Upgrade" banner
- [ ] Settings shows Pro features section (watermark, email gate, DRM, LMS, platforms)
- [ ] Videos empty state hints "Import from platforms" (Pro)
- [ ] Milestones page shows "Trigger actions" teaser
- [ ] Video edit sidebar: LMS Integration + Pro Features teaser meta boxes
- [ ] Admin notice does NOT fire on day 1 (should delay 7 days)
- [ ] Every upsell link points to `https://wbcomdesigns.com/...mediashield-pro` (no `example.com` leftovers)
- [ ] **CRITICAL:** Activate Pro locally — ALL upsell elements above disappear cleanly

---

## SECTION D — Uninstall Safety (Free Only)

**Verdict:** `[ ] PASS   [ ] FAIL`

- [ ] Deactivate → no fatals, no orphan crons (check `wp cron event list`)
- [ ] Re-activate → existing data intact (sessions, milestones, tags survive)
- [ ] Plugins → MediaShield → Delete
- [ ] Confirm all 6 `wp_ms_*` free tables dropped
- [ ] Confirm all free `ms_*` options deleted (list in `uninstall.php`)
- [ ] Confirm `mediashield_*` caps removed from roles
- [ ] **If Pro is also active during delete** — Pro data MUST survive (regression guard per `RELEASE_FIX_PLAN.md` §1.4)

---

## SECTION E — Free + Pro Combo Flow

**Verdict:** `[ ] PASS   [ ] FAIL`

### E1. Dependency Handling
- [ ] With free **inactive**, try to activate Pro → WP 6.5+ blocks activation (thanks to `Requires Plugins: mediashield` header) OR Pro admin notice fires + no fatal
- [ ] Deactivate free while Pro active → Pro shows admin notice, no white screen on any admin page
- [ ] Re-activate free → Pro resumes normally, no duplicate hooks

### E2. License Activation (EDD SL)
- [ ] Pro settings shows License Key field
- [ ] Paste test license → Activate → success toast, status badge green
- [ ] Invalid license → clear error message, no fatal
- [ ] License is **updates-only** — features work without license (per `c795c8f` → `97ce133`)
- [ ] `wp option get mediashield_pro_license` reflects activation state
- [ ] Pro EDD updater `item_id` is non-zero (`1661219`) — no "integration item ID required" error

### E3. Pro Pages Injected into Free SPA
- [ ] Sidebar now shows 13 routes total (7 free + 6 pro: Platforms, Alerts, Heatmap, Realtime, DRM, Export)
- [ ] Each Pro route loads without console errors
- [ ] `mediashield_admin_routes` filter wiring intact (no free pages duplicated)

### E4. Pro Feature Smoke Tests (pick one per area)
- [ ] **Platform Connect** — add a Bunny Stream connection, browse library, import one video
- [ ] **Email Gate** — set one video to `email_gate`, log out, visit page → overlay appears, submit email → video plays
- [ ] **Heatmap** — play a video past 25%, wait for aggregation cron, heatmap chart populates
- [ ] **Realtime** — open video in logged-out window, admin Realtime page shows active viewer within 30s
- [ ] **DRM** — set method to `cloud_bunny`, verify Shaka Player loads on a DRM video (check network for `.mpd`)
- [ ] **Export CSV** — Download watch_sessions CSV, open in Excel, no corrupted encoding
- [ ] **Alerts** — trigger a multi-IP alert manually → appears in Alerts page

### E5. Combo Uninstall Order
- [ ] Delete Pro first → Pro tables (8) dropped, free continues working, no Pro options remain
- [ ] Delete free afterward → all free data gone, no stray Pro tables
- [ ] Reverse order (delete free first while Pro active) → no data loss in Pro (per `RELEASE_FIX_PLAN.md` §1.4 guard)

---

## SECTION F — Release Packaging Gates

**Verdict:** `[ ] PASS   [ ] FAIL`

Run immediately before `git tag v1.0.0`.

### F1. Version Consistency
Free:
- [ ] `mediashield.php` header `Version: 1.0.0`
- [ ] `MEDIASHIELD_VERSION` constant `1.0.0`
- [ ] `readme.txt` Stable tag `1.0.0`
- [ ] `composer.json` version `1.0.0`
- [ ] `package.json` version `1.0.0`

Pro:
- [ ] `mediashield-pro.php` header `Version: 1.0.0`
- [ ] `MEDIASHIELD_PRO_VERSION` constant `1.0.0`
- [ ] `readme.txt` Stable tag `1.0.0`
- [ ] `composer.json` version `1.0.0`
- [ ] `package.json` version `1.0.0`

### F2. Build & Lint
- [ ] Free: `npm run build` clean, no warnings
- [ ] Pro: `npm install && npm run build` — `build/admin/index.js` fresh (`.gitignore` excludes it; dist zip must include it)
- [ ] Free: `composer run phpcs` (or `phpcs .`) zero errors
- [ ] Pro: same zero errors
- [ ] Free: `phpstan analyse` uses baseline, no new errors
- [ ] Pro: same
- [ ] GitHub Actions last run on `main` is green for both repos

### F3. i18n
- [ ] Free: `languages/mediashield.pot` regenerated (mtime today)
- [ ] Pro: `languages/mediashield-pro.pot` regenerated (mtime today)
- [ ] No hardcoded English in any `.js` (grep for `__(` without `@wordpress/i18n` import)

### F4. Distribution Zip
- [ ] Free zip excludes: `node_modules/`, `plan/`, `tests/`, `.github/`, `.git*`, `*.md` except `readme.txt`, `composer.lock`
- [ ] Pro zip excludes same + includes `build/admin/` (production bundle)
- [ ] Pro zip includes `vendor/` with **production deps only** (dompdf, edd-sl-sdk) — no dev tools (per `3caa3aa`)
- [ ] Zip file size sanity: free ~450KB, pro ~2-4MB (check against `mediashield-1.0.0.zip` present in free repo)
- [ ] Unzip in a vanilla WP → activate → Section A golden path repeats clean

### F5. Repo Hygiene
- [ ] Free: `git status` clean on `main`
- [ ] Pro: `git status` clean on `main` (untracked `vendor/` dev files OK; gitignored)
- [ ] `CHANGELOG.md` / readme.txt changelog entry for 1.0.0 present on both
- [ ] Branch protection check: no force-push to `main`
- [ ] Tag created: `git tag v1.0.0 && git push --tags`

---

## SECTION G — Known Post-Launch Watch Items

Not blockers, track in Basecamp:

- [ ] Self-hosted `.mp4` streaming proxy edge cases (Range header on Nginx) — `RELEASE_FIX_PLAN.md` §1.2
- [ ] php-scoper for EDD SDK isolation (production hardening)
- [ ] Webpack chunk splitting (Chart.js lazy load) for admin SPA <200KB goal
- [ ] Automated Playwright smoke test covering Section A golden path
- [ ] Weekly digest email rendering across Gmail + Outlook on Windows

---

## Sign-Off

- [ ] Sections A–D PASS (free stand-alone ships)
- [ ] Sections E PASS (combo ships)
- [ ] Section F PASS (packaging)
- [ ] Release notes drafted and reviewed
- [ ] Slack `#ready-for-release` notification drafted

**Released by:** ______________   **Date:** ____________
