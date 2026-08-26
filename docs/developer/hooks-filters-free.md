# MediaShield -- Hooks & Filters Reference

Every hook here is fired by the free plugin core. Hook names are global strings, not namespaced - the `MediaShield\` namespace in the source paths below is the PHP class namespace, not part of the hook name.

Line references are against 1.3.0.

---

## Actions

### mediashield_loaded

Fired after the core plugin is fully loaded and all hooks are registered.

```php
add_action( 'mediashield_loaded', function() {
    // Plugin is ready, safe to use MediaShield APIs
});
```

**Parameters:** None

**Use case:** Initialize add-ons or integrations that depend on MediaShield.

---

### mediashield_session_started

Fired when a new watch session is created.

```php
add_action( 'mediashield_session_started', function( $session_id, $video_id, $user_id, $ip ) {
    // Log session start to external analytics
    my_analytics_track( 'video_started', [
        'video_id' => $video_id,
        'user_id'  => $user_id,
    ]);
}, 10, 4 );
```

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `$session_id` | int | The new session row ID |
| `$video_id` | int | Video CPT post ID |
| `$user_id` | int | WordPress user ID (0 for guests) |
| `$ip` | string | Client IP address |

Fired from two places in `includes/Access/SessionManager.php`: line 207 for a logged-in session and line 273 for a guest session, where `$user_id` is passed as a literal `0`. A listener that assumes a real user ID will see zeroes on public videos.

---

### mediashield_session_ended

Fired when a watch session is finalized (page unload or explicit end).

```php
add_action( 'mediashield_session_ended', function( $session_id, $video_id, $user_id ) {
    // Trigger completion webhook
}, 10, 3 );
```

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `$session_id` | int | The ended session row ID |
| `$video_id` | int | Video CPT post ID |
| `$user_id` | int | WordPress user ID |

---

### mediashield_concurrent_limit_reached

Fired when a user exceeds their concurrent stream limit.

```php
add_action( 'mediashield_concurrent_limit_reached', function( $user_id, $video_id, $active_count, $max ) {
    // Alert admin or log
    error_log( "User $user_id hit concurrent limit: $active_count/$max" );
}, 10, 4 );
```

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `$user_id` | int | WordPress user ID |
| `$video_id` | int | Video CPT post ID attempted |
| `$active_count` | int | Current active session count |
| `$max` | int | Configured maximum |

---

### mediashield_user_access_revoked

Fired when all active sessions for a user are killed (admin action).

```php
add_action( 'mediashield_user_access_revoked', function( $user_id, $count ) {
    // Notify user their sessions were revoked
}, 10, 2 );
```

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `$user_id` | int | WordPress user ID |
| `$count` | int | Number of sessions revoked |

---

### mediashield_milestone_reached

Fired when any milestone percentage is reached.

```php
add_action( 'mediashield_milestone_reached', function( $user_id, $video_id, $pct, $session_id ) {
    // Integrate with LMS completion
    if ( $pct === 100 ) {
        learndash_process_mark_complete( $user_id, $video_id );
    }
}, 10, 4 );
```

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `$user_id` | int | WordPress user ID |
| `$video_id` | int | Video CPT post ID |
| `$pct` | int | Milestone percentage (25, 50, 75, 100) |
| `$session_id` | int | Current session ID |

---

### mediashield_milestone_{pct}

Specific milestone hook (e.g., `mediashield_milestone_25`, `mediashield_milestone_100`). Fired from `includes/Milestones/MilestoneTracker.php:150`, immediately after `mediashield_milestone_reached`, once per threshold crossed.

The `{pct}` values are whatever `mediashield_milestone_thresholds` returned - `25`, `50`, `75`, `100` by default, but a custom threshold produces a correspondingly named hook.

```php
add_action( 'mediashield_milestone_100', function( $user_id, $video_id ) {
    // Award certificate on 100% completion
}, 10, 2 );
```

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `$user_id` | int | WordPress user ID |
| `$video_id` | int | Video CPT post ID |

---

### mediashield_upload_complete

Fired when a video upload finishes successfully.

```php
add_action( 'mediashield_upload_complete', function( $video_id, $driver_name, $result ) {
    // Post-upload processing
}, 10, 3 );
```

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `$video_id` | int | Video CPT post ID |
| `$driver_name` | string | Upload driver used (e.g., `self_hosted`) |
| `$result` | array | Driver-specific result data |

---

### mediashield_upload_started

Fired just before any upload driver runs. Lets add-ons record the attempt so the full upload lifecycle (start → complete/failed) is tracked.

Source: `includes/Upload/UploadManager.php:113`

```php
add_action( 'mediashield_upload_started', function( $driver, $file_path, $options ) {
    error_log( "[mediashield] upload start: {$driver} {$file_path}" );
}, 10, 3 );
```

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `$driver` | string | Driver name (e.g. `self_hosted`, `bunny`) |
| `$file_path` | string | Absolute path to the source file |
| `$options` | array | Driver-specific options |

---

### mediashield_upload_failed

Fired when an upload driver returns an error (`$result['success']` is falsy).

Source: `includes/Upload/UploadManager.php:127`

```php
add_action( 'mediashield_upload_failed', function( $driver, $error, $options ) {
    // Surface to an error log / Sentry / etc.
}, 10, 3 );
```

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `$driver` | string | Driver name |
| `$error` | string | Human-readable error message from the driver |
| `$options` | array | Driver-specific options |

---

### mediashield_before_player

*Since 1.1.0*

Fired immediately before the `.ms-protected-player` container is emitted by `Player\Renderer`. Use to enqueue per-video assets or print HTML directly above the player.

Source: `includes/Player/Renderer.php:225`

```php
add_action( 'mediashield_before_player', function( $video_id ) {
    echo '<div class="my-pre-roll" data-video="' . esc_attr( $video_id ) . '"></div>';
} );
```

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `$video_id` | int | Video CPT post ID |

---

### mediashield_after_player

*Since 1.1.0*

Fired immediately after the player HTML has been built and the `mediashield_player_html` filter has run.

Source: `includes/Player/Renderer.php:378`

```php
add_action( 'mediashield_after_player', function( $video_id ) {
    // Append a related-videos block, CTA, etc.
}, 10, 1 );
```

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `$video_id` | int | Video CPT post ID |

---

### mediashield_devtools_detected

*Since 1.1.0*

Fired when the `/protection/devtools-event` beacon receives a detection from the client (`assets/js/protection.js`). Pro's `SuspiciousActivity` class hooks this to insert into `ms_activity_alerts`.

Source: `includes/REST/ProtectionController.php:154`

```php
add_action( 'mediashield_devtools_detected', function( $context ) {
    // $context: user_id, ip, url, strategy, ua, screen, at
    error_log( '[mediashield] devtools: ' . wp_json_encode( $context ) );
} );
```

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `$context` | array | `{ user_id:int, ip:string, url:string, strategy:string, ua:string, screen:string, at:string }` |

`strategy` is one of `size_delta` or `debugger_timing`. `at` is a UTC mysql timestamp.

---

### mediashield_privacy_before_erase

Fired before MediaShield's GDPR eraser deletes/anonymizes any rows for `$email`. Third-party listeners can mutate the `$counters` object by reference to roll their own removals into the GDPR receipt.

Source: `includes/Privacy/PrivacyEraser.php:83`

```php
add_action( 'mediashield_privacy_before_erase', function( $email, $user, $page, $counters ) {
    if ( $user ) {
        $removed = (int) my_table_delete_for_user( $user->ID );
        $counters->items_removed += $removed;
    }
}, 10, 4 );
```

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `$email` | string | Email being erased |
| `$user` | `WP_User\|false` | Matched user, or false for guest emails |
| `$page` | int | Pagination page (always 1) |
| `$counters` | object | stdClass with int `items_removed` / `items_retained` (mutate by reference) |

---

## Filters

### mediashield_can_watch

The primary access control gate. Return the `$result` array unchanged to pass through, modify `allowed` to `false` with a `reason` string to deny, or return `array( 'allowed' => false, 'reason' => '...' )` to deny with a custom message.

> **Return shape:** `array{ allowed: bool, reason: string }` - the filter passes and expects this array shape. See `includes/Access/AccessControl.php` for the canonical signature. The hooks doc historically mentioned `WP_Error` returns; the actual consumer is `can_watch()` which expects the array shape documented here.

```php
// Example: Restrict to paid members
add_filter( 'mediashield_can_watch', function( $result, $video_id, $user_id ) {
    if ( ! user_has_active_subscription( $user_id ) ) {
        return array(
            'allowed' => false,
            'reason'  => __( 'An active subscription is required to watch this video.', 'mediashield' ),
        );
    }
    return $result;
}, 10, 3 );

