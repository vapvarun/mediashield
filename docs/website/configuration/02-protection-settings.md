# Protection Settings

The Protection Controls section of **MediaShield > Settings** governs the technical layer that runs in the browser while a video is playing.

## Protection levels

Before tuning individual controls, set the right protection level. Levels are cumulative - each includes everything below it.

| Level | What it includes |
|-------|-----------------|
| None | No protection. Videos play as normal embeds. |
| Basic | Right-click disabled. Source URL hidden from page source. |
| Standard | Everything in Basic, plus dynamic watermark and developer-tools detection. |
| Strict | Everything in Standard, plus keyboard shortcut blocking and fullscreen watermark. |

Set the default level under General Settings. Override it per video on the video edit screen.

## Protection Controls settings

**Block Right-Click** - Disables the browser context menu over the player so viewers can't use "Save video as." Works at Basic level and above.

**Block Keyboard Shortcuts** - Disables common browser shortcuts like Ctrl+S (Save) and Ctrl+U (View Source) while the player is focused. Off by default. Enable on Strict sites.

**Hide Source URL** - Moves the raw video file address out of the visible page source. This is not a DRM boundary - a determined viewer can still find the URL with browser developer tools. It stops casual scraping, which is the right goal for this feature. Leave it on unless you have a specific reason not to.

**Detect Developer Tools** - Detects when a viewer opens browser developer tools while watching. Uses timing and size heuristics. On by default.

**Pause on Developer Tools** - Pauses the video automatically when developer tools are detected. Off by default. Enable if you want to interrupt playback as a deterrent.

**Developer Tools Overlay Title** - The heading shown on the overlay when developer tools are detected. Default: "Developer Tools Detected". Edit to match your site's tone.

**Developer Tools Overlay Message** - The body text below the heading on the overlay.

## What protection cannot do

Developer-tools detection fires a `mediashield_devtools_detected` server-side action. Pro logs these as suspicious activity alerts. Free users can hook this action for custom logging.

Detection is disabled intentionally on touch devices and small screens (under 1024 px wide) to avoid false positives from on-screen keyboards and device orientation changes.

No combination of these settings blocks screen recording. A viewer can always record the screen with a phone camera or system screen recorder. The watermark makes any recording traceable - that is the correct deterrence model. For a full explanation, see the Protection Philosophy section.

## Per-video overrides

Every protection level and player control can be overridden per video. Open the video edit screen and look for the protection and player sections. The per-video value wins over the global default.
