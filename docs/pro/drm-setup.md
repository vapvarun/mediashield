# MediaShield Pro -- DRM Setup Guide

Digital Rights Management (DRM) adds encryption to your videos, making them significantly harder to download or pirate than software-only protection.

**Before you read further, skim the glossary:**

| Term | What it means |
|------|--------------|
| **ClearKey** | A software-based encryption standard built into modern browsers. Keys live in browser memory and can be extracted with effort. Good for most course and membership use cases. |
| **Widevine** | Google's DRM system. ClearKey is technically part of the Widevine family (it's Widevine L3, the software tier). MediaShield uses ClearKey, not the hardware-backed Widevine L1. |
| **DASH** | MPEG-DASH -- a streaming format that splits video into small segments. Required for ClearKey DRM delivery. |
| **HLS** | HTTP Live Streaming -- Apple's streaming format. Used as a fallback for Safari and iOS. |

For a deeper comparison of what ClearKey does and doesn't stop, see [`drm-types-explained.md`](drm-types-explained.md).

---

## Overview

MediaShield Pro supports ClearKey DRM through three methods:

| Method | Key management | Infrastructure | Browser support |
|--------|--------------|----------------|----------------|
| Bunny Stream (cloud) | Automatic | Bunny.net handles everything | Chrome, Firefox, Edge, Android |
| Local packager | Self-managed | CLI tool on your server | Chrome, Firefox, Edge, Android |
| AWS MediaConvert | Planned | AWS cloud packaging | TBD |

**Important:** MediaShield implements ClearKey license serving, not studio-grade Widevine L1. This provides strong protection for most use cases. Safari and iOS fall back to standard protection (watermark + source hiding) because they don't support the ClearKey standard.

---

## Method 1: Bunny Stream DRM (recommended)

The simplest setup -- Bunny handles DRM packaging automatically after you upload your video.

### Prerequisites

- A Bunny Stream library with DRM enabled.
- A connected Bunny platform in MediaShield (see [platform-connections.md](platform-connections.md)).

### Setup

1. Go to **MediaShield > DRM** in the admin.
2. Set **DRM Method** to "Bunny Stream (Cloud)".
3. Save settings.

Videos uploaded to your Bunny library will automatically use DRM-protected playback. The player switches to a DRM-capable mode.

### How it works for the viewer

The viewer clicks play. The player requests a key from your WordPress site, verifying the viewer is logged in and has access to the video. The key is returned only if access is confirmed. The viewer never sees this happen -- the video just plays.

---

## Method 2: Local packager

For self-hosted videos when you want DRM without a cloud provider.

### Prerequisites

- Access to your server's command line.
- Sufficient disk space for packaged output (DASH segment files).

### Setup

1. Install Shaka Packager on your server:
   ```bash
   # Download from https://github.com/shaka-project/shaka-packager/releases
   chmod +x packager
   sudo mv packager /usr/local/bin/
   ```
2. Go to **MediaShield > DRM** in the admin.
3. Set **DRM Method** to "Local Packager".
4. Set **Packager Path** (default: `packager` -- works if the binary is on your system's PATH).
5. Optionally enable **Auto-Package** to automatically DRM-wrap new uploads.
6. Save settings.

### Manual packaging for existing videos

For videos already in your library:
1. Go to the video edit screen.
2. Click **Package with DRM**.
3. MediaShield packages the video into an encrypted streaming format and stores the output. The video library entry updates automatically.

---

## DRM License Types

| Type | Default Duration | Use Case |
|------|-----------------|----------|
| Streaming | 24 hours | Standard web playback |
| Persistent | 30 days | Offline playback (PWA) |

Configure durations in **MediaShield > DRM**:

| Setting | Default | Description |
|---------|---------|-------------|
| Streaming License Duration | 24 hours | How long a streaming license is valid before the player must request a new one. |
| Persistent License Duration | 30 days | How long an offline download license is valid. |

---

## License Management

Admins can revoke DRM licenses from the DRM admin page. Revoking a license immediately prevents the video from being decrypted and played by that user again -- useful when you terminate a membership or suspect credential sharing.

---

## PWA Offline Playback

For persistent licenses:

1. Enable offline support in DRM settings.
2. A "Save for Offline" button appears on the player for viewers.
3. Viewers download the encrypted video. It plays in their browser without an internet connection for up to 30 days (configurable).
4. Admins can revoke offline access at any time.

---

## Browser Compatibility

| Browser | ClearKey DRM |
|---------|-------------|
| Chrome (desktop and Android) | Supported |
| Firefox | Supported |
| Edge | Supported |
| Safari | Not supported (falls back to standard protection) |
| iOS Safari | Not supported (falls back to standard protection) |

Safari and iOS users are protected by MediaShield's watermark, source hiding, and access control -- they just don't get the DRM encryption layer.

---

For developers: how key generation and packaging work internally is documented in [`docs/developer/drm-internals.md`](../developer/drm-internals.md). License endpoint details are in [`docs/developer/rest-api.md`](../developer/rest-api.md).
