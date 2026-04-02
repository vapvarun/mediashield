# MediaShield Pro -- Hooks & Filters Reference

Pro-specific hooks that extend the free plugin's hook system. For free plugin hooks, see [hooks-filters.md](../free/hooks-filters.md).

---

## Actions

### mediashield_pro_loaded

Fired after the Pro plugin is fully loaded.

```php
add_action( 'mediashield_pro_loaded', function() {
    // Pro is ready
});
```

**Parameters:** None

---

### mediashield_fire_webhook

Fired when a milestone action dispatches a webhook.

```php
add_action( 'mediashield_fire_webhook', function( $url, $payload ) {
    // Log webhook dispatch
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

## Hooks Consumed from Free Plugin

Pro hooks into these free plugin hooks at specific priorities:

### Filters

| Free Hook | Pro Class | Priority | Purpose |
|-----------|-----------|----------|---------|
| `mediashield_can_watch` | `Access\EmailGate` | 15 | Require email before access |
| `mediashield_can_watch` | `Access\RoleAccess` | 20 | Enforce per-video role restriction |
| `mediashield_watermark_config` | `Watermark\AdvancedConfig` | 10 | Add 7-field watermark configuration |
| `mediashield_upload_drivers` | `Core\Plugin` | 10 | Register Bunny/Vimeo/YouTube/Wistia drivers |
| `mediashield_player_type` | `Core\Plugin` | 10 | Override to `drm` for DRM videos |
| `mediashield_settings_response` | `Admin\DRMSettings` | 10 | Inject DRM settings into GET |
| `mediashield_settings_response` | `Watermark\AdvancedConfig` | 10 | Inject watermark settings into GET |
| `mediashield_settings_update` | `Admin\DRMSettings` | 10 | Save DRM settings from PUT |
| `mediashield_settings_update` | `Watermark\AdvancedConfig` | 10 | Save watermark settings from PUT |

### Actions

| Free Hook | Pro Class | Purpose |
|-----------|-----------|---------|
| `mediashield_milestone_reached` | `Milestones\AdvancedActions` | Fire webhook, send email, tag user |
| `mediashield_session_started` | `Analytics\SuspiciousActivity` | Check for multi-IP, concurrent streams |

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
| `_ms_access_type` | Editor | Access type (e.g., `email_gate`) |
| `_ms_library_id` | BunnyStream | Bunny library ID |
| `_ms_wistia_numeric_id` | WistiaApi | Wistia numeric ID |
| `_ms_drm_enabled` | Packager | DRM enabled flag |
| `_ms_drm_method` | Packager | DRM method used |
| `_ms_drm_output_dir` | Packager | Shaka output directory |
| `_ms_drm_packaged_at` | Packager | Packaging timestamp |
| `_ms_drm_packaging_status` | Packager | Job status |
| `_ms_drm_packaging_action_id` | Packager | Action Scheduler job ID |
