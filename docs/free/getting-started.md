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
| Tags | Tag dictionary for milestones |
| Students | Per-user watch progress |
| Milestones | Completion percentage settings |
| Settings | All plugin configuration |

## 2. Run the Setup Wizard

On first activation MediaShield redirects you to a setup wizard. The wizard has four steps:

1. **General** -- toggle Enable Protection, pick a default protection level (None / Basic / Standard / Strict), and decide whether playback requires login.
2. **Platform** -- tell MediaShield which video host you use: Self-hosted, YouTube, Vimeo, Bunny Stream, or Wistia. The wizard just records which platforms you intend to use; actual API connections to Bunny / YouTube / Vimeo / Wistia are Pro-only.
3. **Watermark** -- set the overlay opacity, colour, and swap interval. The free watermark renders the logged-in viewer's display name and IP address.
4. **First Video** -- optionally paste a video URL right now. The wizard auto-detects the platform from the URL and creates a video library entry for you.

Each step auto-saves as you go. If you close the tab mid-wizard, your progress sticks -- when you come back, you'll land on the dashboard, and you can re-open the wizard manually until you click **Finish**.

If you skipped step 4, head to the Videos page next.

## 3. Add Your First Video

Open **MediaShield > Videos** and click **Add New Video**.

You'll be asked for:

- **Platform** -- pick from Self-hosted, YouTube, Vimeo, Bunny, or Wistia.
- **Source URL or Video ID** -- paste the platform URL. MediaShield works out which platform it is and extracts the video identifier. If it does not recognise the address, **it now tells you** rather than quietly filing the video as self-hosted -- which used to produce a player that never started, with nothing anywhere to explain why.
  - Pasting the address bar from the **Bunny Stream dashboard** works. If you paste a Bunny *collection* address (a folder of videos) it will say so and ask for the video itself.
  - Already have videos saved wrongly this way from an older version? Run `wp mediashield repair bunny-urls` to find and fix them. It shows you what it would change before changing anything; add `--execute` to apply.
- **Or upload a file** -- new in 1.3.0. Pick a video file and upload it straight from this screen, with a progress bar. Before 1.3.0 there was no uploader: you had to go to the Media Library, upload there, and paste the resulting address back. Your server's upload size limit still applies.
- **Protection Level** -- choose the per-video override:
  - **None** -- no protection; the video plays as a normal embed.
  - **Basic** -- right-click disabled, source URL hidden.
  - **Standard** -- everything in Basic plus the dynamic watermark and developer-tools detection.
  - **Strict** -- everything in Standard plus keyboard shortcut blocking and a fullscreen watermark.
  - Leave empty to inherit the wizard's default.
- **Access Role** (optional) -- restrict playback to a specific WordPress role. Leave blank for all logged-in users.

Save. The video is now in your library, ready to embed.

> **YouTube / Vimeo / Wistia note:** the free plugin plays embedded videos from these hosts via their normal embeds, with the MediaShield wrapper around them. To actually browse, import, or upload through a platform's API, you need Pro -- see the [Pro platform connections guide](../pro/platform-connections.md).

## 4. Embed the Video

You have three ways to put the video on a page. All three produce the same protected output:

### a. Block editor (Gutenberg)

In the block inserter, search for **MediaShield Video**. The block opens a video picker where you select the video you just created. You can also paste a fresh video URL into the block to create a library entry on the fly. The editor renders a live preview; the frontend output includes the player, watermark, session tracking, and the login overlay when required.

### b. Shortcode

Drop this into post content, a page builder text widget, or a custom widget:

```text
[mediashield id=42]
```

Only one attribute (`id`) -- the ID number from your video library. Invalid or missing IDs render nothing.

### c. "User's Watched Videos" shortcode

Build a member dashboard with one line:

```text
[mediashield_my_videos]
```

Renders a grid of every video the current logged-in user has watched, with completion bars. Shows nothing for visitors who aren't logged in -- it's a member-area widget by design.

Full attribute reference and template patterns are in [`shortcodes-blocks.md`](shortcodes-blocks.md).

## 5. Configure Protection

Open **MediaShield > Settings**. Everything auto-saves as you change it.

The sections you'll touch on Day 1:

- **General** -- the master on/off switch, default protection level, login requirement, and badge visibility.
- **Watermark** -- opacity, colour, and swap interval.
- **Access Control** -- how many simultaneous streams one account can hold; which domains are allowed to embed your videos.
- **Player Controls** -- global defaults for speed control, sticky player, keyboard shortcuts, playback resume, and end-screen call-to-action. Every one of these can be overridden per-video via the video edit screen.
- **Protection Controls** -- right-click blocking, keyboard shortcut blocking, source URL hiding, developer-tools detection, and the text shown on the developer-tools overlay.

For a complete description of every setting, see [`configuration.md`](configuration.md).

## 6. Watch the Analytics Roll In

Open **MediaShield > Dashboard**. Have a logged-in user watch the video you embedded; within about 30 seconds you'll see numbers move.

The dashboard surfaces:

- **Four stat cards** -- Total Videos, Total Sessions, Avg Completion, Active Viewers. The date filter (Today / 7 days / 30 days / 90 days) rescopes all four.
- **Activity chart** -- daily session counts, grouped by your site's timezone.
- **Top videos** -- best-performing videos for the active date range.
- **Recent milestones** -- the latest 25 / 50 / 75 / 100% completions. Since 1.1.0, this card honours the date filter alongside everything else on the page.

## 7. Set Up Milestones

Open **MediaShield > Milestones**. MediaShield tracks four completion points per video -- 25%, 50%, 75%, and 100% -- when a viewer crosses each one. The page lets you:

- See per-video completion counts at each threshold.
- Assign a **milestone tag** per video per percentage. When the viewer hits that milestone, the tag is automatically organized in your Tags library, where you can manage it alongside other tags.

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

- **Tags** -- the dictionary backing milestone tags and any tags you assign manually. Edit names, slugs, descriptions; delete tags that are no longer in use.
- **Students** -- per-user watch progress. Click any user to see every video they've started, their furthest position, completion percentage, and milestone history. This is the page you'll open when a learner emails you saying "did my completion register?"

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
