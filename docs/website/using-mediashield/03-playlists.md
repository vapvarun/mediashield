# Playlists

Playlists are groups of videos played in sequence. Use them for course modules, tutorial series, chapter sequences, or any content that has a natural order.

Playlists were introduced and are fully supported in version 1.1.0.

## Creating a playlist

Go to **MediaShield > Playlists** and click **Add New Playlist**.

Give the playlist a title, then add videos using the video picker. You can drag and drop to set the order.

Playlist settings:

**Autoplay** - When on, the next video starts automatically when the current one ends.

**Countdown** - How many seconds to wait between videos when autoplay is on. Default is 5 seconds. A countdown overlay shows the viewer that the next video is about to start.

**Loop** - When the last video in the playlist ends, start from the first video again.

**Shuffle** - Play the videos in a random order instead of the set order.

Click **Save** when done.

## Embedding a playlist

You have two options:

### Shortcode

```
[mediashield_playlist id=15]
```

Replace `15` with your playlist's ID. The ID appears in the URL when editing the playlist (`post=15`).

### Gutenberg block

In the block inserter, search for **MediaShield Playlist**. The block opens a playlist picker.

Both embedding methods produce identical output.

## How playlists work with protection

Every video inside a playlist runs through the same protection layer as a standalone embed:

- The watermark is applied per-video based on each video's protection level
- Session tracking runs per video (each video in the playlist generates its own session record)
- Milestones are tracked per video
- Access control is checked per video - if a viewer doesn't have permission for one video in the playlist, that video is blocked but others continue to work

## Playlist thumbnails

Set a **Featured Image** on the playlist post to use as the playlist thumbnail in any listing. Individual video thumbnails are shown in the playlist player according to each video's own Featured Image.

## Reordering videos

Open the playlist edit screen and drag the videos into the desired order. Save. The new order takes effect immediately for all future playback.
