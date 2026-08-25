# MediaShield -- Configuration Guide

All settings are managed from the **MediaShield > Settings** admin page, which auto-saves as you make changes. Settings are grouped into sections, and the sections below are in the same order as the page. This guide explains what each setting does and when to change it.

---

## General

| Setting | Default | What it does |
|---------|---------|-------------|
| Enable MediaShield | On | Master toggle. Turn this off to disable all protection and session tracking site-wide. Your videos still play - they just play as plain, unprotected embeds. Use it for maintenance, not permanently. |
| Default Protection Level | Standard | Baseline protection for videos that have no per-video override. Choose from None, Basic, Standard, or Strict. |
| Require Login | On | Forces viewers to log in before any video plays. Turn it off for a public marketing or preview video: logged-out visitors can then watch, and their views are still recorded. (Before 1.3.0 turning it off had no effect - the login overlay appeared regardless.) |

**A note on logged-out viewing.** When Require Login is off, guests can watch and their sessions are recorded, but three things do not apply to them, because none of them can work without an account to attach to:

- **No resume.** A guest is never offered "resume from where you left off" - handing one visitor the previous visitor's position would leak one person's progress to another.
- **No milestones.** The 25 / 50 / 75 / 100% completion records are per user, so nothing is recorded for guests.
- **No concurrent-stream limit.** The limit exists to stop credential sharing, and there are no credentials to share. Guests always get a fresh session.

### Protection levels

The level decides how much of MediaShield runs on a given video. It is *not* a simple stack where each tier adds one thing to the last - Basic deliberately skips tracking:

- **None** -- No protection and no gate at all. The video plays as a normal embed, no login check, no watermark, no session tracking, no milestones. This is the setting for a free preview.
- **Basic** -- Login gate plus the browser-level protections you have switched on under **Protection** (right-click blocking and so on). **No watermark, no session tracking, and no milestones** - a Basic video will never appear in your analytics.
- **Standard** -- Login gate, watermark, session tracking, and milestones. This is the level you want for course content, and it is the default.
- **Strict** -- Everything in Standard, and it additionally *forces* developer-tools detection and source-URL hiding on for that video even if you have turned those global toggles off.

Strict does not add keyboard blocking or a different watermark. If you want the Ctrl+S block, turn it on under **Protection**; it then applies at every level except None.

---

## Protection

Controls for the browser-level protection layer that runs while the video is playing.

| Setting | Default | What it does |
|---------|---------|-------------|
| Block Right-Click | On | Disables the browser context menu inside the player, so viewers can't reach "Save video as". Right-clicking elsewhere on the page still works, by design. |
| Block Save Shortcut (Ctrl+S / Cmd+S) | Off | Intercepts the save shortcut while the keyboard focus is inside the player. This is the save shortcut only - it does not block View Source, Print, or anything else. |
| Hide Video Source URL | On | Self-hosted videos only. See below. |
| Detect Developer Tools | On | Detects when a viewer opens browser developer tools while watching, and records it. Skipped on mobile and touch devices, where the detection produces too many false alarms. |
| Pause Video When Detected | Off | Also pauses playback and shows an overlay when developer tools are detected. Detection is recorded either way. |
| Overlay Title | "Developer Tools Detected" | The heading on that overlay. Edit to match your site's tone. |
| Overlay Message | "Please close developer tools to continue watching this video." | The body text below the heading. |

### Hide Video Source URL

**This applies to self-hosted videos only.** When on, the video plays through a permission-checked address and the real file path never appears in the page at all - not in View Source, not in developer tools. Permission is re-checked on every request, so access you revoke stops working straight away.

It does **not** apply to YouTube, Vimeo, Wistia or Bunny videos. Those play in the provider's own iframe, whose address necessarily contains their video ID, so there is nothing MediaShield can hide there. The setting says so on screen.

Leave it on. (Before 1.3.0 this setting did nothing useful: the file address was printed into the page markup even with it enabled, so View Source revealed it immediately.)

