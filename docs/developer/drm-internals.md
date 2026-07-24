# DRM Internals

How MediaShield Pro generates keys, packages video, and serves licenses. This page is for developers extending or debugging the DRM layer. For the site-owner setup guide, see [`docs/pro/drm-setup.md`](../pro/drm-setup.md).

---

## Key generation — `DRM\KeyServer`

When a video is packaged with DRM, `DRM\KeyServer` generates a random AES-128 content key and key ID:

1. `random_bytes(16)` generates a 16-byte (128-bit) AES key.
2. `random_bytes(16)` generates a 16-byte key ID, formatted as a hex string.
3. The key is encrypted at rest with AES-256-CBC using your site's `SECURE_AUTH_SALT` as the encryption key.
4. The encrypted key and key ID are stored in `ms_drm_keys` with a UNIQUE constraint on `video_id` (one key per video).

---

## Video packaging — `DRM\Packager`

When admin clicks "Package with DRM" on a video (or when `ms_drm_auto_package` is on), `DRM\Packager`:

1. Retrieves the content key from `ms_drm_keys` for the video.
2. Calls the Shaka Packager CLI via `shell_exec()`. All arguments are escaped with `escapeshellarg()`.
3. Shaka Packager produces DASH segments plus a DASH manifest (`.mpd`) in a protected output directory under `wp-content/uploads/mediashield/drm/`.
4. Sets `_ms_drm_packaging_status` and `_ms_drm_packaged_at` post meta on the video CPT.

The free plugin's `mediashield_player_type` filter is hooked by Pro to return `'drm'` for any video with `_ms_drm_enabled = true`, which causes `Player\Renderer` to emit a Shaka Player-compatible video element.

---

## License serving — `POST /mediashield-pro/v1/drm/license`

When Shaka Player encounters a ClearKey-protected manifest:

1. Shaka Player sends a ClearKey license request to the configured license URL.
2. MediaShield's license endpoint receives the request.
3. The endpoint calls `Access\AccessControl::can_watch()` — which runs the full `mediashield_can_watch` filter chain including Pro's role check and LMS gates.
4. On approval, the endpoint decrypts the content key from `ms_drm_keys` and returns it in the ClearKey JSON format (`{ keys: [{ kty, k, kid }], type: 'temporary' }`).
5. Shaka Player decrypts and plays the video.

Offline (persistent) licenses use the same flow but set `type: 'persistent-license'` and a longer expiry, stored in `ms_drm_licenses`.

---

## Bunny Stream DRM (cloud)

When DRM method is `cloud_bunny`, MediaShield does not run the local packager. Instead:

1. Video is uploaded to Bunny Stream via the tus resumable upload protocol.
2. Bunny automatically packages DRM-protected DASH and HLS manifests (using Bunny's own Widevine infrastructure).
3. MediaShield's `mediashield_player_type` filter returns `'drm'`.
4. Shaka Player requests a ClearKey license from MediaShield's license endpoint (as above). Bunny's CDN delivers the encrypted segments; MediaShield's WordPress site serves only the key.

The encrypted content never passes through your WordPress server in the Bunny cloud method.

---

## Security considerations

- Content keys are never logged or transmitted in plaintext.
- `SECURE_AUTH_SALT` is the encryption key for at-rest key storage. Rotating this salt will invalidate all stored keys — avoid salt rotation once DRM packaging is in use.
- License requests require a valid WordPress session (`is_user_logged_in()` at minimum) unless a custom `mediashield_can_watch` filter callback permits anonymous access.
- ClearKey is software-based DRM. Keys are visible in the browser JS debugger with effort. This is an inherent limitation of ClearKey. See [`docs/pro/drm-types-explained.md`](../pro/drm-types-explained.md) for the full tier comparison.
