# Settings Overview

**Included in Free.** Two cards on this screen only appear with MediaShield Pro (see the end of this page).

All MediaShield settings live under **MediaShield > Settings**. The page is one scrolling screen of cards, not tabs. Every change saves by itself a moment after you make it, and a small "Settings saved." message confirms it. There is no Save button.

## Before you start

- You need an administrator account. Settings are limited to users who can manage options.
- If you change a setting and a video on the page does not change, clear any page cache. Settings reach the player when the page is built.

## The cards, in order

| Card | What it controls | Detail page |
|------|------------------|-------------|
| General | Master switch, default protection level, login requirement | This page |
| Protection | Right-click, save shortcut, source hiding, developer tools | [Protection Settings](02-protection-settings.md) |
| Watermark | Opacity, colour, move interval, badge | [Watermarks](../using-mediashield/01-watermarks.md) |
| Access | Allowed domains, concurrent streams | [Access Control](04-access-control.md) |
| Analytics Retention | How long watch history is kept | This page |
| Login and Access Messages | Wording of the login and denied messages | This page |
| Player Controls | Speed, keyboard, resume, skipping, sticky, end screen, brand colour | [Player Settings](03-player-settings.md) |
| Video Ads | Break placement for in-video ads | [Video Ads](../using-mediashield/13-video-ads.md) |
| Upload and Storage (Pro) | Default upload target | Pro documentation |
| LMS Integration (Pro) | Lesson completion and enrolment | Pro documentation |

## Every setting and its default

There are 35 settings in Free. "Default" is the value a new install starts with. Out-of-range numbers are corrected when saved (the range is shown).

| Setting (label on screen) | Default | What it does |
|---|---|---|
| Enable MediaShield | On | Master switch. Off means no protection, no watermark and no tracking anywhere. Videos still play as ordinary embeds. |
| Default Protection Level | Standard | The level used by any video that has none of its own. None, Basic, Standard or Strict. |
| Require Login | On | Visitors must be logged in to watch. Off lets guests watch, and their sessions are recorded. |
| Block Right-Click | On | Hides the browser menu over the player so "Save video as" is not offered. |
| Block Save Shortcut (Ctrl+S / Cmd+S) | Off | Stops the save shortcut while the player has focus. |
| Hide Video Source URL | On | Self-hosted video only. The page no longer prints the file address. |
| Detect Developer Tools | On | Notices when a viewer opens browser developer tools. Skipped on touch devices and narrow screens. |
| Pause Video When Detected | Off | Pauses and shows the message below when developer tools are found. Appears only while detection is on. |
| Overlay Title | Developer Tools Detected | Heading of that message. Appears only while Pause Video When Detected is on. |
| Overlay Message | Please close developer tools to continue watching this video. | Body of that message. Appears only while Pause Video When Detected is on. |
| Opacity | 0.5 | Watermark strength from 0 to 1. A decimal, not a percentage. |
| Watermark Color | #ffffff | Colour of the watermark text, as a hex code. |
| Position Swap Interval | 30 | Seconds before the watermark moves. Minimum 1. |
| Show MediaShield Badge | On | Small "Protected by MediaShield" label on the player. |
| Allowed Domains | Empty | Sites allowed to embed your videos. Empty allows any. |
| Max Concurrent Streams | 2 | Videos one account may watch at the same time. Minimum 1. |
| Keep watch history for (months) | 0 | 0 keeps everything. 1 to 120 moves older sessions out of reports. See below. |
| Login Overlay Text | Please log in to watch this video | Message on the video for visitors who are not logged in. |
| Login Button Text | Log In | Label of the login button. |
| Access Denied Text | You do not have access to this video | Message for signed-in viewers who lack the role, or whose domain is not allowed. |
| Speed Control | On | Speed selector, 0.5x to 2x. Self-hosted, and Bunny with Pro direct playback. |
| Keyboard Shortcuts | On | Space, arrows, M and F while the player has focus. |
| Resume Playback | On | Offers to continue where the viewer stopped. |
| Prevent Skipping Ahead | Off | Blocks seeking beyond the furthest point watched. |
| Sticky Player | Off | Keeps the player floating in a corner while the viewer scrolls. |
| End Screen | Off | Shows a message and button when the video ends. |
| End Screen Message | Empty | Text of the end screen. Shown while End Screen is on. |
| End Screen Button URL | Empty | Where the end screen button goes. Empty hides the button. |
| Brand Color | Unset (follows the theme) | Colour of buttons, focus outlines and the current playlist item. |
| Enable In-Video Ads | On | Master switch for ad breaks. Needs WB Ad Manager to supply ads. |
| Pre-roll | On | One ad before the video starts. |
| Mid-roll Count | 3 | Ads spaced across the middle of the video. 0 to 10. |
| Require Full View | Off | Hides the Skip button so every ad must be watched. |
| Skip Delay (seconds) | 5 | Seconds before Skip unlocks. 0 to 60. Ignored when Require Full View is on. |
| Show Break Markers | On | Marks on the seek bar for upcoming ad breaks. |

The ad rows only appear while Enable In-Video Ads is on. The end screen rows only appear while End Screen is on.

Setting names such as `ms_watermark_opacity` are listed for developers in the [Settings reference](../developer-guide/06-post-meta-and-options.md).

## General

**Enable MediaShield** is the master switch. Leave it on. Use Off only for maintenance.

**Default Protection Level** decides what a video gets when it has no level of its own:

- None - No protection and no session tracking.
- Basic - Login gate and right-click blocking. No watermark, and no tracking.
- Standard - Login gate, watermark, tracking and milestones.
- Strict - Standard, plus developer-tools detection, pause on detection and source hiding forced on.

**Require Login** is on by default. Per-video roles, the domain list and any custom rules still apply when it is off.

## Analytics Retention

**Keep watch history for (months)** defaults to 0, which keeps every session forever. That is the recommended value.

If you enter a number, every month MediaShield moves older sessions into an archive table. Reports do not read that archive, so views, completion rates and top videos stop including anything older. This cannot be undone from the screen. The privacy export and erase tools do read the archive. The screen shows a warning while the value is above 0.

## Login and Access Messages

These three texts are what viewers read. While a message still has its default wording it is translated into the viewer's language. Once you edit it, your exact words are shown.

## Pro-only cards

These appear on the Settings screen only when MediaShield Pro is active.

| Setting | Default | What it does |
|---|---|---|
| Default Upload Target | Auto | Where new uploads go when you do not pick a platform. Auto uses the first connected cloud platform. Other choices are Self-hosted and each connected platform. Shown only when at least one platform is connected. |
| Auto-complete lessons | On | Marks the linked LMS lesson complete when the student finishes the video. Shown only when LearnDash, Tutor LMS or LifterLMS is active. |
| Require enrollment | On | Master switch for enrolment checks. On means each video's own "Require enrollment" box decides. Off disables the check everywhere. |
| Global completion % | 100 | Watch percentage that triggers lesson completion: 25, 50, 75, 90 or 100. |

When MediaShield Pro is not active, the same screen shows a short list of Pro features with a link to the product page.

## Uploads

There is no size setting. Self-hosted files are stored under `wp-content/uploads/mediashield/`, and your server's own upload limit applies. See [Upload a Video File](../using-mediashield/11-upload-a-video-file.md).

## Next steps

- [Protection Settings](02-protection-settings.md)
- [The Video Edit Screen](06-video-edit-screen.md)
- [Every Admin Screen](07-admin-screens.md)
