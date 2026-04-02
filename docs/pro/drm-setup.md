# MediaShield Pro -- DRM Setup Guide

Digital Rights Management (DRM) adds hardware-level content protection to your videos, making them significantly harder to pirate than software-only measures.

## Overview

MediaShield Pro supports Widevine ClearKey DRM through three methods:

| Method | Key | Infrastructure | Browser Support |
|--------|-----|---------------|----------------|
| Bunny Stream (cloud) | Automatic | Bunny.net handles everything | Chrome, Firefox, Edge, Android |
| Local Shaka Packager | Self-managed | CLI tool on your server | Chrome, Firefox, Edge, Android |
| AWS MediaConvert | Planned | AWS cloud packaging | TBD |

**Important:** MediaShield implements ClearKey license serving, not a full Widevine proxy. This provides strong protection for most use cases but is not the same as studio-grade Widevine L1.

## Method 1: Bunny Stream DRM (Recommended)

The simplest setup -- Bunny.net handles DRM packaging automatically.

### Prerequisites

- A Bunny Stream library with DRM enabled
- A connected Bunny platform in MediaShield

### Setup

1. Go to **MediaShield > DRM** in the admin SPA.
2. Set **DRM Method** to "Bunny Stream (Cloud)".
3. Save settings.

Videos uploaded to your Bunny library will automatically use DRM-protected playback. The player switches to Shaka Player with Widevine support.

### How It Works

1. Video is uploaded to Bunny Stream via tus.
2. Bunny automatically generates DRM-protected DASH/HLS manifests.
3. MediaShield detects DRM availability and overrides the player type to `drm`.
4. Shaka Player requests a ClearKey license from your WordPress site.
5. License endpoint validates user access via `mediashield_can_watch`, then returns the key.

## Method 2: Local Shaka Packager

For self-hosted videos when you want DRM without a cloud provider.

### Prerequisites

- Shaka Packager binary installed on your server (`packager` command)
- OpenSSL for key generation
- Sufficient disk space for packaged output (DASH segments)

### Setup

1. Install Shaka Packager on your server:
   ```bash
   # Download from https://github.com/shaka-project/shaka-packager/releases
   chmod +x packager
   sudo mv packager /usr/local/bin/
   ```
2. Go to **MediaShield > DRM** in the admin.
3. Set **DRM Method** to "Local Shaka Packager".
4. Set **Shaka Packager Path** (default: `packager`).
5. Optionally enable **Auto-Package** to automatically DRM-wrap new uploads.
6. Save settings.

### Manual Packaging

For existing videos:
1. Go to the video editor.
2. Click **Package with DRM**.
3. MediaShield generates an AES-128 content key, packages the video into DASH segments, and stores the output.

### How It Works

1. `DRM\KeyServer` generates a random AES-128 key and key ID, encrypted with your `SECURE_AUTH_SALT`.
2. `DRM\Packager` calls Shaka Packager CLI with `escapeshellarg()` for all arguments.
3. Output DASH segments are stored in a protected directory.
4. Player loads the DASH manifest and requests a ClearKey license from `/mediashield-pro/v1/drm/license`.

## DRM License Types

| Type | Default Duration | Use Case |
|------|-----------------|----------|
| Streaming | 24 hours | Standard web playback |
| Persistent | 30 days | PWA offline playback |

Configure durations in **MediaShield > DRM**:

| Setting | Option Key | Default |
|---------|-----------|---------|
| Streaming Duration | `ms_drm_license_duration_streaming` | `86400` (24h) |
| Persistent Duration | `ms_drm_license_duration_persistent` | `2592000` (30d) |

## License Management

### REST Endpoints

| Method | Route | Permission | Description |
|--------|-------|------------|-------------|
| POST | `/drm/license` | logged_in | Issue streaming license |
| POST | `/drm/offline` | logged_in | Issue persistent license for offline |
| POST | `/drm/revoke` | manage_options | Revoke all licenses for user+video |

### Revoking Licenses

Admins can revoke DRM licenses from the DRM admin page or via REST API. Revocation sets the `revoked_at` timestamp, preventing the key from being served again.

## PWA Offline Playback

For persistent (offline) DRM licenses:

1. Enable offline support in DRM settings.
2. MediaShield registers a Service Worker that caches DASH segments.
3. A "Save for Offline" button appears on the player.
4. Users can download DRM-protected videos for offline viewing (license valid for 30 days by default).

## Security

- Content keys are encrypted at rest with AES-256-CBC using `SECURE_AUTH_SALT`.
- License requests validate user access via the `mediashield_can_watch` filter chain.
- Each key has a UNIQUE constraint on `video_id` in `ms_drm_keys`.
- Shaka Packager CLI arguments are escaped with `escapeshellarg()`.

## Browser Compatibility

| Browser | Widevine ClearKey |
|---------|------------------|
| Chrome (desktop/Android) | Supported |
| Firefox | Supported |
| Edge | Supported |
| Safari | Not supported (uses FairPlay) |
| iOS Safari | Not supported |

For Safari/iOS users, videos fall back to standard protection (watermark + download prevention) without DRM.
