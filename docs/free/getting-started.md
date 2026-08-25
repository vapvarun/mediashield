# MediaShield -- Getting Started

This is the Day 1 walkthrough for the free plugin. If you've already activated MediaShield and are looking at the admin pages wondering what to do next, follow the steps below in order. By the end you'll have one protected video embedded on a page, the protection layer dialled in, and analytics running.

For the full settings reference, see [`configuration.md`](configuration.md). For embed details, see [`shortcodes-blocks.md`](shortcodes-blocks.md). This guide is the journey -- the others are the manual.

## Requirements

- WordPress 6.5 or higher
- PHP 8.1 or higher
- A modern browser for the admin (Chrome, Firefox, Safari, Edge)

If you need detailed installation steps, see [`installation.md`](installation.md).

## 1. Activate the Plugin

Install MediaShield from the WordPress.org directory (or upload the ZIP), then click **Activate**. A new **MediaShield** entry appears in the WP admin sidebar with a video icon.

MediaShield's admin pages are accessible from that menu item. After activation you'll see seven sections:

| Page | What it shows |
|------|--------------|
| Dashboard | Overview stats + activity chart |
| Videos | Your video library |
| Playlists | Grouped video sequences |
| Viewers | Per-user watch progress |
| Tags | Tag dictionary for milestones |
| Milestones | Log of every completion milestone reached |
| Settings | All plugin configuration |

## 2. Run the Setup Wizard

On first activation MediaShield redirects you to a setup wizard. The wizard has four steps, in this order:

1. **General** -- toggle Enable video protection, decide whether playback requires login, and pick a default protection level. The wizard offers only the two levels most people want here: **Standard** (watermark + tracking) and **None**. Basic and Strict are available afterwards on the Settings page and per video.
2. **Platform** -- an overview of the video hosts MediaShield works with (YouTube, Vimeo, Bunny Stream, Wistia). This step is informational only: there is nothing to pick and nothing is saved. Actual API connections to those platforms are Pro-only. Self-hosted files need no setup here.
3. **First Video** -- optionally paste a video URL right now. The wizard auto-detects the platform from the URL and creates a video library entry for you. This step takes a URL only; to upload a file, use **Videos > Add New** afterwards.
4. **Watermark** -- set the overlay opacity (0.1 to 1), colour, and swap interval (5 to 120 seconds). The free watermark renders the viewer's display name and IP address.

Each step saves when you click **Save & Continue**; **Skip this step** moves on without saving that step.

The wizard has no menu item of its own - it opens once, on first activation. If you close the tab part-way through, everything you already saved is kept, but to get back to the remaining steps you need to go to `wp-admin/admin.php?page=mediashield-wizard` directly. Everything the wizard sets can also be set from **MediaShield > Settings**, so skipping it costs you nothing.

If you skipped the First Video step, head to the Videos page next.

## 3. Add Your First Video

Open **MediaShield > Videos** and click **Add New Video**.

You'll be asked for:

- **Platform** -- pick from Self-hosted, YouTube, Vimeo, Bunny, or Wistia.
- **Source URL or Video ID** -- paste the platform URL. MediaShield works out which platform it is and extracts the video identifier. If it does not recognise the address, **it now tells you** rather than quietly filing the video as self-hosted -- which used to produce a player that never started, with nothing anywhere to explain why.
  - Pasting the address bar from the **Bunny Stream dashboard** works. If you paste a Bunny *collection* address (a folder of videos) it will say so and ask for the video itself.
  - Already have videos saved wrongly this way from an older version? Run `wp mediashield repair bunny-urls` to find and fix them. It shows you what it would change before changing anything; add `--execute` to apply.
