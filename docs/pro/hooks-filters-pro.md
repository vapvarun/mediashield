# MediaShield Pro -- Hooks & Filters Reference

Pro-specific hooks that extend the free plugin's hook system. For free plugin hooks, see [hooks-filters.md](../free/hooks-filters.md).

All Pro-fired hooks use the `mediashield_pro_*` prefix per the Free/Pro architecture contract. Hooks documented here without that prefix (e.g. `mediashield_fire_webhook`) are internal Pro hooks pre-dating the contract and will be renamed under a `do_action_deprecated()` shim in a future release.

---

## Actions

### mediashield_pro_loaded

Fired after the Pro plugin is fully loaded.

Source: `mediashield-pro/includes/Core/Plugin.php:103`

```php
add_action( 'mediashield_pro_loaded', function() {
    // Pro is ready
});
```

**Parameters:** None

---

### mediashield_pro_email_captured

*Since 1.1.0*

Fired after a visitor submits the email-gate form and the capture row has been inserted into `ms_email_captures`. Use this to push the email into a CRM, Mailchimp, ConvertKit, etc.

Source: `mediashield-pro/includes/Access/EmailGate.php:402`

```php
add_action( 'mediashield_pro_email_captured', function( $email, $video_id, $name ) {
    my_crm_subscribe( $email, [ 'video_id' => $video_id, 'name' => $name ] );
}, 10, 3 );
```

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `$email` | string | Submitted email address (already sanitised) |
| `$video_id` | int | Video CPT post ID the gate protected |
| `$name` | string | Submitter name. Empty string when name collection is disabled. |

---

### mediashield_email_captured *(deprecated)*

Back-compat shim fired via `do_action_deprecated()` immediately after `mediashield_pro_email_captured`. Existing listeners on the unprefixed name keep working but should migrate -- the shim is scheduled for removal once portfolio listeners have been updated.

Source: `mediashield-pro/includes/Access/EmailGate.php:415`

> Use `mediashield_pro_email_captured` for any new code.

---

### mediashield_pro_email_gate_webhook_sent

*Since 1.1.0*

Fired after the email-gate webhook (configured via `ms_email_gate_webhook_url`) finishes responding. Fires on both success and failure -- inspect `$response` to differentiate. Skipped entirely when no webhook URL is set.

Source: `mediashield-pro/includes/Access/EmailGate.php:507`

```php
add_action( 'mediashield_pro_email_gate_webhook_sent', function( $email, $video_id, $webhook_url, $response ) {
    if ( is_wp_error( $response ) ) {
        error_log( '[mediashield-pro] webhook failed: ' . $response->get_error_message() );
    }
}, 10, 4 );
```

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `$email` | string | Captured email |
| `$video_id` | int | Video CPT post ID |
| `$webhook_url` | string | The configured webhook target |
| `$response` | array\|`WP_Error` | Raw `wp_remote_post()` return -- `WP_Error` on transport failure, response array otherwise |

A deprecated `mediashield_email_gate_webhook_sent` shim fires alongside this hook for back-compat. Migrate to the prefixed name.

---

### mediashield_pro_privacy_before_erase

*Since 1.1.0*

Fired before MediaShield Pro's GDPR eraser begins removing Pro-owned data (email captures, playback events, activity alerts, DRM licenses, etc.) for a given email. Listeners can mutate the `$counters` object by reference to roll their own removals into the GDPR receipt that WordPress hands back to the user.

This is the Pro counterpart to free's `mediashield_privacy_before_erase`; both fire during the same GDPR erase request -- free first, then Pro.

Source: `mediashield-pro/includes/Privacy/ProPrivacyEraser.php:87`

```php
add_action( 'mediashield_pro_privacy_before_erase', function( $email, $user, $page, $counters ) {
    if ( $user ) {
        $removed = (int) my_pro_addon_delete_for_user( $user->ID );
        $counters->items_removed += $removed;
    }
}, 10, 4 );
```

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `$email` | string | Email being erased |
| `$user` | `WP_User`\|`null` | Matched user, or `null` for guest emails |
| `$page` | int | Pagination page (always 1 in current implementation) |
| `$counters` | object | stdClass with int `items_removed` / `items_retained` -- mutate by reference |

