# MediaShield — Video Protection Plugin for WordPress

**Date:** 2026-03-29
**Status:** Design
**Plugin Slug:** `mediashield` (free) + `mediashield-pro` (pro)
**Namespace:** `MediaShield\*` / `MediaShieldPro\*`
**REST:** `mediashield/v1` / `mediashield-pro/v1`
**DB Prefix:** `ms_`

---

## Context

Course creators and LMS operators lose significant revenue to video piracy — credential sharing, screen recording, video downloading, and content redistribution. The current WordPress ecosystem has one dominant player (VdoCipher at $99+/month with vendor lock-in) and several weak CSS-overlay plugins. There is no affordable, WordPress-native plugin that offers dynamic watermarking, multi-platform support, detailed analytics, and DRM — all without forcing creators off their existing video hosting.

**MediaShield** fills this gap as a standalone freemium WordPress plugin that protects videos across all major platforms (YouTube, Vimeo, Bunny Stream, Wistia, self-hosted) with dynamic watermarking, access control, engagement analytics, milestone-based automation, direct upload to platforms, and Widevine DRM with offline playback.

---

## Market Research & Competitive Landscape

### Demand Signals

- Video protection is the #1 requested feature in LMS forums (LearnDash, LifterLMS, Tutor LMS communities)
- Common complaints: "How do I prevent students from downloading my videos?", "Can I add a watermark with student's name?", "I need DRM but can't afford $300/month"
- Credential sharing is rampant — some courses report 5-10x concurrent viewers vs. paid seats
- LearnPress (100k+ installs), Sensei, and MasterStudy have zero video protection integrations

### Competitor Analysis

| Feature | VdoCipher | Presto Player | BunnyCDN Stream | WP Video Protect | Wistia | Dacast | **MediaShield** |
|---------|-----------|---------------|-----------------|------------------|--------|--------|-----------------|
| **Dynamic Watermark** (IP/username) | Yes | No | No | No | No (static) | No | **Yes** |
| **Download Prevention** | Strong (DRM) | Basic | Signed URLs | CSS overlay | Moderate | Strong | **Standard + DRM** |
| **Domain Restriction** | Yes | Via Bunny | Yes | No | Yes | Yes | **Yes** |
| **DRM (Widevine)** | Yes | No | No | No | No | Yes | **Yes (Pro)** |
| **Offline Playback** | No | No | No | No | No | No | **Yes (Pro/PWA)** |
| **Per-User Analytics** | Yes | Basic | Basic | No | Yes | Yes | **Yes (detailed)** |
| **Engagement Heatmaps** | Basic | No | No | No | Yes | Yes | **Yes (Pro)** |
| **YouTube Support** | No | Yes | No | No | No | No | **Yes** |
| **Vimeo Support** | No | Yes | No | No | No | No | **Yes** |
| **Bunny Stream Support** | No | Yes | Native | No | No | No | **Yes** |
| **Self-hosted Video** | No | Yes | No | Yes | No | No | **Yes** |
| **Wistia Support** | No | No | No | No | Native | No | **Yes** |
| **Upload Hub** | No | No | Manual | No | Own hosting | Own hosting | **Yes (Pro)** |
| **Milestone Actions** | No | No | No | No | No | No | **Yes** |
| **Suspicious Activity Alerts** | No | No | No | No | No | No | **Yes (Pro)** |
| **LMS Integration** | LearnDash | LearnDash, Tutor | Via Presto | No | Manual | No | **LMS-agnostic hooks** |
| **Pricing** | ~$99/mo | ~$69/yr | ~$1/1000min | Free-$49 | $19/mo | $39/mo | **Free + Pro $79-149/yr** |

### Market Gaps We Fill

1. **No affordable all-in-one WordPress-native solution** — VdoCipher is the only serious player but forces vendor lock-in at $99+/month
2. **Dynamic watermarking without vendor lock-in** — VdoCipher is the only option; we offer it on ANY platform
3. **Multi-platform support in one plugin** — No existing plugin wraps YouTube + Vimeo + Bunny + Wistia + self-hosted with unified protection
4. **Milestone-based automation** — Zero competitors offer watch completion triggers (40%, 50%, 100%)
5. **Offline playback for WordPress** — Nobody offers Widevine DRM with offline download as a WordPress plugin
6. **Per-user analytics tied to video progress** — Wistia has analytics but no WP integration; LMS plugins track lesson completion but not actual video engagement

### SaaS Competitors (Non-WordPress)

| SaaS | Price | Strengths | Weaknesses vs MediaShield |
|------|-------|-----------|---------------------------|
| **VdoCipher** | $99-599/mo | Hollywood-grade DRM, dynamic watermark | Vendor lock-in, expensive, no YouTube/Vimeo |
| **Vimeo OTT** | $1/subscriber/mo | Full OTT platform | Overkill for course creators, no WP integration |
| **Dacast** | $39-188/mo | Enterprise DRM, live streaming | Too complex, no WP plugin, expensive |
| **Sprout Video** | $24-199/mo | Login-gated viewing, analytics | No DRM, no dynamic watermark, no WP native |
| **Vidyard** | $19-59/mo | Sales-focused analytics | No DRM, no protection, wrong market |
| **Teachable/Thinkific** | $39-149/mo | Built-in LMS + video | Platform lock-in, not WordPress |

**Our positioning:** WordPress-native, works with your existing video hosting, priced for independent creators ($79-149/year), not a SaaS — you own your data.

---

## Market Size & Revenue Potential

### WordPress LMS Install Base

| LMS Plugin | Active Installs (2025-2026) |
|------------|---------------------------|
| LearnPress | ~90,000–100,000 |
| Tutor LMS | ~90,000–100,000 |
| LearnDash | ~75,000 (premium-only) |
| Sensei LMS | ~67,000 |
| MasterStudy LMS | ~30,000–40,000 |
| LifterLMS | ~10,000 |
| **Total** | **~360,000–430,000** |

### Global TAM

- Global e-learning market: **$325–440 billion (2025)**, projected **$400B+ (2026)**
- CAGR: **12.7–24.2%** through 2030
- Global learner user base: projected **~996 million by 2029**
- Revenue per user: **~$71.90** (2025)

### WordPress SAM

- WordPress powers **43.4%** of all websites, **60.4%** CMS market share
- WordPress share of self-hosted course market: **~25–30%**
- WordPress LMS economy: roughly **$1.8B–$21.5B annually** (360K–430K sites × $5K–$50K avg revenue)

### Competitor Pricing (Detailed)

| Competitor | Pricing Model | Exact Tiers |
|------------|--------------|-------------|
| **VdoCipher** | Annual SaaS | Starter $129/yr, Value $429/yr, Express $699/yr, Pro $1,549/yr, Plus $2,999/yr, Premium $5,499/yr |
| **Presto Player** | Annual WP plugin | Free, Starter $79/yr (1 site), Pro $119/yr (25 sites), Lifetime $399 |
| **BunnyCDN Stream** | Pay-as-you-go | Storage $0.005/GB/mo, Delivery $0.01/GB, no minimum |
| **Wistia** | Monthly SaaS | Free (25GB), Plus $19/mo, Pro $79/mo, Advanced $319/mo |
| **Dacast** | Monthly/Annual SaaS | Starter $39/mo (annual), Scale $165/mo (annual), Event: custom |

VdoCipher: ~3,000+ business customers, estimated ARR $3–5M+.
Presto Player: ~80,000+ free installs, no real protection features.

### Course Creator Demographics

| Segment | Revenue/mo | Avg Course Price | Avg Students | Tool Budget/yr | Video Protection Need |
|---------|-----------|-----------------|-------------|----------------|----------------------|
| **Solo creators** | $0–$5K | $47–$197 | 50–500 | $500–$1,500 | Low awareness, price-sensitive, want plug-and-play |
| **Growing creators** | $5K–$50K | $197–$997 | 500–5,000 | $1,500–$5,000 | Seeing piracy, will pay $79–$149/yr |
| **Organizations** | $50K+ | $997–$5,000+ | 5,000–100,000+ | $5,000–$15,000 | DRM mandatory, audit trails, compliance |

