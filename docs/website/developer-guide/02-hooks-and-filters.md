# Hooks and Filters

All hooks are in the `mediashield` namespace and fire from the free plugin core. This page documents the actions and filters available in version 1.3.0. Pro adds its own on top; those are listed in the Pro reference.

## Actions

### mediashield_loaded

Fires after the core plugin is fully initialized and all hooks are registered. Use this to initialize any code that depends on MediaShield being ready.

```php
add_action( 'mediashield_loaded', function() {
    // Safe to use MediaShield APIs here.
} );
```

---

### mediashield_session_started

Fires when a new watch session is created, for logged-in viewers and guests alike.

**Parameters:** `$session_id` (int), `$video_id` (int), `$user_id` (int - 0 for guests), `$ip` (string)

```php
add_action( 'mediashield_session_started', function( $session_id, $video_id, $user_id, $ip ) {
    // Log to external analytics, fire a webhook, etc.
}, 10, 4 );
```

---

### mediashield_session_ended

Fires when a watch session is finalized.

**Parameters:** `$session_id` (int), `$video_id` (int), `$user_id` (int)

---

### mediashield_concurrent_limit_reached

Fires when a user tries to start a stream beyond their allowed concurrent limit. Never fires for guests, who are not subject to the limit.

**Parameters:** `$user_id` (int), `$video_id` (int), `$active_count` (int), `$max` (int)

---

### mediashield_user_access_revoked

Fires when all sessions for a user are revoked (`POST /session/revoke-user`).

**Parameters:** `$user_id` (int), `$count` (int - number of sessions revoked)

---

### mediashield_milestone_reached

Fires when any milestone percentage is reached for a user and video. Fires once per user per video per threshold.

**Parameters:** `$user_id` (int), `$video_id` (int), `$pct` (int), `$session_id` (int)

```php
add_action( 'mediashield_milestone_reached', function( $user_id, $video_id, $pct, $session_id ) {
    if ( 100 === $pct ) {
        // Grant a certificate, update LMS progress, etc.
    }
}, 10, 4 );
```

---

### mediashield_milestone_{pct}

Fires for a specific milestone percentage. `mediashield_milestone_25`, `_50`, `_75`, and `_100` always exist; a video with milestone tags enabled at other percentages (10% is offered in the admin) gets those too, and `mediashield_milestone_thresholds` can add any value.

**Parameters:** `$user_id` (int), `$video_id` (int)

```php
add_action( 'mediashield_milestone_100', function( $user_id, $video_id ) {
    learndash_process_mark_complete( $user_id, $video_id );
}, 10, 2 );
```

---

### mediashield_upload_started

*Since 1.0.1.* Fires just before an upload driver runs.

**Parameters:** `$driver` (string), `$file_path` (string), `$options` (array)

---

### mediashield_upload_complete

Fires when an upload finishes successfully.

**Parameters:** `$video_id` (int), `$driver` (string), `$result` (array)

---

### mediashield_upload_failed

*Since 1.0.1.* Fires when an upload driver returns an error.

**Parameters:** `$driver` (string), `$error` (string), `$options` (array)

---

### mediashield_before_player

*Since 1.1.0.* Fires immediately before the player container HTML is emitted. Use to enqueue per-video assets or print HTML above the player.

**Parameters:** `$video_id` (int)

---

### mediashield_after_player

*Since 1.1.0.* Fires immediately after the player HTML is built and the `mediashield_player_html` filter has run.

**Parameters:** `$video_id` (int)

---

### mediashield_needs_shaka

Fires while rendering a self-hosted or Bunny player, to flag that adaptive-streaming support may be needed. In free this is what causes the bundled HLS library to be enqueued. Despite the name, free does not load Shaka Player.

**Parameters:** none

---

### mediashield_devtools_detected

*Since 1.1.0.* Fires when the devtools beacon receives a detection event from the browser. Rate limited to one event per user per hour per IP.

**Parameters:** `$context` (array with keys: `user_id`, `ip`, `url`, `strategy`, `ua`, `screen`, `at`)

`strategy` is `size_delta` or `debugger_timing`. `at` is a UTC MySQL timestamp.

---

### mediashield_privacy_before_erase

Fires before MediaShield's GDPR eraser deletes or anonymizes rows for a given email. Use to roll your own removals into the GDPR receipt.

**Parameters:** `$email` (string), `$user` (WP_User or false), `$page` (int), `$counters` (stdClass, passed by reference)

---

## Filters

### mediashield_can_watch

The primary access control gate. Return the `$result` array unchanged to allow access. Return an array with `allowed => false` and a `reason` string to deny.

Note that free core's own checks - the login gate, the per-video role, and the domain whitelist - run *before* this filter, not through it. A denial from any of them short-circuits and the filter never runs.

**Parameters:** `$result` (array `{allowed: bool, reason: string}`), `$video_id` (int), `$user_id` (int)

