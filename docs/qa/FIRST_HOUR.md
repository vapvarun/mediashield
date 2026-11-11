# MediaShield: first-hour walk

> The admin's, creator's and member's first hour on a FRESH install.
> QA walks this independently at every cycle start and before every release gate:
> Free only, then Free+Pro, at 1440 and 390, on the theme matrix for front-end steps (TT5, Reign, Astra, Kadence).
> Standard: `~/.claude/skills/wp-card-qa/references/first-hour-walk.md`.
> Each step passes only when the browser check AND the code-flow trace pass.
> Every escaped defect adds a step or a check line here, tagged `card:<id>`.
> Product scope: plan/PRODUCT_SCOPE.md. MediaShield is a player for gated course video. The admin sees all analytics; a member sees only their own watching.

Last confirmed: 2026-11-11, drafted by QA and reviewed by the developer against the code; pending one human read

## Site admin (minutes 0-60)

| # | Step | Where (route / screen) | Must be true | Emails |
|---|---|---|---|---|
| A1 | Activate Free (then Pro, then licence) | Plugins | A single activation lands on the setup wizard. No error-looking notice. Pro activation keeps the wizard route | none |
| A2 | Setup wizard, all 4 steps | MediaShield > Setup wizard | Step 3 "protect your first video" with a YouTube and a Vimeo URL: the real platform title (card:10482552039). The video has no level of its own, so it follows Default protection (card:10497708780). A dropped or 502 create shows a calm sentence and retry (card:10498410926) | none |
| A3 | First dashboard, empty | MediaShield > Dashboard | Every KPI 0 with a sensible empty state, no "?" icons (card:10469913731), no alarms on a healthy new site | none |
| A4 | First settings changes: watermark opacity, colour, swap interval, "Keep the watermark at the edges", Default protection, Require login | MediaShield > Settings | Each saves (auto-save shows Saving..., then Saved only when it really is; card:10498040903) and survives a reload. The player draws it (opacity 0 means invisible, card:10498041257; typing 8 saves 8, card:10498041496; edges-only is off by default, card:10498012081). The Videos list label follows the new default without a reload (card:10497708780). Defaults match screen and player (card:10498012017) | none |
| A5 | First video, three ways: classic edit screen URL paste; classic edit screen file pick then Publish without Upload; Gutenberg Video block paste | Videos > Add New; post editor | The file uploads, then saves (card:10474873956). The block preview shows the poster, not the live iframe (card:10497708882). The Videos list shows "Default (X)" for a video with no level | none |
| A6 | Visitor and member view of it | the post/lesson | A guest with Require login sees the calm login overlay. A free-preview video plays for a guest. A member sees the watermark with their name. Fullscreen keeps the watermark: the player button, the native control, a double-click, and iOS (card:10498040834). YouTube and Vimeo fill fullscreen (card:10493315237, card:10493306522). The watermark is never under the badge or controls, and long text fits at 390 (card:10498040982, card:10498041127) | none |
| A7 | First member watches | Analytics, Viewers | The member is listed with their real name and history (card:10474177546). Counts move. Milestones at 25/50/75/100 | milestone emails (Pro) if configured; read bodies |
| A8 | Owner acts on the member | Viewers, Alerts (Pro), Tools > Export/Erase Personal Data | Revoke session works. Export lists sessions and Activity Alerts. Erase removes events and alerts and sets sessions to user_id 0, and WP says all data was erased (card:10488481105) | WP privacy confirmation |
| A9 | Deactivate and reactivate | Plugins | Nothing lost or duplicated: videos, settings, sessions, the wizard state | none |
| A10 | Pro: connect Bunny Stream with CDN token auth on, then Save and test, then play HLS | MediaShield > Platform connections, Bunny Playback | Save and test catches a wrong key. HLS playlist, child and segments all 200 in Chrome, Safari and iOS; MP4 still plays; hls.js loads on block themes (card:10497708913). Non-16:9 video is not stretched (card:10497918585) | none |
| A11 | Pro with LearnDash active | Settings | The LMS Integration card shows and saves (card:10497708956). A linked video shows once per lesson, with SEO plugins and Focus Mode (card:10497708726). With Learnomy, no copy promises Learnomy auto-complete | none |
| A12 | Learnomy active (Free integration) | a Learnomy lesson with a MediaShield video | The lesson plays the MediaShield player once, with the watermark, through `learnomy_rest_lesson_payload` (Integrations/Learnomy.php). No LMS card and no lesson-link box, because linking and auto-complete are LearnDash, Tutor and LifterLMS only (card:10497708956). A Learnomy free-preview lesson plays for a guest with Require login on | none |

## Creator: an Author with `upload_mediashield` (minutes 0-60)

| # | Step | Where | Must be true | Emails |
|---|---|---|---|---|
| C1 | Gets the capability: `upload_mediashield` is granted to administrators on activation (Activator.php:64), so the owner grants it to the author's role | Users | The Videos menu appears with only what an author may do | none |
| C2 | Their Videos list, empty and with one video | Videos | Only their own videos | none |
| C3 | Uploads a video and embeds it in their post | Videos > Add New; post editor | Same as A5, as the author | none |
| C4 | Sees their viewers? | Analytics | Per PRODUCT_SCOPE, analytics is admin-only. Author gets 403 on analytics routes, with no broken screen | none |
| C5 | Cannot stream another author's protected file | REST replay | Naming another video's file in their own video is refused (card:10426248216) | none |

