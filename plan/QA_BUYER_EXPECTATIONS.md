# MediaShield -- Buyer Expectations QA

**Purpose:** Validate the product from a **paying customer's perspective**, not a developer's. Complements `QA_CHECKLIST_FREE/PRO.md` (implementation correctness) and `QA_RELEASE_PROSPECT.md` (first-run walkthrough).

**Audience:** Varun + sales/support before release.

**Principle:** Ship honest. Overclaim once → refund + bad review + lifetime trust damage. Better to undersell and delight than oversell and disappoint.

---

## SECTION 0 — MARKETING ACCURACY BLOCKERS

**Verdict:** `[ ] PASS   [ ] FAIL`

These are **hard blockers**. Readme claims must match code behavior. Failing = refund risk + Stripe chargeback liability.

### 0.1 DRM label is misleading [BLOCKER]
- **Current claim:** `readme.txt` (both free + pro) says "Widevine DRM"
- **Reality:** Code implements **ClearKey DRM** (software-based encryption) via Shaka Player. Keys are extractable via JS debugger. Widevine L1 requires hardware attestation and is NOT present.
- **Required fix:**
  - [ ] Change all instances of "Widevine DRM" → `ClearKey DRM encryption (software-based; Widevine L1/hardware DRM not included)`
  - [ ] Update README.md in both plugins
  - [ ] Update store product page (wbcomdesigns.com)
  - [ ] Update any marketing emails / social posts drafted

### 0.2 "DevTools detection" claim not implemented [BLOCKER]
- **Current claim:** Free README.md line 13, Pro readme.txt line 13 list "DevTools detection" as a protection feature
- **Reality:** `grep -r "devtools\|DevTools" assets/js/ includes/` returns zero detection code. Only a hook is fired (`mediashield_devtools_detected` handler exists, but nothing dispatches it).
- **Choose one:**
  - [ ] **Option A (fast):** Remove "DevTools detection" from readme + store page + marketing
  - [ ] **Option B (safer long-term):** Implement basic detection (window size check, debugger timing) — ships honest even if bypassable

### 0.3 Language audit — no hyperbole
- [ ] No "bulletproof" / "unhackable" / "100% protection" / "uncrackable" / "military-grade" anywhere in readme, README, or store page
- [ ] "Protection" used, not "prevention"
- [ ] Phrase "deters casual sharing + provides audit trails" preferred over "stops piracy"

---

## SECTION 1 — Piracy Resistance Matrix (Honest)

**Verdict:** `[ ] PASS   [ ] FAIL` (pass = every row has honest documented answer + support rep trained)

Buyers WILL test these attacks on day 1. Our docs and support must answer honestly. Scale: **1 = cosmetic, 5 = cryptographic.**

| # | Attack | Free | Pro | Our Honest Answer |
|---|---|---|---|---|
| 1 | Right-click → Save | Blocked (1/5) | Blocked (1/5) | Yes, but not meaningful protection |
| 2 | Ctrl+S (save page) | Blocked in player focus | Same | Trivial bypass (tab away) |
| 3 | F12 / Ctrl+Shift+I (DevTools) | **Not blocked** | **Not blocked** | We don't claim to. (Remove claim per §0.2) |
| 4 | Network tab → copy `.m3u8` / `.mp4` → yt-dlp | Self-hosted URL hidden; YouTube/Vimeo/Wistia iframe exposed | Self-hosted HLS encrypted (AES-128 ClearKey) — key still extractable | **Will work against YT/Vimeo embeds always**. Pro makes it harder for self-hosted but not impossible. |
| 5 | Video DownloadHelper / Stream Recorder browser extensions | Works on embeds | Works on embeds; blocked on DRM'd self-hosted HLS only | Partial |
| 6 | OBS / ScreenFlow / macOS screen recording | **Not prevented** | **Not prevented** (watermark visible in recording = deterrent/forensic) | We're honest: no plugin on WP can stop this. Watermark = proof-of-leak. |
| 7 | Mobile built-in screen recording (iOS/Android) | Not prevented | Not prevented (watermark visible) | Same |
| 8 | Camera pointed at monitor | Impossible to prevent | Impossible | Honest |
| 9 | Domain embed theft (iframe on another site) | Referer-checked (2/5) | Same | Works vs casual, spoofable |
| 10 | Account sharing (one login, 5 people) | Concurrent limit (4/5) | Concurrent limit + suspicious alerts (4/5) | Genuine strength |
| 11 | Watermark removal via DOM inspector | MutationObserver pauses video if canvas removed (3/5) | Same | Genuine, well-implemented |
| 12 | Email gate bypass via direct REST call | Rate-limited; session requires email | Same | Needs verification — run actual `curl` test |
| 13 | HLS segment reconstruction via ffmpeg | Works on free (no encryption) | ClearKey keys recoverable via JS debugger | Partial |
| 14 | Screen recording + audio → re-upload to YouTube | Watermark visible → traceable | Same | Forensic value only |

