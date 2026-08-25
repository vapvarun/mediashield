# Player Settings

The Player Controls section of **MediaShield > Settings** tunes how the video player behaves for viewers. Each control is a global default that you can override per video on the video edit screen.

## Player Controls settings

**Speed Control** - Adds a 0.5x-2x playback speed selector to the player. On by default. Useful for course, training, or lecture content where students commonly adjust playback speed. Applies to self-hosted and Bunny Stream videos only. YouTube, Vimeo, and Wistia embeds use the host platform's own speed control.

**Keyboard Shortcuts** - On by default. When the player has focus: Space to play/pause, left/right arrows to seek 5 seconds, up/down for volume, M to mute, F for fullscreen. Play, pause, seek, and fullscreen work on every platform; volume and mute only apply to self-hosted and Bunny videos, since platform iframes own their audio. Turn the whole thing off if your audience is non-technical and you're concerned about accidental key presses.

**Resume Playback** - Remembers where each viewer stopped and offers to resume the next time they open the same video. On by default. This is the single most impactful control for course content - students rarely finish a long lesson in one sitting. It relies on watch sessions, so it does nothing for videos set to None or Basic.

**Sticky Player** - When a viewer scrolls past the video while it is playing, the player shrinks into a corner overlay so they can keep watching while reading content below. Off by default. Well suited for long-form tutorial pages and webinar replays. Avoid on pages with multiple players or very short videos, where the floating overlay may feel intrusive.

**End Screen** - When the video ends, MediaShield shows a short call-to-action overlay with a message and a clickable button. Off by default. Use it to point viewers to the next lesson, an upsell, or a related video.

**End Screen Message** - The call-to-action text. Used as the global fallback when a video doesn't set its own.

**End Screen Button URL** - The destination for the call-to-action button. Leave blank to disable the button.

## Per-video overrides

Speed Control, Keyboard Shortcuts, Resume Playback, Sticky Player, and End Screen each have a Default / On / Off selector on every video, under Player Options in the sidebar of the video edit screen. The per-video value wins over the global default. This lets you keep speed control off site-wide and enable it for one specific course, for example. End screen text and URL can be overridden there too.

Four more options exist only per video, with no global equivalent: **Autoplay**, **Loop**, **Start muted**, and **Show player controls**. Like speed control, they apply to self-hosted and Bunny videos; platform embeds use their own controls.

## Summary table

| Setting | Default | Notes |
|---------|---------|-------|
| Speed Control | On | Self-hosted and Bunny only |
| Keyboard Shortcuts | On | Volume and mute are self-hosted and Bunny only |
| Resume Playback | On | Needs Standard or Strict protection to have a session to resume from |
| Sticky Player | Off | Best for long-form content |
| End Screen | Off | Configure message and URL to enable |
| End Screen Message | Empty | Global fallback |
| End Screen Button URL | Empty | Global fallback |

## Not in the settings UI

The player also supports preventing forward seeking (rewinding is allowed; skipping past the furthest point actually watched is clamped), which is used for watch-enforcement and compliance training. It is implemented and honoured by the player, including the keyboard skip-forward shortcut, but has no control on the Settings screen in this release. Developers can set the `ms_player_prevent_forward_seek` option, or send it to `PUT /wp-json/mediashield/v1/settings`, to switch it on.
