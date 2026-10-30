# Customise the Player

**Included in Free.**

Set the player's behaviour for the whole site, then override single videos.

## Why use it

The player matches how your viewers learn: resume where they left off, a speed selector, a sticky mini view, an end screen that points to the next step, and wording in your own voice.

## Before you start

- Several controls apply only to self-hosted and Bunny videos. YouTube, Vimeo and Wistia use their own player controls.
- Settings take effect when the page is built, so clear any page cache after changing them.

## Change the site-wide behaviour

1. Go to **MediaShield > Settings > Player Controls**.
2. Turn on the controls you want: Speed Control, Keyboard Shortcuts, Resume Playback, Prevent Skipping Ahead, Sticky Player, End Screen.
3. If you turn on End Screen, fill **End Screen Message** and **End Screen Button URL**.
4. Pick a **Brand Color** with the colour picker. Choose **Use the theme color** to go back to your theme.

Every option and default is in [Player Settings](../configuration/03-player-settings.md).

## Change one video

1. Open the video.
2. In the sidebar **Player Options** box, choose On or Off under **Feature Overrides**. Default (global) follows Settings.
3. Set **Autoplay**, **Loop**, **Start muted** and **Show player controls** as needed. These exist only per video.
4. Click **Update**.

## Change the words viewers read

Under **Settings > Login and Access Messages** edit the login message, login button and access denied text. Every other player word (resume prompt, Skip Ad, Replay and so on) is translated through WordPress language files. MediaShield ships a translation template only, so your language needs a language pack or your own `.po` file.

## Common recipes

| Goal | Do this |
|------|---------|
| Course lessons students cannot skip | Turn on Prevent Skipping Ahead. |
| Let students continue later | Keep Resume Playback on. It needs Standard or Strict. |
| Point to the next lesson at the end | Turn on End Screen, set the message and the next lesson URL for each video. |
| Keep reading while watching | Turn on Sticky Player. |
| A background loop on a landing page | Per video: Autoplay, Loop, Start muted on, Show player controls off, protection level None. |

## What is not available

MediaShield has no captions or subtitles track, no quality selector, no picture-in-picture button and no chapters.

## What viewers see

Viewers see the controls, end screen and messages you chose. Controls the host's own player provides (YouTube, Vimeo, Wistia) are the host's.

## Check it worked

Open the page logged in and use the control. The fullscreen button and the controls you enabled should be visible.

## Next steps

- [Playlists](03-playlists.md)
- [Video Ads](13-video-ads.md)
