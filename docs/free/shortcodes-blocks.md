# MediaShield -- Shortcodes & Blocks

## Shortcodes

### [mediashield]

Renders a protected video player.

```
[mediashield id=123]
```

**Attributes:**

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `id` | int | required | The ID number of the video from your MediaShield video library |

**Example:**

```html
<!-- Basic usage -->
[mediashield id=42]

<!-- In a page builder text widget -->
<div class="video-container">
    [mediashield id=42]
</div>
```

If the ID doesn't match a published video, visitors see nothing at all. Anyone who can edit posts sees a short message on the page instead, saying whether the ID matched nothing or matched a video that isn't published yet -- so if you can see an explanation and your visitors report a blank space, that's why.

### [mediashield_playlist]

Renders a protected playlist player.

```
[mediashield_playlist id=15]
```

**Attributes:**

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `id` | int | required | The ID number of the playlist from your MediaShield library |

### [mediashield_my_videos]

Renders a grid of videos the current logged-in user has watched, with progress indicators and All / In Progress / Completed filter buttons.

```
[mediashield_my_videos]
```

No attributes. Visitors who aren't logged in see "Please log in to see your video history." A logged-in user with no watch history yet sees "You have not watched any videos yet."

---

## PHP Template Usage

To render a protected video from a PHP template, run the shortcode:

```php
<?php
// Render a specific video by its library ID
echo do_shortcode( '[mediashield id=42]' );
?>
```

The same works for `[mediashield_playlist id=15]` and `[mediashield_my_videos]`.

---

## Gutenberg Blocks

### MediaShield Video Block

Embed a single protected video with the full player and protection layer.

**How to add it:** In the block inserter, search for "MediaShield Video."

**Block attributes:**

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `videoId` | number | `0` | The video selected from your library |

**Editor features:**
- Video picker with search
- URL paste detection (creates a library entry automatically)
- Live preview in editor
- Sidebar controls for the video's protection level and duration

**Frontend output:**
- Protected video player with watermark overlay (Standard and Strict only)
- Right-click blocking and developer-tools detection, per your Protection settings
- Session tracking with heartbeat (Standard and Strict only)
- Login overlay for visitors who aren't logged in (when required)

> **Developer reference:** the block slug is `mediashield/video`. For developer use only.

---

### MediaShield Playlist Block

Embed a playlist of protected videos with autoplay and countdown.

**How to add it:** In the block inserter, search for "MediaShield Playlist."

**Block attributes:**

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `playlistId` | number | `0` | The playlist selected from your library |

**Playlist features:**
- Configurable autoplay with countdown between videos
- Shuffle and loop modes
- Drag-and-drop reordering in editor
- Progress tracking per video in playlist

> **Developer reference:** the block slug is `mediashield/playlist`. For developer use only.

---

### MediaShield My Videos Block

Display the logged-in user's watched video history with completion progress.

**How to add it:** In the block inserter, search for "MediaShield My Videos."

No configurable attributes. Automatically shows the current user's watch history. Visitors who aren't logged in see a "Please log in to see your video history" line.

> **Developer reference:** the block slug is `mediashield/my-videos`. For developer use only.

---

## Asset Loading

MediaShield doesn't load its CSS and JavaScript on every page. They load when:

1. A `[mediashield]` shortcode is rendered.
2. A MediaShield block is present.
3. The page contains any `<video>` element, `<iframe>`, or Wistia embed at all.

The third case is a wide net on purpose -- MediaShield has to load its assets before it knows whether an embed on the page is one of yours. In practice it means a page carrying an unrelated iframe (an embedded map, for instance) also loads MediaShield's assets. Pages with no video or iframe markup load nothing.

---

## Automatic Detection of Existing Embeds

MediaShield scans each page as it renders and takes over video embeds that weren't placed with a shortcode or block. It recognises:

- Standard `<video>` elements
- YouTube iframes (including youtube-nocookie)
- Vimeo iframes
- Bunny Stream iframes
- Wistia inline embeds

**It only takes over an embed it can match to a video in your MediaShield library** -- by the platform video ID for YouTube / Vimeo / Bunny / Wistia, or by the exact file address for a self-hosted `<video>`. Anything it can't match is left byte-for-byte as the theme or other plugin emitted it. Wrapping a video you don't own would burn a viewer's name and IP onto someone else's content, and for a self-hosted tag it would break playback outright, so MediaShield declines rather than guesses.

Two consequences worth planning around:

- Adding the video to your MediaShield library is what switches automatic detection on for it. There is no "protect every embed on the site" mode.
- An automatically detected embed uses your **site-wide** default protection level and ignores that video's per-video protection level. Place the video with the block or the shortcode if you need the per-video setting to apply.

To keep a specific embed out of MediaShield's hands, give it a `data-ms-skip` attribute or an `ms-skip` class. Detection can be turned off entirely via the `mediashield_enable_output_buffer` filter (see the [developer hooks reference](../developer/hooks-filters-free.md)).
