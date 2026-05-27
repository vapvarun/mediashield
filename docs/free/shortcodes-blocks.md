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

Invalid, missing, or unpublished IDs render nothing and skip asset loading.

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

Renders a grid of videos the current logged-in user has watched, with progress indicators.

```
[mediashield_my_videos]
```

No attributes required. Shows nothing for visitors who aren't logged in.

---

## PHP Template Usage

You can render a protected video directly in PHP templates:

```php
<?php
// Render a specific video by post ID
echo do_shortcode( '[mediashield id=42]' );

// Or use the template helper
if ( function_exists( 'mediashield_render_video' ) ) {
    mediashield_render_video( 42 );
}
?>
```

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
- Sidebar controls for protection level and access settings

**Frontend output:**
- Protected video player with watermark overlay
- Right-click blocking and developer-tools detection
- Session tracking with heartbeat
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

No configurable attributes. Automatically shows the current user's watch history. Shows nothing for visitors who aren't logged in.

> **Developer reference:** the block slug is `mediashield/my-videos`. For developer use only.

---

## Asset Loading

MediaShield only loads its CSS and JavaScript on pages that contain video content. Assets are conditionally loaded when:

1. A `[mediashield]` shortcode is detected in post content.
2. A MediaShield block is present.
3. The output buffer detects a video or iframe element matching known patterns.

This ensures zero performance impact on pages without videos.

---

## Output Buffer Detection

MediaShield uses output buffering to detect and wrap video embeds that aren't placed via shortcode or block. This works with:

- Standard `<video>` elements
- YouTube iframes
- Vimeo iframes
- Bunny Stream iframes
- Wistia inline embeds
- Custom URL patterns (configured in Settings)

The output buffer can be disabled on specific pages via the `mediashield_enable_output_buffer` filter (see the [developer hooks reference](../developer/hooks-filters-free.md)).
