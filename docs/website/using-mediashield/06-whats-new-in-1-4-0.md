# What's New in 1.4.0

**Free + Pro** - MediaShield 1.4.0 and MediaShield Pro 1.4.0 are released together. Install both updates.

This release fixes playback on cached pages, playlists and the standard embed blocks, and adds a brand colour for the player.

## For everyone

- **Playlists play through the protected player.** Every video in a playlist is watermarked, tracked and counted, and moving to the next item swaps the player without reloading the page. Autoplay, loop, muted and controls can be set per playlist. See [Playlists](03-playlists.md).
- **Standard Vimeo and YouTube embed blocks are protected** when the video is in your MediaShield library. An embed for a video MediaShield does not manage is left as it is.
- **Brand colour.** Choose the player's accent colour with the **Brand Color** setting under **MediaShield > Settings > Player Controls**, with a live preview of the watermark, badge and button. See [Player Settings](../configuration/03-player-settings.md).
- **Featured image is the poster.** A video's featured image shows before playback starts and next to the video in a playlist.
- **Fewer "Too many active streams" messages.** The stream limit counts the other devices an account is watching on, not the players on one page.
- **Cached pages no longer break playback.** A protected self-hosted video asks for a fresh address when the page was cached or left open, and resumes where it stopped. Pages that print a stream address are not cached.
- **Privacy.** The page no longer prints a visitor's name or IP address, so a page cache cannot show them to the next visitor. The watermark still shows both while a video plays.
- **Fixes.** Uploaded video files play for logged-in viewers when Hide Video Source URL is off and Require Login is on, encoded characters such as `%20` stay in saved addresses, and Bunny addresses are recognised the same way on every screen.

## MediaShield Pro

- **Playlist funnel.** Analytics lists the videos in each playlist. It showed an empty funnel before.
- **YouTube connection.** The form asks for the Channel ID once.
- **Rapid-seek alerts** also run on videos with no stored duration, using the length the player reports.
- **Bunny.** Free is told which pull zone belongs to the connected library.

Pro requires MediaShield (free) 1.4.0.