One thing worth knowing if you self-host: hiding the address stops it being *published*, but it does not stop your web server handing the file over to anyone who already has the address. Whether it does depends on your server - see **Tools > Site Health**, which checks this for you. There is more detail in *Protection Philosophy*.

---

## Watermark

The watermark is a text overlay that sits on top of the video while it plays. It shows the viewer's identity - display name and IP address - so you can trace leaked recordings back to the source. Logged-out viewers are watermarked as "Guest" plus their IP.

| Setting | Default | What it does |
|---------|---------|-------------|
| Opacity | 0.5 | How visible the watermark is, on a 0 to 1 slider. 0 is invisible, 1 is fully solid. Around 0.3 to 0.5 is visible without being distracting. |
| Watermark Color | White | The watermark text color. Use a color that's visible against your video content. |
| Position Swap Interval | 30 seconds | How often the watermark moves to a new position. Shorter intervals make it harder to crop out. |
| Show MediaShield Badge | On | Displays a "Protected by MediaShield" badge on the player. Many site owners turn this off for a cleaner look. |

The free watermark shows the viewer's display name and IP address. Pro adds 7 configurable fields.

If someone deletes or hides the watermark using browser developer tools, MediaShield does not redraw it - it stops the video instead.

---

## Allowed Domains

| Setting | Default | What it does |
|---------|---------|-------------|
| Allowed Domains | Empty | List of domains that are allowed to embed your videos. Leave empty to allow embeds from any domain. |

**Separate the domains with commas.** (The hint on screen currently says one per line; commas are what the check actually reads.) Your own site is always allowed without being listed, and subdomains of a listed domain are allowed too.

When the list is filled in, a request that arrives with no referring page at all is refused. That is deliberate - stripping the referrer is the usual way to get around a domain list - but it does mean some privacy-hardened browsers will be turned away.

---

## Concurrent Streams

| Setting | Default | What it does |
|---------|---------|-------------|
| Max Concurrent Streams | 2 | How many videos one account can play at the same time. When a viewer tries to open one more than the limit, they are told to close another first. |

This counts per logged-in account. Logged-out viewers are not counted or limited (see the note under **General**).

---

## Analytics Retention

**Keep watch history for (months).** How long watch sessions stay in your
reports. **The default is `0`, which means keep everything, and that is the
recommended setting.**

Set a number of months only if you have a data-retention policy that requires
older sessions to be moved out of reporting. When you do, sessions older than
that window are moved to an archive table each month, and your views, completion
rates and top-video figures stop including them. The setting warns you about this
on screen once you enter a value.

If you are upgrading from an earlier version: MediaShield used to archive at 24
months automatically, whether or not anyone asked it to, which meant reports on
long-running sites quietly lost history. Anything already moved is brought back
automatically by a background job that starts shortly after the upgrade; on a
site with years of history it works through the backlog in batches, so give it a
little time.

---

## Login & Access Messages

| Setting | Default | What it does |
|---------|---------|-------------|
| Login Overlay Text | "Please log in to watch this video" | The message shown when a visitor tries to watch but isn't logged in. Edit to match your brand voice. |
| Login Button Text | "Log In" | The label on the login button in the overlay. |
| Access Denied Text | "You do not have access to this video" | Shown when a logged-in user doesn't have the right role for a specific video, and when a request is turned away by the allowed-domain list. |

---

## Player Controls

The **Player Controls** section tunes how the video player behaves for the people watching. Each control is a global default that you can override per-video on the video edit screen -- so you can keep speed control off site-wide and turn it on for one specific course.

### What each control does (and when to enable it)

**Speed Control.** Adds a 0.5x-2x speed selector to the player so viewers can speed lectures up or slow tutorials down. Turn this on for course / training / lecture content -- students will reach for it on day one. Self-hosted and Bunny videos only; YouTube / Vimeo / Wistia embeds use the host player's built-in speed menu, so this toggle has no effect there.

