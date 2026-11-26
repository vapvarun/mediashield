# The Video Edit Screen

**Included in Free.** Two extra boxes (LMS Integration and Video Ads) appear on this screen when MediaShield Pro is active. They are covered in the Pro documentation.

Every video in your library is a WordPress post of type "video". Open one from **MediaShield > Videos > Edit**, or choose **Add New Video**. This page explains every box on that screen. The main column is in the order you work: 1. The video, 2. Who can watch, and how it is protected, 3. Milestones.

## Why use it

Everything about one video is on one screen: where it comes from, who may watch, how it behaves and what milestones it rewards.

## Before you start

- A video must be **Published** to play on your site. A draft shows nothing to visitors and a short note to editors.
- Videos and playlists have no menu entry of their own. Reach them from the MediaShield screens.

## Title and Featured Image

The title appears in your library, playlists and the My Videos grid. The Featured Image is the poster frame shown before playback. For YouTube, Vimeo, Wistia and Bunny videos MediaShield fetches the platform's poster for you if the field is empty. For self-hosted files, set one yourself.

## 1. The video

A file you uploaded is shown by its name, length and size (the length is read from the file when it is uploaded; files uploaded before 1.4.4 get theirs on update, and a length you typed is kept) ("Uploaded file · 8:15 · 142 MB · kept private, played through a signed link"), not by its signed stream address. The link field under it is then **Replace with a link**: leave it empty to keep the file.


| Field | What it does |
|-------|--------------|
| Video link | Paste a YouTube, Vimeo, Wistia or Bunny Stream URL, or the address of a direct video file. The platform is detected from the URL. When the address is a file in your Media Library, saving moves that file into MediaShield's protected folder and the field then shows the video's protected stream address. A file also used elsewhere on the site, or a file that is not in your Media Library (such as one another plugin stored), stays where it is, and a notice says so. The file moves only when the person saving may edit its Media Library entry. |
| Or upload a file | Choose a video file and click Upload to store it on this site. Shown only to users with the upload permission. Save the video afterwards to keep the file. |
| Detected Platform | Read-only. Shows what MediaShield recognised (YouTube, Vimeo, Wistia, Bunny Stream or Self-hosted) and a note when a URL looks wrong. |
| Duration | Length in seconds. Leave 0 if you do not know it. When it is 0, MediaShield uses the length the player reports. Mid-roll ad breaks need a known length. |

See [Video Hosts](../using-mediashield/10-video-hosts.md) for what each host supports.

## 2. Who can watch, and how it is protected

| Field | Default | What it does |
|-------|---------|--------------|
| Protection Level | Site default (for example "Site default (Standard)") | None, Basic, Standard or Strict for this video, in the same words as Settings; the line under it says what the chosen level does. Site default follows Settings > Protection. |
| Who can watch | Everyone the site allows | Or "Only <role>": only people holding this exact role can watch. Administrators always pass. Require login is not set here: it is one site setting under Settings > Who can watch, and the first choice names its state. |
| Tags | None | Tags on this video. Type names separated by commas to add (a new name becomes a tag), untick one to remove it. Saved with the video. |

## Embed This Video

Shown in the sidebar once the video is saved. It tells you to add the MediaShield Video block and pick the video, and gives the shortcode `[mediashield id=NN]` with a copy button for the Classic editor, widgets and page builders. In a theme template, use `<?php echo do_shortcode( '[mediashield id=NN]' ); ?>`.

## 3. Milestones

When a member reaches 25%, 50%, 75% or 100% of the video, MediaShield records it. To add a tag to the member at a milestone, type the tag and tick **On**. The tag goes on the member's profile, for your CRM, LMS or automation rules, and is listed under MediaShield > Tags. A 10% tag saved before 1.4.4 is still shown and kept.

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