```php
// Restrict to active subscribers.
add_filter( 'mediashield_can_watch', function( $result, $video_id, $user_id ) {
    if ( ! user_has_active_subscription( $user_id ) ) {
        return array(
            'allowed' => false,
            'reason'  => 'An active subscription is required.',
        );
    }
    return $result;
}, 10, 3 );
```

Pro attaches its own gates at priority 20 (role re-check) and 25 (LMS enrollment). Pick a priority outside that range, or be ready for a decision one of them has already made.

---

### mediashield_watermark_config

Customize the watermark configuration handed to the browser when a session starts.

**Parameters:** `$config` (array with keys `enabled`, `opacity`, `color`, `swap_interval`, `text`, `ip`), `$video_id` (int), `$user_id` (int)

`text` is the display name (or "Guest"); `ip` is appended by the client on players 640 px wide or wider. Pro uses this filter to compose its 7-field text and adds `font_size` and `show_badge`.

---

### mediashield_protection_levels

*Since 1.3.0.* Filter the protection levels offered on the video edit screen.

**Parameters:** `$levels` (array, slug => label), `$post` (WP_Post), `$selected` (string)

Adding a level only makes it selectable and storable. Whatever it is meant to *do* still has to be implemented, typically through `mediashield_player_type`. Pro uses this to add `drm`.

---

### mediashield_upload_drivers

Register custom upload driver classes. Each class must implement `MediaShield\Upload\Drivers\DriverInterface`.

**Parameters:** `$drivers` (array, driver name => class name)

```php
add_filter( 'mediashield_upload_drivers', function( $drivers ) {
    $drivers['s3'] = MyPlugin\Upload\S3Driver::class;
    return $drivers;
} );
```

---

### mediashield_stored_filename

*Since 1.3.0.* Filter the on-disk filename for a self-hosted upload. Stored names carry a random token so a file path is impractical to guess; return the plain sanitised name to opt out.

**Parameters:** `$obscured` (string), `$original_name` (string)

---

### mediashield_player_type

Override the player type for a video. Free renders `standard` for every video; Pro uses `drm` for DRM playback.

**Parameters:** `$type` (string), `$video_id` (int)

---

### mediashield_video_stream_url

*Since 1.2.0.* Supply or override the direct stream URL for a video, which is what lets MediaShield play it in a real `<video>` element instead of a provider iframe. Pro uses it to build Bunny HLS playlist URLs for videos imported before the URL was recorded.

**Parameters:** `$stream_url` (string), `$video_id` (int), `$platform` (string), `$platform_video_id` (string)

---

### mediashield_milestone_thresholds

Customize which completion percentages trigger milestones. Percentages enabled in a video's Milestone Tags box are merged in on top of whatever this returns.

**Parameters:** `$thresholds` (array of ints, default `[25, 50, 75, 100]`), `$video_id` (int)

---

### mediashield_settings_response

Filter the settings REST API GET response. Use to expose additional settings to the admin SPA.

**Parameters:** `$settings` (array)

---

### mediashield_settings_update

Filter settings data before saving from the settings REST API PUT. Use to intercept and save your own settings keys.

**Parameters:** `$data` (array)

---

### mediashield_trusted_ip_headers

Configure which HTTP headers are checked for client IP detection when recording a session or a devtools event. Default: `array( 'REMOTE_ADDR' )`. Useful when behind a proxy or CDN.

**Parameters:** `$headers` (array of header name strings)

```php
add_filter( 'mediashield_trusted_ip_headers', function( $headers ) {
    array_unshift( $headers, 'HTTP_CF_CONNECTING_IP' );
    return $headers;
} );
```

The watermark overlay reads `REMOTE_ADDR` directly and is not affected by this filter.

---

### mediashield_enable_output_buffer

Control whether output buffering runs on the current request.

**Parameters:** `$enabled` (bool)

```php
// Disable on WooCommerce checkout.
add_filter( 'mediashield_enable_output_buffer', function( $enabled ) {
    if ( function_exists( 'is_checkout' ) && is_checkout() ) {
        return false;
    }
    return $enabled;
} );
```

---

### mediashield_player_html

Filter the final rendered player HTML.

**Parameters:** `$html` (string), `$video_id` (int), `$atts` (array with keys `platform`, `protection_level`, `player_type`)

---

### mediashield_unprotected_player_html

*Since 1.2.0.* Filter the plain player markup emitted when MediaShield is switched off site-wide.

**Parameters:** `$html` (string), `$video_id` (int), `$platform` (string)

---

### mediashield_allow_empty_referer

*Since 1.1.0.* When the allowed-domain whitelist is active, controls whether requests with no Referer header are allowed. Default `false` (deny).

**Parameters:** `$allow` (bool)

---

### mediashield_frontend_config

*Since 1.1.0.* Filter the frontend localized config payload before it is emitted as `window.mediashieldConfig`.

**Parameters:** `$config` (array)

---

### mediashield_player_classes

*Since 1.1.0.* Filter the CSS classes on the player container element.