## Member (minutes 0-60)

| # | Step | Where | Must be true | Emails |
|---|---|---|---|---|
| M1 | Arrives logged out at a protected lesson | lesson page | Calm login overlay. No video source address in the page (P0 card:10482094152, Learnomy) | none |
| M2 | Logs in and plays | lesson page | Session starts. The watermark shows their name (entities drawn exactly, card:10497708826). Right-click and download are blocked | none |
| M3 | Leaves mid-way, comes back | lesson page | Resume from the last position | none |
| M4 | Watches to 100% | lesson page | Milestones record. LearnDash auto-complete (Pro, if linked) marks the lesson | milestone emails if configured |
| M5 | My Videos block or shortcode | account page | With an LMS or integration that supplies the watch page (Learnomy, or Pro's LearnDash, Tutor LMS or LifterLMS lesson link), titles link to that page and Resume works; without one, titles are plain text with no Resume. Paging works, 40 px tap targets at 390 | none |
| M6 | Cannot see another member's data | REST replay | /analytics/*, viewers and other members' sessions return 401 or 403 | none |

## Break it (every step above that sends a request)

Run each under: connection dropped (`route.abort`), a 502 HTML page, an 8-10 s slow answer, a double submit, and a real refusal. The bar: a calm sentence (never "Failed to fetch", "is not a valid JSON response", "Unexpected token" or a bare status code), a busy state, one row written, the saved value restored, and a working retry.

| Step | Request (route) | Dropped | 502 page | Slow | Double | Refusal |
|---|---|---|---|---|---|---|
| A2 | wizard create video: POST /wp/v2/mediashield-videos, POST /mediashield/v1/wizard/complete | PASS (create) | PASS (create) | not walked | not walked | not walked |
| A4 | settings save: PUT /mediashield/v1/settings (Free), Pro watermark/digest save | PASS (Free, Pro watermark: Not saved + Retry) | PASS (Free, Pro watermark) | PASS (Saving... then Saved, 3 s) | PASS (one PUT) | not walked |
| A5 | upload: POST /mediashield/v1/upload/init, GET /upload/status/{id}; classic Publish | PASS (upload/init) | PASS (upload/init) | not walked | not walked | not walked |
| A5 | Videos list: GET /wp/v2/mediashield-videos | PASS (no count, Try again) | PASS (no count, Try again) | not walked | not walked | not walked |
| A6/M2 | POST /mediashield/v1/session/start, heartbeat, end | not walked | not walked | not walked | not walked | not walked |
| A6/M2 | GET /mediashield/v1/stream/{id}/url refresh | not walked | not walked | not walked | not walked | not walked |
| A7 | GET /analytics/overview, /analytics/users, viewer detail | not walked | not walked | not walked | not walked | not walked |
| A8 | revoke session; Tools export/erase (core AJAX) | not walked | not walked | not walked | not walked | not walked |
| A10 | Bunny connect, Save and test | not walked | not walked | not walked | not walked | not walked |
| M5 | My Videos paging | not walked | not walked | not walked | not walked | not walked |

Developer break-it walk, 2026-11-11, round 3 (lab msb142, Free + Pro, Chromium, 1440 and 390): dropped = `route.abort('failed')`, 502 = an HTML 502 page, slow = a 3 s delay, double = two toggles inside the auto-save delay. Every walked cell read "The server could not be reached or did not answer properly. Check your connection and try again." and the retry worked (card:10498410926). QA still owns the full matrix.

## Decided (not bugs)

| Behaviour | Decision | Who / when |
|---|---|---|
| Bulk or CLI activation skips the wizard redirect | By design | existing |
| No sample viewing data on first run | Not now (synthetic analytics rows mislead owners) | owner, 2026-11-11, card 10478806397 |
| Captions; corner-only position as a feature | Not now | owner, 2026-11-11, card 10497708985 |
| Devtools size check removed | Accepted | owner, 2026-11-11, card 10498012127 |
| "Keep the watermark at the edges" is off by default; help text "keeps it in the corners, off the centre" | Accepted | owner, 2026-11-11 |
| A theme that prints the_content twice shows two players | Developer trade-off: the alternative was a lesson that could lose its video | developer, reported to the owner 2026-11-11 |
| Pro's lesson link and auto-complete support LearnDash, Tutor and LifterLMS, not Learnomy. Learnomy lessons play MediaShield videos through the Free integration (including free preview) | Video auto-complete stays out of Learnomy 2.3.0 | owner, 2026-11-11 |

## Escapes

Defects that got past a gate and were found later; each one has a step or a check line above.

| Escape | Found by | Card |
|---|---|---|
| A protected video whose session/start failed (502, drop) kept playing with no watermark; the player only logged a warning | developer break-it walk, round 3 | card:10498410926 |
| hls.js never loaded on block themes, so Chrome failed every HLS video while every CDN request was 200 | developer, real-CDN check of the Bunny fix | card:10497708913 |
| A tall Pro watermark (all 7 fields, Large) on a phone player was pushed up under the speed button and badge | QA bounce, round 3 | card:10498040982 |
| Fields that give a viewer no text (Email only, for a guest) left the video unwatermarked | QA, round 3 | card:10498041355 |

