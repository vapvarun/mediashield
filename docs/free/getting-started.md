# MediaShield -- Getting Started

This is the Day 1 walkthrough for the free plugin. If you've already activated MediaShield and are looking at the admin SPA wondering what to do next, follow the steps below in order. By the end you'll have one protected video embedded on a page, the protection layer dialled in, and analytics running.

For exhaustive option references, see [`configuration.md`](configuration.md). For embed mechanics, see [`shortcodes-blocks.md`](shortcodes-blocks.md). This guide is the journey -- the others are the manual.

## Requirements

- WordPress 6.5 or higher
- PHP 8.1 or higher
- A modern browser for the admin (Chrome, Firefox, Safari, Edge)

If you need detailed installation steps, see [`installation.md`](installation.md).

## 1. Activate the Plugin

Install MediaShield from the WordPress.org directory (or upload the ZIP), then click **Activate**. A new **MediaShield** entry appears in the WP admin sidebar with the video icon (`dashicons-video-alt3`, position 30).

MediaShield is a single-page React app behind one top-level menu. After activation the admin SPA exposes seven hash routes:

| Route | Page |
|-------|------|
| `#/dashboard` | Overview stats + activity chart |
| `#/videos` | Video CRUD list |
| `#/playlists` | Playlist management |
| `#/tags` | Tag dictionary |
| `#/students` | Per-user watch progress |
| `#/milestones` | Milestone configuration |
| `#/settings` | Plugin settings |

All routes live under `wp-admin/admin.php?page=mediashield`. Bookmark `wp-admin/admin.php?page=mediashield#/dashboard` if you want to land on stats every time.

## 2. Run the Setup Wizard

On first activation MediaShield sets a one-shot transient (`ms_activation_redirect`) and on the next admin page load redirects you to `wp-admin/admin.php?page=mediashield-wizard`. The wizard page is hidden from the menu -- it's only reachable via the redirect, and it disappears once you complete it (the `ms_wizard_completed` option flips to `true`).

The wizard has four steps:

1. **General** -- toggle `Enable Protection`, pick a default protection level (None / Basic / Standard / Strict), and decide whether playback requires login.
2. **Platform** -- tell MediaShield which video host you use: Self-hosted, YouTube, Vimeo, Bunny Stream, or Wistia. The wizard just records which platforms you intend to use; actual API connections to Bunny / YouTube / Vimeo / Wistia are Pro-only.
3. **Watermark** -- set the overlay opacity, colour, and swap interval. The free watermark renders the logged-in viewer's display name and IP address.
4. **First Video** -- optionally paste a video URL right now. The wizard auto-detects the platform from the URL and creates a `mediashield_video` CPT for you.

Each step auto-saves through `PUT /wp-json/mediashield/v1/settings`. If you close the tab mid-wizard, your progress sticks -- when you come back, you'll land on the dashboard, and you can re-open the wizard manually via `wp-admin/admin.php?page=mediashield-wizard` until you click **Finish**.

If you skipped step 4, head to the Videos page next.

## 3. Add Your First Video

Open **MediaShield > Videos** (`#/videos`) and click **Add New Video**.

You'll be asked for:

- **Platform** -- pick from `self`, `youtube`, `vimeo`, `bunny`, or `wistia` (stored as `_ms_platform` meta).
- **Source URL or Video ID** -- paste the platform URL. MediaShield extracts the platform video ID into `_ms_platform_video_id` and keeps the original URL in `_ms_source_url`.
- **Protection Level** -- choose the per-video override:
  - **None** -- no protection; the video plays as a normal embed.
  - **Basic** -- right-click disabled, source URL hidden.
  - **Standard** -- everything in Basic plus the dynamic watermark and devtools detection.
  - **Strict** -- everything in Standard plus keyboard shortcut blocking and a fullscreen watermark.
  - Leave empty to inherit the wizard's default (`ms_default_protection`).
