# Playlists

Playlists are groups of videos played in sequence. Use them for course modules, tutorial series, chapter sequences, or any content that has a natural order.

## Creating a playlist

Go to **MediaShield > Playlists** and click **Add New Playlist**. That opens a standard WordPress edit screen with a **Playlist Items** panel.

Give the playlist a title, then click **Add video** to pick videos from your library. Each row has Move up, Move down, and Remove buttons; the order you build is the order they play. Changes to the item list save as you make them - the Publish/Update button saves the title, description, and featured image.

## Playback options

A playlist has four playback options. Set them under **Playback**, either in the **Manage items** popup on the Playlists screen or in the **Playlist Items** panel on the playlist's edit screen. Each one saves as you change it.

- **Play the next video automatically** - when a video ends, a short countdown runs and the next one starts.
- **Seconds before the next video starts** - the length of that countdown, 1 to 30. Default is 5.
- **Start again from the first video after the last** - loop the playlist.
- **Play in random order** - shuffle.

A new playlist starts with all of them off. The Playlists list shows which are on as badges next to the title.

Every video in a playlist plays in the same protected player as a single video: it is watermarked, tracked, counted towards milestones, and checked against the video's access rules. Autoplay works for every platform.

Developers can also set the options through the REST API (`POST /wp-json/wp/v2/mediashield-playlists/{id}` with `meta`) or `update_post_meta()`. The meta keys are `_ms_autoplay`, `_ms_countdown`, `_ms_loop`, and `_ms_shuffle`.

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