- Average course: **30–150 videos** (at 6–12 min each for 5–25 hours total content)
- Optimal video length for engagement: **6–9 minutes** (drops after 12)

### Piracy Impact

- Global video piracy losses: **~$75 billion/year**, growing **11% annually**, projected **$125B by 2028**
- India e-learning piracy via Telegram: **~$240 million/year** alone
- Some courses report **5–10x concurrent viewers vs. paid seats** from credential sharing
- Estimated **10–30% of paid course content** pirated within months of release

**Most common piracy methods (ranked):**
1. Credential/password sharing (easiest, most common)
2. Screen recording (OBS, phone-to-screen)
3. Browser dev tools + download extensions
4. Telegram/WhatsApp redistribution groups
5. Torrent sites

### Revenue Projections

| Metric | 1% Capture | 5% Capture | 10% Capture | Presto-Scale (80K installs) |
|--------|-----------|-----------|------------|---------------------------|
| Free installs | 4,000 | 20,000 | 40,000 | 80,000 |
| Paid @ 3% conv | 120 | 600 | 1,200 | 2,400 |
| Paid @ 5% conv | 200 | 1,000 | 2,000 | 4,000 |
| **ARR @ $100 avg, 3%** | $12,000 | $60,000 | $120,000 | **$240,000** |
| **ARR @ $100 avg, 5%** | $20,000 | $100,000 | $200,000 | **$400,000** |
| **ARR @ $149 avg, 5%** | $29,800 | $149,000 | $298,000 | **$596,000** |

**Conversion benchmarks:** Average WP freemium: 1–2%. Well-positioned with clear value gap: 3–5%. Top quartile: 8–15%. Security/protection plugins convert higher than average because the pain is acute.

**Expansion beyond LMS:** MediaShield serves any WP site with protected video — membership sites, coaching, corporate training, paid communities. This expands TAM from 400K to **2–5M+ WordPress sites**.

**Realistic 3-year target:** $200K–$600K ARR at Presto Player-scale distribution with 3–5% conversion.

---

## Large Site Strategy & Database Scaling

### Scale Targets

Per project design principle — always plan for extreme scale:

| Metric | Target |
|--------|--------|
| Concurrent viewers | 10,000+ |
| Total watch sessions | 100M+ rows |
| Playback events (pro) | 500M+ rows |
| Videos protected | 50,000+ |
| Heartbeats/second | 300+ (10K viewers × 1 per 30s) |

### Database Partitioning Plan

**ms_watch_sessions** — Grows fastest (1 row per user per video view).

| Row count | Strategy |
|-----------|----------|
| < 1M | No partition. Single InnoDB table, indexes handle it. |
| 1M–10M | Add composite index on `(started_at, video_id)`. Archive sessions older than 1 year to `ms_watch_sessions_archive`. |
| 10M–100M | Partition by `started_at` month using MySQL native range partitioning: `PARTITION BY RANGE (TO_DAYS(started_at))`. 12 monthly partitions + 1 for older. Pruning cron drops partitions > 24 months. |
| 100M+ | Shard by `video_id % N` across multiple tables. Or migrate to TimescaleDB/ClickHouse for analytics queries. |

**ms_playback_events (pro)** — Grows 2–4x faster than sessions.

| Row count | Strategy |
|-----------|----------|
| < 5M | Single table with `(session_id, position)` index. |
| 5M–50M | Hourly cron aggregates events > 90 days into `ms_heatmap_cache` table (pre-computed per-video position density). Raw rows deleted after aggregation. |
| 50M+ | Partition by `timestamp` month. Keep only 3 months of raw events. Aggregated data persists indefinitely. |

**ms_milestones** — Bounded growth (max = users × videos × thresholds). No partition needed until 50M+ rows.

**ms_activity_alerts (pro)** — Moderate growth. Auto-prune alerts > 6 months via cron.

### Heartbeat Endpoint Performance

The heartbeat endpoint receives 1 POST every 30 seconds per active viewer. At 10,000 concurrent viewers = **~333 requests/second**.

**Design for speed:**

```
Client POST /session/heartbeat
  → Validate session_token via HMAC (no DB lookup — token contains video_id + user_id + created_ts, validated by recomputing HMAC)
  → Write to transient batch queue (wp_cache/object cache)
  → Return 204 No Content immediately

Background flush (every 60 seconds via wp_cron or Action Scheduler):
  → Read batch queue
  → Bulk UPDATE ms_watch_sessions (single query with CASE/WHEN for multiple sessions)
  → Bulk INSERT ms_playback_events (if pro active)
  → Run MilestoneTracker::check() for sessions that crossed thresholds
  → Clear queue
```

**Why batch:** A single `UPDATE ... SET total_seconds = CASE id WHEN 1 THEN 60 WHEN 2 THEN 90 END WHERE id IN (1,2)` is 100x cheaper than 100 individual UPDATEs.

**Object cache dependency:** Requires Redis/Memcached for the batch queue at scale. Falls back to direct DB writes if no object cache (fine for < 100 concurrent viewers).

### CDN Strategy

| Platform | CDN Handling |
|----------|-------------|
| YouTube | YouTube's own CDN — no action needed |
| Vimeo | Vimeo's CDN — no action needed |
| Bunny Stream | BunnyCDN built-in — signed URLs via their API |
| Wistia | Wistia's CDN — domain-restricted |
| Self-hosted | **Needs CDN for scale.** Serve video files through BunnyCDN Pull Zone or Cloudflare. Plugin generates signed URLs with TTL. |
| DRM content | DASH segments served via BunnyCDN or self-hosted with Cloudflare. Manifest (.mpd) can be served directly from WP. |

**Self-hosted CDN setup (admin setting):**
- CDN Pull Zone URL (e.g., `cdn.example.com`)
- CDN auth token (for signed URL generation)
- Fallback: serve directly from `wp-content/uploads/mediashield/` (only for small sites)

### Caching Layers

```
Layer 1: Browser cache
  — DASH segments cached by browser (immutable URLs with content hash)
  — Manifest files: no-cache (always fresh for access control)

Layer 2: Object cache (Redis/Memcached)
  — Session token validation cache (60s TTL)
  — Heartbeat batch queue
  — Dashboard stats (5 min TTL)
  — Active viewers list (30s TTL, refreshed by polling endpoint)

Layer 3: Transient/DB cache
  — Heatmap aggregated data (ms_heatmap_cache, hourly refresh)
  — Video stats summaries (1 hour TTL)
  — Top videos list (5 min TTL)

Layer 4: CDN edge cache
  — Video segments (long TTL, immutable)
  — Thumbnails (1 day TTL)
  — Plugin static assets (versioned, long TTL)
```

### Table Pruning Policies

| Table | Retention | Pruning Method |
|-------|-----------|----------------|
| ms_watch_sessions | 24 months active, then archived | Monthly cron: `INSERT INTO ms_watch_sessions_archive SELECT * FROM ms_watch_sessions WHERE started_at < NOW() - INTERVAL 24 MONTH; DELETE...` |
| ms_playback_events | 90 days raw, then aggregated | Hourly cron: aggregate into position buckets, delete raw rows > 90 days |
| ms_activity_alerts | 6 months | Monthly cron: `DELETE WHERE created_at < NOW() - INTERVAL 6 MONTH` |
| ms_drm_licenses | Revoked: 30 days. Active: until expiry + 30 days | Weekly cron |
| ms_upload_queue | Completed: 7 days. Failed: 30 days | Daily cron |

### Query Optimization Rules

1. **All list queries MUST use LIMIT/OFFSET** — never unbounded SELECT.
2. **All WHERE clauses MUST hit an index** — no full table scans.
3. **Dashboard stats computed from pre-aggregated data** — never COUNT(*) on raw tables at scale.
4. **Active viewers query is a single indexed lookup**: `WHERE is_active = 1 AND last_heartbeat > NOW() - INTERVAL 2 MINUTE` — hits `idx_active` composite index.
5. **Heatmap data served from cache table** — never aggregate `ms_playback_events` on the fly for responses.

