# DRM Internals

How MediaShield Pro generates keys, packages video, and serves licenses. This page is for developers extending or debugging the DRM layer. For the site-owner setup guide, see [`docs/pro/drm-setup.md`](../pro/drm-setup.md).

---

## Status: experimental, and partly unwired

Read this before anything below.

- **Before 1.3.0 DRM could not be turned on at all.** Pro decides a video is DRM-protected by reading `_ms_protection_level === 'drm'`, but the Protection Level list on the video edit screen was a closed array, so nothing could ever store that value. The `mediashield_protection_levels` filter (free, 1.3.0) is what opened it; Pro's `Core\Plugin::register_drm_level()` hooks it and adds the `drm` level - and only when `ms_drm_method` is something other than `none`. The label Pro registers is literally "DRM - Encrypted playback (experimental)".
- **Encrypted playback has never been verified end to end.** Treat everything on this page as the intended design plus the code that exists, not as a shipped, proven pipeline.
- **The packaging code was deleted in 1.3.0.** `DRM\Packager` had no callers anywhere in either plugin, and `DRM\KeyServer::generate_key()` was called only from inside it, so no key row could ever be created. Rather than keep ~480 lines that nothing could reach, both were removed along with the `ms_drm_shaka_path` and `ms_drm_auto_package` settings and the `local_shaka` / `cloud_aws` methods. `KeyServer::get_key()` and `DRM\WidevineLicense` remain because `REST\DRMController` genuinely calls them - but with no writer for `ms_drm_keys`, `POST /drm/license` answers `drm_key_not_found` for every video. Restoring DRM means writing a packaging path AND a trigger for it, not re-wiring what was there.

---

## Key generation - `DRM\KeyServer`

`KeyServer::generate_key( $video_id )` (**removed in 1.3.0** - documented here only so an older install's rows make sense):

1. `bin2hex( random_bytes( 16 ) )` produces a 128-bit key ID as 32 hex characters.
2. `bin2hex( random_bytes( 16 ) )` produces the 128-bit content key, also carried as a hex string (not raw bytes) everywhere in PHP.
3. The hex content key is encrypted with `PlatformController::encrypt()` - AES-256-CBC keyed on `SECURE_AUTH_SALT`, with the IV embedded in the ciphertext.
4. The row is written to `ms_drm_keys` as `key_id` + `content_key_encrypted`, INSERTed or UPDATEd against the UNIQUE `uk_video` constraint on `video_id` (one key per video).

The column is `content_key_encrypted`; there is no plaintext `content_key` column. The legacy `iv` column was never read and is dropped at DB v2 - see [database-tables.md](database-tables.md#ms_drm_keys).

---

## Video packaging - removed in 1.3.0

There is no packaging code. `DRM\Packager` held it - a dispatcher on
`ms_drm_method` with a `cloud_bunny` branch, a `local_shaka` branch that shelled
out to Shaka Packager, a `cloud_aws` stub that returned 501, and an Action
Scheduler variant - and **nothing in either plugin ever called any of it**. It
was deleted rather than kept as ~480 lines of unreachable code that reads like a
working feature.

Removed with it: `DRM\KeyServer::generate_key()` (the only caller was Packager),
the `ms_drm_shaka_path` and `ms_drm_auto_package` settings, the `local_shaka` and
`cloud_aws` values of `ms_drm_method`, and the `mediashield_before_drm_package` /
`mediashield_after_drm_package` actions, which only ever fired from inside the
dead path.

`ms_drm_method` survives, narrowed to `none` | `cloud_bunny`, because
`Plugin::override_player_type()` and `Plugin::register_drm_level()` both read it -
it now means "should protected videos use the encrypted player", not "how do we
package". A stored `local_shaka` or `cloud_aws` coerces to `none` on next save.

**Consequence, stated plainly:** nothing writes `ms_drm_keys`, so
`KeyServer::get_key()` returns null for every video and the license endpoint
below answers `drm_key_not_found` every time. That was already true before the
deletion - the write path was unreachable - it is simply visible in the code
now. Restoring DRM means writing a packaging path *and* a trigger for it.

## License serving - `POST /mediashield-pro/v1/drm/license`

Permission callback is `is_user_logged_in()`. Body: `video_id` (required), `device_id` (optional).

1. The player sends a ClearKey license request to the configured license URL.
2. `WidevineLicense::issue_clearkey_license()` calls `issue_license()`, which runs `Access\AccessControl::can_watch()` - the full `mediashield_can_watch` chain including Pro's role check at 20 and the LMS gates at 25 - and refuses if any row for that user+video pair has a non-NULL `revoked_at`.
3. On approval it writes an `ms_drm_licenses` row with `license_type = 'streaming'` and `expires_at = now + ms_drm_license_duration_streaming` (default 86400 s, floored at 300 s by the settings validator).
4. It decrypts `content_key_encrypted`, converts both the key ID and the content key from hex to base64url, and returns the ClearKey JWK set:

```json
{ "keys": [ { "kty": "oct", "kid": "<base64url>", "k": "<base64url>" } ], "type": "temporary" }
```

`type` is always `temporary`. **There is no `/drm/offline` route and no persistent-license flow** - offline licensing was removed in 1.2.0. The `persistent` value survives in the `ms_drm_licenses.license_type` enum for legacy rows only, and `ms_drm_license_duration_persistent` is not a real option.

Revocation is `POST /drm/revoke` (`manage_options`, body `video_id` + `user_id`), exposed in the admin as the "Revoke All Licenses" form on the DRM settings page. It stamps `revoked_at`, which `is_revoked()` then reads on every subsequent issue - a standing decision about the person, not about one license row.

---

## Bunny Stream DRM (cloud)

When DRM method is `cloud_bunny`, MediaShield does not run the local packager. Instead:

1. Video is uploaded to Bunny Stream via the tus resumable upload protocol (`Upload\Drivers\BunnyStream`).
2. Bunny packages and serves the streams from its own infrastructure.
3. `Packager::package_cloud_bunny()` only validates that `_ms_source_url` holds a usable URL and writes the DRM meta, including `_ms_protection_level = 'drm'`.
4. `Platform\BunnyUrls` supplies the playback URL through free's `mediashield_video_stream_url` filter, optionally token-signed (`mediashield_pro_bunny_token_key`, `mediashield_pro_bunny_token_ttl`, default 6 hours).

The encrypted content never passes through your WordPress server in the Bunny cloud method.

---

## Security considerations

- Content keys are never logged or returned in plaintext hex; the license response carries them base64url-encoded inside the JWK, which is what ClearKey requires.
- `SECURE_AUTH_SALT` is the encryption key for at-rest key storage. Rotating this salt will invalidate all stored keys - avoid salt rotation once DRM packaging is in use.
- License requests require a logged-in WordPress session at the permission callback, and then the full `mediashield_can_watch` chain inside the handler.
- ClearKey is software-based DRM. Keys are visible in the browser JS debugger with effort. This is an inherent limitation of ClearKey, and part of why the level is labelled experimental. See [`docs/pro/drm-types-explained.md`](../pro/drm-types-explained.md) for the full tier comparison.