**Keyboard Shortcuts.** Lets viewers use the keyboard while the player is focused -- **Space** to play / pause, **left / right arrows** to seek five seconds, **up / down** for volume, **M** to mute, **F** for fullscreen. Turn it on if you have a power-user audience (developers, designers, course completers); leave it off if your audience is non-technical and you're worried about accidental key presses.

**Resume Playback.** Remembers where each viewer left off and shows a resume prompt the next time they open the same video. This is the single highest-impact control for course content -- students rarely finish a 40-minute lesson in one sitting, and forcing them to scrub back is a guaranteed drop-off point. Logged-out viewers are never offered a resume point.

**Sticky Player.** When the viewer scrolls past the video, the player shrinks into a corner overlay so they can keep watching while reading transcripts, notes, or comments below. Great for long-form tutorial pages and webinar replays. Skip it on short videos or galleries with multiple players on one page -- the floating overlay can feel intrusive when the video is only 30 seconds long.

**End Screen (message + button URL).** When the video finishes, MediaShield can show a short call-to-action overlay with a message and a clickable button. Use it to push viewers to the next lesson ("Continue to Module 2"), an upsell ("Get the full course"), or a related video. Leave both fields blank on a video to fall back to the global default, or fill them in per video to customise per topic.

### Player controls settings

| Setting | Default | Description |
|---------|---------|-------------|
| Speed Control | On | Show the playback-rate menu (self-hosted / Bunny only) |
| Keyboard Shortcuts | On | Allow Space/Arrow/M/F shortcuts on the player |
| Resume Playback | On | Resume from the last reached position when re-opening a video |
| Sticky Player | Off | Pin the player to a corner when scrolled off-screen during playback |
| End Screen | Off | Render the end-screen call-to-action when the video ends |
| End Screen Message | Empty | Call-to-action text (global fallback when a video doesn't set its own) |
| End Screen Button URL | Empty | Call-to-action URL |

> **Per-video overrides:** all five of these controls, plus the end-screen text and URL, can be set individually on each video via the video edit screen. The per-video setting wins over the global default. Note that the override only takes effect when you place the video with the MediaShield block or shortcode - a video that MediaShield picks up automatically from an existing embed uses the global settings.

---

## Video Ads

MediaShield can play sponsor ads inside your videos - one before the video starts, and a number spaced through the middle. The ad creatives themselves come from **WB Ad Manager**; if that plugin isn't active, or has no video ads set up, nothing here has any effect and no ads play. Individual videos can override these placements from the video's own "Video Ads" box.

| Setting | Default | What it does |
|---------|---------|-------------|
| Enable In-Video Ads | On | Master switch. Off means no ads play anywhere, whatever else is set here. |
| Pre-roll | On | Play one ad before the video starts. |
| Mid-roll Count | 3 | How many breaks to space evenly across the middle of the video, between the 10% and 90% marks. 0 to 10; 0 means no mid-rolls. The screen shows you roughly where they would land. |
| Require Full View | Off | The viewer must watch each ad in full - the Skip button never appears. Use this where the law requires it (CLE and similar). Overrides the skip delay. |
| Skip Delay (seconds) | 5 | How long before the Skip button unlocks on each ad. 0 to 60. |
| Show Break Markers | On | Draw a marker on the seek bar for each upcoming ad break. |

**Skip Delay is a fallback, not a hard rule.** If an individual ad creative in WB Ad Manager has its own "allow skip after" value, that value wins for that ad and the setting here is used only for ads that don't specify one. (Before 1.3.0 the per-ad value was thrown away and every ad counted down from the site-wide default.) Require Full View still beats both.

---

## Sections you may not see

Two more sections appear on the Settings page only when they apply:

- **Upload & Storage** appears once you have connected a cloud platform, which requires Pro. It chooses where new uploads are stored. Without it, self-hosted videos go into a protected folder inside your WordPress uploads directory.
- **LMS Integration** appears with Pro, for auto-completing LearnDash / LifterLMS / Tutor LMS lessons when a student finishes a video.

---

For developers: setting option keys and the REST API are documented in [`docs/developer/`](../developer/README.md).