- **Or upload a file** -- new in 1.3.0. Pick a video file and upload it straight from this screen, with a progress bar. Before 1.3.0 there was no uploader: you had to go to the Media Library, upload there, and paste the resulting address back. Your server's upload size limit still applies.
- **Protection Level** -- choose the per-video override:
  - **None** -- no protection and no login gate; the video plays as a normal embed.
  - **Basic** -- login gate plus the browser-level protections you turned on in Settings. No watermark, and **no tracking**, so a Basic video never shows up in your analytics.
  - **Standard** -- login gate, watermark, session tracking, and milestones. This is what you want for course content.
  - **Strict** -- everything in Standard, and it forces developer-tools detection and source-URL hiding on for this video even if those global toggles are off.
  - Leave it on the default to inherit the site-wide setting.
- **Access Role** (optional) -- restrict playback to a specific WordPress role. Leave blank for all logged-in users.
- **Milestone Tags** (optional) -- give a tag name to the 10%, 25%, 50%, 75%, and 100% marks, and tick the ones you want active. When a viewer crosses a marked point, that tag is recorded against them. See step 7.

Save. The video is now in your library, ready to embed.

> **YouTube / Vimeo / Wistia note:** the free plugin plays embedded videos from these hosts via their normal embeds, with the MediaShield wrapper around them. To actually browse, import, or upload through a platform's API, you need Pro -- see the [Pro platform connections guide](../pro/platform-connections.md).

> **Only videos in your library are protected.** An embed you paste straight into a post is left exactly as it is. MediaShield does scan your pages for videos, but it only takes over an embed when it recognises it as one of the videos in your MediaShield library. Adding the video here is what makes that match possible.

## 4. Embed the Video

You have three ways to put the video on a page. All three produce the same protected output:

### a. Block editor (Gutenberg)

In the block inserter, search for **MediaShield Video**. The block opens a video picker where you select the video you just created. You can also paste a fresh video URL into the block to create a library entry on the fly. The editor renders a live preview; the frontend output includes the player, watermark, session tracking, and the login overlay when required.

### b. Shortcode

Drop this into post content, a page builder text widget, or a custom widget:

```text
[mediashield id=42]
```

Only one attribute (`id`) -- the ID number from your video library. If the ID doesn't match a published video, visitors see nothing at all, while you (and anyone else who can edit posts) see a short message on the page explaining what went wrong.

### c. "User's Watched Videos" shortcode

Build a member dashboard with one line:

```text
[mediashield_my_videos]
```

Renders a grid of every video the current logged-in user has watched, with completion bars and All / In Progress / Completed filters. Visitors who aren't logged in see a short "Please log in to see your video history" line instead -- it's a member-area widget by design.

Full attribute reference and template patterns are in [`shortcodes-blocks.md`](shortcodes-blocks.md).

## 5. Configure Protection

Open **MediaShield > Settings**. Everything auto-saves as you change it.

The sections you'll touch on Day 1:

- **General** -- the master on/off switch, default protection level, and login requirement.
- **Protection** -- right-click blocking, the Ctrl+S / Cmd+S block, source URL hiding, developer-tools detection, and the text shown on the developer-tools overlay.
- **Watermark** -- opacity, colour, swap interval, and whether the "Protected by MediaShield" badge appears.
- **Allowed Domains** and **Concurrent Streams** -- which domains may embed your videos, and how many streams one account can hold at once.
- **Login & Access Messages** -- the wording shown when someone has to log in or lacks access.
- **Player Controls** -- global defaults for speed control, keyboard shortcuts, playback resume, sticky player, and end-screen call-to-action. Every one of these can be overridden per-video via the video edit screen.
- **Video Ads** -- where sponsor ad breaks land inside your videos. Only does anything if you also run WB Ad Manager.

For a complete description of every setting, see [`configuration.md`](configuration.md).

## 6. Watch the Analytics Roll In

Open **MediaShield > Dashboard**. Have a logged-in user watch the video you embedded; within about 30 seconds you'll see numbers move.

The dashboard surfaces:

