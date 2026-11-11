# What's New in 1.4.2

**Free + Pro** - MediaShield 1.4.2 and MediaShield Pro 1.4.2 are released together. Install both updates.

A security release: videos from your Media Library are protected like uploaded ones, and nothing a visitor can read names your videos' platform IDs any more.

## For everyone

- **Media Library videos are protected.** A self-hosted video picked from the Media Library moves to the protected folder in the background after the update and plays only through the protected player. A file also used elsewhere (a post, an Elementor page, an ACF field, a widget or a Learnomy lesson) stays where it is, and a notice lists those videos with how to protect them. See [Protect Videos You Already Host](19-protect-videos-you-already-host.md).
- **No video IDs for visitors.** Posters, video titles and addresses no longer carry the YouTube, Vimeo, Wistia or Bunny ID. The update renames existing posters and cleans old "youtube - ID" titles in the background; a title you typed yourself is kept. A poster address pasted by hand into a post before the update stops working.
- **Allowed Domains never blocks your own students.** Opening a lesson from a bookmark, an email link or after logging in plays the video; pages on other sites are still checked. See [Access Control](../configuration/04-access-control.md).
- **Revoke session** on each Viewers row ends that member's active sessions after you confirm, and the Viewers list and each viewer's history are paged, so a site with thousands of viewers reaches every one. See [Viewers, Tags and My Videos](14-viewers-tags-and-my-videos.md).
- **Creators get a Videos menu.** A role given `upload_mediashield` sees **All videos** and **Add New**, with only its own videos. See [Roles and Permissions](16-roles-and-permissions.md).
- **Keep the watermark at the edges** keeps it in the corners, off the centre of the video. Off by default. See [Watermarks](01-watermarks.md).
- **The player never plays without its watermark.** A session that cannot start stops the video with a message and a retry, double-click and the browser's own fullscreen keep the watermark, and watch progress keeps recording past the first two minutes.
- **Plain messages.** When the server cannot be reached or answers with an error page, MediaShield screens and the player say so in one sentence and offer Retry, instead of browser or parser errors.
- **Names as written.** Names and titles with an ampersand, apostrophe or dash read as written in the watermark and on every screen.

## MediaShield Pro

- **Add new Bunny uploads automatically.** An opt-in switch on the Bunny card adds each new upload as a draft MediaShield video with your default protection. Waiting drafts are listed on the Bunny card and the Dashboard. See [Bunny Webhook](https://github.com/vapvarun/mediashield-pro/blob/main/docs/website/features/14-bunny-webhook.md).
- **Bunny with CDN token authentication** streams in the full player, gets its posters, and Save and test checks a quality level and a video part before direct playback is switched on.
- **Uploads to Bunny Stream work**, fill the video you are editing, get their real length after encoding, and a failed upload no longer leaves an empty video in your library.
- **Imports with no title** are named "Untitled video" instead of the video ID, and a featured image you set is never replaced by an import.
- **Settings that fail to load** say so with Try again, instead of showing defaults a save would store over your settings.

Pro requires MediaShield (free) 1.4.2.
