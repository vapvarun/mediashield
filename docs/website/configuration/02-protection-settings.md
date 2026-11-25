# Protection Settings

The Protection section of **MediaShield > Settings** governs the technical layer that runs in the browser while a video is playing.

## Protection levels

Settings, the video edit screen, the setup wizard, the Videos list and the block use the same four levels in the same words. Each level includes the one above it:

| Level | What the viewer gets |
|-------|-------------|
| None | The video plays as the platform shows it. Right-click stays blocked while **Block right-click on every video** is on (the default). Player features (speed, resume, sticky, end screen) still work. |
| Basic | Right-click and download blocked. No watermark, and no tracked viewing or milestones, because no watch session is started. |
| Standard | Basic, plus a watermark with the viewer's name and tracked viewing (milestones, resume, analytics, the stream limit). |
| Strict | Standard, plus the file address hidden from the page and developer tools detected (the video pauses while they are open). |

Login is not part of a level. **Require login** is one site setting, under **Settings > Who can watch**, and applies at every level, None included.

Basic protects the player but records nothing. If you want analytics for a video, use Standard or Strict.

Pick the site's level on the Protection card. A video can set its own on its edit screen.

### Sites updated from 1.4.3 or earlier

Before 1.4.4, **Hide Video Source URL**, **Detect Developer Tools** and **Pause Video When Detected** were site-wide switches that applied at every protected level. They now belong to Strict.

An update changes nothing for your members: on a site that ran 1.4.3 or earlier, the switches that were on keep working for every protected video, whether you ever changed them or not. The Protection card names them, with a **Use the levels** button. Picking a level (or pressing that button) moves the site onto the levels, and from then on Standard videos no longer hide a pasted file's address or watch for developer tools; Strict videos do. A new install starts on the levels.

## Protection controls

**Block right-click on every video** - Basic, Standard and Strict always block the browser's context menu over the player. On by default, so a video set to None blocks it too; turn it off to leave None videos untouched.

**Block the save shortcut (Ctrl+S / Cmd+S)** - Intercepts the save shortcut when the keypress happens inside a protected player. Off by default. This is the only shortcut it blocks; it does not intercept View Source, print, or developer-tools shortcuts, because browsers do not let a page do that reliably.

**File address (Strict)** - Self-hosted video only. MediaShield stops printing the file's own address in the page and points the player at `/wp-json/mediashield/v1/stream/<id>` instead. That endpoint runs the same access check as the player on every request, including every seek, so a URL copied out of the page is useless to somebody without access. The URL carries a signed token naming the viewer, because a `<video>` element cannot send an authentication header.

Videos you upload to MediaShield, and files it moved into its protected folder, always play through that signed address, at every level. A file you pasted from elsewhere in your Media Library plays through it on Strict.

It does nothing for YouTube, Vimeo, Wistia, or Bunny embeds. Those play in the provider's iframe, whose address has to contain the provider's video ID for playback to work at all.

**Developer tools (Strict)** - Detects when a viewer opens browser developer tools while watching a Strict video, using a debugger-timing check, which browser zoom does not affect, and pauses the video with an overlay until they close.

**Developer Tools Title (Strict)** and **Developer Tools Message (Strict)**, under **Settings > Messages** - The heading and body of that overlay. Defaults: "Developer Tools Detected" and "Please close developer tools to continue watching this video."

## What detection does and does not do

A detection sends a beacon to `POST /mediashield/v1/protection/devtools-event`, which fires the `mediashield_devtools_detected` action. Free does not store it anywhere: hook the action if you want your own logging. Pro records each detection as a suspicious-activity entry. There is no devtools report in the free admin.

The beacon is rate limited to one event per user per hour per IP, so a viewer who opens and closes devtools repeatedly does not flood your log.

Detection is skipped on touch devices and on screens narrower than 1024 px, to avoid false positives from on-screen keyboards and orientation changes.

No combination of these settings blocks screen recording. A viewer can always record the screen with a phone camera or a system screen recorder. The watermark makes any recording traceable - that is the correct deterrence model. See [Watermarks](../using-mediashield/01-watermarks.md) and "What MediaShield does not promise" in the [Introduction](../getting-started/01-introduction.md).

## Per-video overrides

Protection level and the player controls can be set per video on the video edit screen, and the per-video value wins over the global default.

One exception worth knowing: MediaShield also wraps video embeds it finds in your page output automatically. Those auto-wrapped players use the **site-wide** default protection level, not the video's own. If a specific video needs its own level, embed it with the `[mediashield id=X]` shortcode or the MediaShield Video block, which do respect it.