---

## Technical Flow Diagrams

### Flow 1: Video Playback (Full Lifecycle)

```
┌──────────────────────────────────────────────────────────────────────┐
│                        PAGE LOAD                                      │
│                                                                       │
│  1. Browser requests page                                             │
│  2. WordPress renders page content                                    │
│  3. PlayerWrapper::wrap_content() intercepts via output buffer        │
│  4. Regex scans HTML for video embeds                                 │
│     (YouTube iframe, Vimeo iframe, Bunny iframe, <video>, Wistia)    │
│  5. Each match → look up or auto-register in ms_videos               │
│  6. Wrap each embed in .ms-protected-player div                       │
│  7. Inject: canvas overlay, protection overlay, data attributes       │
│  8. Assets.php enqueues JS/CSS (only if videos found on page)        │
│  9. wp_localize_script injects: REST URL, nonce, watermark config     │
└──────────────────────────────────────────────────────────────────────┘
         │
         ▼
┌──────────────────────────────────────────────────────────────────────┐
│                    CLIENT-SIDE INITIALIZATION                         │
│                                                                       │
│  10. player-wrapper.js runs on DOMContentLoaded                       │
│  11. For each .ms-protected-player:                                   │
│      a. Check config.isLoggedIn → if false, show login overlay        │
│      b. POST /session/start { video_id }                              │
│         → Server: AccessControl::can_watch() → if denied, return 403  │
│         → Server: SessionManager::start() → create ms_watch_sessions  │
│         → Server: return { session_token, watermark_config, video }   │
│      c. Init watermark.js → Canvas overlay with random position       │
│      d. Init tracker.js → Start 30s heartbeat interval                │
│      e. Init protection.js → Right-click block, src hiding            │
│      f. If DRM (pro): Init drm-player.js → Shaka Player + EME        │
└──────────────────────────────────────────────────────────────────────┘
         │
         ▼
┌──────────────────────────────────────────────────────────────────────┐
│                    ACTIVE WATCHING (LOOP)                              │
│                                                                       │
│  Every 30 seconds while page is open:                                 │
│                                                                       │
│  12. tracker.js collects: position, duration, playing, focused        │
│  13. POST /session/heartbeat { session_token, position, duration,     │
│       playing, focused, events[] (pro: play/pause/seek) }            │
│  14. Server validates HMAC token (no DB lookup)                       │
│  15. Server queues update in object cache batch                       │
│  16. Returns 204 immediately                                          │
│                                                                       │
│  Every 60 seconds (background flush):                                 │
│                                                                       │
│  17. Batch updates ms_watch_sessions (total_seconds, max_position,    │
│      completion_pct, last_heartbeat)                                  │
│  18. Pro: Batch inserts ms_playback_events                            │
│  19. MilestoneTracker::check() for crossed thresholds                 │
│  20. Pro: SuspiciousActivity::check() for anomalies                   │
│                                                                       │
│  Watermark swaps position every 15-30 seconds (client-side only)      │
└──────────────────────────────────────────────────────────────────────┘
         │
         ▼
┌──────────────────────────────────────────────────────────────────────┐
│                    PAGE UNLOAD                                         │
│                                                                       │
│  21. beforeunload event fires                                         │
│  22. navigator.sendBeacon( /session/end, { session_token } )          │
│  23. Server: SessionManager::end() → is_active = 0                    │
│  24. Fire: mediashield_session_ended action                           │
└──────────────────────────────────────────────────────────────────────┘
```

### Flow 2: Widevine DRM Licensing

```
┌─────────────┐     ┌──────────────────┐     ┌──────────────────────┐
│  Browser     │     │  MediaShield Pro  │     │  Widevine Key Server │
│  (Shaka      │     │  License Proxy    │     │  (Google)            │
│   Player)    │     │  (WordPress)      │     │                      │
└──────┬───────┘     └────────┬──────────┘     └──────────┬───────────┘
       │                      │                            │
       │  1. Load .mpd        │                            │
       │  manifest            │                            │
       │◄─────────────────────│  (served from CDN/WP)     │
       │                      │                            │
       │  2. Detect CENC      │                            │
       │  encryption in MPD   │                            │
       │                      │                            │
       │  3. EME API requests │                            │
       │  license             │                            │
       │─────────────────────►│                            │
       │  POST /drm/license   │                            │
       │  { video_id }        │                            │
       │  + X-WP-Nonce        │                            │
       │                      │                            │
       │                      │  4. Validate:              │
       │                      │  - User logged in?         │
       │                      │  - Session valid?          │
       │                      │  - AccessControl::can_watch? │
       │                      │  - Not revoked?            │
       │                      │                            │
       │                      │  5. Fetch content key      │
       │                      │  from KeyServer            │
       │                      │                            │
       │                      │  6. Request license        │
       │                      │─────────────────────────►  │
       │                      │  { key_id, content_key,    │
       │                      │    type: streaming,        │
       │                      │    duration: 86400 }       │
       │                      │                            │
       │                      │  7. Receive license blob   │
       │                      │◄─────────────────────────  │
       │                      │                            │
       │                      │  8. Record in              │
       │                      │  ms_drm_licenses           │
       │                      │                            │
       │  9. Return license   │                            │
       │◄─────────────────────│                            │
       │  (base64 blob)       │                            │
       │                      │                            │
       │  10. CDM decrypts    │                            │
       │  content, plays      │                            │
       │  video. Content      │                            │
       │  never in cleartext  │                            │
       │  to JavaScript.      │                            │
       │                      │                            │

OFFLINE (Persistent License):
Same flow but:
  - Step 3: POST /drm/offline { video_id }
  - Step 6: type: persistent, duration: 2592000 (30 days)
  - Step 10: License stored in IndexedDB
  - Step 11: Service Worker caches encrypted segments
  - Step 12: Playback works offline until license expires
```

### Flow 3: Upload Hub (Multi-Platform)

```
┌──────────────┐     ┌──────────────────┐     ┌─────────────────┐
│  Admin/       │     │  MediaShield      │     │  Target Platform │
│  Instructor   │     │  (WordPress)      │     │  (Bunny/Vimeo/   │
│  Browser      │     │                   │     │   YouTube/Wistia) │
└──────┬────────┘     └────────┬──────────┘     └────────┬─────────┘
       │                       │                          │
       │  1. Select file +     │                          │
       │  choose platform      │                          │
       │                       │                          │
       │  2. POST /upload/init │                          │
       │  { file, platform,    │                          │
       │    title, tags[] }    │                          │
       │─────────────────────► │                          │
       │                       │                          │
       │                       │  3. Validate:            │
       │                       │  - MIME type whitelist    │
       │                       │  - File size limit        │
       │                       │  - User has upload cap    │
       │                       │                          │
       │                       │  4. Save to temp dir     │
       │                       │                          │
       │                       │  5. Create ms_upload_queue│
       │                       │  row (status: pending)    │
       │                       │                          │
       │  6. Return queue_id   │                          │
       │◄───────────────────── │                          │
       │                       │                          │
       │                       │  7. Action Scheduler     │
       │                       │  picks up job            │
       │                       │                          │
       │                       │  8. UploadManager gets   │
       │                       │  driver for platform     │
       │                       │                          │
       │                       │  9. Driver::upload()     │
       │                       │─────────────────────────►│
       │                       │  (tus/resumable upload)  │
       │                       │                          │
       │                       │  10. Update queue:       │
       │                       │  status: uploading       │
       │                       │  progress: 0→100%        │
       │                       │                          │
       │  (polling)            │                          │
       │  GET /upload/status/  │                          │
       │  {queue_id}           │                          │
       │─────────────────────► │                          │
       │◄───────────────────── │                          │
       │  { progress: 65% }    │                          │
       │                       │                          │
       │                       │  11. Platform processes  │
       │                       │◄─────────────────────────│
       │                       │  { platform_video_id,    │
       │                       │    embed_url, status }   │
       │                       │                          │
       │                       │  12. Create ms_videos row│
       │                       │  with platform_video_id  │
       │                       │                          │
       │                       │  13. Update queue:       │
       │                       │  status: complete        │
       │                       │                          │
       │                       │  14. Fire action:        │
       │                       │  mediashield_upload_complete │
       │                       │                          │
       │                       │  15. If auto_package_drm:│
       │                       │  queue DRM packaging job │
       │                       │                          │
       │  (polling)            │                          │
       │  GET /upload/status/  │                          │
       │◄───────────────────── │                          │
       │  { status: complete,  │                          │
       │    video_id: 123 }    │                          │
```

