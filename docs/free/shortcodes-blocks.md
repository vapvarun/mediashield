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
| `id` | int | required | The video CPT post ID |

**Example:**

```html
<!-- Basic usage -->
[mediashield id=42]

<!-- In a page builder text widget -->
<div class="video-container">
    [mediashield id=42]
</div>
```

### [mediashield_my_videos]

Renders a grid of videos the current logged-in user has watched, with progress indicators.

```
[mediashield_my_videos]
```

No attributes required. Shows nothing for non-logged-in users.

---

## PHP Template Usage

You can render a protected video directly in PHP templates:

```php
<?php
// Render a specific video by post ID
echo do_shortcode( '[mediashield id=42]' );

// Or use the block render function
if ( function_exists( 'mediashield_render_video' ) ) {
    mediashield_render_video( 42 );
}
?>
```

---

## Gutenberg Blocks

### MediaShield Video Block

**Slug:** `mediashield/video`

Embed a single protected video with full player wrapper.

**Block Attributes:**

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `videoId` | number | `0` | Selected video CPT post ID |

**Editor Features:**
- Video picker modal with search
- URL paste detection (auto-creates video CPT)
- Live preview in editor
- Sidebar controls for protection level and access settings

**Frontend Output:**
- Protected video player with watermark overlay
- Right-click blocking and devtools detection
- Session tracking with heartbeat
- Login overlay for non-authenticated users (when required)

### MediaShield Playlist Block

**Slug:** `mediashield/playlist`

Embed a playlist of protected videos with autoplay and countdown.

**Block Attributes:**

| Attribute | Type | Default | Description |
|-----------|------|---------|-------------|
| `playlistId` | number | `0` | Selected playlist CPT post ID |

**Playlist Features:**
- Configurable autoplay with countdown between videos
- Shuffle and loop modes
- Drag-and-drop reordering in editor
- Progress tracking per video in playlist

### MediaShield My Videos Block

**Slug:** `mediashield/my-videos`

Display the logged-in user's watched video history with completion progress.

**Block Attributes:**

No configurable attributes. Automatically shows the current user's watch history.

---

## Asset Loading

MediaShield only loads its CSS and JavaScript on pages that contain video content. Assets are conditionally enqueued when:

1. A `[mediashield]` shortcode is detected in post content
2. A MediaShield Gutenberg block is present
3. The output buffer detects a video/iframe element matching known patterns

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

The output buffer can be disabled on specific pages via the `mediashield_enable_output_buffer` filter:

```php
add_filter( 'mediashield_enable_output_buffer', function( $enabled ) {
    if ( is_checkout() ) {
        return false; // Don't scan checkout pages
    }
    return $enabled;
});
```