// Example: Allow specific videos for everyone
add_filter( 'mediashield_can_watch', function( $result, $video_id, $user_id ) {
    $free_videos = [ 10, 15, 22 ];
    if ( in_array( $video_id, $free_videos, true ) ) {
        return array( 'allowed' => true, 'reason' => '' );
    }
    return $result;
}, 5, 3 );
```

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `$result` | array | `{ allowed: bool, reason: string }` current access decision |
| `$video_id` | int | Video CPT post ID |
| `$user_id` | int | WordPress user ID |

**Priority chain:** free registers no callback of its own - its login gate, `_ms_access_role` check and allowed-domain check all run in `AccessControl::can_watch()` *before* `apply_filters()` and short-circuit on denial, so a denied request never reaches this filter. Pro stacks role access at 20 and the LMS adapters at 25. Priority 10 is unused and available.

---

### mediashield_watermark_config

Customize the watermark overlay configuration.

```php
add_filter( 'mediashield_watermark_config', function( $config, $video_id, $user_id ) {
    $config['opacity'] = 0.5;
    $config['color']   = '#ff0000';
    $config['text']    = 'CONFIDENTIAL - ' . wp_get_current_user()->display_name;
    return $config;
}, 10, 3 );
```

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `$config` | array | `{ enabled:bool, opacity:float, color:string, swap_interval:int, text:string, ip:string }`. `text` defaults to the viewer's display name - the key is `text`, not `username`, because that is what `assets/js/watermark.js` reads. Pro's `Watermark\AdvancedConfig` overwrites `text` when active. |
| `$video_id` | int | Video CPT post ID |
| `$user_id` | int | WordPress user ID |

---

### mediashield_upload_drivers

Register custom upload drivers.

```php
add_filter( 'mediashield_upload_drivers', function( $drivers ) {
    $drivers['s3'] = MyPlugin\Upload\S3Driver::class;
    return $drivers;
});
```

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `$drivers` | array | Associative array of driver name => class name |

Each driver class must implement `MediaShield\Upload\Drivers\DriverInterface`.

---

### mediashield_protection_levels

*Since 1.3.0.*

Add or relabel the Protection Level choices on the video edit screen.

Until 1.3.0 this list was a closed array, which made DRM unreachable as a whole:
Pro decides a video is DRM-protected by reading `_ms_protection_level === 'drm'`,
but nothing could ever write that value because the only UI that saves the meta
did not offer it and Pro had no way in.

Adding a level here only makes it **selectable and storable**. Whatever the level
is meant to *do* is still yours to implement, usually via `mediashield_player_type`.

```php
add_filter( 'mediashield_protection_levels', function ( $levels, $post, $selected ) {
    // Only offer it once the thing behind it is actually configured.
    if ( 'none' === get_option( 'my_packaging_method', 'none' ) ) {
        return $levels;
    }

    $levels['drm'] = __( 'DRM - Encrypted playback', 'my-plugin' );

    return $levels;
}, 10, 3 );
```

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `$levels` | array | Level slug => human label |
| `$post` | WP_Post | The video being edited |
| `$selected` | string | Currently stored level |

---

### mediashield_stored_filename

*Since 1.3.0.*

Change the on-disk filename chosen for a self-hosted upload.

By default the stored name carries a random token (`my-video-a1b2c3….mp4`) so a
file's address cannot be derived from the video title. That exists because the
`.htaccess` deny rule in the uploads folder is Apache-only and nginx ignores it -
see `Admin\HealthCheck`. It is defence in depth, not access control.

Return the plain sanitised name to opt out, for instance on an Apache host where
the deny rule does apply and predictable names are wanted operationally.

```php
add_filter( 'mediashield_stored_filename', function ( $obscured, $original_name ) {
    return sanitize_file_name( $original_name );
}, 10, 2 );
```

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `$obscured` | string | Filename including the random token |
| `$original_name` | string | Name as supplied by the client |

---

### mediashield_player_type

Override the player type for a video.

```php
add_filter( 'mediashield_player_type', function( $type, $video_id ) {
    // Force DRM player for specific videos
    if ( get_post_meta( $video_id, '_ms_drm_enabled', true ) ) {
        return 'drm';
    }
    return $type;
}, 10, 2 );
```

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `$type` | string | Incoming player type. **The default differs by call site**: `Player\Renderer`, `Player\PlayerWrapper` and `Player\PlaylistRenderer` pass the literal `'standard'`; `REST\SessionController` passes the raw `_ms_protection_level` meta (which may be `''`). Return `'drm'` to switch the client to the DRM player. |
| `$video_id` | int | Video CPT post ID |

Pro's own callback (`Core\Plugin::override_player_type`, priority 10) returns `'drm'` when `_ms_protection_level === 'drm'` **and** `ms_drm_method !== 'none'`. It does not read `_ms_drm_enabled` - the example above is illustrative user code, not what Pro does.

---

### mediashield_milestone_thresholds

Customize which percentages trigger milestones.

```php
add_filter( 'mediashield_milestone_thresholds', function( $thresholds, $video_id ) {
    // Add a 10% milestone for short videos
    return [ 10, 25, 50, 75, 100 ];
}, 10, 2 );
```

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `$thresholds` | array | Array of integers (default: `[25, 50, 75, 100]`) |
| `$video_id` | int | Video CPT post ID |

---

### mediashield_settings_response

Filter the settings REST API GET response.

```php
add_filter( 'mediashield_settings_response', function( $settings ) {
    $settings['my_custom_setting'] = get_option( 'ms_my_custom', 'default' );
    return $settings;
});
```

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `$settings` | array | Settings key-value pairs |

---

### mediashield_settings_update

Filter settings data before saving from REST API PUT.

```php
add_filter( 'mediashield_settings_update', function( $data ) {
    if ( isset( $data['my_custom_setting'] ) ) {
        update_option( 'ms_my_custom', sanitize_text_field( $data['my_custom_setting'] ) );
    }
    return $data;
});
```

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `$data` | array | Incoming settings data |

---

### mediashield_trusted_ip_headers

Configure which HTTP headers to check for client IP detection.

```php
add_filter( 'mediashield_trusted_ip_headers', function( $headers ) {
    // Add Cloudflare header
    array_unshift( $headers, 'HTTP_CF_CONNECTING_IP' );
    return $headers;
});
```

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `$headers` | array | Header names to check in order |

---

### mediashield_enable_output_buffer

Control whether output buffering runs on the current page.

```php
add_filter( 'mediashield_enable_output_buffer', function( $enabled ) {
    // Disable on WooCommerce checkout
    if ( function_exists( 'is_checkout' ) && is_checkout() ) {
        return false;
    }
    return $enabled;
});
```

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `$enabled` | bool | Whether to run output buffer scanning |

---

### mediashield_force_output_buffer

*Added in 1.3.0.*

Force output buffering on regardless of what the current page's content holds.

Buffering only starts when the queried post's own content contains something the
auto-wrap handles (a YouTube/Vimeo/Bunny/Wistia embed or a `<video>` tag). That
keeps buffering off pages with no video at all, and - more importantly - means
the wrapper only ever replaces an embed on a page where it has already enqueued
the player assets. Before 1.3.0 it wrapped first and enqueued from inside the
output-buffer callback, which fires after `wp_footer()` has printed, so the
scripts never loaded and every auto-detected embed rendered a blank box.

The trade-off is that an embed printed by the theme or a page builder, rather
than stored in post content, is not detected. Use this filter for those sites.

```php
// A page builder renders video embeds that are not in post_content.
add_filter( 'mediashield_force_output_buffer', function ( $force ) {
    return is_singular( 'portfolio' ) ? true : $force;
} );
```

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `$force` | bool | Whether to buffer unconditionally. Default `false`. |

Note this is separate from `mediashield_enable_output_buffer`, which turns
buffering off. This one turns it on where content inspection would have skipped
it; that one vetoes it entirely. A `false` from `mediashield_enable_output_buffer`
wins.

---

### mediashield_default_upload_driver

*Added in 1.3.0.*

Supplies the driver used when **Settings > Default Upload Target** is `auto` and
the upload request did not name one. Free cannot answer this itself - platform
connections are a Pro table - so it asks, and falls back to `self_hosted` when
nothing responds. Pro hooks this and returns its first active connection.

```php
add_filter( 'mediashield_default_upload_driver', function ( $driver ) {
    return 'bunny';
} );
```

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `$driver` | string | Driver name. Default `'self_hosted'`. |

The returned name must exist in `mediashield_upload_drivers`; an unrecognised
value falls back to `self_hosted`.

---

### mediashield_player_html

Filter the final player HTML output.

```php
add_filter( 'mediashield_player_html', function( $html, $video_id, $atts ) {
    // Add a custom wrapper
    return '<div class="my-player-wrapper">' . $html . '</div>';
}, 10, 3 );
```

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `$html` | string | The rendered player HTML |
| `$video_id` | int | Video CPT post ID |
| `$atts` | array | Shortcode/block attributes |

---

### mediashield_admin_routes

Filter admin SPA route definitions (used by Pro to inject pages).

```php
// This is a JavaScript filter using wp.hooks, not PHP
wp.hooks.addFilter( 'mediashield_admin_routes', 'my-addon', function( routes ) {
    routes.push({
        path: '/my-page',
        label: 'My Page',
        component: MyPageComponent,
    });
    return routes;
});
```

See [extension-architecture.md](extension-architecture.md) for the full SlotFill + route injection pattern.

---

### mediashield_allow_empty_referer

*Since 1.1.0*

When the allowed-domain whitelist (`ms_allowed_domains`) is configured, decides whether to permit playback for requests with no `Referer` header. Default `false` (deny) - stripping the Referer is the typical bypass.

Source: `includes/Access/AccessControl.php:138`

```php
// Opt in to allow empty-referer playback (e.g. for privacy-respecting browsers).
add_filter( 'mediashield_allow_empty_referer', '__return_true' );
```

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `$allow` | bool | Whether to allow empty Referer. Default `false`. |

---

### mediashield_frontend_config

*Since 1.1.0*

Filter the frontend localized config payload before it is emitted as `window.mediashieldConfig`. Pro hooks this to inject player config, DRM hints, etc.

Source: `includes/Core/Settings.php:440`

```php
add_filter( 'mediashield_frontend_config', function( $config ) {
    $config['myAddon'] = array( 'enabled' => true );
    return $config;
} );
```

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `$config` | array | `{ restUrl, nonce, isLoggedIn, userId, loginUrl, requireLogin, interval, player: { speedControl, preventForwardSeek, keyboard, resume, sticky, endscreen, endscreenText, endscreenUrl }, messages: { loginOverlay, loginButton, accessDenied, loadFailed } }` |

`Core\Assets::register_frontend()` then adds `watermark` (from `Player\Watermark::get_config()`) and `protection` (from `Player\Protection::get_config()`) to the payload **after** this filter has run, so those two keys are not visible here and cannot be filtered from this hook - use `mediashield_watermark_config` and `mediashield_protection_config` instead.

---

### mediashield_privacy_erase_result

Filter the final GDPR erasure result before WordPress consumes it. Use to append messages or rewrite counts after MediaShield's eraser has run.

Source: `includes/Privacy/PrivacyEraser.php:221`

```php
add_filter( 'mediashield_privacy_erase_result', function( $result, $email, $user, $page ) {
    $result['messages'][] = 'My add-on also cleared 3 rows.';
    $result['items_removed'] += 3;
    return $result;
}, 10, 4 );
```

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `$result` | array | `{ items_removed:int, items_retained:int, messages:array, done:bool }` |
| `$email` | string | Email being erased |
| `$user` | `WP_User\|false` | Matched user, or false for guest emails |
| `$page` | int | Pagination page (always 1) |

---

### mediashield_player_access_type

*Since 1.1.0*

Return a non-empty slug to emit on the `.ms-protected-player` container as `data-access-type="..."`. An extension returns the `_ms_access_type` meta value so its own client code can step aside the player wrapper and render an alternative gate UI.

Source: `includes/Player/Renderer.php:249` (and `includes/Player/PlayerWrapper.php:230` on the output-buffer path)

```php
add_filter( 'mediashield_player_access_type', function( $access_type, $video_id ) {
    if ( get_post_meta( $video_id, '_my_paywall', true ) ) {
        return 'paywall';
    }
    return $access_type;
}, 10, 2 );
```

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `$access_type` | string | Slug to emit; empty string disables the attribute |
| `$video_id` | int | Video CPT post ID |

---

### mediashield_session_allow_anonymous_start

*Since 1.1.0*

Gates whether `POST /session/start` is reachable by anonymous (logged-out) visitors for a given video. Defaults to `true` when the `ms_require_login` setting is **off** (since 1.3.0) **or** the video's `_ms_access_type` meta is non-empty. Downstream `AccessControl::can_watch()` still enforces the actual gate - this filter only decides whether the controller is allowed to run for anonymous traffic. Revoke remains logged-in and `manage_options` regardless. Heartbeat and end are **not** logged-in-only: they accept an anonymous caller presenting a non-empty `token`, because the HMAC-signed session token is itself the authentication and `SessionManager` rejects anything it did not mint.

Source: `includes/REST/SessionController.php:219`

```php
// Permit anonymous /session/start for a custom access type.
add_filter( 'mediashield_session_allow_anonymous_start', function( $allow, $video_id, $access_type, $request ) {
    if ( 'paywall' === $access_type ) {
        return true;
    }
    return $allow;
}, 10, 4 );
```

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `$allow` | bool | Whether to allow anonymous start. Default `! Settings::get( 'ms_require_login' ) \|\| '' !== $access_type`. |
| `$video_id` | int | Video CPT post ID |
| `$access_type` | string | Stored `_ms_access_type` meta value |
| `$request` | `WP_REST_Request` | Current REST request |

---

### mediashield_needs_shaka

**This is an action used as a flag, not a filter, and despite the name it does not load Shaka Player.**

Firing it sets `Assets::$needs_shaka` and enqueues the `mediashield-hls` handle - the bundled `assets/vendor/hls.min.js` (hls.js 1.5.20). Safari plays `.m3u8` in a plain `<video>`; Chrome, Firefox and Edge do not, so without this an HLS source silently fails everywhere except Safari. Shaka Player itself is never registered or enqueued by the free plugin; `assets/js/player-wrapper.js` uses the global `shaka` object *if a site supplies one* and otherwise falls back to hls.js and then to the native element.

`Player\Renderer` (line 215) and `Player\PlayerWrapper` (line 181) fire it for adaptive-streaming platforms. `Assets::enqueue()` re-checks the flag, and the listener registered in `Assets::register()` (line 49) also enqueues directly - because `Renderer` calls `enqueue()` *before* firing the action, so setting the flag alone would always be one step too late.

Hook it to opt in for additional platforms:

```php
add_action( 'wp_enqueue_scripts', function() {
    if ( my_page_needs_hls() ) {
        do_action( 'mediashield_needs_shaka' );
    }
}, 5 );
```

**Parameters:** None

---

### mediashield_enqueue_frontend

*Since 1.1.0*

Return `false` to completely prevent the frontend player JS/CSS from registering. Use to disable MediaShield on specific request types (REST, admin-ajax fallbacks, etc.).

Source: `includes/Core/Assets.php:81`

```php
add_filter( 'mediashield_enqueue_frontend', function( $register ) {
    if ( wp_doing_ajax() ) {
        return false;
    }
    return $register;
} );
```

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `$register` | bool | Whether to register assets. Default `true`. |

---

### mediashield_shortcode_source_url

*Since 1.1.0*

Filter the source URL resolved by the `[mediashield]` shortcode. Return a non-empty URL to temporarily override `_ms_source_url` for the duration of the render (the original meta is restored afterwards).

Source: `includes/Block/Shortcode.php:61`

```php
add_filter( 'mediashield_shortcode_source_url', function( $source_url, $video_id, $atts ) {
    // Resolve to a signed CDN URL at render time.
    return my_cdn_sign( get_post_meta( $video_id, '_ms_source_url', true ) );
}, 10, 3 );
```

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `$source_url` | string | Replacement source URL; empty string means "use the CPT meta as-is" |
| `$video_id` | int | Video CPT post ID |
| `$atts` | array | Shortcode attributes |

---

### mediashield_player_classes

*Since 1.1.0*

Filter the CSS classes applied to the `.ms-protected-player` container.

Source: `includes/Player/Renderer.php:235`

```php
add_filter( 'mediashield_player_classes', function( $classes, $video_id ) {
    $classes[] = 'theme-skin-rounded';
    return $classes;
}, 10, 2 );
```

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `$classes` | array | Class names (default `['ms-protected-player']`) |
| `$video_id` | int | Video CPT post ID |

---

### mediashield_protection_config

*Since 1.1.0*

Filter the protection JS config (right-click, keyboard, source-hiding, devtools detection thresholds) before it is localized to `protection.js`.

Source: `includes/Player/Protection.php:54`

```php
add_filter( 'mediashield_protection_config', function( $config ) {
    // Disable the devtools overlay for admins.
    if ( current_user_can( 'manage_options' ) ) {
        $config['detect_devtools']   = false;
        $config['pause_on_devtools'] = false;
    }
    return $config;
} );
```

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `$config` | array | `{ block_right_click:bool, block_keyboard:bool, hide_source:bool, detect_devtools:bool, pause_on_devtools:bool, devtools_title:string, devtools_message:string }` |

---

### mediashield_video_stream_url

*Since 1.2.0*

Supply or rewrite the playback URL for a video. Returning a non-empty string wins over the stored `_ms_source_url`. This is how Pro's `Platform\BunnyUrls` hands back a signed Bunny CDN URL, and the seam to use for any CDN that signs at render time.

Source: `includes/Player/Renderer.php:164` and `includes/REST/SessionController.php:371`.

```php
add_filter( 'mediashield_video_stream_url', function ( $stream_url, $video_id, $platform, $platform_video_id ) {
    if ( 'self' !== $platform ) {
        return $stream_url;
    }
    return my_cdn_sign( $stream_url );
}, 10, 4 );
```

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `$stream_url` | string | Stored `_ms_stream_url`, usually empty |
| `$video_id` | int | Video CPT post ID |
| `$platform` | string | Platform slug (`self`, `bunny`, `youtube`, ...). `SessionController` coalesces an empty platform to `'self'`; `Renderer` passes the raw meta. |
| `$platform_video_id` | string | Provider-side video ID / GUID |

---

### mediashield_unprotected_player_html

*Since 1.2.0*

Filters the plain player markup emitted when the master `ms_enabled` toggle is **off**. This is a different string from `mediashield_player_html`, which only ever runs on the protected path - a callback registered on one will not see the other.

Source: `includes/Player/Renderer.php:107`

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `$html` | string | The rendered unprotected player HTML |
| `$video_id` | int | Video CPT post ID |
| `$platform` | string | Platform slug |

---

### mediashield_video_ad_breaks

*Since 1.2.0*

The list of ad breaks handed to `ad-breaks.js` on the player container. Default is an empty array, so no ads run until something fills it - `Integrations\AdManagerBridge` is the free-side implementation, and Pro's `Ads\AdResolver` supplies the creatives. A non-empty return also causes the `mediashield-ad-breaks` script to be enqueued.

Source: `includes/Player/Renderer.php:263`

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `$breaks` | array | List of break descriptors. Default `[]`. |
| `$video_id` | int | Video CPT post ID |
| `$duration` | int | Video duration in seconds |

---

### mediashield_video_ads

The ordered pool of video-ad creatives used to fill this video's breaks. Return an empty array to suppress ads for the video. Order is preserved - breaks are filled round-robin in it.

Source: `includes/Integrations/AdManagerBridge.php:104`. Pro's `Ads\AdResolver::resolve_ads()` subscribes at priority 10 to apply per-video selection with a site-wide default-set fallback.

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `$ads` | array | Global pool of enabled creatives. Each item must keep the shape `{ id, label, video_url, click_url, skip_after }`. |
| `$video_id` | int | Video CPT post ID |
| `$duration` | int | Video duration in seconds |

---

### mediashield_ad_break_plan

Which slots this video gets, before creatives are assigned to them.

Source: `includes/Integrations/AdManagerBridge.php:119`. Pro's `Ads\AdResolver::resolve_plan()` subscribes at priority 10.

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `$plan` | array | `{ pre: bool, mid_count: int, post: bool }`. Defaults come from `ms_ads_preroll` and `ms_ads_midroll_count`; `post` defaults to `false`. |
| `$video_id` | int | Video CPT post ID |
| `$duration` | int | Video duration in seconds. Mid-rolls are skipped entirely when this is 0. |

---

### mediashield_embed_url

*Since 1.3.0*

Filters a minted signed embed URL. `Embed\EmbedLink::url()` returns an empty string (and never fires this filter) when the video is missing, is not a `mediashield_video`, or is not published - so a consumer can tell "this lesson has no video" from "here is a link that fails".

Source: `includes/Embed/EmbedLink.php:78`

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `$url` | string | `home_url( '/' )` with the signed token on the `mediashield_embed` query arg |
| `$video_id` | int | Video CPT post ID |
| `$user_id` | int | Viewer the link was minted for (0 for a guest) |

Related constants on the same class: `EmbedLink::TTL` (900 s, the default embed-link lifetime) and `EmbedLink::STREAM_TTL` (6 hours, used for `/stream/{id}` tokens). The stream window is deliberately longer: an embed link is redeemed once for a page, while a stream token rides every range request for as long as someone is watching. `EmbedLink::token( $video_id, $user_id, $ttl )` mints a bare token with no URL around it, for callers that need to carry the credential in a query parameter of their own.

---

### mediashield_privacy_export_result

*Since 1.0.1*

Filters the result MediaShield's GDPR **exporter** hands back to WordPress. Use it to append your own groups to the export.

Source: `includes/Privacy/PrivacyExporter.php:121`

```php
add_filter( 'mediashield_privacy_export_result', function ( $result, $email, $user, $page ) {
    $result['data'][] = array(
        'group_id'    => 'my-addon',
        'group_label' => 'My Add-on',
        'item_id'     => 'my-addon-1',
        'data'        => array( array( 'name' => 'Thing', 'value' => 'Value' ) ),
    );
    return $result;
}, 10, 4 );
```

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `$result` | array | `{ data: array, done: bool }`. Each `data` entry must follow WP's exporter shape. Set `done` to `false` while you still have rows for a follow-up page. |
| `$email` | string | Email being exported |
| `$user` | `WP_User\|false` | Matched user, or false for guest emails |
| `$page` | int | Pagination page (1-based) |

---

### mediashield_restore_archive_batch_size

*Since 1.3.0*

How many `ms_watch_sessions_archive` rows the `ms_restore_archived_sessions` job moves per pass. Clamped to 100-20000 after the filter runs, so an out-of-range return is corrected rather than honoured.

Source: `includes/Cron/Cleanup.php:400`

```php
add_filter( 'mediashield_restore_archive_batch_size', function () { return 250; } );
```

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `$batch` | int | Rows per pass. Default `2000`. |

---

## JavaScript Events

DOM `CustomEvent`s dispatched on `window` by the frontend player wrapper (`assets/js/player-wrapper.js`). Listen with `window.addEventListener( name, handler )`.

### mediashield:access-denied

Dispatched when `POST /session/start` returns 403 or a denied error code (`access_denied`, `login_required`). **Since 1.1.0 the event is `cancelable: true`** - listeners that render an alternative gate UI (email form, paywall, etc.) should call `event.preventDefault()` to suppress the wrapper's generic error overlay.

Source: `assets/js/player-wrapper.js:898`

```js
window.addEventListener( 'mediashield:access-denied', function ( event ) {
    if ( event.detail.reason !== 'access_denied' ) {
        return;
    }
    // We are rendering our own gate - suppress the fallback overlay.
    event.preventDefault();
    renderEmailGate( event.detail.el, event.detail.videoId );
} );
```

**Detail:**
| Key | Type | Description |
|-----|------|-------------|
| `el` | HTMLElement | The `.ms-protected-player` container |
| `videoId` | number | Video CPT post ID |
| `reason` | string | The REST error code, or `'access_denied'` when the response carried none. Since 1.3.0 `SessionController` promotes the denial reason to the error code, so this can be any slug a `mediashield_can_watch` callback returned. |

`bubbles: true`, `cancelable: true`. `login_required` for a logged-out viewer is handled by the wrapper directly (login overlay) and does not dispatch this event; a `login_required` denial for a viewer the client believes is logged in does.

---

### Other dispatched events

All on `window`, all `CustomEvent`.

| Event | Dispatched by | Detail | Notes |
|-------|--------------|--------|-------|
| `mediashield:player-ready` | `player-wrapper.js` (3 sites: no video id, session started, and the unprotected path) | `{ el, videoId, adapter }`, plus `{ token, resumePosition, watermarkConfig }` on the session-started dispatch | The main integration event. `watermark.js`, `tracker.js`, `protection.js` and `ad-breaks.js` all bootstrap off it. Not cancelable. |
| `mediashield:concurrent-limit` | `player-wrapper.js` | `{ el, videoId, message }` | Fired on HTTP 429 / `concurrent_limit` from `/session/start`, alongside the inline error overlay. `bubbles: true`, not cancelable. |
| `mediashield:devtools-detected` | `protection.js` | `{ strategy }` | Client-side only, fired before the beacon POST. `strategy` is `size_delta` or `debugger_timing`. |
| `mediashield:playlist-switch` | `src/blocks/playlist/view.js` | see source | Fired when the playlist block advances to another video. |
