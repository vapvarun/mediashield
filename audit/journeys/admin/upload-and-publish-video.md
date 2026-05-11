---
journey: upload-and-publish-video
plugin: mediashield
priority: critical
roles: [administrator]
covers: [upload-init, cpt-publish, shortcode-render, video-block-render]
prerequisites:
  - "Site reachable at $SITE_URL"
  - "MediaShield activated; ms_max_upload_size >= 10 (MB)"
  - "Test fixture: small MP4 file at audit/fixtures/sample-5s.mp4 (~1MB)"
  - "Self-hosted upload driver registered (default — no Pro needed)"
estimated_runtime_minutes: 6
---

# Admin uploads a video and publishes it via shortcode + block

The admin author flow. An administrator initiates an upload via
`POST /upload/init`, the SelfHosted driver writes the file to wp-content,
the resulting `mediashield_video` CPT is published, and the same video
renders both via `[mediashield id=X]` shortcode and the `mediashield/video`
Gutenberg block. If this breaks, content creators can't ship videos and
the whole free-tier funnel stalls.

## Setup

- Site: `$SITE_URL`
- Test user: `journey-admin` (administrator role, autologin via `?autologin=journey-admin`)
- Fixture file: `audit/fixtures/sample-5s.mp4` (any small MP4)
- DB clean:
  ```sql
  DELETE FROM wp_posts WHERE post_type = 'mediashield_video' AND post_title LIKE 'Journey upload%';
  ```

## Steps

### 1. Authenticate as admin
- **Action**: `playwright_navigate $SITE_URL/?autologin=journey-admin`
- **Expect**: redirect to `/wp-admin/`; admin bar visible.
- **On fail**: dev-auto-login mu-plugin missing OR user role mismatch.

### 2. Initialize upload via REST
- **Action**: extract REST nonce from `wpApiSettings.nonce`, then
  `curl -X POST $SITE_URL/wp-json/mediashield/v1/upload/init -H "X-WP-Nonce: <nonce>" -F "file=@audit/fixtures/sample-5s.mp4" -F "title=Journey upload $(date +%s)"`
- **Expect**: HTTP 200; JSON body has `upload_id`, `video_id` (the new CPT post ID), `status: 'complete'` (single-shot upload, not chunked).
- **Capture**: `VIDEO_ID`.
- **On fail**: `includes/REST/UploadController.php::init_upload` permission/capability gate OR `includes/Upload/Drivers/SelfHosted.php::store_chunk` OR `upload_mediashield` capability not present on administrator role.

### 3. Verify CPT row + meta
- **Action**: `mysql_query "SELECT post_status, post_title FROM wp_posts WHERE ID = $VIDEO_ID"`
- **Expect**: `post_status = 'publish'`; title matches the upload title.
- **Action**: `mysql_query "SELECT meta_key, meta_value FROM wp_postmeta WHERE post_id = $VIDEO_ID AND meta_key IN ('_ms_platform','_ms_source_url')"`
- **Expect**: `_ms_platform = 'self'`; `_ms_source_url` is a non-empty URL pointing under wp-content/uploads.
- **On fail**: `includes/Upload/UploadManager.php::create_video_post` OR `includes/CPT/VideoPostType.php` meta registration.

### 4. Shortcode renders the video
- **Action**: create a draft post via WP-CLI: `wp post create --post_type=post --post_status=publish --post_title='Journey shortcode page' --post_content='[mediashield id=$VIDEO_ID]'`. Capture `POST_ID`. Navigate to `$SITE_URL/?p=$POST_ID`.
- **Expect**: DOM contains `<div class="ms-protected-player">` AND a `<video>` or platform-iframe element pointing at the source URL captured above.
- **On fail**: `includes/Block/Shortcode.php::render` OR `includes/Player/Renderer.php` validation rejected the CPT.

### 5. Block renders the same video
- **Action**: create another post: `wp post create --post_type=post --post_status=publish --post_title='Journey block page' --post_content='<!-- wp:mediashield/video {"videoId":$VIDEO_ID} /-->'`. Navigate.
- **Expect**: same `.ms-protected-player` DOM with the same source URL.
- **On fail**: `includes/Block/VideoBlock.php::render_callback` OR `src/blocks/video/index.js` → `view.js` mismatch.

## Pass criteria

ALL of the following hold:
1. `/upload/init` returns 200 with `video_id`.
2. The `mediashield_video` CPT is `publish` with `_ms_source_url` populated.
3. `[mediashield id=X]` renders `.ms-protected-player`.
4. `<!-- wp:mediashield/video -->` renders the same `.ms-protected-player`.
5. Both renders point at the same `_ms_source_url` value.

## Fail diagnostics

| Symptom | Likely cause | File to inspect |
|---|---|---|
| `/upload/init` returns 403 | Capability check (`upload_mediashield`) missing | `includes/REST/UploadController.php::init_upload_permissions`, `includes/Core/Activator.php` (cap grant) |
| `/upload/init` returns 200 but no file on disk | Driver store_chunk failure | `includes/Upload/Drivers/SelfHosted.php::store_chunk` |
| CPT created but `_ms_source_url` empty | Meta save race | `includes/Upload/UploadManager.php::create_video_post` |
| Shortcode renders empty string | Renderer validation killed it | `includes/Player/Renderer.php` (CPT not published / source URL invalid) |
| Block edit works, block view empty | view.js entry not built | `webpack.config.js`, `build/blocks/video/view.js` |
