# Developer Guide Overview

This section is for developers building integrations, add-ons, or custom code on top of MediaShield.

**Free + Pro** - This section documents the Free plugin and the seams Pro and third-party add-ons use.

Version 1.4.0. Requires PHP 8.1 and WordPress 6.5.

## What the developer guide covers

- [Hooks and Filters](02-hooks-and-filters.md) - the 15 actions and 35 filters the free plugin fires, with parameters and examples
- [REST API](03-rest-api.md) - the 25 routes under `mediashield/v1`, permissions, arguments, and the core `wp/v2` routes for the two post types
- [Database Tables](04-database-tables.md) - the 6 free plugin tables, their columns, indexes, and cleanup behavior
- [Extension Architecture](05-extension-architecture.md) - how to build an add-on: boot order, the access decision, upload drivers, settings, SlotFill, signed embeds
- [Post Meta and Options Reference](06-post-meta-and-options.md) - every `_ms_*` meta key, user meta, and `ms_*` option with type and default
- [Background Jobs and WP-CLI](07-background-jobs-and-wp-cli.md) - scheduled jobs, the upgrade jobs, and the `wp mediashield` commands
- [JavaScript Events and Player Config](08-javascript-events-and-config.md) - the browser events and the `mediashieldConfig` payload

## Architecture summary

MediaShield uses a singleton bootstrap pattern. The entry point is `mediashield.php`, which loads the Composer autoloader and, on `plugins_loaded`, runs `Migrator::run()` and then `Plugin::instance()`. The plugin fires `mediashield_loaded` when all internal hooks are registered. Free boots on `plugins_loaded` at the default priority 10, so an add-on should boot on `plugins_loaded` at priority 20 or later (Pro does exactly this) or check `did_action( 'mediashield_loaded' )`; hooking `mediashield_loaded` itself only works if your code is registered before Free boots.

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
| `Integrations\Learnomy` | Adds a playable `mediashield` block to Learnomy lesson REST payloads |
| `Integrations\AdManagerBridge` | Supplies in-video ad breaks from WB Ad Manager video creatives |
| `Admin\HealthCheck` | Site Health test for direct download of stored video files |

## Custom Post Types

MediaShield registers two CPTs. Neither has its own admin menu item (`show_in_menu` is false); both are reached from the admin SPA.

- `mediashield_video` - individual protected videos. `public` false, `show_ui` true. REST base: `mediashield-videos`. Supports title, thumbnail, custom fields.
- `mediashield_playlist` - ordered groups of videos. `public` true. REST base: `mediashield-playlists`. Supports title, editor, thumbnail.

Every meta key is listed in [Post Meta and Options Reference](06-post-meta-and-options.md).

## Settings

All free-plugin options are defined in `Core\Settings::schema()`, which carries each option's type, default, and optional validator. Adding a new option requires updating that schema, bumping `MEDIASHIELD_DB_VERSION` (the migrator re-seeds defaults on a version bump, so existing installs pick the option up), and, if the value needs to reach the browser, referencing it in `Settings::frontend_config()`.

Uninstall derives the list of options to delete from the same schema, so an option added there is cleaned up automatically.

The schema holds 35 options. A few more options exist outside the schema (state flags and Pro's upload target); they are listed in the [reference](06-post-meta-and-options.md).

## JavaScript

The player stack uses vanilla JS for the frontend (no framework dependency):

- `player-wrapper.js` - detects and wraps video elements, owns the platform adapters, the login gate, and session start
- `watermark.js` - renders the watermark canvas overlay and its anti-tamper observer
- `tracker.js` - sends session heartbeats every 30 seconds, and ends the session with `sendBeacon` on unload
- `protection.js` - right-click blocking, the Ctrl+S/Cmd+S guard, source hiding, and devtools detection
- `ad-breaks.js` - in-video ad break engine, enqueued only when a video actually has breaks
- `assets/vendor/hls.min.js` - bundled hls.js 1.5.20, enqueued only for self-hosted or Bunny sources that need it

The events these scripts dispatch are in [JavaScript Events and Player Config](08-javascript-events-and-config.md).

The admin SPA is a React application with hash routing. Blocks are built with `@wordpress/scripts`.

## WP-CLI

Two command groups, `wp mediashield repair` and `wp mediashield scale`. See [Background Jobs and WP-CLI](07-background-jobs-and-wp-cli.md).

## Testing and CI

The plugin ships a local CI pipeline (`bin/local-ci.sh`) covering PHP lint, WPCS, PHPStan, architecture invariants, end-to-end customer journeys (Playwright), and a scale benchmark for hot-path query budgets. Run `composer ci` for the full gate, `composer ci:quick` for the fast subset, or `composer check` for lint plus WPCS plus PHPStan only.