Action items:
- [ ] `docs/free/faq.md` has an entry per row above, answering honestly
- [ ] Support team trained on the honest answers (no overclaiming)
- [ ] Consider a `docs/protection-philosophy.md` page explaining "deter + trace > prevent"

---

## SECTION 2 — Competitor Parity Reality Check

**Verdict:** `[ ] PASS   [ ] FAIL` (pass = positioning is defensible on a sales call)

**Our tier:** $99/yr range, WordPress-native, software-DRM max.

| Competitor | Their advantage we can't match | Our counter |
|---|---|---|
| **VdoCipher** ($350+/yr) | Widevine L1 + FairPlay hardware DRM, piracy-detection ML | Cheaper, WP-native, multi-platform (YT/Vimeo/Bunny/Wistia unified) |
| **Bunny Stream MediaCage Enterprise** ($99+/mo) | Widevine + FairPlay | No WP admin UI, no unified multi-platform, no LMS adapters |
| **Presto Player Pro** ($79-399/yr) | Gutenberg polish, lifetime deal | We have DRM (ClearKey), heatmaps, realtime, email gate; they don't |
| **CopySafe / DRM-X** | Claims screen-recording block (proprietary codec, unverifiable) | Standards-based (HLS/DASH), no vendor lock-in |
| **Teachable/Thinkific/Kajabi built-in** | All-in-one | Keep your WP site; we're an add-on, not a platform migration |

### Our Honest 1-Sentence Pitch

> "MediaShield is WordPress-native video protection for course creators and membership sites who need **dynamic watermarking, access control, engagement analytics, and audit trails** — priced for real WP businesses, not enterprise OTT budgets. For Widevine L1 hardware DRM, use VdoCipher. For everything else, we're the best value on WordPress."

- [ ] Sales page echoes this positioning
- [ ] We don't compare ourselves to VdoCipher on DRM strength — we compare on WP-native UX + price
- [ ] Free-vs-Pro table on store page clearly distinguishes tiers

---

## SECTION 3 — 5-Minute Demo Script (for sales calls)

**Verdict:** `[ ] PASS   [ ] FAIL` (pass = Varun can run this from memory, every beat works on demo site)

Goal: take a cold prospect from "what is this?" to "I need this" in 5 minutes.

### Minute 0–1: The Pain
- [ ] Open a random YouTube course video
- [ ] Paste URL into yt-dlp in terminal → video downloads instantly
- [ ] Say: "This is every course creator's nightmare. Students share this on Telegram."

### Minute 1–2: Watermark Deterrent
- [ ] Switch to MediaShield-protected video in WP admin
- [ ] Show the video playing in a published page
- [ ] Point at the watermark: "Name, email, IP — embedded on every frame, moves position every X seconds"
- [ ] Say: "If they screen-record this and share, we know WHO. That's enough to shut down 90% of leaks."

### Minute 2–3: Access Control
- [ ] Log out → refresh page → show login overlay
- [ ] Log in as Subscriber → show access-denied message
- [ ] Log in as enrolled user → show video playing
- [ ] Show Settings → Concurrent Stream Limit → "One account = one device"

### Minute 3–4: Analytics (Pro)
- [ ] Admin → Dashboard → Real-time viewers
- [ ] Admin → Heatmap → "See where students drop off"
- [ ] Admin → Alerts → "We detected this user watching from 3 IPs — sharing account"

### Minute 4–5: Unified Multi-Platform (the killer feature)
- [ ] Show importing a video from Bunny → YouTube → Vimeo — same workflow
- [ ] Say: "You keep your existing video hosting. We add the protection layer."
- [ ] Close: "$99/yr, WordPress-native, 14-day refund. Works tonight."

Verify:
- [ ] Demo site has 3+ sample videos already loaded (not "no data" empty states)
- [ ] yt-dlp installed on Varun's laptop and rehearsed
- [ ] Realtime page has simulated viewers visible

---

## SECTION 4 — Top 15 Buyer FAQs — Coverage Check

**Verdict:** `[ ] PASS   [ ] FAIL` (pass = all 15 answered in docs AND discoverable from plugin UI)

