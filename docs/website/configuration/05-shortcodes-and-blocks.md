# Shortcodes and Blocks

MediaShield provides three shortcodes and three Gutenberg blocks for embedding video content. Each shortcode has a matching block.

## Shortcodes

### [mediashield] - Embed a single video

Renders a protected video player.

```
[mediashield id=42]
```

| Attribute | Required | Description |
|-----------|----------|-------------|
| `id` | Yes | The ID number of the video from your MediaShield video library |

`id` is the only attribute. Find the video ID in the URL when editing the video (`post=42`), or use the copy-shortcode button in the Videos list.

If the ID is missing, invalid, the video is unpublished, or the video has no source URL yet, visitors see nothing at all and no player assets are loaded. Anyone who can edit posts sees a one-line message naming which of those it was, so a blank space in a page is never a mystery for the site owner.

### [mediashield_playlist] - Embed a playlist

Renders a protected playlist player: the current video, plus a sidebar list of the queue.

```
[mediashield_playlist id=15]
```

| Attribute | Required | Description |
|-----------|----------|-------------|
| `id` | Yes | The ID number of the playlist from your MediaShield library |

Access control, session tracking, milestones, and the watermark all apply per video as the queue advances, using each video's own protection level. One exception: "Hide Video Source URL" is not applied to the playlist markup, so a self-hosted video's file address is visible in the page source of a playlist even when it would be hidden on a single-video embed. If that matters for a particular video, embed it on its own with `[mediashield id=X]`.

### [mediashield_my_videos] - User's watched videos

Renders a grid of every video the current logged-in user has watched, with completion progress bars.

```
[mediashield_my_videos]
```

No attributes. Visitors who are not logged in see "Please log in to see your video history." Use this on a member dashboard or course completion page.

Only videos at Standard or Strict protection appear here, because those are the levels that record watch sessions.

## Gutenberg Blocks

All three blocks are available in the block inserter by searching for "MediaShield".

### MediaShield Video block

Embeds a single protected video. In the block editor it opens a video picker, and you can also paste a video URL to create a library entry on the fly. The editor shows a preview, and the block sidebar exposes the video's protection level and duration so you can change them without leaving the page.

Frontend output includes the player, watermark, session tracking, and the login overlay when required.

### MediaShield Playlist block

Embeds a playlist. The block opens a playlist picker. Playback options (autoplay, countdown, loop, shuffle) are stored on the playlist itself - see [Playlists](../using-mediashield/03-playlists.md).

### MediaShield My Videos block

Displays the current logged-in user's watched video history with completion progress. No configuration options. Use it anywhere you want to show a viewer their watch history.

## A retired Pro shortcode

MediaShield Pro used to register a fourth shortcode, `[mediashield_upload]`, for a member-facing upload form. It never worked in any released version - the form rendered, but submitting it could not reach the upload endpoint - and it was retired in 1.3.0.

The shortcode is still registered so it does not print as literal text on pages that still contain it. It now shows visitors nothing, and shows editors a note explaining the retirement. Remove it from your content when convenient. Uploading is done from **Videos > Add New** in the admin.

## Using shortcodes in page builders

MediaShield works with Elementor, Beaver Builder, Divi, WPBakery, and any builder that supports shortcodes or raw HTML widgets. Place `[mediashield id=X]` in a text or shortcode widget.

For builders that render video via JavaScript after the page loads (which can run after MediaShield's output buffer has already finished), use the block or shortcode rather than pasting a raw video URL. That guarantees the protection wrapper is applied.

## Automatic detection of embeds

Besides shortcodes and blocks, MediaShield scans page output for video embeds: `<video>` elements, YouTube and YouTube-nocookie iframes, Vimeo iframes, Bunny Stream iframes, and Wistia inline embeds.

Two rules govern what it does with them:

- **Only videos in your MediaShield library are wrapped.** An embed MediaShield does not recognise is left exactly as your theme or another plugin emitted it. Wrapping a video you do not own would burn a viewer's watermark onto somebody else's embed.
- **Auto-wrapped players use the video's own protection level**, falling back to the site-wide default when it has none - the same answer the shortcode and block give. Before 1.3.0 this path ignored the per-video setting, so the same video could be protected differently depending on how it reached the page.
- **The scan only runs on pages whose content contains an embed.** MediaShield checks the page's own content first and skips the scan entirely when there is nothing to find, which keeps it off pages with no video. The trade-off is that a video printed by your theme or page builder, rather than saved in the page content, is not detected - use the block or shortcode for those, or have a developer switch the `mediashield_force_output_buffer` filter on.

To exclude a specific embed from the scan, add `data-ms-skip` or the class `ms-skip` to it.

## Asset loading

MediaShield's CSS and JavaScript are registered on every frontend page but only loaded when there is something to play. They are enqueued when:

1. A MediaShield shortcode renders output
2. A MediaShield block renders output
3. The page's content contains a video embed, which is what starts the output-buffer scan

Point 3 changed in 1.3.0. The decision used to be made during the scan itself, which runs after the page's scripts have already been written out - so the assets never actually loaded and an auto-detected embed was replaced with a player that had no player behind it. Deciding before the page is built means MediaShield only ever replaces an embed on a page where it can also load the code to play it.

Note the third case is deliberately broad: the scan enqueues on any page carrying a video or iframe element, including iframes that have nothing to do with MediaShield. Pages with no video and no iframe at all load nothing. Developers who want tighter control can use the `mediashield_enable_output_buffer` and `mediashield_enqueue_frontend` filters.
