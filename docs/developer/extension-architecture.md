# Extension Architecture

How third-party plugins and the Pro add-on extend MediaShield.

---

## Free / Pro contract

The free plugin is the base. Pro is a pure add-on: it never replaces free behavior, only extends through hooks. If Pro is deactivated, the free plugin continues working exactly as configured.

Pro hooks in at the following boundaries:

1. **Admin SPA routes** — `mediashield_admin_routes` JS filter adds pages to the hash router.
2. **SlotFill** — the admin SPA uses `wp.hooks` for Pro to inject UI components into existing pages without forking the free SPA codebase.
3. **Settings REST** — `mediashield_settings_response` (GET) and `mediashield_settings_update` (PUT) filters let Pro add and save settings. Pro callbacks `unset()` their own keys from `$data` so the free `SettingsController` loop ignores them.
4. **Player type** — `mediashield_player_type` filter lets Pro override to `drm` for DRM-protected videos.
5. **Access control** — `mediashield_can_watch` filter stacked at priorities 15 (email gate), 20 (role access), 25 (LMS adapters).
6. **Upload drivers** — `mediashield_upload_drivers` filter registers Bunny, YouTube, Vimeo, Wistia upload driver classes.

---

## Filter chain order

`mediashield_can_watch` is the primary access gate. The full priority stack:

| Priority | Subscriber | Decision |
|----------|-----------|---------|
| 10 | `Access\AccessControl` (free core) | Login gate, role check, domain whitelist |
| 15 | `Access\EmailGate` (Pro) | Require email submission for `email_gate` access type |
| 20 | `Access\RoleAccess` (Pro) | Per-video role restriction via `_ms_access_role` meta |
| 25 | LMS adapters (Pro) | LearnDash / LifterLMS / TutorLMS enrolment + course-progress gates |

Return shape: `array{ allowed: bool, reason: string }`. Custom callbacks should return the same shape. Pick a priority outside the 10–25 band, or accept that Pro's gates may have already denied access.

---

## LMS adapters — `mediashield_lms_adapters` filter

*Since 1.1.0*

Pro registers LMS adapter classes via the `mediashield_lms_adapters` filter. Each adapter class must implement `MediaShield\Pro\LMS\AdapterInterface`.

```php
add_filter( 'mediashield_lms_adapters', function( $adapters ) {
    $adapters['my_lms'] = MyPlugin\LMS\MyLMSAdapter::class;
    return $adapters;
} );
```

An adapter receives the video ID and post ID at enrollment check time and returns whether the current user is enrolled and has progress gate cleared.

---

## Admin SPA routes — `mediashield_admin_routes`

The admin SPA is a React app with hash routing under `wp-admin/admin.php?page=mediashield`. Pro injects additional pages via a JavaScript-side `wp.hooks` filter.

```js
// In your plugin's admin JS bundle (loaded after MediaShield's admin bundle):
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

Your component receives no props by default. Use `wp.apiFetch` with the standard `mediashield/v1` REST endpoints to load data.

---

## SlotFill pattern

The admin SPA exposes named slot components via `wp.hooks`. Pro uses these to inject panels into existing pages without overriding the free SPA.

```js
wp.hooks.addFilter( 'mediashield_video_editor_sidebar_panels', 'my-addon', function( panels ) {
    panels.push( MyDRMPanel );
    return panels;
} );
```

Available slot hooks are declared in `src/admin/App.js`. Check the current list before using undocumented slots — they may change between releases.

---

## Upload driver contract

All upload drivers implement `MediaShield\Upload\Drivers\DriverInterface`:

```php
interface DriverInterface {
    public function upload( string $file_path, array $options ): array;
    // Returns: [ 'success' => bool, 'url' => string, 'platform_video_id' => string, 'error' => string ]
}
```

Register a driver:

```php
add_filter( 'mediashield_upload_drivers', function( $drivers ) {
    $drivers['s3'] = MyPlugin\Upload\S3Driver::class;
    return $drivers;
} );
```

The `UploadManager` instantiates driver classes lazily. The `mediashield_upload_started`, `mediashield_upload_complete`, and `mediashield_upload_failed` actions fire around the driver's `upload()` call regardless of which driver is active.

---

## How Pro extends the free plugin's bootstrap

Pro's `includes/Core/Plugin.php` hooks into `mediashield_loaded` (the free plugin's final boot action) to initialize all Pro subsystems. This guarantees Pro never initializes before the free plugin is ready.

```
plugins_loaded
  └── free: Migrator::run() → Plugin::instance() → do_action('mediashield_loaded')
                                                              └── Pro: Plugin::instance() → register all Pro hooks
```

Pro's `Plugin::instance()` is a singleton that runs only once. It registers all Pro hooks, filters, CPT meta, and REST routes. It does not modify any free plugin hook registrations.
