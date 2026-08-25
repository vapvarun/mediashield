# MediaShield Pro -- Hooks & Filters Reference

Pro-specific hooks that extend the free plugin's hook system. For free plugin hooks, see [hooks-filters-free.md](hooks-filters-free.md).

Most Pro-fired hooks use the `mediashield_pro_*` prefix per the Free/Pro architecture contract. Several pre-date that contract and keep a bare `mediashield_` prefix (`mediashield_fire_webhook`, `mediashield_generate_pdf`, `mediashield_before_drm_package`, `mediashield_after_drm_package`, `mediashield_bunny_encoded`, `mediashield_bunny_failed`, `mediashield_lms_adapters`, `mediashield_lms_adapters_loaded`, `mediashield_lms_lesson_completed`, `mediashield_vpn_lookup_url`). They are all real and all fired by Pro; a future release may rename them behind a `do_action_deprecated()` shim.

Line references are against 1.3.0. Paths are relative to the `mediashield-pro` repo.

---

## Actions

### mediashield_pro_loaded

Fired at the end of Pro's `Core\Plugin` constructor, after every Pro subsystem, hook and REST route is registered.

Source: `includes/Core/Plugin.php:119`

```php
add_action( 'mediashield_pro_loaded', function() {
    // Pro is ready
});
```

**Parameters:** None

Note Pro does **not** hook free's `mediashield_loaded`. Both plugins boot on `plugins_loaded` - free at priority 10, Pro at 20 - so this action fires after free is fully up. See [extension-architecture.md](extension-architecture.md#how-pro-boots-alongside-the-free-plugin).

---

### mediashield_pro_privacy_before_erase

*Since 1.1.0*

Fired before MediaShield Pro's GDPR eraser begins removing Pro-owned data (playback events, activity alerts, DRM licenses, etc.) for a given email. Listeners can mutate the `$counters` object by reference to roll their own removals into the GDPR receipt that WordPress hands back to the user.

This is the Pro counterpart to free's `mediashield_privacy_before_erase`. The two erasers are registered independently on WordPress's own `wp_privacy_personal_data_erasers` filter - Pro does not subscribe to free's action, and neither one runs the other.

Source: `includes/Privacy/ProPrivacyEraser.php:87`

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

### mediashield_before_drm_package

*Since 1.1.0*

Fired at the top of `DRM\Packager::package()`, before the method dispatches to the Bunny / AWS / Shaka branch.

Source: `includes/DRM/Packager.php:64`

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `$video_id` | int | Video CPT post ID |
| `$method` | string | `cloud_bunny`, `cloud_aws` or `local_shaka` (the `none` case returns before this fires) |

---

### mediashield_after_drm_package

*Since 1.1.0*

Fired after packaging returns, on both the success and failure paths.

Source: `includes/DRM/Packager.php:97`

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `$video_id` | int | Video CPT post ID |
| `$method` | string | Packaging method used |
| `$result` | array\|`WP_Error` | Packaging result, or the error. Check with `is_wp_error()`. |

