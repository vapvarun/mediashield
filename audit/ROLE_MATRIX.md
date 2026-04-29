# MediaShield FREE — Role Matrix

**Generated:** 2026-04-30
**Source:** [`audit/manifest.json`](manifest.json)

This matrix documents which actions each WordPress role can take. Permissions are enforced primarily through `manage_options` (admin) for management endpoints and `is_user_logged_in` for viewer-side endpoints, modulated by the `mediashield_can_watch` filter chain.

Legend: **C** = Create · **R** = Read · **U** = Update · **D** = Delete · **–** = No access · **R*** = Read with custom rules (see footnotes)

---

## Admin SPA + Settings

| Feature | Administrator | Editor | Author | Subscriber | Anonymous |
|---|---|---|---|---|---|
| Admin Dashboard | R | – | – | – | – |
| Videos (CPT manage) | CRUD | – | – | – | – |
| Playlists (CPT manage) | CRUD | – | – | – | – |
| Tags (manage) | CRUD | – | – | – | – |
| Settings | RU | – | – | – | – |
| Setup Wizard | RU | – | – | – | – |
| Milestone configuration | RU | – | – | – | – |
| Suspicious-activity review¹ | – | – | – | – | – |
| Analytics (Overview / Users / Milestones) | R | – | – | – | – |
| User-watch-progress (Students) | R | – | – | – | – |

> ¹ Suspicious-activity review is a PRO feature; row included for matrix completeness.

## Video playback (per-video, mediated by `mediashield_can_watch`)

| Action | Administrator | Editor | Author | Subscriber | Anonymous |
|---|---|---|---|---|---|
| Watch a public video (no role gate) | R | R | R | R | R* |
| Watch a video gated by `_ms_access_role` | R | R* | R* | R* | – |
| Watch when `ms_require_login = true` | R | R | R | R | – |
| Stream protected media file (`/stream/{video_id}`) | R | R | R | R | R* |
| Right-click / keyboard shortcuts on player | blocked | blocked | blocked | blocked | blocked |
| See dynamic watermark (with viewer email/name) | R | R | R | R | R (no email if anon) |

> R* = depends on settings. Anonymous users can watch only when `ms_require_login = false` AND no role gate is set on the video. Role-gated videos pass `mediashield_can_watch` only when the user has the assigned role.

## Watch sessions

| Action | Administrator | Editor | Author | Subscriber | Anonymous |
|---|---|---|---|---|---|
| Start a session (`POST /session/start`) | C | C | C | C | C* |
| Send heartbeat (`POST /session/heartbeat`) | U | U | U | U | U* |
| End own session (`POST /session/end`) | U | U | U | U | U* |
| Revoke another user's sessions (`POST /session/revoke-user`) | D | – | – | – | – |
| View own watch history (`/analytics/my-videos`) | R | R | R | R | – |

> C*/U* = anonymous users get a session token only when access is granted; tokens are HMAC-signed with limited TTL.

## Tags + Tagging

| Action | Administrator | Editor | Author | Subscriber | Anonymous |
|---|---|---|---|---|---|
| List all tags (`GET /tags`) | R | – | – | – | – |
| Create tag (`POST /tags`) | C | – | – | – | – |
| Update / delete tag (`PUT/DELETE /tags/{id}`) | UD | – | – | – | – |
| Get tags for a video (`GET /videos/{id}/tags`) | R | – | – | – | – |
| Assign / remove tag from video | CD | – | – | – | – |

## Playlists

| Action | Administrator | Editor | Author | Subscriber | Anonymous |
|---|---|---|---|---|---|
| List items (`GET /playlists/{id}/items`) | R | – | – | – | – |
| Add item (`POST /playlists/{id}/items`) | C | – | – | – | – |
| Remove item (`DELETE /playlists/{id}/items/{item}`) | D | – | – | – | – |
| Reorder items (`POST /playlists/{id}/items/reorder`) | U | – | – | – | – |
| View playlist on frontend | R | R | R | R | R* |

## Uploads

| Action | Administrator | Editor | Author | Subscriber | Anonymous |
|---|---|---|---|---|---|
| Initialize upload (`POST /upload/init`) | C | – | – | – | – |
| Check upload status (`GET /upload/status/{id}`) | R | – | – | – | – |

> Editor / Author roles can be granted upload via the `mediashield_upload_drivers` filter or through Pro's per-role upload-permission feature.

## Privacy + GDPR

| Action | Administrator | Editor | Author | Subscriber | Anonymous |
|---|---|---|---|---|---|
| Export own data (WordPress Tools → Export Personal Data) | R | R | R | R | – |
| Erase own data (WordPress Tools → Erase Personal Data) | D | D | D | D | – |
| Approve / process privacy requests | RUD | – | – | – | – |

## Devtools-event reporting

| Action | Administrator | Editor | Author | Subscriber | Anonymous |
|---|---|---|---|---|---|
| Log devtools-detected event (`POST /protection/devtools-event`) | C | C | C | C | C* |

> C* = rate-limited per IP via `ms_devtools_rl_*` options. Anonymous users can submit but at much lower throughput.

---

## Custom capabilities

MediaShield FREE does NOT define custom WP capabilities. All checks gate on `manage_options` (admin) or `is_user_logged_in`. The `mediashield_can_watch` filter chain is the extension point for finer-grained access control — Pro adds role-based, email-gate, and LMS-enrollment checks here.

## How to extend permissions

Plugins or themes that need to expose admin features to non-admin roles should:

1. **Filter `mediashield_can_watch`** to add custom access logic.
2. **Override capability checks** by registering REST permission filters (e.g., `rest_pre_dispatch`) — but be aware this bypasses the manifest's documented contract, so future PRO features may not respect overrides.
3. **For role-based access**, prefer installing MediaShield Pro which exposes `RoleAccess` declaratively via the `_ms_access_role` post meta.
