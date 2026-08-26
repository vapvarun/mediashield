# DRM Internals

How MediaShield Pro generates keys, packages video, and serves licenses. This page is for developers extending or debugging the DRM layer. For the site-owner setup guide, see [`docs/pro/drm-setup.md`](../pro/drm-setup.md).

---

## Status: experimental, and partly unwired

Read this before anything below.

- **Before 1.3.0 DRM could not be turned on at all.** Pro decides a video is DRM-protected by reading `_ms_protection_level === 'drm'`, but the Protection Level list on the video edit screen was a closed array, so nothing could ever store that value. The `mediashield_protection_levels` filter (free, 1.3.0) is what opened it; Pro's `Core\Plugin::register_drm_level()` hooks it and adds the `drm` level - and only when `ms_drm_method` is something other than `none`. The label Pro registers is literally "DRM - Encrypted playback (experimental)".
- **Encrypted playback has never been verified end to end.** Treat everything on this page as the intended design plus the code that exists, not as a shipped, proven pipeline.
- **The packaging entry points are not wired.** `DRM\Packager::package()`, `DRM\Packager::schedule_packaging()` and `DRM\KeyServer::generate_key()` are public and complete, but nothing in either plugin calls them: there is no "Package with DRM" control, and `ms_drm_auto_package` is stored and returned by the settings API without any code reading it at upload time. In practice a key row is never created, so `POST /drm/license` returns 404 `drm_key_not_found`. Wiring a trigger is the missing piece; the code below is what that trigger would drive.

---

## Key generation - `DRM\KeyServer`

`KeyServer::generate_key( $video_id )`:

1. `bin2hex( random_bytes( 16 ) )` produces a 128-bit key ID as 32 hex characters.
2. `bin2hex( random_bytes( 16 ) )` produces the 128-bit content key, also carried as a hex string (not raw bytes) everywhere in PHP.
3. The hex content key is encrypted with `PlatformController::encrypt()` - AES-256-CBC keyed on `SECURE_AUTH_SALT`, with the IV embedded in the ciphertext.
4. The row is written to `ms_drm_keys` as `key_id` + `content_key_encrypted`, INSERTed or UPDATEd against the UNIQUE `uk_video` constraint on `video_id` (one key per video).

The column is `content_key_encrypted`; there is no plaintext `content_key` column. The legacy `iv` column was never read and is dropped at DB v2 - see [database-tables.md](database-tables.md#ms_drm_keys).

---

## Video packaging - `DRM\Packager`

`Packager::package( $video_id, $file_path )` reads `ms_drm_method` and dispatches. It fires `mediashield_before_drm_package` ($video_id, $method) on entry and `mediashield_after_drm_package` ($video_id, $method, $result) on exit, whichever branch ran.

| `ms_drm_method` | Behaviour |
|-----------------|-----------|
| `none` | `WP_Error( 'drm_disabled' )`, 400. |
| `cloud_bunny` | No local work. Verifies `_ms_source_url` is a valid URL (set by the BunnyStream driver), then marks the video up. |
| `cloud_aws` | `WP_Error( 'drm_not_implemented' )`, 501. The admin dropdown offers it as "Coming Soon"; the method body is a stub. |
| `local_shaka` | Runs the Shaka Packager CLI. |

### `local_shaka`

1. Requires `proc_open` to be available, else `WP_Error`. (`function_exists()` is the check - PHP reports a function listed in `disable_functions` as non-existent, which is how shared hosts switch process execution off.)
2. Requires `ms_drm_shaka_path` to be an **absolute path to an executable**, else `WP_Error`. A bare program name is refused: it would rely on `PATH`, and PHP-FPM's `PATH` is not the shell's on most managed hosts, so a bare name resolves in a CLI test and fails in production with nothing but "packaging failed".
3. Retrieves the content key from `ms_drm_keys` for the video.
4. Runs the binary through `proc_open` with an **argument array** - no shell is involved, so nothing is escaped - writing into `wp-content/uploads/mediashield/drm/{video_id}/`. Both stdout and stderr are captured.
5. Success is judged by `stream.mpd` existing in the output directory after the call.
6. Sets `_ms_drm_enabled = true`, `_ms_drm_method = 'local_shaka'`, `_ms_drm_output_dir`, `_ms_drm_packaged_at`.

Changed in 1.3.0. This previously assembled a single command string and passed it to PHP's shell-execution function, with `escapeshellarg()` on each value. That was correct - there was no injection - but malware scanners match the *shape* of the call, so customers running Wordfence could see their install flagged as backdoored. The argument-array form gives a scanner nothing to match. It also fixed a real bug: `escapeshellarg()` was applied to the paths *inside* the `in=...,output=...` stream descriptor, which the shell unquotes back into one word only while no path contains a space - on a host whose uploads path has one, the descriptor split across two arguments and packaging failed.

Note it does **not** set `_ms_protection_level`, which is the meta Pro's player-type override actually reads - a locally packaged video still needs the Protection Level set to `drm` on the edit screen before the DRM player engages.

### `cloud_bunny`

Sets `_ms_drm_enabled = true`, `_ms_drm_method = 'cloud_bunny'`, `_ms_drm_packaged_at`, **and** `_ms_protection_level = 'drm'`. This is the one path that switches the player over by itself.

### Async variant

`Packager::schedule_packaging( $video_id, $file_path )` enqueues `mediashield_pro_drm_package` on Action Scheduler (group `mediashield-pro`, args `video_id` + `file_path`), writes `_ms_drm_packaging_status = 'queued'` and stores the action id in `_ms_drm_packaging_action_id`. Returns `WP_Error( 'drm_no_scheduler' )` when Action Scheduler is absent.

### How the player is switched over

Pro hooks free's `mediashield_player_type` filter with `Core\Plugin::override_player_type()`, which returns `'drm'` when **`_ms_protection_level === 'drm'` and `ms_drm_method !== 'none'`**. It does not read `_ms_drm_enabled`. Free's `Player\Renderer`, `PlayerWrapper` and `SessionController` all consult the same filter, and `SessionController::resolve_drm_source_url()` only puts a manifest URL in the `/session/start` payload when the filter says `drm`.

---

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
