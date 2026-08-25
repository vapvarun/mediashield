# Developer Guide Overview

This section is for developers building integrations, add-ons, or custom code on top of MediaShield.

Version 1.3.0. Requires PHP 8.1 and WordPress 6.5.

## What the developer guide covers

- [Hooks and Filters](02-hooks-and-filters.md) - the PHP actions and filters the free plugin exposes, with parameters and examples
- [REST API](03-rest-api.md) - all endpoints under `mediashield/v1`, authentication, request and response formats
- [Database Tables](04-database-tables.md) - the 6 free plugin tables, their columns, indexes, and cleanup behavior
- [Extension Architecture](05-extension-architecture.md) - how to build an add-on, the filter chain order, upload driver contract, and how Pro extends the free plugin

## Architecture summary

MediaShield uses a singleton bootstrap pattern. The entry point is `mediashield.php`, which loads the Composer autoloader and, on `plugins_loaded`, runs `Migrator::run()` and then `Plugin::instance()`. The plugin fires `mediashield_loaded` when all internal hooks are registered - this is the correct hook for add-ons to initialize.

The PHP namespace is `MediaShield\`, PSR-4 autoloaded from `includes/`. WP-CLI commands live in `src/CLI/` and are required directly by the bootstrap.

Key classes:

| Class | Role |
|-------|------|
| `Core\Plugin` | Singleton entry point, registers all hooks |
| `Core\Settings` | Single source of truth for all free-plugin options |
| `Core\Migrator` | DB version tracking; re-seeds new options on upgrade |
| `Access\AccessControl` | Runs the access checks and the `mediashield_can_watch` filter |
| `Access\SessionManager` | HMAC token generation and concurrent stream enforcement |
| `Milestones\MilestoneTracker` | Detects completion thresholds, fires milestone actions |
| `Upload\UploadManager` | Upload driver registry via `mediashield_upload_drivers` filter |
| `Player\Renderer` | Shared single-video player output (shortcode, block, single template) |
| `Player\PlayerWrapper` | Output buffer scan for video elements |
| `Player\Protection` | Protection config, and the server-side source-URL decision |
| `Embed\EmbedLink` / `Embed\EmbedPage` | Signed standalone embed links for non-PHP clients |
| `Admin\HealthCheck` | Site Health test for direct download of stored video files |

## Custom Post Types

MediaShield registers two CPTs. Neither has its own admin menu item (`show_in_menu` is false); both are reached from the admin SPA.

- `mediashield_video` - individual protected videos. `public` false, `show_ui` true. REST base: `mediashield-videos`. Supports title, thumbnail, custom fields.
- `mediashield_playlist` - ordered groups of videos. `public` true. REST base: `mediashield-playlists`. Supports title, editor, thumbnail.

Post meta keys for both are documented in the full developer reference (`docs/developer/post-meta-reference.md` in the plugin repository).

## Settings

All free-plugin options are defined in `Core\Settings::schema()`, which carries each option's type, default, and optional validator. Adding a new option requires updating that schema, bumping `MEDIASHIELD_DB_VERSION` (the migrator re-seeds defaults on a version bump, so existing installs pick the option up), and, if the value needs to reach the browser, referencing it in `Settings::frontend_config()`.

Uninstall derives the list of options to delete from the same schema, so an option added there is cleaned up automatically.

Two schema entries have no admin UI in 1.3.0 and are settable only through the option or the settings REST route: `ms_player_prevent_forward_seek` (clamps forward seeking to the furthest point watched) and `ms_bunny_webhook_url` in Pro.

## JavaScript

The player stack uses vanilla JS for the frontend (no framework dependency):

- `player-wrapper.js` - detects and wraps video elements, owns the platform adapters, the login gate, and session start
- `watermark.js` - renders the watermark canvas overlay and its anti-tamper observer
- `tracker.js` - sends session heartbeats every 30 seconds, and ends the session with `sendBeacon` on unload
- `protection.js` - right-click blocking, the Ctrl+S/Cmd+S guard, source hiding, and devtools detection
- `ad-breaks.js` - in-video ad break engine, enqueued only when a video actually has breaks
- `assets/vendor/hls.min.js` - bundled HLS playback, enqueued only for self-hosted or Bunny sources that need it

The admin SPA is a React application with hash routing. Blocks are built with `@wordpress/scripts`.

## WP-CLI

```
wp mediashield repair bunny-urls [--dry-run] [--execute]
wp mediashield scale seed [--users=<n>] [--sessions-per-user=<n>]
wp mediashield scale benchmark
wp mediashield scale teardown
```

`repair bunny-urls` fixes videos that older versions saved as self-hosted from a Bunny dashboard URL. `scale` seeds and benchmarks a synthetic dataset for hot-path query budgets; it is a development tool, not something to run on production data.

## Testing and CI

The plugin ships a local CI pipeline (`bin/local-ci.sh`) covering PHP lint, WPCS, PHPStan, architecture invariants, end-to-end customer journeys (Playwright), and a scale benchmark for hot-path query budgets. Run `composer ci` for the full gate, `composer ci:quick` for the fast subset, or `composer check` for lint plus WPCS plus PHPStan only.