### Flow 4: Milestone Triggering

```
Heartbeat arrives (every 30s)
  │
  ▼
Tracker::process_heartbeat()
  │
  ├─ Update ms_watch_sessions:
  │   total_seconds += 30 (if playing)
  │   max_position = max(current, new_position)
  │   completion_pct = (max_position / duration) * 100
  │   last_heartbeat = NOW()
  │
  ▼
MilestoneTracker::check( video_id, user_id, completion_pct, session_id )
  │
  ├─ Get thresholds: apply_filters('mediashield_milestone_thresholds', [25,50,75,100])
  │
  ├─ For each threshold <= completion_pct:
  │   │
  │   ├─ INSERT IGNORE INTO ms_milestones
  │   │   (video_id, user_id, milestone_pct, reached_at, session_id)
  │   │
  │   ├─ If INSERT succeeded (not duplicate):
  │   │   │
  │   │   ├─ do_action('mediashield_milestone_reached', user_id, video_id, pct)
  │   │   │   │
  │   │   │   └─ Pro hooks: AdvancedActions::handle()
  │   │   │       ├─ 'tag' → update_user_meta(user_id, "ms_completed_{video_id}", timestamp)
  │   │   │       ├─ 'email' → wp_mail() to admin or user
  │   │   │       └─ 'webhook' → wp_remote_post(url, payload) non-blocking
  │   │   │
  │   │   └─ do_action("mediashield_milestone_{pct}", user_id, video_id)
  │   │       │
  │   │       └─ Any LMS plugin can hook here:
  │   │           add_action('mediashield_milestone_100', 'mark_lesson_complete')
  │   │
  │   └─ If INSERT was IGNORE'd (already exists): skip, no action
  │
  └─ Return array of newly fired milestones

Example: User jumps from 20% to 60%
  → Fires: mediashield_milestone_25 ✓
  → Fires: mediashield_milestone_50 ✓
  → Skips: mediashield_milestone_75 (60% < 75%)
  → Skips: mediashield_milestone_100 (60% < 100%)
```

### Flow 5: Watermark Rendering Pipeline

```
┌─────────────────────────────────────────────────────────────────┐
│  SERVER SIDE (PHP — one-time on page render)                     │
│                                                                  │
│  Watermark.php → get_config( user_id )                          │
│    │                                                             │
│    ├─ Free: text = "{display_name} • {ip_address}"              │
│    │                                                             │
│    ├─ Pro filter: mediashield_watermark_config                   │
│    │   AdvancedConfig::extend_config()                           │
│    │   → Read ms_watermark_fields option                         │
│    │   → Build text from: username|email|ip|user_id|             │
│    │     timestamp|site_name|custom_text                         │
│    │   → text = "john@email.com | 192.168.1.1 | 2026-03-29"    │
│    │                                                             │
│    ├─ Config JSON:                                               │
│    │   { text, opacity: 0.3, color: "#fff",                     │
│    │     interval: 20, fontSize: "medium" }                      │
│    │                                                             │
│    └─ Injected via wp_localize_script → mediashieldConfig        │
└─────────────────────────────────────────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────────────────────────────────┐
│  CLIENT SIDE (JavaScript — continuous during playback)           │
│                                                                  │
│  watermark.js → init( container, config )                       │
│    │                                                             │
│    ├─ Get <canvas class="ms-watermark-layer">                   │
│    │   (positioned absolute over video, z-index: 10)            │
│    │                                                             │
│    ├─ Set canvas dimensions to match container                   │
│    │                                                             │
│    ├─ Render text:                                               │
│    │   ctx.font = max(14, container.width * 0.02) + "px sans"   │
│    │   ctx.fillStyle = config.color                              │
│    │   ctx.globalAlpha = config.opacity                          │
│    │   ctx.fillText( config.text, randomX, randomY )            │
│    │                                                             │
│    ├─ Position swap interval:                                    │
│    │   setInterval( () => {                                      │
│    │     randomX = margin + Math.random() * (width - margin*2)  │
│    │     randomY = margin + Math.random() * (height - margin*2) │
│    │     re-render with fade transition                          │
│    │   }, config.interval * 1000 )                               │
│    │                                                             │
│    ├─ Anti-tamper: MutationObserver on container                 │
│    │   If canvas removed from DOM → pause video/clear iframe    │
│    │                                                             │
│    └─ Resize: ResizeObserver re-renders on container resize      │
└─────────────────────────────────────────────────────────────────┘
```

---

## Technical Decisions

### Data Model: Hybrid CPT + Custom Tables

**Videos** registered as Custom Post Type `mediashield_video`:
- Gets WP admin edit screen, Gutenberg editor, REST API (/wp/v2/), revisions, trash, search for free
- Post meta for: platform, platform_video_id, source_url, protection_level, access_role, duration
- Featured image for thumbnail (auto-fetched from platform API, manually overridable)
- Same pattern as WooCommerce products, EDD downloads, LearnDash courses

**Analytics/session data** in custom tables (ms_watch_sessions, ms_milestones, ms_playback_events, etc.):
- High-write, high-read workload needs optimized schemas
- No post meta overhead for millions of rows
- Clean JOINs for aggregation queries

### Playlists

**Playlist CPT** (`mediashield_playlist`) with relationship table:

```sql
CREATE TABLE {prefix}ms_playlist_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    playlist_id BIGINT UNSIGNED NOT NULL,  -- post ID of playlist CPT
    video_id BIGINT UNSIGNED NOT NULL,     -- post ID of video CPT
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    added_at DATETIME NOT NULL,
    KEY idx_playlist (playlist_id, sort_order),
    KEY idx_video (video_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Playlist CPT meta:**
- `_ms_autoplay` — bool (auto-play next video)
- `_ms_countdown` — int (countdown seconds: 3/5/10)
- `_ms_loop` — bool (loop playlist)
- `_ms_shuffle` — bool (shuffle order)

**Playlist behavior:** Admin-configurable per playlist. Video-level tracking only (no playlist-level progress).

### Single Video Pages + Embeddable

Each video gets its own permalink via CPT (e.g., `/video/my-course-intro/`). Videos also embeddable anywhere via:
- **Gutenberg block** (`mediashield/video`) — picker modal (video library) + URL paste (auto-detect)
- **Shortcode** — `[mediashield id=123]` for classic editor users

### Tech Stack

| Component | Technology | Rationale |
|-----------|-----------|-----------|
| **Frontend player** | Shaka Player (self-hosted/Bunny) + iframe wrapper (YouTube/Vimeo) | Shaka needed for DRM anyway; unified player for non-iframe sources |
| **Frontend state** | WordPress Interactivity API (data-wp-* directives) | WP-native, no build step for frontend, modern reactive patterns |
| **Admin UI** | React (@wordpress/scripts) + @wordpress/components | Full-page SPA with sidebar nav, Notion-style premium UX |
| **Admin styling** | @wordpress/components + custom polish | WP-ecosystem consistent but elevated design |
| **Background jobs** | Action Scheduler | Battle-tested (WooCommerce), retry, monitoring UI, async |
| **Heartbeat auth** | HMAC session token (no DB lookup) | Zero DB queries per heartbeat — recompute HMAC to validate |
| **Secrets encryption** | OpenSSL AES-256-CBC with AUTH_SALT | Simple, no extra dependencies, standard PHP |
| **i18n** | Full from day 1 | All strings __() wrapped, .pot file, JS translations |
| **Multisite** | Network-aware from day 1 | Per-site tables ($wpdb->prefix), network-wide platform connections |

### Admin UX: Notion-Style Settings

Full-page React application with sidebar navigation:
- **Sidebar:** General | Watermark | Platforms | DRM | Export
- **Right panel:** Settings for selected section
- **Inline auto-save** on blur/toggle — no "Save" button
- **Toast notifications** on save success/failure
- **Built with @wordpress/components** (Button, TextControl, ToggleControl, SelectControl, etc.) + custom CSS polish
- **Dark/light follows WP admin color scheme**

### Setup Wizard

On first activation, redirect to 4-step setup wizard:
1. **General settings** — enable protection, require login, default protection level
2. **Watermark config** — choose fields, set opacity/color
3. **Connect a platform** (optional) — API keys for Bunny/Vimeo/YouTube/Wistia
4. **Protect your first video** — paste a URL or upload

### Video Player Architecture

| Platform | Player | Protection | DRM | Offline |
|----------|--------|-----------|-----|---------|
| Self-hosted | Shaka Player | Full (watermark + protection + tracking) | Yes | Yes |
| Bunny CDN | Shaka Player | Full | Yes | Yes |
| YouTube | Iframe wrapper | Overlay (watermark + tracking) | No (YouTube's own) | No |
| Vimeo | Iframe wrapper | Overlay (watermark + tracking) | No (Vimeo's own) | No |
| Wistia | Iframe wrapper | Overlay (watermark + tracking) | No | No |
| Generic iframe | Iframe wrapper | Overlay (watermark only) | No | No |

Shaka Player is the primary player for all non-iframe sources. Needed for DRM anyway — reduces library count.

### Video Block (Gutenberg)

The `mediashield/video` block offers two insertion modes:
1. **"Choose from library"** — opens a custom video picker modal (searchable, filterable by tag/platform)
2. **"Paste URL"** — auto-detects platform, registers video, renders protected preview

Block renders a live preview in the editor showing thumbnail + platform badge.

### Thumbnails

Auto-fetch from platform API on video registration:
- YouTube: `https://img.youtube.com/vi/{id}/maxresdefault.jpg`
- Vimeo: Vimeo oEmbed API → `thumbnail_url`
- Bunny: Bunny Stream API → thumbnail URL
- Wistia: Wistia Data API → `thumbnail.url`
- Self-hosted: FFmpeg frame extraction (if available) or default placeholder