- **Four stat cards** -- Total Videos, Total Sessions, Avg Completion, Active Viewers. The date filter (Today / 7 days / 30 days / 90 days) rescopes all four.
- **Activity chart** -- daily session counts, grouped by your site's timezone.
- **Top videos** -- best-performing videos for the active date range.
- **Recent milestones** -- the latest 25 / 50 / 75 / 100% completions. Since 1.1.0, this card honours the date filter alongside everything else on the page.

## 7. Set Up Milestones

MediaShield records four completion points per video -- 25%, 50%, 75%, and 100% -- the first time each logged-in viewer crosses one. Nothing is recorded for logged-out viewers, or for videos set to Basic or None.

There are two places involved, and it's worth knowing which does what:

- **The video edit screen** is where you set milestones up. Its **Milestone Tags** box lists 10%, 25%, 50%, 75%, and 100%. Type a tag name against a percentage and tick **Active**, and a viewer who reaches that point gets the tag recorded against them. The tag is also added to your Tags library so it shows up alongside your other tags. Ticking a percentage that isn't one of the four standard ones - 10%, say - starts tracking that point too.
- **MediaShield > Milestones** is the read-only log of what has happened: who reached which milestone on which video, and when, newest first, with paging. There is nothing to configure on this page.

Custom code can react to milestones via the `mediashield_milestone_reached` action -- see the [developer hooks reference](../developer/hooks-filters-free.md).

## 8. Build a Playlist (new in 1.1.0)

If you have a series (a course module, a tutorial sequence, a chapter playlist) build a playlist under **MediaShield > Playlists**:

1. Click **Add New Playlist**.
2. Drag-and-drop the videos you want in order.
3. Toggle autoplay, countdown (seconds between videos), loop, and shuffle.

Embed it with either:

```text
[mediashield_playlist id=15]
```

Or the **MediaShield Playlist** block -- pick the playlist in the block picker. Both routes produce identical output.

Every video inside the playlist runs through the same protection layer, session tracking, and milestone detection as a stand-alone embed.

## 9. Optional Surfaces

Two pages you don't need on Day 1 but will return to:

- **Tags** -- the dictionary backing milestone tags and any tags you assign manually. You can add a tag by name (the slug is generated for you) and delete tags that are no longer in use. Tag names can't be edited after creation; delete and re-add instead.
- **Viewers** -- per-user watch progress. Click any user to see every video they've watched, their progress, and when they last watched it. This is the page you'll open when a learner emails you saying "did my completion register?"

## What's in Pro

If you outgrow the free protection-and-tracking layer, MediaShield Pro adds the things that don't fit the "player" baseline:

- **ClearKey DRM** via Bunny Stream or local packager
- **Real-time dashboard** -- live viewer count with auto-refresh
- **Per-video heatmaps** -- playback position buckets so you can see where viewers drop off
- **Suspicious activity alerts** -- multi-device usage, developer-tools opens, rapid-seek detection
- **Platform connections** -- API uploaders for Bunny, YouTube, Vimeo, Wistia
- **LMS integrations** -- LearnDash, LifterLMS, TutorLMS lesson gating
- **Data export** -- CSV and PDF reports
- **Weekly digest email** -- automated analytics summary
- **Advanced watermark** -- 7 configurable fields
- **Milestone actions** -- on milestone, also tag user / send email / fire webhook

When you're ready to upgrade, [`docs/pro/getting-started.md`](../pro/getting-started.md) is the matching walkthrough on the Pro side. Pro is purely additive -- it never replaces a free behaviour, only extends it -- so everything you set up above keeps working unchanged.

## Where to Go Next

- [Configuration reference](configuration.md) -- every setting explained
- [Shortcodes & blocks](shortcodes-blocks.md) -- complete embed reference
- [FAQ](faq.md) -- common questions
- [Troubleshooting](troubleshooting.md) -- when the player doesn't render or analytics don't move
- [Protection philosophy](protection-philosophy.md) -- what MediaShield does and doesn't promise about preventing rip

---

For developers: hooks, REST API, and database tables are in [`docs/developer/`](../developer/README.md).
