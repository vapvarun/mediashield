# Playlists

Playlists are groups of videos played in sequence. Use them for course modules, tutorial series, chapter sequences, or any content that has a natural order.

## Creating a playlist

Go to **MediaShield > Playlists** and click **Add New Playlist**. That opens a standard WordPress edit screen with a **Playlist Items** panel.

Give the playlist a title, then click **Add video** to pick videos from your library. Each row has Move up, Move down, and Remove buttons; the order you build is the order they play. Changes to the item list save as you make them - the Publish/Update button saves the title, description, and featured image.

## Playback options

A playlist carries four playback options: **autoplay**, **countdown** (seconds between videos, default 5), **loop**, and **shuffle**. The player honours all four, and the Playlists list shows which are on as badges next to the title.

There is no UI to change them in this release. A new playlist gets the defaults: autoplay off, countdown 5, loop off, shuffle off. Until an editing screen exists, they can be set through the WordPress REST API on the playlist's meta:

```
POST /wp-json/wp/v2/mediashield-playlists/15
{ "meta": { "_ms_autoplay": true, "_ms_countdown": 8 } }
```

or with `update_post_meta()` in code. The meta keys are `_ms_autoplay`, `_ms_countdown`, `_ms_loop`, and `_ms_shuffle`.

## Embedding a playlist

You have two options:

### Shortcode

```
[mediashield_playlist id=15]
```

Replace `15` with your playlist's ID. The ID appears in the URL when editing the playlist (`post=15`).

### Gutenberg block

In the block inserter, search for **MediaShield Playlist**. The block opens a playlist picker.

Both embedding methods produce the same output. An empty playlist, or one that is not published, renders nothing for visitors and a short explanation for anyone who can edit posts.

## How playlists work with protection

Each video in the queue is treated on its own terms:

- The watermark is applied per video, based on that video's protection level
- Session tracking runs per video, so each one generates its own session record
- Milestones are tracked per video
- Access is checked per video: a video the viewer may not watch is refused when it comes up, and the rest of the playlist still works

One gap to be aware of: "Hide Video Source URL" is **not** applied to playlist markup. A self-hosted video's file address appears in the page source of a playlist even when the setting is on. If a particular video needs that protection, embed it on its own with `[mediashield id=X]`.

## Playlist thumbnails

Set a **Featured Image** on the playlist post to use as the playlist thumbnail in any listing. Inside the playlist player, each queue item shows its own video's Featured Image, with a placeholder icon for videos that have none.

## Reordering videos

Open the playlist edit screen and use the Move up and Move down buttons in the Playlist Items panel. The new order saves immediately and takes effect for all future playback.