> Neither of these fires today: nothing in either plugin calls `Packager::package()`. See [drm-internals.md](drm-internals.md#status-experimental-and-partly-unwired).

---

### mediashield_lms_lesson_completed

*Since 1.1.0*

Fired by each built-in LMS adapter after it has marked the linked lesson complete for a viewer.

Source: `includes/LMS/LearnDashAdapter.php:197`, `includes/LMS/LifterLMSAdapter.php:167`, `includes/LMS/TutorLMSAdapter.php:183`

```php
add_action( 'mediashield_lms_lesson_completed', function( $user_id, $video_id, $lesson_id, $source ) {
    my_award_points( $user_id, $lesson_id );
}, 10, 4 );
```

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `$user_id` | int | WordPress user ID |
| `$video_id` | int | Video CPT post ID |
| `$lesson_id` | int | Lesson / topic post ID |
| `$source` | string | Adapter slug: `learndash`, `lifterlms` or `tutor` |

---

### mediashield_lms_adapters_loaded

*Since 1.1.0*

Fired after `LMS\LMSManager` has type-checked every adapter and called `register()` on each survivor.

Source: `includes/LMS/LMSManager.php:238`

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `$adapters` | array | `slug => LMSAdapterInterface` map of active adapters |

**Read-only.** An action cannot mutate the array - to add an adapter use the `mediashield_lms_adapters` filter below.

---

### mediashield_bunny_encoded

Fired when the Bunny Stream webhook reports a video finished encoding, after the thumbnail has been refreshed.

Source: `includes/REST/BunnyWebhookController.php:226`

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `$post_id` | int | The `mediashield_video` post ID |
| `$video_guid` | string | The Bunny video GUID |
| `$body` | array | Full webhook payload |

---

### mediashield_bunny_failed

Fired when the Bunny Stream webhook reports an encoding failure. `_ms_bunny_encode_status` is set to `error` first.

Source: `includes/REST/BunnyWebhookController.php:259`

**Parameters:** same as `mediashield_bunny_encoded`.

---

### mediashield_fire_webhook

An **Action Scheduler hook**, not a plain `do_action()`. `Milestones\AdvancedActions` enqueues it (`as_enqueue_async_action`, group `mediashield-pro`) when a milestone action is configured with a webhook URL, and registers itself as the handler at priority 10 with 2 args. When Action Scheduler is unavailable the POST is made synchronously and the hook never fires.

Source: enqueued at `includes/Milestones/AdvancedActions.php:169`, handled via `add_action` at `includes/Milestones/AdvancedActions.php:29`

```php
add_action( 'mediashield_fire_webhook', function( $url, $payload ) {
    error_log( 'Webhook fired to: ' . $url );
}, 10, 2 );
```

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `$url` | string | Webhook target URL |
| `$payload` | array | `{ event: 'milestone_reached', user_id, video_id, percentage, session_id, timestamp, site_url }` |

---

### mediashield_generate_pdf

An **Action Scheduler hook**. Queued by `POST /mediashield-pro/v1/export/pdf/report` and handled by `Export\PdfExporter::handle_async()`.

Source: enqueued at `includes/REST/ExportController.php:254`, handled via `add_action` at `includes/Core/Plugin.php:74` (priority 10, 3 args)

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `$user_id` | int | Admin who requested the report |
| `$filters` | array | `{ period }` from the request |
| `$report_id` | string | `wp_generate_uuid4()` minted at enqueue; the transient key the status poller reads |

Consumed by `PdfExporter` - don't hook it to *do* the work. Use the export REST API to trigger reports.

---

## Filters

### mediashield_pro_privacy_erase_result

*Since 1.1.0*

Filter the final result array Pro returns to WordPress's GDPR erase request -- last chance to amend the `items_removed`, `items_retained`, `messages`, or `done` keys before the response is handed back.

Source: `includes/Privacy/ProPrivacyEraser.php:218`

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

### mediashield_pro_privacy_export_result

*Since 1.1.0*

The exporter counterpart. Filters the Pro-owned groups returned to WordPress's GDPR export request.

Source: `includes/Privacy/ProPrivacyExporter.php:143`

**Parameters:** `$result` (`{ data, done }`), `$email`, `$user`, `$page` -- same shape as free's `mediashield_privacy_export_result`.

---

### mediashield_lms_adapters

*Since 1.1.0*

Register an LMS adapter for a system Pro does not auto-detect. The array holds **instances**, keyed by a unique slug, and each must implement `MediaShieldPro\LMS\LMSAdapterInterface`. Anything failing the `instanceof` check is dropped with a `_doing_it_wrong()` notice.

Source: `includes/LMS/LMSManager.php:197`

```php
add_filter( 'mediashield_lms_adapters', function ( $adapters ) {
    $adapters['learnomy'] = new MyPlugin\LearnomyAdapter();
    return $adapters;
} );
```

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `$adapters` | array | `slug => LMSAdapterInterface` map. Built-in entries are already included. A non-array return is discarded and the auto-detected set kept. |

The eight-method interface is documented in [extension-architecture.md](extension-architecture.md#lms-adapters---mediashield_lms_adapters-filter).

---

### mediashield_pro_render_linked_lesson_video

*Since 1.1.0*

Gate that lets themes and sites suppress Pro's auto-rendered linked-lesson video. By default, when a LearnDash / LifterLMS / TutorLMS lesson post has a linked MediaShield video, Pro auto-prepends the player to the lesson content. Return `false` to opt out -- typically when the theme already renders the video in a custom template.

Source: `includes/LMS/LMSManager.php:105`

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

Source: `includes/Core/Plugin.php:338`

```php
// Force Pro features in development
add_filter( 'mediashield_pro_license_valid', '__return_true' );
```

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `$valid` | bool | Whether the license is active |

---

### Bunny playback filters

All in `includes/Platform/BunnyUrls.php`. These shape the URL Pro hands back through free's `mediashield_video_stream_url`.

| Filter | Source | Params | Purpose |
|--------|--------|--------|---------|
| `mediashield_pro_bunny_stream_url` | line 96 | `$url` (string, `''` when unknown), `$platform_video_id` (string GUID), `$video_id` (int) | Final say on the derived Bunny stream URL. |
| `mediashield_pro_bunny_direct_play` | line 114 | `$enabled` (bool, default from the `ms_bunny_direct_play` option, itself `false`) | Whether Bunny videos may play through a direct stream URL instead of the iframe. Deliberately off by default - the owner opts in once they have confirmed the pull zone serves the request. |
| `mediashield_pro_bunny_prefer_mp4` | line 130 | `$prefer` (bool, default from `ms_bunny_prefer_mp4`, itself `false`) | Serve Bunny's MP4 rendition instead of the HLS playlist. Only works on libraries with MP4 fallback enabled in Bunny. |
| `mediashield_pro_bunny_token_ttl` | line 220 | `$ttl` (int seconds, default `6 * HOUR_IN_SECONDS`) | Lifetime of a signed Bunny URL. Floored at 60 s after the filter. |
| `mediashield_pro_bunny_token_key` | line 263 | `$key` (string, decrypted `ms_bunny_token_key`, `''` when unset) | The pull zone's token-authentication key used to sign URLs. |

---

### mediashield_vpn_lookup_url

*Since 1.2.0*

The lookup URL template for VPN / proxy detection. Default is `https://pro.ip-api.com/json/%1$s?key=%2$s&fields=...`; `%1$s` receives the URL-encoded IP and `%2$s` the API key, so a template using only `%s` receives the IP and works unchanged.

Source: `includes/Analytics/VpnDetection.php:151`

Point it at your own resolver to avoid sending visitor IPs off-site. Doing so also removes the API-key requirement: `VpnDetection` treats "the filter changed the URL" as the signal that the bundled provider's key no longer applies.

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `$url` | string | The lookup URL template |

---

### mediashield_pro_playback_event_retention_days

*Since 1.2.0*

How many days of raw `ms_playback_events` rows the daily `ms_playback_event_retention` job keeps. Floored at 2 days after the filter runs, so it can never overtake the hourly heatmap aggregation.

Source: `includes/Cron/ProCleanup.php:118`

**Parameters:**
| Param | Type | Description |
|-------|------|-------------|
| `$days` | int | Retention window in days. Default `90`. |

---

## Free hooks Pro consumes

Pro extends the free plugin by subscribing to free's hooks rather than rewriting them. The table below is the integration surface -- if you're writing a Pro-adjacent extension, you're sharing these hooks with the subscribers listed.

For free hook parameters / examples, see [hooks-filters-free.md](hooks-filters-free.md). Pro callbacks documented here are the additional consumers stacked on top.

### Filters

| Free hook | Pro subscriber | Priority | What Pro adds |
|-----------|---------------|----------|---------------|
| `mediashield_can_watch` | `Access\RoleAccess::check_role` | 20 | Enforce per-video role restriction (`_ms_access_role` meta) |
| `mediashield_can_watch` | LearnDash / LifterLMS / TutorLMS adapters | 25 | Enrolment + course-progress gates |
| `mediashield_settings_response` | `Admin\ProSettings`, `Admin\DRMSettings`, `Watermark\AdvancedConfig` | 10 | Append Pro keys to the GET response |
| `mediashield_settings_update` | same three classes | 10 | Save the same keys on PUT, `unset()` from `$data` so free's loop ignores them |
| `mediashield_player_type` | `Core\Plugin::override_player_type` | 10 | Return `'drm'` when `_ms_protection_level === 'drm'` and `ms_drm_method !== 'none'` |
| `mediashield_protection_levels` | `Core\Plugin::register_drm_level` | 10 | Add the `drm` level to the video edit screen, but only when `ms_drm_method !== 'none'` |
| `mediashield_upload_drivers` | `Core\Plugin::register_upload_drivers` | 10 | Register Bunny, YouTube, Vimeo, Wistia upload drivers |
| `mediashield_watermark_config` | `Watermark\AdvancedConfig::enhance_config` | 10 | Extend the watermark `text` with any of 7 fields: `username`, `email`, `ip`, `user_id`, `timestamp`, `site_name`, `custom_text` |
| `mediashield_video_stream_url` | `Platform\BunnyUrls::supply_stream_url` | 10 | Derive (and optionally token-sign) the Bunny playback URL |
| `mediashield_video_ads` | `Ads\AdResolver::resolve_ads` | 10 | Per-video creative selection with a site-wide default-set fallback |
| `mediashield_ad_break_plan` | `Ads\AdResolver::resolve_plan` | 10 | Per-video pre/mid/post-roll plan |
| `mediashield_enqueue_frontend` | `Core\Plugin` | n/a (reads it) | Pro respects the same opt-out before registering its own frontend assets |

### Actions

| Free hook | Pro subscriber | Priority | What Pro does |
|-----------|---------------|----------|---------------|
| `mediashield_milestone_reached` | `Milestones\AdvancedActions::handle_milestone` | 10 | Fire webhook, send email, grant role / tag user |
| `mediashield_milestone_reached` | LearnDash / LifterLMS / TutorLMS adapters | 10 | Auto-complete the linked lesson when the configured percentage hits |
| `mediashield_session_started` | `Analytics\SuspiciousActivity::on_session_started` | 10 | Multi-IP / concurrent-stream detection -> `ms_activity_alerts` |
| `mediashield_session_started` | `Analytics\VpnDetection::queue_lookup` | 20 | Queue the `ms_vpn_lookup` async action for an uncached IP |
| `mediashield_devtools_detected` | `Analytics\SuspiciousActivity::on_devtools_detected` | 10 | Insert detection into `ms_activity_alerts` |
| `mediashield_upload_started` | `Upload\UploadQueue::on_started` | 10 | Insert an `ms_upload_queue` row with status `uploading` |
| `mediashield_upload_complete` | `Upload\UploadQueue::on_complete` | 10 | Mark the queue row `complete` |
| `mediashield_upload_failed` | `Upload\UploadQueue::on_failed` | 10 | Mark the queue row `failed` and store the error |

> Free registers **no** callback on `mediashield_can_watch` - its own gates run before `apply_filters()` and short-circuit. Pro stacks at 20 (role) and 25 (LMS). A custom callback should pick a priority outside that band, or accept that Pro's gates may have already returned a denied result.

> `Reports\WeeklyDigest` does **not** subscribe to `mediashield_session_started`. It queries `ms_watch_sessions` directly at send time.

> Pro's GDPR eraser/exporter register on WordPress's `wp_privacy_personal_data_erasers` / `_exporters` filters, not on free's `mediashield_privacy_before_erase`.

---

## Pro Settings (wp_options)

Options Pro reads or writes. The `Exposed` column says whether the key appears in the `GET /mediashield/v1/settings` response (via `mediashield_settings_response`); several response keys are derived, not stored, and are marked as such.

| Option Key | Default | Exposed | Description |
|------------|---------|---------|-------------|
| `ms_pro_db_version` | `0` | no | Pro DB schema version |
| `ms_pro_watermark_fields` | `['username','ip']` | yes | Active watermark text fields. Submitted values are intersected against `username`, `email`, `ip`, `user_id`, `timestamp`, `site_name`, `custom_text`. |
| `ms_pro_watermark_custom_text` | `''` | yes | Custom watermark text string |
| `ms_pro_watermark_font_size` | `'medium'` | yes | Font size: small/medium/large |
| `ms_show_badge` | `true` | yes | Show MediaShield badge. Shared with free - the key exists in `Settings::schema()` too. |
| `ms_pro_milestone_config` | `[]` | no (own route) | Milestone action configurations. Read through `GET /milestones/config`. |
| `ms_drm_method` | `'none'` | yes | `none`, `cloud_bunny`, `cloud_aws`, `local_shaka`. Anything else submitted is coerced to `none`. `cloud_aws` is a stub. |
| `ms_drm_shaka_path` | `'packager'` | yes | Shaka Packager binary path. An empty submitted value is ignored, keeping the previous path. |
| `ms_drm_license_duration_streaming` | `86400` | yes | Streaming license seconds. Anything under 300 is reset to 86400. |
| `ms_drm_auto_package` | `false` | yes | Auto-package uploads with DRM. **Stored and returned but never read** - no code packages on upload. |
| `ms_suspicious_sensitivity` | `'medium'` | yes | `low`, `medium` or `high`; anything else is coerced to `medium`. |
| `ms_safe_users` | `[]` | no (own route) | Whitelisted user IDs, written by `POST /analytics/suspicious/safe-user` |
| `ms_weekly_digest_enabled` | `true` | yes | Enable weekly digest |
| `ms_weekly_digest_email` | admin email | yes | Digest recipient. An invalid address is rejected; an empty one deletes the option so it falls back to `admin_email`. |
| `ms_heatmap_last_aggregated` | `'1970-01-01 00:00:00'` | no | Heatmap aggregation watermark |
| `ms_vpn_detection_enabled` | `false` | yes | VPN / proxy detection toggle |
| `ms_vpn_api_key` | `''` | **no** | Provider API key. The settings response exposes only the derived boolean `ms_vpn_api_key_set`, never the key - a settings response is readable by anyone who can open the screen. |
| `ms_default_upload_target` | `'auto'` | yes | Default upload destination |
| `ms_lms_auto_complete` | `true` | yes | Mark the linked lesson complete on milestone |
| `ms_lms_enrollment_check` | `true` | yes | Gate playback on enrolment |
| `ms_lms_complete_pct` | `100` | yes | Milestone percentage that triggers completion |
| `ms_bunny_direct_play` | `false` | no | Direct (non-iframe) Bunny playback. Filterable via `mediashield_pro_bunny_direct_play`. |
| `ms_bunny_prefer_mp4` | `false` | no | Serve the MP4 rendition instead of HLS |
| `ms_bunny_mp4_height` | `720` | no | MP4 rendition height. Must be one of 240/360/480/720/1080/1440/2160; anything else falls back to 720. |
| `ms_bunny_token_key` | `''` | no | Encrypted pull-zone token-auth key |
| `ms_bunny_webhook_key` | auto-generated | no | Token embedded in the webhook URL; authenticates Bunny's callback |
| `ms_ads_default_ad_ids` | `[]` | no | Site-wide default creative set, used by `Ads\AdResolver` when a video makes no explicit selection |

Derived, response-only keys (not options - do not try to `get_option()` them):

| Response key | Source |
|--------------|--------|
| `ms_vpn_api_key_set` | `'' !== trim( get_option( 'ms_vpn_api_key' ) )` |
| `ms_bunny_webhook_url` | `BunnyWebhookController::get_webhook_url()` - the REST URL with `ms_token` appended |
| `ms_connected_platforms` | `SELECT id, platform FROM ms_platform_connections WHERE is_active = 1` |

There is **no** `ms_drm_license_duration_persistent` option - offline licensing was removed in 1.2.0.

---

## Post Meta (Pro-managed)

| Meta Key | Set By | Purpose |
|----------|--------|---------|
| `_ms_library_id` | BunnyStream driver | Bunny library ID |
| `_ms_wistia_numeric_id` | WistiaApi driver | Wistia numeric ID |
| `_ms_bunny_encode_status` | BunnyWebhookController | Latest encode state from the Bunny webhook (`error` on failure) |
| `_ms_drm_enabled` | DRM\Packager | DRM packaging completed flag |
| `_ms_drm_method` | DRM\Packager | `cloud_bunny` or `local_shaka` |
| `_ms_drm_output_dir` | DRM\Packager | Shaka output directory (local method only) |
| `_ms_drm_packaged_at` | DRM\Packager | Packaging timestamp |
| `_ms_drm_packaging_status` | DRM\Packager | Job status |
| `_ms_drm_packaging_action_id` | DRM\Packager | Action Scheduler action ID |
| `_ms_linked_lesson` | LMS\LMSMetaBox | Lesson / topic post ID, validated against the owning adapter's `owns_post()` |
| `_ms_lms_require_enrollment` | LMS\LMSMetaBox | Per-video override of `ms_lms_enrollment_check` |
| `_ms_lms_complete_pct` | LMS\LMSMetaBox | Per-video override of `ms_lms_complete_pct` |
| `_ms_ad_mode` | Ads\VideoAdsMetaBox | How ads run on this video |
| `_ms_ad_ids` | Ads\VideoAdsMetaBox | Selected creative post IDs |
| `_ms_ad_preroll` | Ads\VideoAdsMetaBox | Per-video pre-roll override |
| `_ms_ad_postroll` | Ads\VideoAdsMetaBox | Per-video post-roll override |
| `_ms_ad_midroll_count` | Ads\VideoAdsMetaBox | Per-video mid-roll count override |
| `_ms_ad_plan_custom` | Ads\VideoAdsMetaBox | Hand-authored break positions |

`_ms_access_role` and `_ms_protection_level` are **free-owned** meta registered by `CPT\VideoPostType` - Pro reads them, it does not own them. `_ms_access_type` is not written by Pro at all; it is an extension seam free reads. See [post-meta-reference.md](post-meta-reference.md).
