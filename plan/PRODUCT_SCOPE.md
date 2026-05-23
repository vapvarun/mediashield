# MediaShield — Product Scope

> **Contract.** Read this before triaging any feature request, QA card, or UX suggestion. New cards that violate this scope are **wontfix**, not deferred — they belong in a different product, not in MediaShield 1.x.

## What MediaShield is

MediaShield is a **player** for cloud video (self-hosted, YouTube, Vimeo, Wistia, Bunny Stream) used inside **member-gated courses and lessons**. The customer is a course creator / instructor / membership-site operator. Their viewers are paying or otherwise gated members going through structured content.

Everything the plugin ships exists to support that single shape:

- **Watermarking** — identity reminder on the player, so a screen-recorded leak traces back.
- **Session tracking** — HMAC tokens, heartbeat, concurrent-stream limits.
- **Access gates** — role-based, allowed-domain, login-required, per-video access type (Pro).
- **Milestone tracking** — 25/50/75/100% completion, optional auto-complete into LMS lessons.
- **Engagement analytics** — heatmap, suspicious-activity alerts, realtime active viewers, weekly digest, CSV/PDF export (Pro).
- **Player UX** — sticky player, resume playback, speed control, end-screen CTA, Gutenberg blocks and shortcodes.

## What MediaShield is not

It is **not** a public video-sharing or distribution platform. It does not — and will not in 1.x — ship features that work against the gated-content model the customer is building:

| Feature | Status | Why |
|---|---|---|
| Public "Share to Twitter/Facebook/email" buttons on the player | **Wontfix** | Every share affordance hands out a URL that bypasses session tracking, watermark, and access gates. |
| Public view-count badge ("1.2K views") on the player | **Wontfix** | Cheapens premium gated content; gives a signal members didn't ask for. |
| "Trending" / "Popular" feeds across members | **Wontfix** | Members shouldn't see other members' watch behaviour. |
| Cross-member comments / discussion under the video | **Out of scope** | If a customer wants this, they layer it via BuddyPress / bbPress / LearnDash, not a feature inside MediaShield. |
| Restyling YouTube's *native* share modal | **Wontfix (impossible)** | YouTube's share UI renders inside the cross-origin `youtube.com/embed` iframe. We have no DOM access. YT exposes no parameter to add/restyle share icons. We already minimize YT's surface via `modestbranding=1`, `rel=0`, `fs=0`. |

## The architectural rule — admin / member split

Every non-public surface gates by capability:

### Admin sees everything

- Full engagement analytics (`/analytics/overview`, `/analytics/users`, `/analytics/milestones`).
- Per-bucket heatmap (Pro `/analytics/heatmap/{id}`).
- Realtime active viewers (Pro `/realtime/viewers`).
- Suspicious activity alerts (Pro `/analytics/suspicious`).
- CSV + async PDF reports (Pro `/export/*`).
- Weekly digest email (Pro).
- Milestone configuration (Pro `/milestones/config`).
- "Browse my own YouTube/Vimeo/Bunny/Wistia library" pickers (Pro) — the only place `viewCount` is shown, and only on the admin's own content.

All gated behind `admin_check` permission callbacks (Pro) or `manage_options` (Free).

### Member sees only their own watch experience

- Own watermark (display name + IP — identity reminder, not analytics).
- Own resume position via `/session/start` → `resume_position`.
- Own milestones (written to `wp_ms_milestones`, read back per-user).
- Own watched-list via `[mediashield_my_videos]` + `/analytics/my-videos` (scoped to `get_current_user_id()`).
- Own login overlay / access-denied message.

No `view_count`, no "watched by N other members", no leaderboards, no other-members data anywhere on the public player. The `mediashieldConfig` payload localized on every frontend page contains player settings + watermark config + protection toggles + login messages — and nothing else.

### Legitimate non-admin REST endpoints

Only these exist by design:

- `mediashield-pro/v1/drm/license` and `/drm/offline` — `logged_in_check`. A member must be able to request *their own* DRM license to play.
- `mediashield-pro/v1/bunny/webhook` — `__return_true`. Server-to-server callback from Bunny CDN with payload validation; not a member-accessible endpoint.

## Decision rule for new feature requests

When a card lands in **Bugs**, **UI issues**, **Triage**, or **Suggestion**, ask:

1. **Does this surface analytics or other-member data to the *member*?** → almost certainly wontfix.
2. **Does this add a distribution affordance (share button, public link, "embed everywhere" widget) to the public player?** → wontfix.
3. **Does this strengthen the admin's view of *their own members'* behaviour?** → in scope.
4. **Does this make the *member's* own experience cleaner (own progress, own resume, own player UX)?** → in scope.

The exception that *would* be in scope: a **gated-access variant** of share — e.g. a B2B "send a signed link to a teammate" feature with its own access control, audit trail, and expiry. That would be a new feature card in its own lane, designed end-to-end with access in mind. Not a UI patch on top of YouTube's modal.

## How to honour this in code reviews

- Any new public-facing template, block, or shortcode: check that nothing it renders comes from "other members" data.
- Any new REST route: `permission_callback` must be either `admin_check` / `manage_options` (admin) or `logged_in_check` / `__return_true` with a documented payload validation (member or server callback).
- Any new field in `Settings::frontend_config()`: justify why a logged-in member needs it on the page. If it's an analytics field, it doesn't belong.
- Any new `wp_localize_script` for a member-facing handle: same test.

## Audit history

- **2026-05-23** — Confirmed during 1.1.0 release prep: zero share UI in either plugin; every `view_count` reference is admin-side analytics only; every public REST endpoint either gates by capability or is a documented server-to-server callback. The Youtube-Share-modal-layout card (`9829146054`) is the canonical wontfix example.