| # | Question | Docs covered? | In-UI hint? |
|---|---|---|---|
| 1 | Does it block screen recording? | [ ] | [ ] |
| 2 | Does it work with LearnDash? | [ ] | [ ] |
| 3 | Can I use my existing YouTube videos? | [ ] | [ ] |
| 4 | Will it slow down my site? | [ ] | [ ] |
| 5 | Does it work on mobile? | [ ] | [ ] |
| 6 | What about page caching (WP Rocket, LiteSpeed)? | [ ] | [ ] |
| 7 | Can users download for offline viewing? | [ ] | [ ] |
| 8 | What happens if my license expires? | [ ] | [ ] |
| 9 | Is it GDPR compliant? | [ ] | [ ] |
| 10 | Does it work with CDN (Cloudflare)? | [ ] | [ ] |
| 11 | How do I migrate from Presto Player / VdoCipher? | [ ] | — |
| 12 | What's the difference between free and Pro? | [ ] | [ ] |
| 13 | Can I white-label the watermark? | [ ] | [ ] |
| 14 | Does it integrate with BuddyBoss / membership plugins? | [ ] | [ ] |
| 15 | Refund policy? | [ ] | — |

For each "No" box: either add doc / UI hint, or accept it as known gap.

---

## SECTION 5 — Value Moment Metrics

**Verdict:** `[ ] PASS   [ ] FAIL`

Time-to-value measurements. Stopwatch on a fresh install.

- [ ] Install → activate → first protected video on a page: **under 5 minutes** for non-technical admin
- [ ] Admin → Dashboard first visit: within 2 seconds understands WHAT the plugin does (copy + visual)
- [ ] First "wow" moment (watermark visible with their own name): **under 10 minutes**
- [ ] Pro: License activation → first pro feature used (Heatmap OR Platform Import): **under 5 minutes**
- [ ] Minimum settings needed for basic protection: **3 clicks or fewer**
- [ ] Can non-technical admin enable DRM without reading docs? **Be honest** — if no, that's fine, but ensure docs are linked from DRM page

---

## SECTION 6 — Trust Signals (Admin UX)

**Verdict:** `[ ] PASS   [ ] FAIL`

Does the plugin feel like paid commercial software?

- [ ] Admin UI matches or exceeds **Jetonomy** settings page polish (per `feedback_mediashield_ux.md`)
- [ ] No Lorem Ipsum, no `TODO`, no placeholder images, no `example.com` links
- [ ] Every feature has a help tooltip OR link to docs
- [ ] Errors are actionable ("License expired — click to renew") not cryptic ("error 403")
- [ ] Plugin screenshots on WP.org / store page: high-resolution, show real data, recent UI
- [ ] No console errors on any admin page (open DevTools, click every tab)
- [ ] Loading states are skeleton/spinner, not blank screen
- [ ] Empty states have icon + helpful CTA, not just "No data"
- [ ] Settings save immediately show success toast, not silent

---

## SECTION 7 — Post-Purchase Experience

**Verdict:** `[ ] PASS   [ ] FAIL`

The first 48 hours after purchase define refund decisions.

- [ ] Purchase → receipt email: contains license key + download link + docs link + support link
- [ ] Download link works for 48+ hours (EDD setting check)
- [ ] Welcome email (day 0): quickstart guide + "reply to this email" support path
- [ ] Day 3 email: "how's it going?" with common-problem links
- [ ] EDD license page: clear activation status, easy deactivation for site move
- [ ] Support ticket response SLA documented (24hr? 48hr?) and honored
- [ ] Refund policy: visible on checkout, in welcome email, in plugin admin

---

## Sign-Off

- [ ] §0 Marketing accuracy BLOCKERS resolved (both code + readme + store)
- [ ] §1 Piracy resistance matrix documented in FAQ
- [ ] §2 Positioning defensible — sales pitch rehearsed
- [ ] §3 5-min demo flows without hiccup on demo site
- [ ] §4 Top-15 FAQs covered in docs
- [ ] §5 Value-moment stopwatch targets hit
- [ ] §6 Admin UX matches paid-software bar
- [ ] §7 Post-purchase flow end-to-end tested

**Signed:** ______________   **Date:** ____________

---

## Appendix — One Liner for Every Audience

- **Course creator:** "Stop casual sharing of your course videos with dynamic watermarks and concurrent login limits — $99/yr, WP-native."
- **Membership site:** "Role-based video access + audit trails for your member-only content library."
- **Agency selling to clients:** "Ship video protection as part of your LMS build without adding $350+/yr VdoCipher bills."
- **Technical buyer:** "ClearKey DRM via Shaka Player, HLS/DASH support, unified API for YouTube/Vimeo/Bunny/Wistia, 8 Pro DB tables, REST API."
- **Non-technical buyer:** "Paste your video URL, click Protect, done. Watermark your student's name on every frame automatically."
