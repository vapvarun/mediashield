# The Video Edit Screen

**Included in Free.** Two extra boxes (LMS Integration and Video Ads) appear on this screen when MediaShield Pro is active. They are covered in the Pro documentation.

Every video in your library is a WordPress post of type "video". Open one from **MediaShield > Videos > Edit**, or choose **Add New Video**. This page explains every box on that screen.

## Why use it

Everything about one video is on one screen: where it comes from, who may watch, how it behaves and what milestones it rewards.

## Before you start

- A video must be **Published** to play on your site. A draft shows nothing to visitors and a short note to editors.
- Videos and playlists have no menu entry of their own. Reach them from the MediaShield screens.

## Title and Featured Image

The title appears in your library, playlists and the My Videos grid. The Featured Image is the poster frame shown before playback. For YouTube, Vimeo, Wistia and Bunny videos MediaShield fetches the platform's poster for you if the field is empty. For self-hosted files, set one yourself.

## Video Source

| Field | What it does |
|-------|--------------|
| Video URL | Paste a YouTube, Vimeo, Wistia or Bunny Stream URL, or the address of a direct video file. The platform is detected from the URL. |
| Or upload a file | Choose a video file and click Upload to store it on this site. Shown only to users with the upload permission. Save the video afterwards to keep the file. |
| Detected Platform | Read-only. Shows what MediaShield recognised (YouTube, Vimeo, Wistia, Bunny Stream or Self-hosted) and a note when a URL looks wrong. |
| Duration | Length in seconds. Leave 0 if you do not know it. When it is 0, MediaShield uses the length the player reports. Mid-roll ad breaks need a known length. |

See [Video Hosts](../using-mediashield/10-video-hosts.md) for what each host supports.

## Video Settings

| Field | Default | What it does |
|-------|---------|--------------|
| Protection Level | Default (your site setting) | None, Basic, Standard or Strict for this video. Saving stores an explicit value. |
| Restrict to Role | Any logged-in user | Only people holding this exact role can watch. Administrators always pass. |

## Embed This Video

Shown in the sidebar once the video is saved. It gives you the shortcode `[mediashield id=NN]` with a copy button, a note on finding the MediaShield Video block, and a PHP template snippet for theme developers.

## Milestone Tags

A row for each of 10%, 25%, 50%, 75% and 100%. Type a tag name and tick **Active**. When a viewer reaches that point, the tag is saved on their profile and added to your Tags list. Blank rows do nothing. The site always records 25, 50, 75 and 100 percent milestones, whether or not a tag is set. 10% is only recorded when you add a tag row for it.

## Player Options

These apply to self-hosted videos and to Bunny videos played through Pro direct playback. YouTube, Vimeo, Wistia and Bunny's own embedded player use their own controls.

| Option | Default | What it does |
|--------|---------|--------------|
| Autoplay | Off | Starts playing on load. Most browsers start it muted. |
| Loop | Off | Plays again from the start when it ends. |
| Start muted | Off | Starts with sound off. |
| Show player controls | On | Hides the control bar when off. |

### Feature Overrides

Each has Default (global), On and Off. A value here beats the Settings screen for this video only.

- Speed Control
- Keyboard Shortcuts
- Resume Playback
- Sticky Player
- End Screen
- Prevent Skipping Ahead

**End Screen Text** and **End Screen URL** are per-video wording for the end screen. Leave them blank to use the global ones.

## Pro upsell boxes

Without MediaShield Pro, two small teaser boxes ("LMS Integration" and "Pro Features") are shown at the bottom of the sidebar. They do nothing and can be hidden through Screen Options.

## Saving and permissions

Saving checks that you are allowed to edit the post. The upload box needs the `upload_mediashield` capability, which administrators have by default. See [Roles and Permissions](../using-mediashield/16-roles-and-permissions.md).

## Next steps

- [Protect a Paid Video](../using-mediashield/07-protect-a-paid-video.md)
- [Customise the Player](../using-mediashield/12-customise-the-player.md)
