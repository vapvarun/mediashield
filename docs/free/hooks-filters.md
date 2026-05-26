# MediaShield -- Hooks & Filters Reference

All hooks are in the `MediaShield\` namespace and fire from the free plugin core.

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

Specific milestone hook (e.g., `mediashield_milestone_25`, `mediashield_milestone_100`).

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

Source: `includes/Player/Renderer.php:81`

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

Source: `includes/Player/Renderer.php:211`

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

The primary access control gate. Return `true` to allow, or a `WP_Error` to deny with a reason.

```php
// Example: Restrict to paid members
add_filter( 'mediashield_can_watch', function( $result, $video_id, $user_id ) {
    if ( ! user_has_active_subscription( $user_id ) ) {
        return new WP_Error(
            'subscription_required',
            __( 'An active subscription is required to watch this video.', 'mediashield' )
        );
    }
    return $result;
}, 10, 3 );

// Example: Allow specific videos for everyone
add_filter( 'mediashield_can_watch', function( $result, $video_id, $user_id ) {
    $free_videos = [ 10, 15, 22 ];
    if ( in_array( $video_id, $free_videos, true ) ) {
        return true;
    }
    return $result;
}, 5, 3 );
```

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `$result` | bool\|WP_Error | Current access decision |
| `$video_id` | int | Video CPT post ID |
| `$user_id` | int | WordPress user ID |

**Priority chain:** Free plugin checks at priority 10. Pro adds email gate at 15 and role access at 20.

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
| `$config` | array | Watermark settings (opacity, color, text, swap_interval) |
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
| `$type` | string | Player type (`standard` or `drm`) |
| `$video_id` | int | Video CPT post ID |

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
// This is a JavaScript filter, not PHP
wp.hooks.addFilter( 'mediashield_admin_routes', 'my-addon', function( routes ) {
    routes.push({
        path: '/my-page',
        label: 'My Page',
        component: MyPageComponent,
    });
    return routes;
});
```

This is a JS-side filter using `wp.hooks`.

---

### mediashield_allow_empty_referer

*Since 1.1.0*

When the allowed-domain whitelist (`ms_allowed_domains`) is configured, decides whether to permit playback for requests with no `Referer` header. Default `false` (deny) — stripping the Referer is the typical bypass.

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

Source: `includes/Core/Settings.php:372`

```php
add_filter( 'mediashield_frontend_config', function( $config ) {
    $config['myAddon'] = array( 'enabled' => true );
    return $config;
} );
```

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `$config` | array | `{ restUrl, nonce, isLoggedIn, userId, loginUrl, interval, player:{...}, messages:{...} }` |

---

### mediashield_privacy_erase_result

Filter the final GDPR erasure result before WordPress consumes it. Use to append messages or rewrite counts after MediaShield's eraser has run.

Source: `includes/Privacy/PrivacyEraser.php:182`

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

Return a non-empty slug to emit on the `.ms-protected-player` container as `data-access-type="..."`. Pro registers a callback that returns the `_ms_access_type` meta value so the client (e.g. Pro's `email-gate.js`) can step aside the player wrapper and render an alternative gate UI.

Source: `includes/Player/Renderer.php:105`

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

Gates whether `POST /session/start` is reachable by anonymous (logged-out) visitors for a given video. Defaults to `true` when the video's `_ms_access_type` meta is non-empty (Pro's email-gate path). Downstream `AccessControl::can_watch()` still enforces the actual gate — this filter only decides whether the controller is allowed to run for anonymous traffic. Heartbeat / end / revoke remain logged-in-only regardless.

Source: `includes/REST/SessionController.php:182`

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
| `$allow` | bool | Whether to allow anonymous start. Default `true` iff `_ms_access_type` is non-empty. |
| `$video_id` | int | Video CPT post ID |
| `$access_type` | string | Stored `_ms_access_type` meta value |
| `$request` | `WP_REST_Request` | Current REST request |

---

### mediashield_needs_shaka

Filter whether Shaka Player should be enqueued. Defaults to `true` when the render path includes a self-hosted or Bunny video.

Source: `includes/Core/Assets.php:45` (action `mediashield_needs_shaka` fired from `Player/Renderer.php:71` and `Player/PlayerWrapper.php:200` flips the flag; `Assets::register_frontend()` reads it).

> Note: `mediashield_needs_shaka` is implemented as an **action** flag (fire to opt in), not a boolean filter. Hook it to opt in for additional platforms:

```php
add_action( 'wp_enqueue_scripts', function() {
    if ( my_page_needs_drm() ) {
        do_action( 'mediashield_needs_shaka' );
    }
}, 5 );
```

---

### mediashield_enqueue_frontend

*Since 1.1.0*

Return `false` to completely prevent the frontend player JS/CSS from registering. Use to disable MediaShield on specific request types (REST, admin-ajax fallbacks, etc.).

Source: `includes/Core/Assets.php:72`

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

Source: `includes/Player/Renderer.php:91`

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

Source: `includes/Player/Protection.php:52`

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

## JavaScript Events

DOM `CustomEvent`s dispatched on `window` by the frontend player wrapper (`assets/js/player-wrapper.js`). Listen with `window.addEventListener( name, handler )`.

### mediashield:access-denied

Dispatched when `POST /session/start` returns 403 or a denied error code (`access_denied`, `email_gate_required`, `login_required`). **Since 1.1.0 the event is `cancelable: true`** — listeners that render an alternative gate UI (email form, paywall, etc.) should call `event.preventDefault()` to suppress the wrapper's generic error overlay.

Source: `assets/js/player-wrapper.js:742`

```js
window.addEventListener( 'mediashield:access-denied', function ( event ) {
    if ( event.detail.reason !== 'email_gate_required' ) {
        return;
    }
    // We are rendering our own gate — suppress the fallback overlay.
    event.preventDefault();
    renderEmailGate( event.detail.el, event.detail.videoId );
} );
```

**Detail:**
| Key | Type | Description |
|-----|------|-------------|
| `el` | HTMLElement | The `.ms-protected-player` container |
| `videoId` | number | Video CPT post ID |
| `reason` | string | Denial code (`access_denied`, `email_gate_required`, `login_required`, ...) |

`bubbles: true`, `cancelable: true`. `login_required` for logged-out users is handled by the wrapper directly (login overlay) and does not dispatch this event.