---

### mediashield_fire_webhook

Fired when a milestone action dispatches a webhook.

```php
add_action( 'mediashield_fire_webhook', function( $url, $payload ) {
    error_log( 'Webhook fired to: ' . $url );
}, 10, 2 );
```

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `$url` | string | Webhook target URL |
| `$payload` | array | Webhook payload data |

---

### mediashield_generate_pdf

Handles async PDF report generation (fired by Action Scheduler).

```php
// This is consumed by PdfExporter -- don't hook into it directly.
// Use the export REST API to trigger reports.
```

---

## Filters

### mediashield_pro_privacy_erase_result

*Since 1.1.0*

Filter the final result array Pro returns to WordPress's GDPR erase request -- last chance to amend the `items_removed`, `items_retained`, `messages`, or `done` keys before the response is handed back.

Source: `mediashield-pro/includes/Privacy/ProPrivacyEraser.php:228`

```php
add_filter( 'mediashield_pro_privacy_erase_result', function( $result, $email, $user, $page ) {
    $result['messages'][] = 'Synced with downstream CRM.';
    return $result;
}, 10, 4 );
```

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `$result` | array | `{ items_removed, items_retained, messages, done }` |
| `$email` | string | Email being erased |
| `$user` | `WP_User`\|`null` | Matched user, or `null` for guests |
| `$page` | int | Pagination page |

---

### mediashield_pro_render_linked_lesson_video

*Since 1.1.0*

Gate that lets themes and sites suppress Pro's auto-rendered linked-lesson video. By default, when a LearnDash / LifterLMS / TutorLMS lesson post has a linked MediaShield video, Pro auto-prepends the player to the lesson content. Return `false` to opt out -- typically when the theme already renders the video in a custom template.

Source: `mediashield-pro/includes/LMS/LMSManager.php:102`

```php
add_filter( 'mediashield_pro_render_linked_lesson_video', function( $render, $video_id, $post_id ) {
    // Suppress on a specific course where the theme handles rendering.
    if ( get_post_type( $post_id ) === 'sfwd-lessons' && 'my-theme' === get_template() ) {
        return false;
    }
    return $render;
}, 10, 3 );
```

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `$render` | bool | Whether to render the linked-lesson video (default `true`) |
| `$video_id` | int | Linked MediaShield video CPT post ID |
| `$post_id` | int | Hosting lesson post ID |

---

### mediashield_pro_license_valid

Override the license check result.

```php
// Force Pro features in development
add_filter( 'mediashield_pro_license_valid', '__return_true' );
```

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `$valid` | bool | Whether the license is active |

---

## Free hooks Pro consumes

Pro extends the free plugin by subscribing to free's hooks rather than rewriting them. The table below is the integration surface -- if you're writing a Pro-adjacent extension, you're sharing these hooks with the subscribers listed.

For free hook parameters / examples, see [`../free/hooks-filters.md`](../free/hooks-filters.md). Pro callbacks documented here are the additional consumers stacked on top.

### Filters

| Free hook | Pro subscriber | Priority | What Pro adds |
|-----------|---------------|----------|---------------|
| `mediashield_can_watch` | `Access\EmailGate` | 15 | Require email submission before playback |
| `mediashield_can_watch` | `Access\RoleAccess` | 20 | Enforce per-video role restriction (`_ms_access_role` meta) |
| `mediashield_can_watch` | LMS adapters | 25 | LearnDash / LifterLMS / TutorLMS enrolment + course-progress gates |
| `mediashield_settings_response` | DRMSettings, AdvancedConfig, ProSettings | 10 | Inject DRM, watermark, email-gate, milestone, LMS keys into GET response |
| `mediashield_settings_update` | DRMSettings, AdvancedConfig, ProSettings | 10 | Save the same keys on PUT, `unset()` from `$data` so free's loop ignores them |
| `mediashield_player_type` | `Core\Plugin` (→ DRM\Packager) | 10 | Override to `'drm'` for DRM-protected videos |
| `mediashield_player_access_type` *(new in 1.1.0)* | `Core\Plugin` | 10 | Returns `'email_gate'` when `_ms_access_type` post meta is set, so the front-end renders the email-gate UI instead of the login overlay |
| `mediashield_upload_drivers` | `Core\Plugin` | 10 | Register Bunny, YouTube, Vimeo, Wistia upload drivers |
| `mediashield_watermark_config` | `Watermark\AdvancedConfig` | 10 | Extend the watermark with 7 configurable fields (username, email, IP, user ID, timestamp, site name, custom text) |

