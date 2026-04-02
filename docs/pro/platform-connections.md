# MediaShield Pro -- Platform Connections

Platform connections let you browse, import, and upload videos to external hosting services directly from WordPress.

## Supported Platforms

| Platform | Upload Method | Browse & Import | DRM Support |
|----------|--------------|----------------|-------------|
| Bunny Stream | tus resumable | Yes | Yes (cloud) |
| Vimeo | tus resumable (API v3) | Yes | No |
| YouTube | Resumable (Data API v3) | Yes | No |
| Wistia | Multipart form | Yes | No |

## Connecting a Platform

1. Go to **MediaShield > Platforms** in the admin SPA.
2. Click **Add Connection**.
3. Select the platform.
4. Enter your API credentials.
5. Click **Connect**.

Credentials are encrypted with AES-256-CBC using your site's `SECURE_AUTH_SALT` before storage.

### Bunny Stream

**Required credentials:**
- **API Key** -- Your Bunny.net API key (Account > API Keys)
- **Library ID** -- The video library ID (Stream > Library > Settings)
- **Pull Zone Hostname** -- e.g., `vz-abc123.b-cdn.net` (stored in `extra_config`)
- **CDN Token Key** -- For signed URL generation (optional, stored in `extra_config`)

**What it enables:**
- Browse all videos in your Bunny library
- Bulk import videos as MediaShield video CPTs
- Upload new videos via tus resumable upload
- Auto-generate HLS streaming URLs
- Signed/token-authenticated playback URLs
- Cloud DRM via Bunny's built-in Widevine support

### YouTube

**Required credentials:**
- **API Key** -- YouTube Data API v3 key (Google Cloud Console)
- **Channel ID** -- Your YouTube channel ID

**What it enables:**
- Browse your channel's video library
- Import videos as MediaShield video CPTs (embeds, not downloads)
- Upload new videos to your channel

Note: YouTube videos play via iframe embed. Protection overlay is applied on top of the iframe.

### Vimeo

**Required credentials:**
- **Access Token** -- Vimeo API v3 access token (developer.vimeo.com)

**What it enables:**
- Browse your Vimeo video library
- Import videos as MediaShield video CPTs
- Upload new videos via tus resumable upload

### Wistia

**Required credentials:**
- **API Token** -- Wistia API token (Account > Settings > API Access)

**What it enables:**
- Browse your Wistia project videos
- Import videos as MediaShield video CPTs
- Upload new videos via multipart form upload

## Multiple Connections

You can connect multiple libraries from the same platform (e.g., two Bunny Stream libraries for different course categories). Each connection is stored as a separate row in `ms_platform_connections`.

## REST API

| Method | Route | Description |
|--------|-------|-------------|
| GET | `/mediashield-pro/v1/platforms` | List all platform connections |
| POST | `/mediashield-pro/v1/platforms` | Create a new connection |
| DELETE | `/mediashield-pro/v1/platforms/{id}` | Disconnect a platform |

All endpoints require `manage_options` capability.

## Browsing & Importing

Once connected, use the **Platforms** admin page to:

1. Select a connected platform from the dropdown.
2. Browse available videos with thumbnails and metadata.
3. Select videos to import (single or bulk).
4. Imported videos create `mediashield_video` CPTs with:
   - `_ms_platform` set to the platform name
   - `_ms_platform_video_id` set to the external ID
   - `_ms_source_url` set to the streaming/embed URL
   - Title and thumbnail pulled from the platform API

## Uploading

From the video editor or Platforms page:

1. Click **Upload Video**.
2. Select the target platform connection.
3. Choose a file (drag-and-drop supported).
4. Upload begins with progress tracking.
5. On completion, the video CPT meta is updated automatically.

Upload progress is tracked in the `ms_upload_queue` table with status: `pending` > `uploading` > `processing` > `complete` (or `failed`).

## Frontend Upload

Add `[mediashield_upload]` to any page to allow authorized users to upload videos. Users must have the `upload_mediashield` capability. Connected platforms appear as upload targets in the form.