- **Access Role** (optional) -- restrict playback to a specific WordPress role (`_ms_access_role`). Leave blank for all logged-in users.

Save. The video is now a published `mediashield_video` CPT, ready to embed.

> **YouTube / Vimeo / Wistia note:** the free plugin plays embedded videos from these hosts via their normal `<iframe>` embeds, with the MediaShield wrapper around them. To actually pull a video catalogue from Bunny / YouTube / Vimeo / Wistia (browse, import, manage uploads through their API), you need Pro -- see the [Pro platform connections guide](../pro/platform-connections.md).

## 4. Embed the Video

You have three ways to put the video on a page. All three produce the same protected output through `Player\Renderer`:

### a. Gutenberg block

In the block inserter, search for **MediaShield Video** (`mediashield/video`). The block opens a video picker modal where you select the CPT you just created. You can also paste a fresh video URL into the block to auto-create a CPT on the fly. The editor renders a live preview; the frontend output includes the player wrapper, watermark, session tracking, and the login overlay when required.

### b. Shortcode

Drop this into post content, a page builder text widget, or a custom widget:

```text
[mediashield id=42]
```

Only one attribute (`id`) -- the video CPT post ID. Invalid, missing, or non-published IDs render nothing and skip asset enqueue.

### c. "User's Watched Videos" shortcode

Build a member dashboard with one line:

```text
[mediashield_my_videos]
```

Renders a grid of every video the current logged-in user has touched, with completion bars. Outputs nothing for anonymous visitors -- it's a member-area widget by design.

Full attribute reference and PHP template patterns are in [`shortcodes-blocks.md`](shortcodes-blocks.md).

## 5. Configure Protection

Open **MediaShield > Settings** (`#/settings`). The page is one form with several sections; everything auto-saves through `PUT /wp-json/mediashield/v1/settings`.

The sections you'll touch on Day 1:

- **General** -- `ms_enabled` (master kill switch), `ms_default_protection`, `ms_require_login`, `ms_show_badge`.
- **Watermark** -- opacity (`0-1`), colour (hex, normalised to lowercase), and swap interval in seconds.
- **Access Control** -- `ms_max_concurrent_streams` (default `2`) caps how many simultaneous sessions one account can hold; `ms_allowed_domains` is a comma-separated whitelist for embed origins (empty disables the check). Empty-Referer requests deny by default; if you embed from a context that strips Referer and you trust it, opt back in with the `mediashield_allow_empty_referer` filter.
- **Player Controls** -- global defaults for speed control, sticky player, keyboard shortcuts, playback resume, and end-screen CTA. Every one of these is overridable per-video on the Video CPT metabox via a tri-state (on / off / inherit).
- **Protection Controls** -- right-click blocking, keyboard capture-shortcut blocking, source URL hiding, devtools detection, pause-on-devtools, and the strings shown on the devtools overlay.

For the complete option matrix with every default, validator, and per-video override key, see [`configuration.md`](configuration.md).

## 6. Watch the Analytics Roll In

Open **MediaShield > Dashboard** (`#/dashboard`). Have a logged-in user watch the video you embedded; within ~30 seconds you'll see numbers move because `assets/js/tracker.js` POSTs a heartbeat to `/wp-json/mediashield/v1/session/heartbeat` on that cadence.

The dashboard surfaces:

- **Four KPI cards** -- Total Videos, Total Sessions, Avg Completion, Active Viewers. The date filter (Today / 7 days / 30 days / 90 days) rescopes all four.
- **Activity chart** -- daily session counts, grouped by the WP site timezone (via `CONVERT_TZ()` in the analytics query, so the daily buckets line up with what you see on the calendar, not UTC).
- **Top videos** -- best-performing videos for the active date range.
- **Recent milestones** -- the latest 25 / 50 / 75 / 100% completions. Since 1.1.0, this card honours the date filter alongside everything else on the page.

## 7. Set Up Milestones