### Actions

| Free hook | Pro subscriber | What Pro does |
|-----------|---------------|---------------|
| `mediashield_milestone_reached` | `Milestones\AdvancedActions` | Fire webhook, send email, grant role / tag user |
| `mediashield_milestone_reached` | LMS adapters | Auto-complete the linked lesson when 100% milestone hits |
| `mediashield_session_started` | `Analytics\SuspiciousActivity` | Multi-IP / concurrent-stream detection -> `ms_activity_alerts` |
| `mediashield_session_started` | `Reports\WeeklyDigest` | Aggregate for the weekly digest email |
| `mediashield_devtools_detected` | `Analytics\SuspiciousActivity` | Insert detection into `ms_activity_alerts` |
| `mediashield_privacy_before_erase` | `Privacy\ProPrivacyEraser` | Pro's own eraser runs after free's so the receipt is unified |

> Free fires `mediashield_can_watch` at priority 10. Pro stacks at 15 (email-gate), 20 (role), 25 (LMS). Any custom callback should pick a priority outside that band, or accept that Pro's gates may have already returned a `WP_Error`.

---

## Pro Settings (wp_options)

Complete list of Pro-managed options:

| Option Key | Default | Description |
|------------|---------|-------------|
| `ms_pro_db_version` | `0` | Pro DB schema version |
| `ms_pro_watermark_fields` | `['username','ip']` | Active watermark text fields |
| `ms_pro_watermark_custom_text` | `''` | Custom watermark text string |
| `ms_pro_watermark_font_size` | `'medium'` | Font size: small/medium/large |
| `ms_show_badge` | `true` | Show MediaShield badge |
| `ms_pro_milestone_config` | `[]` | Milestone action configurations |
| `ms_drm_method` | `'none'` | DRM method: cloud_bunny/cloud_aws/local_shaka/none |
| `ms_drm_shaka_path` | `'packager'` | Shaka Packager binary path |
| `ms_drm_license_duration_streaming` | `86400` | Streaming license seconds |
| `ms_drm_license_duration_persistent` | `2592000` | Persistent license seconds |
| `ms_drm_auto_package` | `false` | Auto-package uploads with DRM |
| `ms_suspicious_sensitivity` | `'medium'` | Alert sensitivity level |
| `ms_safe_users` | `[]` | Whitelisted user IDs |
| `ms_email_gate_webhook_url` | `''` | Webhook URL for email captures |
| `ms_email_gate_cookie_duration` | `7` | Cookie expiry in days |
| `ms_email_retention_months` | `12` | Email capture retention period |
| `ms_weekly_digest_enabled` | `true` | Enable weekly digest |
| `ms_weekly_digest_email` | admin email | Digest recipient email |
| `ms_heatmap_last_aggregated` | epoch | Last heatmap aggregation timestamp |

---

## Post Meta (Pro-managed)

| Meta Key | Set By | Purpose |
|----------|--------|---------|
| `_ms_access_role` | Editor | Required role for video access |
| `_ms_access_type` | Editor | Access type (e.g., `email_gate`) -- read by `mediashield_player_access_type` |
| `_ms_library_id` | BunnyStream | Bunny library ID |
| `_ms_wistia_numeric_id` | WistiaApi | Wistia numeric ID |
| `_ms_drm_enabled` | Packager | DRM enabled flag |
| `_ms_drm_method` | Packager | DRM method used |
| `_ms_drm_output_dir` | Packager | Shaka output directory |
| `_ms_drm_packaged_at` | Packager | Packaging timestamp |
| `_ms_drm_packaging_status` | Packager | Job status |
| `_ms_drm_packaging_action_id` | Packager | Action Scheduler job ID |
