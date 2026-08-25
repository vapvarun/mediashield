# Protection Settings

The Protection section of **MediaShield > Settings** governs the technical layer that runs in the browser while a video is playing.

## Protection levels

Before tuning individual controls, set the right protection level. The levels are not a simple ladder of extras - each one changes what the player does:

| Level | What happens |
|-------|-------------|
| None | Free preview. No login gate, no watermark, no session tracking. Player features (speed, resume, sticky, end screen) still work. |
| Basic | Login gate plus the protection controls below. No watermark, and no session tracking or milestones, because no watch session is started. |
| Standard | Login gate, watermark, session tracking, and milestones. |
| Strict | Everything in Standard, and forces developer-tools detection, pause-on-detection, and source-URL hiding on for that video even when those toggles are off globally. |

The important one to know is Basic: it protects the player but records nothing. If you want analytics for a video, use Standard or Strict.

Set the default level under General. Override it per video on the video edit screen.

## Protection controls

These apply to every player except those set to None.

**Block Right-Click** - Disables the browser context menu over the player so viewers can't use "Save video as". On by default.

**Block Save Shortcut (Ctrl+S / Cmd+S)** - Intercepts the save shortcut when the keypress happens inside a protected player. Off by default. This is the only shortcut it blocks; it does not intercept View Source, print, or developer-tools shortcuts, because browsers do not let a page do that reliably.

**Hide Video Source URL** - On by default, and **self-hosted video only**.

When on, MediaShield stops printing the file's own address in the page and points the player at `/wp-json/mediashield/v1/stream/<id>` instead. That endpoint runs the same access check as the player on every request, including every seek, so a URL copied out of the page is useless to somebody without access. The URL carries a signed token naming the viewer, because a `<video>` element cannot send an authentication header.

It does nothing for YouTube, Vimeo, Wistia, or Bunny embeds, and the setting says so in the admin. Those play in the provider's iframe, whose address has to contain the provider's video ID for playback to work at all. Blanking it would break the video and protect nothing.

If you upgraded: before 1.3.0 this setting was cosmetic. The server printed the file URL into the player markup and the JavaScript stripped it afterwards, so View Source showed the address instantly. It hid nothing from anyone who looked.

**Detect Developer Tools** - Detects when a viewer opens browser developer tools while watching, using window-size delta and debugger-timing heuristics. On by default.

**Pause Video When Detected** - Pauses playback and shows an overlay when developer tools are detected. Off by default; detection is recorded either way. Videos set to Strict force this on.

**Overlay Title** and **Overlay Message** - The heading and body of that overlay. Defaults: "Developer Tools Detected" and "Please close developer tools to continue watching this video."

## What detection does and does not do

A detection sends a beacon to `POST /mediashield/v1/protection/devtools-event`, which writes an entry to the PHP error log and fires the `mediashield_devtools_detected` action. Pro turns those into suspicious activity alerts; on free you can hook the action for your own logging. There is no devtools report in the free admin.

The beacon is rate limited to one event per user per hour per IP, so a viewer who opens and closes devtools repeatedly does not flood your log.

Detection is skipped on touch devices and on screens narrower than 1024 px, to avoid false positives from on-screen keyboards and orientation changes.

No combination of these settings blocks screen recording. A viewer can always record the screen with a phone camera or a system screen recorder. The watermark makes any recording traceable - that is the correct deterrence model. See [Watermarks](../using-mediashield/01-watermarks.md) and "What MediaShield does not promise" in the [Introduction](../getting-started/01-introduction.md).

## Per-video overrides

Protection level and the player controls can be set per video on the video edit screen, and the per-video value wins over the global default.

One exception worth knowing: MediaShield also wraps video embeds it finds in your page output automatically. Those auto-wrapped players use the **site-wide** default protection level, not the video's own. If a specific video needs its own level, embed it with the `[mediashield id=X]` shortcode or the MediaShield Video block, which do respect it.
