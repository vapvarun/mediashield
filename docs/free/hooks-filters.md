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