Stored as WordPress featured image on the video CPT. Admin can override at any time.

### Data Export (Pro)

- **CSV export** — buttons on analytics pages for watch sessions, milestones, user history
- **PDF reports** — auto-generated weekly/monthly summaries with charts (using TCPDF or Dompdf)
- **REST API** — all analytics data accessible programmatically for third-party tools

---

## Architecture

### Plugin Model

**Monolith with modular internals.** Single free plugin + single pro add-on. All features in one codebase per tier, organized into internal modules.

### Free vs Pro Split

| Feature | Free | Pro |
|---------|------|-----|
| Video wrapping (all platforms) | All platforms | All platforms |
| Single video pages + embeds | Yes (block + shortcode) | Yes |
| Playlists | Basic (no playback config) | Full (auto-play, countdown, loop, shuffle) |
| Watermark | Username + IP (fixed) | Fully configurable (email, custom text, timestamp, site name, user ID) |
| Download prevention | Standard (right-click, hide source) | Standard |
| Access control | Login-required | + Role-based restriction |
| Analytics | View count + basic per-user | + Heatmaps, drop-off, device/browser, suspicious activity alerts |
| Dashboard | Basic stats | + Near real-time active viewers (30s polling) |
| Upload | Self-hosted only | + Bunny, Vimeo, YouTube, Wistia API upload |
| Frontend upload | No | Yes (instructor role) |
| Milestones | WordPress action hooks fire | + Admin UI, configurable thresholds, built-in actions (tag, email, webhook) |
| Tags | Basic tagging | Basic tagging |
| Thumbnails | Auto-fetch from platform | + Manual override |
| Gutenberg block | URL paste only | + Video library picker modal |
| Setup wizard | No | Yes (4-step onboarding) |
| Data export | No | CSV + PDF reports + REST API |
| DRM / Offline | No | Widevine DRM + PWA offline download |
| Multisite | Per-site (basic) | + Network-wide platform connections |

---

## Plugin Structure

```
mediashield/                        # Free plugin
├── mediashield.php                 # Bootstrap, plugin header
├── includes/
│   ├── Core/
│   │   ├── Plugin.php              # Singleton, service container, hook registration
│   │   ├── Activator.php           # DB tables, default options, CPT registration
│   │   ├── Deactivator.php         # Cleanup
│   │   ├── Migrator.php            # Schema versioning (version-based migrations)
│   │   └── Assets.php              # Enqueue JS/CSS (frontend + admin)
│   ├── CPT/
│   │   ├── VideoPostType.php       # Register mediashield_video CPT + meta
│   │   ├── PlaylistPostType.php    # Register mediashield_playlist CPT + meta
│   │   └── Thumbnail.php           # Auto-fetch thumbnails from platform APIs
│   ├── Block/
│   │   ├── VideoBlock.php          # Register mediashield/video Gutenberg block
│   │   ├── block.json              # Block metadata
│   │   └── PlaylistBlock.php       # Register mediashield/playlist block
│   ├── Player/
│   │   ├── PlayerWrapper.php       # Detect & wrap video embeds on page render
│   │   ├── Watermark.php           # Canvas overlay config & data injection
│   │   └── Protection.php          # Anti-download measures (DOM manipulation)
│   ├── Access/
│   │   ├── AccessControl.php       # Login gate + role check
│   │   └── SessionManager.php      # Issue/validate video session tokens (HMAC)
│   ├── Analytics/
│   │   ├── Tracker.php             # Receives heartbeat events, writes to DB
│   │   ├── Storage.php             # Batched DB writes (wp_queue or direct)
│   │   └── Reporter.php            # Query aggregation for dashboard
│   ├── Milestones/
│   │   ├── MilestoneTracker.php    # Check thresholds, fire actions
│   │   └── Actions.php             # Built-in action handlers (tag user, etc.)
│   ├── Upload/
│   │   ├── UploadManager.php       # Orchestrate upload flow
│   │   └── Drivers/
│   │       ├── DriverInterface.php # Upload driver contract
│   │       └── SelfHosted.php      # Local wp-content/uploads driver
│   ├── Tags/
│   │   └── TagManager.php          # CRUD for video tags
│   ├── REST/
│   │   ├── SessionController.php   # /session/start, /session/heartbeat, /session/end
│   │   ├── AnalyticsController.php # /videos/{id}/stats, /milestones
│   │   ├── VideoController.php     # /videos CRUD
│   │   ├── TagController.php       # /tags, /videos/{id}/tags
│   │   ├── UploadController.php    # /upload/init, /upload/status, /upload/complete
│   │   └── SettingsController.php  # /settings
│   ├── Admin/
│   │   ├── Settings.php            # Settings page (React or classic)
│   │   ├── Dashboard.php           # Analytics dashboard page
│   │   └── VideoManager.php        # Video library screen
│   └── DB/
│       └── Schema.php              # All table CREATE statements
├── assets/
│   ├── js/
│   │   ├── player-wrapper.js       # DOM scanning, video detection, wrapping
│   │   ├── watermark.js            # Canvas rendering engine
│   │   ├── tracker.js              # Heartbeat + event tracking
│   │   ├── protection.js           # Right-click, context menu, DevTools detection
│   │   └── admin/
│   │       ├── dashboard.js        # Admin dashboard charts/tables
│   │       └── settings.js         # Settings page JS
│   └── css/
│       ├── player.css              # Protected player styles
│       └── admin.css               # Admin pages
└── templates/
    ├── video-player.php            # Protected player template
    └── upload-form.php             # Frontend upload form (Pro activates this)

mediashield-pro/                    # Pro add-on (requires mediashield free)
├── mediashield-pro.php             # Bootstrap, dependency check
├── includes/
│   ├── Core/
│   │   └── Plugin.php              # Pro initialization, hooks into free
│   ├── Upload/Drivers/
│   │   ├── BunnyStream.php         # Bunny Stream API (tus upload)
│   │   ├── VimeoApi.php            # Vimeo API (OAuth + tus upload)
│   │   ├── YouTubeApi.php          # YouTube Data API v3
│   │   └── WistiaApi.php           # Wistia Upload API
│   ├── Analytics/
│   │   ├── Heatmap.php             # Aggregate playback position data
│   │   ├── SuspiciousActivity.php  # Multi-IP, DevTools, rapid-seek detection
│   │   └── RealtimeDashboard.php   # Active viewer polling endpoint
│   ├── Milestones/
│   │   └── AdvancedActions.php     # Email, webhook, advanced tag actions
│   ├── Watermark/
│   │   └── AdvancedConfig.php      # All watermark fields + style options
│   ├── DRM/
│   │   ├── WidevineLicense.php     # License proxy server (validates user → issues key)
│   │   ├── Packager.php            # Shaka Packager integration (DASH + CENC)
│   │   ├── OfflineManager.php      # PWA service worker + persistent license
│   │   └── KeyServer.php           # Content key management
│   ├── REST/
│   │   ├── HeatmapController.php
│   │   ├── SuspiciousController.php
│   │   ├── RealtimeController.php
│   │   ├── DRMController.php       # /drm/license, /drm/offline
│   │   └── MilestoneConfigController.php
│   └── Admin/
│       ├── PlatformConnections.php # OAuth flows + API key management
│       └── DRMSettings.php         # Widevine config, Shaka path, license duration
├── assets/
│   ├── js/
│   │   ├── drm-player.js          # Shaka Player integration (EME)
│   │   ├── offline-sw.js          # Service Worker for offline playback
│   │   ├── frontend-upload.js     # Instructor upload form JS
│   │   └── admin/
│   │       ├── heatmap.js         # Heatmap visualization
│   │       └── realtime.js        # Active viewers polling
│   └── css/
└── templates/
    └── frontend-upload.php         # Instructor upload form
```

