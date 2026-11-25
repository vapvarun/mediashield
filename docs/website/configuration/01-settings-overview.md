# Settings Overview

**Included in Free.** Two cards on this screen only appear with MediaShield Pro (see the end of this page).

All MediaShield settings live under **MediaShield > Settings**. The cards sit in one column with a section list on the left, one item per card, named as the card is: Protection, Who can watch, Watermark (and with Pro, Watermark fields), Player, Messages, Viewing history, and only where they can work Video ads (WB Ad Manager's video ads installed), Uploads (a platform connected with Pro) and Lesson completion (Learnomy, or with Pro an LMS). Click a section to jump to it; the item lit follows the card at the top of the screen as you scroll. Type in **Search settings** above the list to show only the cards that mention what you typed, for example "IP" or "resume". On a phone the section list is a **Go to section** menu. Every change saves by itself a moment after you make it, and a "Saved" message confirms it at the bottom of the screen. There is no Save button. If a change cannot be saved (the site cannot be reached, or the value is not allowed), the control switches back to the saved value and the message says "Not saved", with **Try again**.

## Before you start

- You need an administrator account. Settings are limited to users who can manage options.
- If you change a setting and a video on the page does not change, clear any page cache. Settings reach the player when the page is built.

## The cards, in order

| Card | What it controls | Detail page |
|------|------------------|-------------|
| Protection | Protect videos (master switch), the site's protection level, right-click on None videos, save shortcut | [Protection Settings](02-protection-settings.md) |
| Who can watch | Require login, streams at once per member, allowed domains | [Access Control](04-access-control.md) |
| Watermark | What it shows (Free: email and IP address, both off on a new install), opacity, colour, move interval, badge | [Watermarks](../using-mediashield/01-watermarks.md) |
| Watermark fields (Pro) | The fields Pro prints | Pro documentation |
| Player | Speed, keyboard, resume, skipping, sticky, end screen, brand colour | [Player Settings](03-player-settings.md) |
| Messages | Login, denied, membership, course, unavailable and Strict's developer tools messages | This page |
| Video ads | Break placement for in-video ads | [Video Ads](../using-mediashield/13-video-ads.md) |
| Uploads (Pro) | Where an uploaded file goes | Pro documentation |
| Viewing history | How long watch history is kept | This page |
| Lesson completion | Learnomy: set on each lesson. LearnDash (Pro): the one "require this % watched" setting. Tutor LMS, LifterLMS (Pro): auto-complete at a %. Auto-complete and enrollment switches with Pro | This page |

## Every setting and its default

"Default" is the value a new install starts with. Out-of-range numbers are corrected when saved (the range is shown).

| Setting (label on screen) | Default | What it does |
|---|---|---|
| Protect videos | On | Master switch. Off means no protection, no watermark and no tracking anywhere. Videos still play as ordinary embeds. |
| Protection level | Standard | The level used by any video that has none of its own. None, Basic, Standard or Strict, each including the one before. |
| Require login | On | Visitors must be logged in to watch. Off lets guests watch, and their sessions are recorded. |
| Block right-click on every video | On | Basic, Standard and Strict always hide the browser menu over the player. On: videos set to None hide it too. |
| Block Save Shortcut (Ctrl+S / Cmd+S) | Off | Stops the save shortcut while the player has focus. |
| Developer Tools Title (Strict) | Developer Tools Detected | Heading of the message a Strict video shows while developer tools are open. Under Messages. |
| Developer Tools Message (Strict) | Please close developer tools to continue watching this video. | Body of that message. Under Messages. |
| Email address (watermark) | Off (on for a site updated from before 1.4.4) | Adds the viewer's email to the watermark. Free only; Pro sets its own fields. |
| IP address (watermark) | Off | Adds the viewer's IP address to the watermark. Free only. |
| Opacity | 0.5 | Watermark strength from 0 to 1. A decimal, not a percentage. |
| Watermark Color | #ffffff | Colour of the watermark text, as a hex code. |
| Position Swap Interval | 30 | Seconds before the watermark moves. Minimum 1. |
| Keep the watermark at the edges | Off | Keeps the watermark in the corners, off the centre of the video. |
| Show MediaShield Badge | Off | Small "Protected by MediaShield" label on the player, a pill in your Brand Color. Off on new installs. A site updated from an earlier version keeps its setting and sees one notice on the MediaShield screens offering the new defaults (badge off; watermark name and email, IP opt-in): **Apply new defaults** or **Keep my settings**, and the notice is gone for good. **Not now** hides it for a week. |
| Allowed Domains | Empty | Sites allowed to embed your videos. Separate entries with commas, spaces or new lines, in any mix. A pasted web address is reduced to its domain. Empty allows any. See [Access Control](04-access-control.md). |
| Streams at once per member | 2 | Videos one account may watch at the same time. Minimum 1. |
| Keep watch history for (months) | 0 | 0 keeps everything. 1 to 120 moves older sessions out of reports. See below. |
| Login Overlay Text | Please log in to watch this video | Message on the video for visitors who are not logged in. |
| Login Button Text | Log In | Label of the login button. |
| Access Denied Text | You do not have access to this video | Message for signed-in viewers who lack the role, or whose domain is not allowed. |
| Membership Page | Empty | Your membership or pricing page. When set, a signed-in viewer who may not watch gets a button to it under the message. Empty shows no button. A course link from your LMS is used instead when there is one. |
| Membership Button Text | See membership options | Label of that button. Shown once a Membership Page is set. |
| Course Access Text | This video is part of %s. Enroll to watch it. | Shown to a member not enrolled in the course a video belongs to (Learnomy, or with Pro a linked LearnDash lesson). %s becomes the course name. A button to the course sits under it. |
| Course Button Text | View course | Label of that button. |
| Unavailable Video Text | This video is unavailable. | Shown when YouTube or Vimeo says the video was removed, made private or may not be embedded, with "The site owner has been told. Please try again later." and a Try again button. You and your editors also see the cause and an Edit video link. |
| Speed Control | On | Speed selector, 0.5x to 2x. Self-hosted, and Bunny with Pro direct playback. |
| Keyboard Shortcuts | On | Space, arrows, M and F while the player has focus. |
| Resume Playback | On | Offers to continue exactly where the viewer stopped, rewinds included. Play resumes; Start over plays from the beginning. |
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
| Skip Delay (seconds) | 5 | Seconds before Skip unlocks. 0 to 60; 0 means skippable at once. An ad with its own skip delay in WB Ad Manager keeps it; an ad left blank or at 0 uses this value. Ignored when Require Full View is on. |
| Show Break Markers | On | Marks on the seek bar for upcoming ad breaks. |

The ad rows only appear while Enable In-Video Ads is on. The end screen rows only appear while End Screen is on.

Setting names such as `ms_watermark_opacity` are listed for developers in the [Settings reference](../developer-guide/06-post-meta-and-options.md).

## Protection

**Protect videos** is the master switch. Leave it on. Use Off only for maintenance.

**Protection level** decides what a video gets when it has no level of its own, in the same words as every other screen:

- None - The video plays as the platform shows it. Right-click stays blocked while Block right-click on every video is on.
- Basic - Right-click and download blocked.
- Standard - Plus a watermark and tracked viewing.
- Strict - Plus file address hidden and developer tools detected.

**Require login** (under Who can watch) is on by default and applies at every level. Per-video roles, the domain list and any custom rules still apply when it is off.

## Viewing history

**Keep watch history for (months)** defaults to 0, which keeps every session forever. That is the recommended value.

If you enter a number, every month MediaShield moves older sessions into an archive table. Reports do not read that archive, so views, completion rates and top videos stop including anything older. This cannot be undone from the screen. The privacy export and erase tools do read the archive. The screen shows a warning while the value is above 0.

## Messages

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

## Lesson completion

One card for when a lesson that plays a protected video counts as complete. It shows only what applies to the LMS on your site:

- **Learnomy 2.4.1 and later** - set on each lesson in Learnomy (Completion: Auto: video watched). The card says so, with the % new protected video lessons start at.
- **LearnDash (Pro)** - one setting, **LearnDash lessons: require this % of the video watched**. Mark Complete stays disabled until the member has watched that share, and with **Auto-complete lessons** on, the lesson completes at that point (on the first milestone at or above it).
- **Tutor LMS and LifterLMS (Pro)** - **Complete lessons at**: the share watched that completes the lesson; a video can set its own.

Milestone tags stay on each video's edit screen.
