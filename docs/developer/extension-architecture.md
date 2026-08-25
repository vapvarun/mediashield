# Extension Architecture

How third-party plugins and the Pro add-on extend MediaShield.

---

## Free / Pro contract

The free plugin is the base. Pro is a pure add-on: it never replaces free behavior, only extends through hooks. If Pro is deactivated, the free plugin continues working exactly as configured.

Pro hooks in at the following boundaries:

1. **Admin SPA routes** - the `mediashield_admin_routes` JS filter adds pages to the hash router.
2. **Settings REST** - `mediashield_settings_response` (GET) and `mediashield_settings_update` (PUT) filters let Pro add and save settings. Pro callbacks `unset()` their own keys from `$data` so the free `SettingsController` loop ignores them. Three Pro classes subscribe: `Admin\ProSettings`, `Admin\DRMSettings`, `Watermark\AdvancedConfig`.
3. **Player type** - `mediashield_player_type` lets Pro override to `drm`.
4. **Protection levels** - `mediashield_protection_levels` (1.3.0) is how Pro adds the `drm` level to the video edit screen at all.
5. **Access control** - `mediashield_can_watch` stacked at priorities 20 (role) and 25 (LMS adapters).
6. **Upload drivers** - `mediashield_upload_drivers` registers Bunny, YouTube, Vimeo, Wistia driver classes.
7. **Upload lifecycle** - `mediashield_upload_started` / `_complete` / `_failed` feed Pro's `ms_upload_queue` table.
8. **Stream URL** - `mediashield_video_stream_url` lets `Platform\BunnyUrls` supply signed Bunny CDN URLs.
9. **Ads** - `mediashield_video_ads` and `mediashield_ad_break_plan` are resolved by Pro's `Ads\AdResolver`.
10. **Watermark** - `mediashield_watermark_config` is extended by `Watermark\AdvancedConfig`.
11. **Analytics** - `mediashield_session_started` (SuspiciousActivity at 10, VpnDetection at 20), `mediashield_devtools_detected`, `mediashield_milestone_reached`.

---

## Filter chain order

`mediashield_can_watch` is the primary access gate. The full priority stack:

| Priority | Subscriber | Decision |
|----------|-----------|---------|
| n/a (before the filter) | `Access\AccessControl` (free core) | Admin bypass, login gate, per-video `_ms_access_role` check, allowed-domain check. These run **before** `apply_filters()` and short-circuit on denial - a custom callback never sees a request they refused. |
| 20 | `Access\RoleAccess` (Pro) | Per-video role restriction via `_ms_access_role` meta |
| 25 | LMS adapters (Pro) | LearnDash / LifterLMS / TutorLMS enrolment + course-progress gates |

Free does not register a callback on its own filter, so priority 10 is free for you to use - but only for widening or annotating a decision that already passed free's built-in gates. Pick a priority outside the 20-25 band, or accept that Pro's gates may have already denied access.

Return shape: `array{ allowed: bool, reason: string }`. Custom callbacks must return the same shape. `reason` is surfaced to the client and is also promoted to the REST error code by `SessionController`, so keep it a stable slug if you want the player to route on it.

---

## LMS adapters - `mediashield_lms_adapters` filter

*Since 1.1.0*

Pro registers LMS adapters via the `mediashield_lms_adapters` filter in `LMS\LMSManager`. The array holds **instances**, not class names, keyed by a unique slug, and each must implement `MediaShieldPro\LMS\LMSAdapterInterface`. Anything failing the `instanceof` check is dropped with a `_doing_it_wrong()` notice.

```php
add_filter( 'mediashield_lms_adapters', function ( $adapters ) {
    $adapters['my_lms'] = new MyPlugin\LMS\MyLMSAdapter();
    return $adapters;
} );
```

The interface is eight methods, not one:

```php
interface LMSAdapterInterface {
    public function register(): void;                       // wire your own hooks
    public function get_name(): string;                     // slug
    public function get_label(): string;                    // display label
    public function get_linkable_items(): array;            // lessons/topics for the metabox dropdown
    public function owns_post( int $post_id ): bool;        // guards _ms_linked_lesson saves
    public function is_user_enrolled( int $user_id, int $lesson_id ): bool;
    public function mark_complete( int $user_id, int $lesson_id ): bool;
    public function get_user_progress( int $user_id, int $course_id ): float;
}
```

`LMSManager` calls `register()` on every accepted adapter and then fires `mediashield_lms_adapters_loaded` with the final map. That action is read-only - an action cannot mutate the array, so use the filter to add an adapter.

Built-in adapters register their own `mediashield_can_watch` callback at 25 and a `mediashield_milestone_reached` listener at 10, and fire `mediashield_lms_lesson_completed` ($user_id, $video_id, $lesson_id, $source) after marking a lesson complete.

---

## Admin SPA routes - `mediashield_admin_routes`