---

## Database Schema

### Core Tables (Free — created on activation)

```sql
-- Video registry: tracks all protected videos regardless of hosting platform
CREATE TABLE {prefix}ms_videos (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    post_id BIGINT UNSIGNED NULL,
    platform ENUM('self','bunny','youtube','vimeo','wistia','iframe') NOT NULL DEFAULT 'self',
    platform_video_id VARCHAR(255) DEFAULT '',
    source_url TEXT NOT NULL,
    title VARCHAR(255) NOT NULL DEFAULT '',
    duration INT UNSIGNED NULL,
    protection_level ENUM('none','standard','drm') NOT NULL DEFAULT 'standard',
    access_role VARCHAR(100) NOT NULL DEFAULT '',
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    KEY idx_post (post_id),
    KEY idx_platform (platform, platform_video_id(50)),
    KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tags for categorizing/organizing videos
CREATE TABLE {prefix}ms_tags (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    description TEXT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY idx_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Many-to-many video-tag relationships
CREATE TABLE {prefix}ms_video_tags (
    video_id BIGINT UNSIGNED NOT NULL,
    tag_id BIGINT UNSIGNED NOT NULL,
    tagged_by BIGINT UNSIGNED NOT NULL,
    tagged_at DATETIME NOT NULL,
    PRIMARY KEY (video_id, tag_id),
    KEY idx_tag (tag_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Per-user watch sessions: who watched what, when, how much
CREATE TABLE {prefix}ms_watch_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    video_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    session_token VARCHAR(64) NOT NULL,
    ip_address VARCHAR(45) NOT NULL DEFAULT '',
    user_agent TEXT,
    device_type ENUM('desktop','mobile','tablet') NOT NULL DEFAULT 'desktop',
    browser VARCHAR(50) NOT NULL DEFAULT '',
    started_at DATETIME NOT NULL,
    last_heartbeat DATETIME NOT NULL,
    total_seconds INT UNSIGNED NOT NULL DEFAULT 0,
    max_position INT UNSIGNED NOT NULL DEFAULT 0,
    completion_pct DECIMAL(5,2) NOT NULL DEFAULT 0.00,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    KEY idx_video_user (video_id, user_id),
    KEY idx_active (is_active, last_heartbeat),
    KEY idx_user (user_id),
    KEY idx_started (started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Milestone tracking: prevents duplicate action firing
CREATE TABLE {prefix}ms_milestones (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    video_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    milestone_pct INT UNSIGNED NOT NULL,
    reached_at DATETIME NOT NULL,
    session_id BIGINT UNSIGNED NOT NULL,
    UNIQUE KEY unique_milestone (video_id, user_id, milestone_pct),
    KEY idx_user (user_id),
    KEY idx_video (video_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

### Pro Tables (created by mediashield-pro on activation)

```sql
-- Granular playback events for heatmaps
CREATE TABLE {prefix}ms_playback_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id BIGINT UNSIGNED NOT NULL,
    event_type ENUM('play','pause','seek','buffer','complete','focus_lost','focus_gained') NOT NULL,
    position INT UNSIGNED NOT NULL DEFAULT 0,
    timestamp DATETIME NOT NULL,
    metadata JSON NULL,
    KEY idx_session (session_id),
    KEY idx_position (session_id, position)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Platform API connections (encrypted credentials)
