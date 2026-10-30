# Extension Architecture

**Free + Pro** - The seams below are in the free plugin. Sections marked Pro describe code in MediaShield Pro.

How to build add-ons and integrations on top of MediaShield.

## Free / Pro contract

The free plugin is the base. Pro is a pure add-on: it never replaces free behavior, only extends through hooks. If Pro is deactivated, the free plugin continues working exactly as before.

The same contract applies to third-party add-ons. Build against the hooks - not against internal class methods or private APIs - and your add-on will survive free plugin updates.

## Boot order

```
plugins_loaded (priority 10)
  - free: Migrator::run() -> Plugin::instance() -> ... -> do_action('mediashield_loaded')
plugins_loaded (priority 20)
  - Pro and add-ons: initialize here
plugins_loaded (priority 30)
  - Pro: LMSManager::detect_lms()
```

`Migrator::run()` goes first so that schema changes and newly declared option defaults are in place before anything reads them. `mediashield_loaded` fires at the end of the free plugin's own hook registration, inside the priority-10 callback. Pro does not listen to it: it boots on `plugins_loaded` at priority 20, which is the pattern to copy. An add-on that adds its `mediashield_loaded` callback after Free has booted has missed it, so use priority 20 or test `did_action( 'mediashield_loaded' )`.

## The access decision

`AccessControl::can_watch( $video_id, $user_id )` is the single gate. It runs at session start and again on every request to the self-hosted stream endpoint.

Its built-in checks are **not** filter callbacks - they run inline, before the filter:

| Order | Check | Outcome |
|-------|-------|---------|
| 1 | Administrator (`manage_options`) | Allowed, nothing else runs |
| 2 | Login gate (`ms_require_login`) | Denies guests with the login overlay text |
| 3 | Per-video role (`_ms_access_role`) | Exact role match; denies with the access-denied text |
| 4 | Domain whitelist (`ms_allowed_domains`) | Referer check |
| 5 | `mediashield_can_watch` filter | Everything else |

A denial from steps 2-4 short-circuits and the filter never runs, so a callback cannot "un-deny" a video the core rules refused.

Inside the filter, Pro attaches:

| Priority | Component | Decision |
|----------|-----------|---------|
| 20 | Pro: RoleAccess | Re-checks the per-video role (a duplicate of step 3, kept from before free gained that check) |
| 25 | Pro: LMS adapters | LearnDash / LifterLMS / TutorLMS enrollment checks |

Return shape: `array{ allowed: bool, reason: string }`. Custom callbacks must return the same shape, and should return `$result` untouched when they have no opinion. Choose a priority outside 20-25 or accept that Pro's gates may have already set the decision.

## LMS adapters (Pro, since 1.1.0)

**PRO** - requires MediaShield Pro. The adapter registry and `MediaShieldPro\LMS\LMSAdapterInterface` live in Pro.

Pro auto-detects LearnDash, Tutor LMS, and LifterLMS. Anything else registers through the `mediashield_lms_adapters` filter.

The filter passes a map of **slug => adapter instance**, not class names, and each instance must implement `MediaShieldPro\LMS\LMSAdapterInterface`. Entries that fail the type check are dropped with a `_doing_it_wrong()` notice so the mistake is visible during development.

```php
add_filter( 'mediashield_lms_adapters', function( $adapters ) {
    $adapters['my_lms'] = new MyPlugin\LMS\MyLMSAdapter();
    return $adapters;
} );
```

The interface requires `register()`, `get_name()`, and the rest of the contract in Pro's `includes/LMS/LMSAdapterInterface.php`. Adapters are registered on `plugins_loaded` at priority 30, so add your filter before then.

There is also a `mediashield_lms_adapters_loaded` action, which fires after adapters are wired up. It is read-only - an action cannot change the array - so use the filter to register anything new.

## Admin SPA - adding pages

The admin SPA is a React app with hash routing. Add new pages via the `mediashield_admin_routes` JavaScript filter (a `wp.hooks` filter, not a PHP filter).

Route objects use `hash`, not `path`:

```js
wp.hooks.addFilter( 'mediashield_admin_routes', 'my-addon', function( routes ) {
    routes.push( {
        hash:      '#/my-page',
        label:     'My Page',
        icon:      'admin-generic',
        component: MyPageComponent,
    } );
    return routes;
} );
```

`icon` is a legacy Dashicons-style token (`dashboard`, `format-video`, `tag`, `flag`, `admin-generic`, `cloud`, `lock`, and so on) that the SPA maps to a Lucide icon. Do not include the `dashicons-` prefix. An unrecognised token renders a question-mark icon rather than failing.

Your component loads data via `wp.apiFetch` using the `mediashield/v1` REST endpoints.

## SlotFill - injecting into the video block sidebar

The MediaShield Video block exposes one `@wordpress/components` Slot in its inspector sidebar:

```js
import { Fill } from '@wordpress/components';

const MyPanel = () => (
    <Fill name="mediashield-video-access-controls">
        { /* your controls */ }
    </Fill>
);
```

That is the only slot in this release. The admin SPA wraps itself in a `SlotFillProvider`, but declares no named slots of its own; use the `mediashield_admin_routes` filter above to add admin UI.

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

Unset your own keys from `$data` before returning from the update filter. The free `SettingsController` loops over the remaining keys and skips anything not in `Core\Settings::schema()`, so leaving them in is harmless - but removing them explicitly keeps the behavior unambiguous.

