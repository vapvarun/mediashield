# MediaShield Pro -- Platform Connections

Platform connections let you browse, import, and upload videos to external hosting services directly from WordPress.

## Supported Platforms

| Platform | Upload Support | Browse & Import | DRM Support |
|----------|--------------|----------------|-------------|
| Bunny Stream | Yes (large files won't fail if your connection drops) | Yes | Yes (cloud) |
| Vimeo | Yes (large files won't fail if your connection drops) | Yes | No |
| YouTube | Yes | Yes | No |
| Wistia | Yes | Yes | No |

## Connecting a Platform

1. Go to **MediaShield > Platforms** in your admin.
2. Click **Add Connection**.
3. Select the platform.
4. Enter your API credentials.
5. Click **Connect**.

Your credentials are encrypted before being stored -- they're never readable in plaintext, even from database backups.

### Bunny Stream

**Required credentials:**
- **API Key** -- Your Bunny.net API key (Account > API Keys in the Bunny dashboard).
- **Library ID** -- The video library ID (Stream > Library > Settings).
- **Pull Zone Hostname** -- e.g., `vz-abc123.b-cdn.net` (shown in your Bunny Stream library settings).
- **CDN Token Key** -- For signed URL playback (optional; required if your Bunny library has token authentication enabled).

**What it enables:**
- Browse all videos in your Bunny library.
- Bulk import videos into MediaShield.
- Upload new videos with progress tracking.
- Automatically generate streaming URLs.
- Signed/token-authenticated playback URLs.
- Cloud DRM via Bunny's built-in Widevine support.

### YouTube

**Required credentials:**
- **API Key** -- YouTube Data API v3 key (Google Cloud Console > APIs & Services).
- **Channel ID** -- Your YouTube channel ID (found in YouTube Studio > Settings > Channel).

**What it enables:**
- Browse your channel's video library.
- Import videos into MediaShield (as embeds, not downloads).
- Upload new videos to your channel.

Note: YouTube videos play via the standard YouTube embed. MediaShield wraps the embed with its protection layer on top.

### Vimeo

**Required credentials:**
- **Access Token** -- Vimeo API v3 access token (developer.vimeo.com > My Apps > Generate Token).

**What it enables:**
- Browse your Vimeo video library.
- Import videos into MediaShield.
- Upload new videos.

### Wistia

**Required credentials:**
- **API Token** -- Wistia API token (Account > Settings > API Access in the Wistia dashboard).

**What it enables:**
- Browse your Wistia project videos.
- Import videos into MediaShield.
- Upload new videos.

## Multiple Connections

You can connect multiple libraries from the same platform -- for example, two separate Bunny Stream libraries for different course categories. Each connection is stored and managed independently.

## Browsing & Importing

Once connected, use the **Platforms** admin page to:

1. Select a connected platform from the dropdown.
2. Browse available videos with thumbnails and metadata.
3. Select videos to import (one at a time or in bulk).
4. Imported videos appear in your MediaShield video library, ready to embed.

MediaShield automatically copies the video title and thumbnail from the platform into the new library entry.

## Uploading

From the video editor or the Platforms page:

1. Click **Upload Video**.
2. Select the target platform connection.
3. Choose a file (drag-and-drop supported).
4. Upload begins with a progress bar.
5. Once complete, the library entry is updated automatically.

The Platforms page shows your uploads moving through stages: pending → uploading → processing → complete. If an upload fails, the error reason is shown alongside a retry option.

## Frontend Upload

Upload videos from **Videos > Add New** in the admin. The upload permission must be assigned to a user's role first.

The `[mediashield_upload]` front-end shortcode was retired in 1.3.0 - it never worked.

---

For developers: platform REST endpoints and the upload queue table schema are documented in [`docs/developer/`](../developer/README.md).
