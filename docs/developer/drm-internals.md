# DRM Internals (removed)

There is no DRM layer in MediaShield Pro 1.4.1. The DRM protection level, the DRM admin page, the `/drm/license` and `/drm/revoke` routes and the encrypted player were removed.

MediaShield controls who can start playback. Encryption and DRM, if needed, come from the video host (Bunny Stream and similar).

Leftover DRM tables and post meta keys from older versions are listed as legacy in [Database Tables](database-tables.md) and [Post Meta Reference](post-meta-reference.md).