**Parameters:** `$classes` (array, default `['ms-protected-player']`), `$video_id` (int)

---

### mediashield_protection_config

*Since 1.1.0.* Filter the protection JavaScript config before it is passed to `protection.js`.

**Parameters:** `$config` (array with keys `block_right_click`, `block_keyboard`, `hide_source`, `detect_devtools`, `pause_on_devtools`, `devtools_title`, `devtools_message`)

---

### mediashield_player_access_type

*Since 1.1.0.* Return a non-empty slug to emit as `data-access-type` on the player container, so the client can render an alternative gate UI instead of the login overlay. Applied on both render paths (shortcode/block and auto-wrapped embeds).

**Parameters:** `$access_type` (string), `$video_id` (int)

---

### mediashield_session_allow_anonymous_start

*Since 1.1.0.* Gates whether anonymous (logged-out) visitors may call `POST /session/start` for a given video.

Defaults to `true` when the `ms_require_login` setting is off (*since 1.3.0*), or when the video has a non-empty `_ms_access_type` meta value. Reaching the handler is not the same as being allowed to watch: `AccessControl::can_watch()` still runs.

**Parameters:** `$allow` (bool), `$video_id` (int), `$access_type` (string), `$request` (WP_REST_Request)

---

### mediashield_shortcode_source_url

*Since 1.1.0.* Override the source URL resolved by the `[mediashield]` shortcode at render time.

**Parameters:** `$source_url` (string), `$video_id` (int), `$atts` (array)

---

### mediashield_enqueue_frontend

*Since 1.1.0.* Return `false` to prevent the frontend player assets from registering.

**Parameters:** `$register` (bool)

---

### mediashield_embed_url

*Since 1.3.0.* Filter a minted signed embed URL. See [Extension Architecture](05-extension-architecture.md) for what embed links are.

**Parameters:** `$url` (string), `$video_id` (int), `$user_id` (int)

---

### mediashield_video_ad_breaks

Supply the in-video ad break list for a video. Returning a non-empty array is what causes the ad engine to load. Free ships a bridge that fills this from WB Ad Manager video creatives; with no ad plugin the list is empty and nothing loads.

**Parameters:** `$breaks` (array), `$video_id` (int), `$duration` (int, seconds)

---

### mediashield_video_ads

Filter the pool of ad creatives available to a video before breaks are planned. Return an empty array to suppress ads for that video. Each item keeps the shape `{ id, label, video_url, click_url, skip_after }`.

**Parameters:** `$ads` (array), `$video_id` (int), `$duration` (int)

---

### mediashield_ad_break_plan

Filter the break plan. Default is a pre-roll plus the configured number of mid-rolls, no post-roll.

**Parameters:** `$plan` (array `{ pre: bool, mid_count: int, post: bool }`), `$video_id` (int), `$duration` (int)

---

### mediashield_restore_archive_batch_size

*Since 1.3.0.* Rows moved per batch by the one-off job that restores archived watch sessions to the live table. Default 2000, clamped to 100-20000.

**Parameters:** `$batch` (int)

---

### mediashield_privacy_export_result

*Since 1.0.1.* Filter the GDPR export result before WordPress processes it. Append your own items, or set `done` to false while you still have rows to hand back on a later page.

**Parameters:** `$result` (array `{data: array, done: bool}`), `$email` (string), `$user` (WP_User or false), `$page` (int)

---

### mediashield_privacy_erase_result

Filter the final GDPR erasure result before WordPress processes it. Append messages or adjust counts.

**Parameters:** `$result` (array), `$email` (string), `$user` (WP_User or false), `$page` (int)

---

## JavaScript Events

The frontend player dispatches DOM `CustomEvent`s on `window`. Listen with `window.addEventListener( name, handler )`.

### mediashield:player-ready

Dispatched once a watch session has started and the player is wired up. This is the event the watermark and tracker scripts key off, so it is the right place to hang your own per-player behaviour.

**Detail:** `el` (HTMLElement), `videoId` (number), `token` (string), `resumePosition` (number), `watermarkConfig` (object), `video` (object), `adapter` (object)

### mediashield:access-denied

*Since 1.1.0, this event is cancelable.* Dispatched when `POST /session/start` returns 403 or a denial code. Call `event.preventDefault()` to suppress the default error overlay if you're rendering your own gate UI.

**Detail:** `el` (HTMLElement), `videoId` (number), `reason` (string - `access_denied`, `login_required`, etc.)

### mediashield:concurrent-limit

Dispatched when session start is refused because the viewer is at their concurrent stream limit.

**Detail:** `el` (HTMLElement), `videoId` (number), `message` (string)

### mediashield:devtools-detected

Dispatched in the browser the moment developer tools are detected, before the beacon is sent to the server.

**Detail:** `strategy` (string - `size_delta` or `debugger_timing`)

### mediashield:playlist-switch

Dispatched by the playlist player when the queue advances to another video.

**Detail:** `el` (HTMLElement - the main player), `videoId` (number)