CREATE TABLE {prefix}ms_platform_connections (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    platform VARCHAR(50) NOT NULL,
    api_key TEXT NOT NULL,
    api_secret TEXT NULL,
    extra_config JSON NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    connected_by BIGINT UNSIGNED NOT NULL,
    connected_at DATETIME NOT NULL,
    KEY idx_platform (platform)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Upload queue for async uploads to platforms
CREATE TABLE {prefix}ms_upload_queue (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    video_id BIGINT UNSIGNED NULL,
    file_path TEXT NOT NULL,
    target_platform VARCHAR(50) NOT NULL,
    status ENUM('pending','uploading','processing','complete','failed') NOT NULL DEFAULT 'pending',
    progress TINYINT UNSIGNED NOT NULL DEFAULT 0,
    error_message TEXT NULL,
    uploaded_by BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL,
    completed_at DATETIME NULL,
    KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Suspicious activity alerts
CREATE TABLE {prefix}ms_activity_alerts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    video_id BIGINT UNSIGNED NOT NULL,
    alert_type ENUM('multi_ip','devtools','rapid_seek','concurrent_stream','vpn_detected') NOT NULL,
    severity ENUM('low','medium','high') NOT NULL DEFAULT 'low',
    details JSON NULL,
    created_at DATETIME NOT NULL,
    KEY idx_user (user_id),
    KEY idx_severity (severity, created_at),
    KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Widevine DRM licenses
CREATE TABLE {prefix}ms_drm_licenses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    video_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    license_type ENUM('streaming','persistent') NOT NULL DEFAULT 'streaming',
    license_token TEXT NOT NULL,
    device_id VARCHAR(255) NOT NULL DEFAULT '',
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL,
    revoked_at DATETIME NULL,
    KEY idx_video_user (video_id, user_id),
    KEY idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## Player Wrapper System

### Video Detection

On page render, `PlayerWrapper.php` uses output buffering to scan HTML for video embeds:

1. **YouTube** — `<iframe src="*youtube.com/embed/*">` or `<iframe src="*youtu.be/*">`
2. **Vimeo** — `<iframe src="*player.vimeo.com/*">`
3. **Bunny Stream** — `<iframe src="*iframe.mediadelivery.net/*">`
4. **Wistia** — `<div class="wistia_embed*">` or `<script src="*wistia.com*">`
5. **Self-hosted** — `<video>` tags with `<source>` elements
6. **Generic iframe** — Any `<iframe>` matching admin-configured URL patterns

Each detected embed is wrapped:

```html
<div class="ms-protected-player" data-video-id="123" data-session="abc...">
    <div class="ms-player-container">
        <!-- Original embed (YouTube iframe, video tag, etc.) -->
    </div>
    <canvas class="ms-watermark-layer"></canvas>
    <div class="ms-protection-overlay"></div>
</div>
```

### Watermark Engine

- **Renderer:** HTML5 Canvas positioned absolutely over the video
- **Movement:** Random position swap every 15-30 seconds with fade transition
- **Content (Free):** `{username} • {ip_address}`
- **Content (Pro):** Admin-configurable: `{username}`, `{email}`, `{ip}`, `{user_id}`, `{timestamp}`, `{site_name}`, `{custom_text}`
- **Style:** Semi-transparent (opacity 0.2-0.4), scales with video size, admin-configurable color
- **Anti-tamper:** MutationObserver watches for canvas removal → pauses video if canvas is detached

### Download Prevention (Standard)

- Disable right-click on `.ms-protected-player` container
- Block context menu events
- Hide `<source>` `src` attributes from DOM (load via JS, not inline)
- CSS `pointer-events: none` on video element (interactions via overlay)
- Disable Ctrl+S / Cmd+S keyboard shortcuts on player
- No download button rendered on `<video>` (remove `controlsList` defaults)

---

## Session & Access Control

### Session Flow

1. Page loads → `player-wrapper.js` detects video embeds
2. For each video, JS calls `POST mediashield/v1/session/start` with `video_id`
3. Server validates: user logged in? Role allowed? Video exists?
4. If valid → creates `ms_watch_sessions` row, returns `session_token` + watermark config
5. JS initializes player wrapper with session token
6. Every 30 seconds → `POST mediashield/v1/session/heartbeat` with `session_token`, `position`, `duration`
7. Server updates `last_heartbeat`, `total_seconds`, `max_position`, `completion_pct`
8. Server checks milestone thresholds → fires `do_action('mediashield_milestone_reached', ...)` if crossed
9. On page unload → `POST mediashield/v1/session/end` via `navigator.sendBeacon()`

### Access Control

- **Free:** Video requires login. Non-logged-in users see a "Login to watch" overlay.
- **Pro:** Per-video role restriction via `access_role` field on `ms_videos`. Admin assigns required WordPress role. `AccessControl::can_watch($video_id, $user_id)` checks role.

---

## Milestone System

### Thresholds

Default: 25%, 50%, 75%, 100%. Admin-configurable (Pro).

### Event Flow

```
Heartbeat arrives → Tracker calculates completion_pct
  → MilestoneTracker::check($video_id, $user_id, $new_pct)
    → For each threshold <= $new_pct that hasn't been recorded:
      → INSERT INTO ms_milestones (deduplicated by UNIQUE key)
      → do_action('mediashield_milestone_reached', $user_id, $video_id, $pct)
      → do_action("mediashield_milestone_{$pct}", $user_id, $video_id)
      → Pro: execute configured actions (tag, email, webhook)
```

### WordPress Hooks (Public API)

```php
// Any plugin can hook into these — LMS-agnostic integration point
add_action('mediashield_milestone_reached', function($user_id, $video_id, $pct) {
    // e.g., Mark LMS lesson complete at 100%
}, 10, 3);

add_action('mediashield_milestone_100', function($user_id, $video_id) {
    // Fired specifically at 100% completion
}, 10, 2);
```

### Built-in Actions (Pro)

- **Tag user:** Add user meta `ms_completed_{video_id}` = timestamp
- **Email:** Send templated email to user or admin
- **Webhook:** POST JSON to configured URL `{ user_id, video_id, milestone_pct, timestamp }`

---

## Upload Hub (Pro)

### Flow

1. Admin or instructor navigates to upload page (admin or frontend)
2. Selects target platform from connected platforms
3. Uploads file (chunked for large files)
4. Plugin queues upload → sends to platform API in background (Action Scheduler)
5. On platform processing complete → creates `ms_videos` row with `platform_video_id`
6. Video is immediately protected

### Platform Drivers

Each driver implements `DriverInterface`:

```php
interface DriverInterface {
    public function upload(string $file_path, array $options): UploadResult;
    public function get_status(string $platform_video_id): VideoStatus;
    public function delete(string $platform_video_id): bool;
    public function get_embed_url(string $platform_video_id): string;
}
```

- **SelfHosted:** Moves file to protected uploads dir, generates signed URL
- **BunnyStream:** Bunny Stream API (tus protocol for resumable uploads)
- **VimeoApi:** Vimeo API v3 (OAuth + tus upload)
- **YouTubeApi:** YouTube Data API v3 (resumable upload)
- **WistiaApi:** Wistia Upload API

### Frontend Upload (Pro)

Instructor-facing upload form rendered via shortcode `[mediashield_upload]` or template. Checks `upload_mediashield` capability (granted to admin + editor + custom "instructor" role). Supports drag-and-drop, progress bar, platform selection.

---

## Analytics System

### Data Collection

**Heartbeat data (every 30s from client):**
- `session_token` — identifies the session
- `position` — current playback position (seconds)
- `playing` — boolean, is video actually playing
- `focused` — boolean, is browser tab focused

**Server processing per heartbeat:**
- Update `ms_watch_sessions`: `last_heartbeat`, `total_seconds` += 30 (if playing), `max_position`, `completion_pct`
- Pro: INSERT into `ms_playback_events` (play/pause/seek events sent with heartbeat)
- Pro: Check suspicious patterns (same user from different IP within 5 minutes)

### Dashboard Views

**Basic (Free):**
- Total protected videos count
- Total watch sessions (today / 7 days / 30 days)
- Average completion rate across all videos
- Top 10 most-watched videos table

**Detailed (Pro):**
- **Active Viewers panel** — polls `/mediashield-pro/v1/realtime/viewers` every 30s
  - Table: User | Video | Progress % | IP | Device | Watch Duration
  - Sortable by any column, filterable by video
- **Per-Video Drill-down:**
  - Playback heatmap (x-axis: video timeline, y-axis: view density)
  - Drop-off chart (% of viewers remaining at each point)
  - Completion funnel: Started → 25% → 50% → 75% → 100%
  - User list with individual completion percentages (paginated, server-side)
- **Suspicious Activity Feed (Pro):**
  - Alert cards with severity badges
  - Alert types: multi-IP (same user, different IPs in short window), DevTools opened, rapid seeking (scrubbing through entire video in seconds), concurrent streams
  - Drill-down per user showing full activity history

---

## Widevine DRM & Offline (Pro)

### Prerequisites

- Widevine Key Server credentials (requires business registration with Google)
- Shaka Packager installed on server (or cloud packaging service)
- `php-gmp` extension for crypto operations
- Bunny CDN or self-hosted storage (DRM does not apply to YouTube/Vimeo — they handle their own)

### Content Preparation Flow

1. Video uploaded to self-hosted or Bunny CDN
2. Background job (Action Scheduler) runs Shaka Packager:
   - Input: MP4 file
   - Output: DASH manifest (.mpd) + encrypted segments (.m4s)
   - Encryption: CENC with Widevine content key
3. Encrypted content stored alongside (or replaces) original
4. `ms_videos.protection_level` set to `drm`

### Playback Flow (DRM)

1. Player loads — detects `protection_level = 'drm'`
2. Switches to Shaka Player (JavaScript, replaces native `<video>`)
3. Shaka Player loads .mpd manifest → detects CENC encryption
4. Browser EME API requests license from `POST mediashield-pro/v1/drm/license`
5. License Proxy validates: session valid? User authorized? Not revoked?
6. If valid → requests license from Widevine Key Server → returns to browser
7. Browser CDM decrypts → video plays. Never exposed in cleartext to JavaScript.

### Offline Download (PWA)

1. User clicks "Save for Offline" button on video
2. JS requests persistent license from `POST mediashield-pro/v1/drm/offline`
3. Server issues Widevine persistent license (configurable expiry, default 30 days)
4. Service Worker downloads encrypted segments to IndexedDB/Cache API
5. `ms_drm_licenses` row created with `license_type = 'persistent'`
6. When offline: Service Worker intercepts video requests → serves from cache
7. Shaka Player uses stored persistent license for decryption
8. On license expiry: user must go online → plugin renews or revokes

### Platform Applicability

| Platform | Standard Protection | DRM | Offline |
|----------|-------------------|-----|---------|
| Self-hosted | Yes | Yes | Yes |
| Bunny CDN | Yes | Yes | Yes |
| YouTube | Yes (watermark + tracking) | No (YouTube's own) | No |
| Vimeo | Yes (watermark + tracking) | No (Vimeo's own) | No |
| Wistia | Yes (watermark + tracking) | No | No |
| Generic iframe | Yes (watermark only) | No | No |

---

## REST API Endpoints

### Free (mediashield/v1)

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/session/start` | POST | Start watch session → returns session_token + watermark config |
| `/session/heartbeat` | POST | Update watch progress (called every 30s) |
| `/session/end` | POST | End watch session (sendBeacon on unload) |
| `/videos` | GET | List protected videos (paginated, filterable) |
| `/videos/{id}` | GET | Single video details + stats |
| `/videos` | POST | Register a new protected video |
| `/videos/{id}` | PATCH | Update video settings |
| `/videos/{id}` | DELETE | Remove video from protection |
| `/videos/{id}/stats` | GET | Video statistics summary |
| `/milestones` | GET | List milestones (filterable by user, video, pct) |
| `/tags` | GET/POST | List or create tags |
| `/tags/{id}` | PATCH/DELETE | Update or delete tag |
| `/videos/{id}/tags` | GET/POST/DELETE | Manage video-tag assignments |
| `/settings` | GET/PUT | Plugin settings |

### Pro (mediashield-pro/v1)

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/realtime/viewers` | GET | Currently active viewers (for polling dashboard) |
| `/analytics/heatmap/{video_id}` | GET | Playback position density data |
| `/analytics/user/{user_id}` | GET | Per-user watch history across videos |
| `/analytics/suspicious` | GET | Suspicious activity alerts (paginated) |
| `/milestones/config` | GET/PUT | Milestone threshold + action configuration |
| `/upload/init` | POST | Start upload to a connected platform |
| `/upload/status/{id}` | GET | Upload progress check |
| `/upload/complete/{id}` | POST | Finalize upload + register video |
| `/platforms` | GET | List connected platforms |
| `/platforms` | POST | Add platform connection |
| `/platforms/{id}` | DELETE | Remove platform connection |
| `/drm/license` | POST | Request Widevine streaming license |
| `/drm/offline` | POST | Request Widevine persistent license (offline) |
| `/drm/revoke` | POST | Revoke a specific license |

---

## Admin UI

### Menu Structure

```
MediaShield (top-level menu)
├── Dashboard          # Analytics overview + active viewers (Pro)
├── Videos             # Video library / manager
├── Upload             # Upload hub (Pro)
├── Tags               # Tag management
├── Milestones         # Milestone config + logs
├── Alerts             # Suspicious activity (Pro)
├── Platforms          # Platform connections (Pro)
├── DRM                # DRM settings (Pro)
└── Settings           # General settings
```

### Settings Fields

**General:**
- `ms_enabled` — Enable/disable protection globally (bool)
- `ms_default_protection` — Default protection level: none / standard / drm
- `ms_require_login` — Require login to watch (bool)
- `ms_watermark_opacity` — 0.1 to 0.5 (float)
- `ms_watermark_color` — Hex color
- `ms_watermark_swap_interval` — Seconds between position swaps (15-60)

**Pro Watermark:**
- `ms_watermark_fields` — Array of enabled fields: username, email, ip, user_id, timestamp, site_name, custom_text
- `ms_watermark_custom_text` — Custom text string
- `ms_watermark_font_size` — Small / Medium / Large

**Pro DRM:**
- `ms_widevine_key_server_url` — Widevine key server endpoint
- `ms_widevine_signing_key` — HMAC signing key (encrypted in DB)
- `ms_shaka_packager_path` — Path to Shaka Packager binary
- `ms_license_duration_streaming` — Seconds (default 86400 = 24h)
- `ms_license_duration_persistent` — Seconds (default 2592000 = 30 days)
- `ms_auto_package_uploads` — Auto-DRM new uploads (bool)

---

## WordPress Hooks (Public API)

### Actions

```php
// Core events
do_action('mediashield_loaded');                              // Plugin initialized
do_action('mediashield_video_registered', $video_id);        // New video added
do_action('mediashield_session_started', $session_id, $user_id, $video_id);
do_action('mediashield_session_ended', $session_id, $user_id, $video_id, $total_seconds);

// Milestones
do_action('mediashield_milestone_reached', $user_id, $video_id, $milestone_pct);
do_action('mediashield_milestone_25', $user_id, $video_id);
do_action('mediashield_milestone_50', $user_id, $video_id);
do_action('mediashield_milestone_75', $user_id, $video_id);
do_action('mediashield_milestone_100', $user_id, $video_id);

// Upload
do_action('mediashield_upload_complete', $video_id, $platform, $platform_video_id);
do_action('mediashield_upload_failed', $queue_id, $error);

// Suspicious activity (Pro)
do_action('mediashield_suspicious_activity', $user_id, $alert_type, $details);
```

### Filters

```php
// Access control
apply_filters('mediashield_can_watch', $allowed, $video_id, $user_id); // Override access
apply_filters('mediashield_session_data', $data, $video_id, $user_id); // Modify session response

// Watermark
apply_filters('mediashield_watermark_text', $text, $user_id, $video_id);   // Custom watermark text
apply_filters('mediashield_watermark_config', $config, $video_id);          // Modify watermark style

// Player
apply_filters('mediashield_detect_video', $detected, $html);               // Custom video detection
apply_filters('mediashield_player_html', $wrapped_html, $video_id);        // Modify player output

// Milestones
apply_filters('mediashield_milestone_thresholds', $thresholds, $video_id); // Custom thresholds per video

// Upload
apply_filters('mediashield_upload_drivers', $drivers);                     // Register custom upload drivers
```

---

## Scale Considerations

Per CLAUDE.md design principle — always plan for extreme scale:

- **Watch sessions table** will grow fast (one row per user per video view). Partitioning by `started_at` month recommended at 10M+ rows. All queries use indexed columns.
- **Playback events** (Pro) grows 2x per minute per active viewer (play/pause events). Pruning cron: events older than 90 days are aggregated into hourly buckets, then raw rows deleted.
- **Heartbeat endpoint** receives POST every 30s per active viewer. Must be lightweight: validate token (HMAC, no DB lookup), batch writes via transient queue flushed every 60s.
- **Active viewers query** (Pro real-time dashboard) — single indexed query: `WHERE is_active = 1 AND last_heartbeat > NOW() - INTERVAL 2 MINUTE`. Capped at 100 results per page.
- **Heatmap aggregation** — never aggregate on-the-fly for large datasets. Background job (hourly cron) pre-computes per-video heatmap data into a `ms_heatmap_cache` transient or custom table.

---

## Verification Plan

### Manual Testing

1. **Activate free plugin** → verify DB tables created (`wp db tables --all-tables | grep ms_`)
2. **Embed a YouTube video** on a test page → verify it gets wrapped in `.ms-protected-player`
3. **Check watermark** → username + IP displayed, swaps position every 15-30s
4. **Right-click disabled** on video player
5. **Watch 30+ seconds** → verify heartbeat fires, `ms_watch_sessions` row created
6. **Watch to 100%** → verify milestone row in `ms_milestones`, action hook fires
7. **Check analytics dashboard** → video stats appear
8. **Log out** → verify video shows "Login required" overlay

### Pro Testing

9. **Connect Bunny Stream** → upload video → verify it appears on Bunny + registered in `ms_videos`
10. **Configure watermark** → change to email + timestamp → verify display
11. **Check heatmap** → watch video partially, skip around → verify heatmap data
12. **Active viewers** → open video in 2 tabs → verify both appear in real-time dashboard
13. **Suspicious activity** → use VPN to switch IP → verify alert generated
14. **DRM** → package a self-hosted video → verify encrypted playback in Chrome
15. **Offline** → save DRM video offline → disconnect → verify playback

### Automated

- PHPUnit for service classes (AccessControl, SessionManager, MilestoneTracker)
- Jest for JS (watermark rendering, heartbeat timing, video detection)
- Playwright E2E for full flow (login → watch → milestone → analytics)