The admin SPA is a React app with hash routing under `wp-admin/admin.php?page=mediashield`. `src/admin/App.js` runs `applyFilters( 'mediashield_admin_routes', defaultRoutes )` and `Sidebar` renders whatever comes back.

A route object is `{ hash, label, icon, component }`. The key is **`hash`** (a full `#/slug` string), not `path`, and `App` matches on it directly - a route with a `path` key will render in the sidebar and never activate. `icon` is a Dashicons-derived name token **without** the `dashicons-` prefix (`'dashboard'`, `'format-video'`, `'cloud'`); `components/Icon.js` maps those tokens onto Lucide icons, and an unmapped token renders nothing.

```js
// In your plugin's admin JS bundle (loaded after MediaShield's admin bundle):
wp.hooks.addFilter( 'mediashield_admin_routes', 'my-addon', function ( routes ) {
    // Guard against double-injection on re-render, as Pro does.
    if ( routes.some( ( r ) => r.hash === '#/my-page' ) ) {
        return routes;
    }
    return [ ...routes, {
        hash:      '#/my-page',
        label:     'My Page',
        icon:      'admin-generic',
        component: MyPageComponent,
    } ];
} );
```

Your component receives no props. Use `wp.apiFetch` with the `mediashield/v1` REST endpoints to load data. Because the filter hands you the whole array, you can also wrap an existing route's `component` to inject UI into a free page - that is exactly how Pro adds its watermark section to Settings.

---

## SlotFill

`App.js` wraps the SPA in a `SlotFillProvider`, but the admin SPA does not currently expose any named `Slot` of its own. There is no `mediashield_video_editor_sidebar_panels` hook - injecting a page, or wrapping a free page's component, is done through `mediashield_admin_routes` above.

The one real named slot in the free plugin is in the **block editor**, not the SPA:

```js
// Rendered by src/blocks/video/edit.js inside the block's InspectorControls.
<Slot name="mediashield-video-access-controls" />
```

Fill it from your own block-editor bundle:

```js
import { Fill } from '@wordpress/components';

const MyAccessPanel = () => (
    <Fill name="mediashield-video-access-controls">
        <PanelBody title="My gate">...</PanelBody>
    </Fill>
);
```

Nothing fills it today, including Pro. Check `src/blocks/video/edit.js` and `src/admin/App.js` for the current list before relying on a slot - they may change between releases.

---

## Upload driver contract

All upload drivers implement `MediaShield\Upload\Drivers\DriverInterface`, which is five methods:

```php
interface DriverInterface {
    public function upload( string $file_path, array $options = array() ): array;
    // Returns: [ 'success' => bool, 'video_id' => int, 'platform_video_id' => string,
    //            'embed_url' => string, 'error' => string ]

    public function get_status( string $upload_id ): array;
    // Returns: [ 'status' => string, 'progress' => int, 'error' => string ]

    public function delete( string $platform_video_id ): bool;
    public function get_embed_url( string $platform_video_id ): string;
    public function get_name(): string;
}
```

Note `upload()` returns `video_id` and `embed_url` - not a `url` key - and `get_status()` returns `progress`, not `progress_pct`. `Cron\Cleanup::handle_video_delete()` calls `delete()` when a video CPT is permanently deleted, so a driver that no-ops `delete()` will leave orphans on the remote platform.

Register a driver by class name (the filter takes class strings, unlike the LMS filter which takes instances):

```php
add_filter( 'mediashield_upload_drivers', function( $drivers ) {
    $drivers['s3'] = MyPlugin\Upload\S3Driver::class;
    return $drivers;
} );
```

`UploadManager::get_driver()` instantiates driver classes lazily. The `mediashield_upload_started`, `mediashield_upload_complete`, and `mediashield_upload_failed` actions fire inside `UploadManager::upload()`, around the driver's `upload()` call, so every caller (admin uploader, frontend uploader, REST) reports the same lifecycle.

---

## How Pro boots alongside the free plugin

Pro does **not** hook `mediashield_loaded`. Both plugins bootstrap on `plugins_loaded`; free at the default priority 10, Pro at 20:

```
plugins_loaded (10)
  └── free: Migrator::run() → Plugin::instance() → … → do_action( 'mediashield_loaded' )

plugins_loaded (20)
  └── pro: guard on defined( 'MEDIASHIELD_VERSION' )
           → Migrator::run() → Plugin::instance() → do_action( 'mediashield_pro_loaded' )
```

Order is carried by the priority gap plus a `defined( 'MEDIASHIELD_VERSION' )` guard that shows an admin notice and returns when the free plugin is missing. Do not re-time free's bootstrap expecting Pro to wait on an action - it does not.

`mediashield_loaded` is still fired by free at the end of `Core\Plugin`'s constructor and is the right hook for **your** add-on, which does not have Pro's priority arrangement. `mediashield_pro_loaded` is its Pro counterpart.

Pro's `Plugin::instance()` is a singleton that runs only once. It registers all Pro hooks, filters, CPT meta, and REST routes. It does not modify any free plugin hook registration.
