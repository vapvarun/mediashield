# JavaScript Events and Player Config

**Included in Free** - Pro scripts listen to the same events.

The front-end player is plain JavaScript with no build step (`assets/js/`). It talks to add-ons through browser events and one config object.

## Events

Events are `CustomEvent`s. Unless noted they are dispatched on `window`.

```js
window.addEventListener( 'mediashield:player-ready', function ( e ) {
    console.log( e.detail.videoId, e.detail.token );
} );
```

| Event | Target | Detail | When |
|-------|--------|--------|------|
| `mediashield:player-ready` | `window` | `el`, `videoId`, `token`, `resumePosition`, `watermarkConfig`, `video`, `adapter` | A watch session has started and the player is wired up. The watermark, tracker, protection and ad scripts all key off it. |
| `mediashield:player-ready` | `window` | `el`, `videoId` (0), `adapter` | Fired without a session when the player has no video ID (nothing to track). Check `token` before using it. |
| `mediashield:access-denied` | `window`, cancelable | `el`, `videoId`, `reason` | `POST /session/start` was refused. Call `event.preventDefault()` to suppress the built-in overlay and draw your own. |
| `mediashield:concurrent-limit` | `window` | `el`, `videoId`, `message` | Session start was refused because the viewer is at the stream limit. |
| `mediashield:devtools-detected` | `window` | `strategy` (`size_delta` or `debugger_timing`) | Developer tools were detected, before the beacon is sent. |
| `mediashield:ended` | the player element, bubbles | none | Playback reached the end. The playlist uses it to advance. |
| `mediashield:player-destroy` | `window` | `el` | A player element is being replaced (the playlist does this when it swaps in the next video). The tracker ends the session and the wrapper cleans up. |

There is no `mediashield:playlist-switch` event. To react to a playlist moving on, listen for `mediashield:player-destroy` followed by the next `mediashield:player-ready`.

## window.mediashieldConfig

Localized onto the player script by `Settings::frontend_config()` and filterable with `mediashield_frontend_config`.

| Key | Value |
|-----|-------|
| `restUrl` | `mediashield/v1/` base URL. |
| `nonce` | `wp_rest` nonce. |
| `isLoggedIn`, `userId`, `loginUrl` | Current viewer and the login link for the overlay. |
| `requireLogin` | The `ms_require_login` setting. |
| `interval` | Heartbeat interval in milliseconds (30000). |
| `player` | `speedControl`, `preventForwardSeek`, `keyboard`, `resume`, `sticky`, `endscreen`, `endscreenText`, `endscreenUrl`, from the matching `ms_player_*` settings. A video's own overrides are merged per player. |
| `messages` | Every viewer-facing string (login overlay, access denied, load failed, concurrent limit, resume prompt, ad labels and so on). Owner-edited messages are passed through untouched; defaults are translated. |

Protection settings travel separately, through `mediashield_protection_config`.

## Admin SPA filter

The admin app is a React app with hash routing. Add pages with the JavaScript filter `mediashield_admin_routes` (`wp.hooks`, not PHP). Route objects use `hash`, `label`, `icon`, `component`; see [Extension Architecture](05-extension-architecture.md).

## Block editor slot

The MediaShield Video block renders one `Slot` named `mediashield-video-access-controls` in its inspector. Nothing in Free or the current Pro fills it yet, so it is available to add-ons.
