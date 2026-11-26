# What's New in 1.4.4

**Free + Pro** - MediaShield 1.4.4 and MediaShield Pro 1.4.4 are released together. Install both updates.

A guided first run, a calmer player, clearer video and tag lists, and a safer update for member tags.

## For everyone

- **A first run built from your own content.** The Dashboard and every Analytics screen show four steps until your site is set up. See [Setup Wizard](../getting-started/03-setup-wizard.md).
- **Who can watch, in two plain choices.** Wizard step 1 asks **Anyone who opens the page** or **Logged-in members only**. Your current setting is preselected and nothing changes until you pick.
- **Save & Continue adds your first video.** A pasted link is added before the wizard moves on, and a step is ticked only once it is done.
- **Add or edit from a link with no account.** An upload shows its file name, and a removed video is easy to find.
- **A clearer Videos list.** Thumbnails for platform videos and uploads, **Used in** on one line, known lengths, and a **Length needed** chip for a watch-rule video with no known length. **Used in** also names the Learnomy lessons (drafts included) that play a video, with no cap on how many places are found. See [Viewers, Tags and My Videos](14-viewers-tags-and-my-videos.md).
- **Tags kept apart.** Video tags and the tags members earn from milestones no longer mix. Tags can be renamed and the Videos list can be filtered by tag. After the update, a background job separates old member tags and removes only tags a milestone created, never your own.
- **Stop playback for members watching now.** On Viewers, the button appears only for members with a viewing in progress.
- **A calmer player.** The end of a video counts as 100 percent, short clips resume, Vimeo shows one fullscreen button, and watched time counts from the first second to the pause. A viewing starts at the first play, and a refused member's player no longer asks the server anything.
- **Faster lists.** **Used in**, My Videos and old playlist links read a stored index instead of scanning post content on every view.
- **Lesson watch rules at None and Basic.** A lesson's watch rule is measured at these levels without turning on anything else from Standard.
- **Links 1.4.3 saved still play.** A link saved with a platform keeps its platform after the update, and existing lengths are read in the background.

## For developers

- The filter `mediashield_create_video` adds a video the way **Add New** does. See [Hooks and Filters](../developer-guide/02-hooks-and-filters.md).

## MediaShield Pro

- **Reports count real viewings.** Realtime, exports, drop-off and device counts include only sessions that played, and Funnel, device breakdown and the weekly digest count the way the Dashboard does.
- **The Heatmap shows first viewings at once** and keeps its stored totals, so a short video's counts never drop.
- **LearnDash lessons unlock in place** as soon as the watch rule is met, at the one setting under **Settings > Lesson completion**.
- **Safer report downloads.** A PDF report downloads only for a signed-in administrator, the folder is closed to the web, reports are deleted after a day, and the update removes reports saved earlier. CSV exports write text that starts like a formula as plain text. See Pro's Export guide.
- **A clearer Bunny card, Realtime and alerts.** The webhook row says when it is waiting for Bunny, Disconnect sits behind a confirm, **Watching now** appears on Realtime, and security alerts show in clear cards.

Pro requires MediaShield (free) 1.4.4.