Do not add option names to the free schema from an add-on. Uninstall derives the list of options to delete from that schema, so anything you put there will be deleted with the free plugin.

## Upload driver contract

Register custom upload drivers for new hosting platforms:

```php
add_filter( 'mediashield_upload_drivers', function( $drivers ) {
    $drivers['my_platform'] = MyPlugin\Upload\MyPlatformDriver::class;
    return $drivers;
} );
```

Drivers are registered as **class names** (Pro registers `bunny`, `vimeo`, `youtube`, and `wistia` this way) and instantiated on demand with no constructor arguments. Each must implement `MediaShield\Upload\Drivers\DriverInterface`:

```php
interface DriverInterface {
    // Returns: [ 'success' => bool, 'video_id' => int, 'platform_video_id' => string,
    //            'embed_url' => string, 'error' => string ]
    public function upload( string $file_path, array $options = array() ): array;

    // Returns: [ 'status' => string, 'progress' => int, 'error' => string ]
    public function get_status( string $upload_id ): array;

    public function delete( string $platform_video_id ): bool;

    public function get_embed_url( string $platform_video_id ): string;

    public function get_name(): string;
}
```

`upload()` is expected to create the `mediashield_video` post and return its ID, or to fill in the post passed as `$options['attach_to']`. `delete()` is called by the cascade **only for self-hosted media** - a file this plugin put in the site's own uploads folder. A driver for a remote platform should implement it as a refusal that returns `false`: MediaShield deliberately never deletes media from a hosting platform, so removing a video here leaves the master in place and it can be linked back by importing it again. These services have no trash and no undo, and the master is usually something the owner pays to store and may use elsewhere.

Which driver runs when the request does not name one comes from the `ms_default_upload_target` option: `self` means `self_hosted`, a driver name means that driver, and `auto` asks the `mediashield_default_upload_driver` filter (default `self_hosted`). An unregistered name falls back to `self_hosted`.

The `mediashield_upload_started`, `mediashield_upload_complete`, and `mediashield_upload_failed` actions fire around the driver's `upload()` call regardless of which driver is active.

## Player type extension

Override the player type for specific videos:

```php
add_filter( 'mediashield_player_type', function( $type, $video_id ) {
    if ( get_post_meta( $video_id, '_my_drm_enabled', true ) ) {
        return 'drm';
    }
    return $type;
}, 10, 2 );
```

The value is emitted as `data-player-type` on the player container for client-side code to act on. Free itself renders `standard` for every video and does not ship a DRM player; what it does load, on self-hosted and Bunny sources, is the bundled HLS library, triggered by the `mediashield_needs_shaka` action. The name is historical - free does not use Shaka Player.

**PRO** - Pro registers its own `drm-player.js` whenever its DRM method setting is anything other than `none`, and enqueues it alongside the free player assets. It is not keyed on the player type.

To make a new protection level selectable in the admin at all, pair this with the `mediashield_protection_levels` filter so the level appears in the level list on the video edit screen.

## Learnomy integration

*Since 1.3.0.* Free filters Learnomy's `learnomy_rest_lesson_payload`. For a lesson whose `content_type` is `mediashield` and whose `video_url` holds a MediaShield video ID, and for a logged-in requester, it adds a `mediashield` block to the payload so an app that cannot run the shortcode can still play the video:

```json
{ "mediashield": { "video_id": 291, "embed_url": "https://example.com/?mediashield_embed=...", "expires_in": 900, "poster_url": "https://..." } }
```

The block is omitted when the video does not exist, is not published, or the requester is a guest. `embed_url` is a signed embed link (next section), so the same 15 minute lifetime and access re-check apply. Nothing runs when Learnomy is not active.

## In-video ads (WB Ad Manager)

`Integrations\AdManagerBridge` fills `mediashield_video_ad_breaks` from WB Ad Manager video creatives. To feed breaks from another source, return the break list from that filter yourself; to change what the bridge plans, use `mediashield_video_ads` (creative pool) and `mediashield_ad_break_plan` (`pre`, `mid_count`, `post`). Without a source that returns breaks, `ad-breaks.js` is never enqueued.

## Signed embed links

*Since 1.3.0.* Shortcodes, blocks, and template calls all require you to be rendering a WordPress page in PHP, which a native app or an off-site LMS cannot do. `Embed\EmbedLink` mints a standalone URL instead:

```php
$url = \MediaShield\Embed\EmbedLink::url( $video_id, $user_id );
// https://example.com/?mediashield_embed=<signed token>
```

The link resolves to a self-contained player page with the full protection layer attached. Tokens are valid for 15 minutes by default (pass a third argument to change it), and the page re-runs `can_watch()` for the named viewer when the link is opened, so a revoked viewer holding an unexpired link is still refused. Filter the minted URL with `mediashield_embed_url`.

## Privacy integration

If your add-on stores per-user data related to video watching, integrate with WordPress's GDPR tools via the MediaShield privacy hooks:

- `mediashield_privacy_before_erase` - add your deletions to the erasure count
- `mediashield_privacy_erase_result` - append messages to the erasure report
- `mediashield_privacy_export_result` - append items to the export

All three are documented in [Hooks and Filters](02-hooks-and-filters.md).
