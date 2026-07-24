# Hooks and Filters

All hooks are in the `mediashield` namespace and fire from the free plugin core. This page documents the actions and filters available in version 1.1.0.

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

Fires when a new watch session is created.

**Parameters:** `$session_id` (int), `$video_id` (int), `$user_id` (int), `$ip` (string)

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

Fires when a user tries to start a stream beyond their allowed concurrent limit.

**Parameters:** `$user_id` (int), `$video_id` (int), `$active_count` (int), `$max` (int)

---

### mediashield_user_access_revoked

Fires when an admin revokes all sessions for a user.

**Parameters:** `$user_id` (int), `$count` (int - number of sessions revoked)

---

### mediashield_milestone_reached

Fires when any milestone percentage is reached for a user and video.

**Parameters:** `$user_id` (int), `$video_id` (int), `$pct` (int - 25, 50, 75, or 100), `$session_id` (int)

```php
add_action( 'mediashield_milestone_reached', function( $user_id, $video_id, $pct, $session_id ) {
    if ( 100 === $pct ) {
        // Grant a certificate, update LMS progress, etc.
    }
}, 10, 4 );
```

---

### mediashield_milestone_{pct}

Fires for a specific milestone percentage. Available: `mediashield_milestone_25`, `mediashield_milestone_50`, `mediashield_milestone_75`, `mediashield_milestone_100`.

**Parameters:** `$user_id` (int), `$video_id` (int)

```php
add_action( 'mediashield_milestone_100', function( $user_id, $video_id ) {
    learndash_process_mark_complete( $user_id, $video_id );
}, 10, 2 );
```

---

### mediashield_upload_started

*Since 1.0.0.* Fires just before an upload driver runs.

**Parameters:** `$driver` (string), `$file_path` (string), `$options` (array)

---

### mediashield_upload_complete

Fires when an upload finishes successfully.

**Parameters:** `$video_id` (int), `$driver_name` (string), `$result` (array)

---

### mediashield_upload_failed

Fires when an upload driver returns an error.

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

### mediashield_devtools_detected

*Since 1.1.0.* Fires when the devtools beacon receives a detection event from the browser.

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

Priority chain: Free core runs at 10. If you add your own gate, use a priority outside 10-25 to avoid colliding with Pro's gates (role access at 20, LMS adapters at 25).

---

### mediashield_watermark_config

Customize the watermark overlay settings.

**Parameters:** `$config` (array with keys: `opacity`, `color`, `text`, `swap_interval`), `$video_id` (int), `$user_id` (int)

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

### mediashield_player_type

Override the player type for a video. Values: `standard` or `drm`.

**Parameters:** `$type` (string), `$video_id` (int)

---

### mediashield_milestone_thresholds

Customize which completion percentages trigger milestones.

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

Configure which HTTP headers are checked for client IP detection. Useful when behind a proxy or CDN.

**Parameters:** `$headers` (array of header name strings)

```php
add_filter( 'mediashield_trusted_ip_headers', function( $headers ) {
    array_unshift( $headers, 'HTTP_CF_CONNECTING_IP' );
    return $headers;
} );
```

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

**Parameters:** `$html` (string), `$video_id` (int), `$atts` (array)

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

**Parameters:** `$classes` (array), `$video_id` (int)

---

### mediashield_protection_config

*Since 1.1.0.* Filter the protection JavaScript config before it is passed to `protection.js`.

**Parameters:** `$config` (array with boolean and string keys for each protection feature)

---

### mediashield_player_access_type

*Since 1.1.0.* Return a non-empty slug to emit as `data-access-type` on the player container, so the client can render an alternative gate UI instead of the login overlay.

**Parameters:** `$access_type` (string), `$video_id` (int)

---

### mediashield_session_allow_anonymous_start

*Since 1.1.0.* Gates whether anonymous (logged-out) visitors can call `POST /session/start` for a given video. Defaults to `true` when the video has a non-empty `_ms_access_type` meta value.

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

### mediashield_privacy_erase_result

Filter the final GDPR erasure result before WordPress processes it. Append messages or adjust counts.

**Parameters:** `$result` (array), `$email` (string), `$user` (WP_User or false), `$page` (int)

---

## JavaScript Events

The frontend player wrapper dispatches DOM `CustomEvent`s on `window`. Listen with `window.addEventListener( name, handler )`.

### mediashield:access-denied

*Since 1.1.0, this event is cancelable.* Dispatched when `POST /session/start` returns 403 or a denial code. Call `event.preventDefault()` to suppress the default error overlay if you're rendering your own gate UI.

**Detail:** `el` (HTMLElement), `videoId` (number), `reason` (string - `access_denied`, `login_required`, etc.)
