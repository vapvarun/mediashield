# MediaShield -- Configuration Guide

All settings are managed from the **MediaShield > Settings** admin page, which auto-saves as you make changes. Settings are grouped into sections. This guide explains what each setting does and when to change it.

---

## General Settings

| Setting | Default | What it does |
|---------|---------|-------------|
| Enable Protection | On | Master toggle. Turn this off to disable all protection and session tracking site-wide. Use it for maintenance, not permanently. |
| Default Protection Level | Standard | Baseline protection for all videos. Applies when a video has no per-video override. Choose from None, Basic, Standard, or Strict. |
| Require Login | On | Forces viewers to log in before any video plays. Turn this off only for public preview videos. |
| Show Badge | On | Displays a "Protected by MediaShield" badge on the player. Many site owners turn this off for a cleaner look. |

### Protection levels

- **None** -- No protection. Videos play as normal embeds.
- **Basic** -- Right-click is disabled, source URL is hidden.
- **Standard** -- Everything in Basic, plus the dynamic watermark overlay and developer-tools detection.
- **Strict** -- Everything in Standard, plus keyboard shortcut blocking and a fullscreen watermark.

---

## Watermark Settings

The watermark is a text overlay that sits on top of the video while it plays. It shows the viewer's identity — name and IP address — so you can trace leaked recordings back to the source.

| Setting | Default | What it does |
|---------|---------|-------------|
| Opacity | 50% | How visible the watermark is. 0% is invisible, 100% is fully solid. A value around 30–50% is visible without being distracting. |
| Color | White | The watermark text color. Use a color that's visible against your video content. |
| Swap Interval | 30 seconds | How often the watermark moves to a new position. Shorter intervals make it harder to crop out. |

The free watermark shows the viewer's display name and IP address. Pro adds 7 configurable fields.

---

## Access Control

| Setting | Default | What it does |
|---------|---------|-------------|
| Max Concurrent Streams | 2 | How many devices can play simultaneously under one account. When a viewer tries to open a third stream, they get an error until they close another. |
| Allowed Domains | Empty | Comma-separated list of domains that are allowed to embed your videos. Leave empty to allow embeds from any domain. When filled in, requests with a missing origin header are denied by default. |
| Login Overlay Text | "Please log in to watch this video" | The message shown when a visitor tries to watch but isn't logged in. Edit to match your brand voice. |
| Login Button Text | "Log In" | The label on the login button in the overlay. |
| Access Denied Text | "You do not have access to this video" | Shown when a logged-in user doesn't have the right role for a specific video. |

---

## Upload Settings

| Setting | Default | What it does |
|---------|---------|-------------|
| Max Upload Size | 500 MB | The largest file a user with upload permission can upload in one go. |

Self-hosted videos are stored in your WordPress uploads folder in a protected subfolder.

---

## Custom URL Patterns

| Setting | Default | What it does |
|---------|---------|-------------|
| Custom URL Patterns | Empty | Additional URL patterns for MediaShield's automatic video detection. Add one pattern per line. Use this when you embed videos from a host that MediaShield doesn't recognize automatically. |

---

## Player Controls

The **Player Controls** section of the settings page tunes how the video player behaves for the people watching. Each control is a global default that you can override per-video on the video edit screen -- so you can keep speed control off site-wide and turn it on for one specific course.

### What each control does (and when to enable it)

**Show playback speed control.** Adds a 0.5×-2× speed selector to the player so viewers can speed lectures up or slow tutorials down. Turn this on for course / training / lecture content -- students will reach for it on day one. Self-hosted and Bunny videos only; YouTube / Vimeo / Wistia embeds use the host player's built-in speed menu, so this toggle has no effect there.

**Sticky player.** When the viewer scrolls past the video, the player shrinks into a corner overlay so they can keep watching while reading transcripts, notes, or comments below. Great for long-form tutorial pages and webinar replays. Skip it on short videos or galleries with multiple players on one page -- the floating overlay can feel intrusive when the video is only 30 seconds long.

**Keyboard shortcuts.** Lets viewers use the keyboard while the player is focused -- **Space** to play / pause, **left / right arrows** to seek five seconds, **up / down** for volume, **M** to mute, **F** for fullscreen. Turn it on if you have a power-user audience (developers, designers, course completers); leave it off if your audience is non-technical and you're worried about accidental key presses.

**Resume playback.** Remembers where each viewer left off and shows a "Resume from X:XX?" prompt the next time they open the same video. This is the single highest-impact control for course content -- students rarely finish a 40-minute lesson in one sitting, and forcing them to scrub back is a guaranteed drop-off point.

**End-screen message + URL.** When the video finishes, MediaShield can show a short call-to-action overlay with a message and a clickable button. Use it to push viewers to the next lesson ("Continue to Module 2"), an upsell ("Get the full course"), or a related video. Leave both fields blank to fall back to the global default, or fill them in per video to customise per topic.

**Hide source URL.** When on, MediaShield moves the raw video URL out of the visible page source so it doesn't appear when someone clicks "View Source". This is not a real DRM boundary -- a determined user can still find the URL with browser developer tools -- but it stops the most casual scraping. Leave it on unless you have a specific need to expose the URL.

### Player controls settings

| Setting | Default | Description |
|---------|---------|-------------|
| Speed Control | On | Show the playback-rate menu (self-hosted / Bunny only) |
| Sticky Player | Off | Pin the player to a corner when scrolled off-screen during playback |
| Keyboard Shortcuts | On | Allow Space/Arrow/M/F shortcuts on the player |
| Resume Playback | On | Resume from the last reached position when re-opening a video |
| End Screen | Off | Render the end-screen call-to-action when the video ends |
| End Screen Text | Empty | Call-to-action text (global fallback when a video doesn't set its own) |
| End Screen URL | Empty | Call-to-action URL |

> **Per-video overrides:** every Player Control can be set individually on each video via the video edit screen. The per-video setting wins over the global default.

---

## Protection Controls

Controls for the technical protection layer that runs in the browser while the video is playing.

| Setting | Default | What it does |
|---------|---------|-------------|
| Block Right-Click | On | Disables the browser context menu over the player, so viewers can't "Save video as." |
| Block Keyboard Shortcuts | Off | Disables common browser shortcuts like Ctrl+S (Save) and Ctrl+U (View Source) while the player is in focus. |
| Hide Source URL | On | Hides the direct video file address from the page source. |
| Detect Developer Tools | On | Detects when a viewer opens browser developer tools while watching. |
| Pause on Developer Tools | Off | Pauses the video automatically when developer tools are detected. |
| Developer Tools Overlay Title | "Developer Tools Detected" | The heading shown on the overlay when developer tools are detected. Edit to match your site's tone. |
| Developer Tools Overlay Message | "Please close developer tools..." | The body text below the heading. |

---

For developers: setting option keys and the REST API are documented in [`docs/developer/`](../developer/README.md).