Open **MediaShield > Milestones** (`#/milestones`). Out of the box MediaShield fires four hooks per video -- `mediashield_milestone_25`, `_50`, `_75`, `_100` -- when a viewer crosses each threshold. The page lets you:

- See per-video completion counts at each threshold.
- Assign a **milestone tag** per video per percentage. When the viewer hits that milestone, the tag is promoted into the unified `ms_tags` dictionary (via `TagManager::ensure()`), linked to the video in `ms_video_tags`, and recorded on the user with `tag_id` alongside the display string in `_ms_video_tags` user meta. The tag then shows up on the user's profile and is queryable like any other tag.

Custom code can react to milestones via the `mediashield_milestone_reached` action or any of the per-percentage variants -- see [`hooks-filters.md`](hooks-filters.md).

## 8. Build a Playlist (new in 1.1.0)

If you have a series (a course module, a tutorial sequence, a chapter playlist) build a `mediashield_playlist` CPT under **MediaShield > Playlists** (`#/playlists`):

1. Click **Add New Playlist**.
2. Drag-and-drop the videos you want in order.
3. Toggle `_ms_autoplay`, `_ms_countdown` (seconds between videos), `_ms_loop`, and `_ms_shuffle`.

Embed it with either:

```text
[mediashield_playlist id=15]
```

Or the **MediaShield Playlist** block (`mediashield/playlist`) -- pick the playlist in the block picker. Both routes delegate to `Player\PlaylistRenderer` so the output is identical.

Every video inside the playlist runs through the same protection layer, session tracking, and milestone detection as a stand-alone embed.

## 9. Optional Surfaces

Two pages you don't need on Day 1 but will return to:

- **Tags** (`#/tags`) -- the dictionary backing milestone tags and any tags you assign manually. Edit names, slugs, descriptions; delete tags that are no longer in use. Tags live in `ms_tags` and link to videos via `ms_video_tags`.
- **Students** (`#/students`) -- per-user watch progress. Click any user to see every video they've started, their max position, completion percentage, and milestone history. This is the page you'll open when a learner emails you saying "did my completion register?"

## What's in Pro

If you outgrow the free protection-and-tracking layer, MediaShield Pro adds the things that don't fit the "player" baseline:

- **ClearKey DRM** via Bunny Stream or local Shaka Packager
- **Real-time dashboard** -- live viewer count with auto-refresh
- **Per-video heatmaps** -- playback position buckets so you can see where viewers drop off
- **Suspicious activity alerts** -- multi-IP usage, devtools opens, rapid-seek detection routed into an Alerts page
- **Email gate** -- capture an email before the video plays, with webhook integration
- **Platform connections** -- API uploaders for Bunny, YouTube, Vimeo, Wistia (the free plugin embeds them; Pro imports and manages them)
- **LMS integrations** -- LearnDash, LifterLMS, TutorLMS lesson gating
- **Data export** -- streaming CSV + async PDF reports
- **Weekly digest email** -- automated analytics summary
- **Advanced watermark** -- 7 configurable fields (username, email, IP, user ID, timestamp, site name, custom text)
- **Milestone actions** -- on milestone, also tag user / send email / fire webhook (free fires the hook; Pro adds turnkey actions on top)

When you're ready to upgrade, [`docs/pro/getting-started.md`](../pro/getting-started.md) is the matching walkthrough on the Pro side. Pro is purely additive -- it never replaces a free behaviour, only extends through hooks -- so everything you set up above keeps working unchanged.

## Where to Go Next

- [Configuration reference](configuration.md) -- every option with its default and validator
- [Shortcodes & blocks](shortcodes-blocks.md) -- complete embed reference, asset-loading rules, custom URL patterns
- [Hooks & filters](hooks-filters.md) -- extension points for developers
- [FAQ](faq.md) -- common questions
- [Troubleshooting](troubleshooting.md) -- when the player doesn't render or analytics don't move
- [Protection philosophy](protection-philosophy.md) -- what MediaShield does and doesn't promise about preventing rip
