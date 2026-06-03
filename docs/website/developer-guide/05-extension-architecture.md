# Extension Architecture

How to build add-ons and integrations on top of MediaShield.

## Free / Pro contract

The free plugin is the base. Pro is a pure add-on: it never replaces free behavior, only extends through hooks. If Pro is deactivated, the free plugin continues working exactly as before.

The same contract applies to third-party add-ons. Build against the hooks - not against internal class methods or private APIs - and your add-on will survive free plugin updates.

## Boot order

```
plugins_loaded
  - free: Migrator::run() -> Plugin::instance() -> do_action('mediashield_loaded')
                                                          - Pro or add-on: initialize here
```

Pro hooks into `mediashield_loaded` to initialize. This guarantees add-ons never initialize before the free plugin is ready. Use the same pattern for your own code.

## The mediashield_can_watch filter chain

This is the primary access gate. It runs at session start to decide whether a viewer can watch a video.

| Priority | Component | Decision |
|----------|-----------|---------|
| 10 | Free core (AccessControl) | Login requirement, role check, domain whitelist |
| 15 | Pro: EmailGate | Require email submission for email_gate access type |
| 20 | Pro: RoleAccess | Per-video role restriction |
| 25 | Pro: LMS adapters | LearnDash / LifterLMS / TutorLMS enrollment checks |

Return shape: `array{ allowed: bool, reason: string }`. Custom callbacks must return the same shape. Choose a priority outside 10-25 or accept that Pro's gates may have already set the decision.

## LMS adapters (since 1.1.0)

Pro registers LMS adapter classes via the `mediashield_lms_adapters` filter. Third-party plugins can register their own adapters the same way. Each adapter class must implement `MediaShield\Pro\LMS\AdapterInterface`.

```php
add_filter( 'mediashield_lms_adapters', function( $adapters ) {
    $adapters['my_lms'] = MyPlugin\LMS\MyLMSAdapter::class;
    return $adapters;
} );
```

## Admin SPA - adding pages

The admin SPA is a React app with hash routing. Add new pages via the `mediashield_admin_routes` JavaScript filter (a `wp.hooks` filter, not a PHP filter).

```js
wp.hooks.addFilter( 'mediashield_admin_routes', 'my-addon', function( routes ) {
    routes.push( {
        path:      '/my-page',
        label:     'My Page',
        icon:      'dashicons-admin-generic',
        component: MyPageComponent,
    } );
    return routes;
} );
```

Your component loads data via `wp.apiFetch` using the `mediashield/v1` REST endpoints.

## SlotFill - injecting into existing pages

The admin SPA exposes named slot hooks via `wp.hooks`. Use these to inject panels or controls into existing admin pages without forking the free SPA code.

```js
wp.hooks.addFilter( 'mediashield_video_editor_sidebar_panels', 'my-addon', function( panels ) {
    panels.push( MyCustomPanel );
    return panels;
} );
```

Available slot hooks are declared in `src/admin/App.js`. Check the current list before using undocumented slots - they may change between releases.

## Settings REST extension

Add your own settings to the GET and PUT endpoints using filters:

```php
// Expose your setting in the GET response.
add_filter( 'mediashield_settings_response', function( $settings ) {
    $settings['my_addon_setting'] = get_option( 'my_addon_setting', 'default' );
    return $settings;
} );

// Save your setting on PUT.
add_filter( 'mediashield_settings_update', function( $data ) {
    if ( isset( $data['my_addon_setting'] ) ) {
        update_option( 'my_addon_setting', sanitize_text_field( $data['my_addon_setting'] ) );
        unset( $data['my_addon_setting'] ); // Remove so free controller ignores it.
    }
    return $data;
} );
```

Unset your own keys from `$data` before returning from the update filter. The free `SettingsController` loops over the remaining keys - keys not in the free schema are silently ignored, but removing yours explicitly keeps the behavior unambiguous.

## Upload driver contract

Register custom upload drivers for new hosting platforms:

```php
add_filter( 'mediashield_upload_drivers', function( $drivers ) {
    $drivers['my_platform'] = MyPlugin\Upload\MyPlatformDriver::class;
    return $drivers;
} );
```

Each driver class must implement `MediaShield\Upload\Drivers\DriverInterface`:

```php
interface DriverInterface {
    public function upload( string $file_path, array $options ): array;
    // Returns: [ 'success' => bool, 'url' => string, 'platform_video_id' => string, 'error' => string ]
}
```

The `mediashield_upload_started`, `mediashield_upload_complete`, and `mediashield_upload_failed` actions fire around the driver's `upload()` call regardless of which driver is active.

## Player type extension

Override the player type for specific videos to trigger DRM playback:

```php
add_filter( 'mediashield_player_type', function( $type, $video_id ) {
    if ( get_post_meta( $video_id, '_my_drm_enabled', true ) ) {
        return 'drm';
    }
    return $type;
}, 10, 2 );
```

The DRM player (Shaka Player) is enqueued only when the player type is `drm`. Free uses `standard` for all videos.

## Privacy integration

If your add-on stores per-user data related to video watching, integrate with WordPress's GDPR tools via the MediaShield privacy hooks:

- `mediashield_privacy_before_erase` - add your deletions to the erasure count
- `mediashield_privacy_erase_result` - append messages to the erasure report

Both hooks are documented in [Hooks and Filters](02-hooks-and-filters.md).
