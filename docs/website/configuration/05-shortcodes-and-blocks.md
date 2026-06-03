# Shortcodes and Blocks

MediaShield provides three shortcodes and three Gutenberg blocks for embedding video content. All three shortcodes have matching blocks.

## Shortcodes

### [mediashield] - Embed a single video

Renders a protected video player.

```
[mediashield id=42]
```

| Attribute | Required | Description |
|-----------|----------|-------------|
| `id` | Yes | The ID number of the video from your MediaShield video library |

Find the video ID in the URL when editing the video (`post=42`), or in the Videos list.

If the ID is missing, invalid, or the video is unpublished, the shortcode renders nothing and skips loading assets.

### [mediashield_playlist] - Embed a playlist

Renders a protected playlist player.

```
[mediashield_playlist id=15]
```

| Attribute | Required | Description |
|-----------|----------|-------------|
| `id` | Yes | The ID number of the playlist from your MediaShield library |

Every video inside the playlist runs through the full protection layer, session tracking, and milestone detection.

### [mediashield_my_videos] - User's watched videos

Renders a grid of every video the current logged-in user has watched, with completion progress bars.

```
[mediashield_my_videos]
```

No attributes. Shows nothing for visitors who are not logged in. Use this on a member dashboard or course completion page.

## Gutenberg Blocks

All three blocks are available in the block inserter by searching for "MediaShield."

### MediaShield Video block

Embeds a single protected video. In the block editor, it opens a video picker modal. You can also paste a video URL directly into the block to create a library entry on the fly. The editor shows a live preview.

Frontend output includes the player, watermark, session tracking, and the login overlay when required.

### MediaShield Playlist block

Embeds a playlist. The block opens a playlist picker. Playlist features include autoplay with countdown, shuffle, loop, and per-video progress tracking.

### MediaShield My Videos block

Displays the current logged-in user's watched video history with completion progress. No configuration options. Use it anywhere you want to show a viewer their watch history.

## Using shortcodes in page builders

MediaShield works with Elementor, Beaver Builder, Divi, WPBakery, and any builder that supports shortcodes or raw HTML widgets. Place `[mediashield id=X]` in a text or shortcode widget.

For builders that render video via JavaScript after the page loads (which can sometimes run after the output buffer), use the block or shortcode method rather than pasting a raw video URL. That guarantees the protection wrapper is applied.

## Asset loading

MediaShield loads its CSS and JavaScript only on pages that contain video content. Assets are not loaded on pages without videos, so there is no performance impact on unrelated pages.

Assets are loaded when:
1. A `[mediashield]` shortcode is found in post content
2. A MediaShield block is present on the page
3. The output buffer detects a video or iframe element matching a known pattern

The output buffer detection covers standard `<video>` elements, YouTube iframes, Vimeo iframes, Bunny Stream iframes, Wistia inline embeds, and any custom URL patterns you've added in Settings.
