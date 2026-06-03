# Developer Guide Overview

This section is for developers building integrations, add-ons, or custom code on top of MediaShield.

Version 1.1.0. Requires PHP 8.1 and WordPress 6.5.

## What the developer guide covers

- [Hooks and Filters](02-hooks-and-filters.md) - every PHP action and filter the free plugin exposes, with parameters and examples
- [REST API](03-rest-api.md) - all endpoints under `mediashield/v1`, authentication, request and response formats
- [Database Tables](04-database-tables.md) - the 6 free plugin tables, their columns, indexes, and cleanup behavior
- [Extension Architecture](05-extension-architecture.md) - how to build an add-on, the filter chain order, upload driver contract, and how Pro extends the free plugin

## Architecture summary

MediaShield uses a singleton bootstrap pattern. The entry point is `mediashield.php`, which loads the Composer autoloader and calls `Plugin::instance()` on `plugins_loaded`. The plugin fires `mediashield_loaded` when all internal hooks are registered - this is the correct hook for add-ons to initialize.

The PHP namespace is `MediaShield\`. Classes are PSR-4 autoloaded from `includes/`.

Key classes:

| Class | Role |
|-------|------|
| `Core\Plugin` | Singleton entry point, registers all hooks |
| `Core\Settings` | Single source of truth for all free-plugin options |
| `Access\AccessControl` | Runs the `mediashield_can_watch` filter chain |
| `Access\SessionManager` | HMAC token generation and concurrent stream enforcement |
| `Milestones\MilestoneTracker` | Detects 25/50/75/100% completion, fires milestone actions |
| `Upload\UploadManager` | Upload driver registry via `mediashield_upload_drivers` filter |
| `Player\Renderer` | Shared single-video player output (shortcode, block, single template) |
| `Player\PlayerWrapper` | Output buffer scan for video elements |

## Custom Post Types

MediaShield registers two CPTs:

- `mediashield_video` - individual protected videos. REST base: `mediashield-videos`.
- `mediashield_playlist` - ordered groups of videos. REST base: `mediashield-playlists`.

Both CPTs support custom fields. The meta keys are documented in the full developer reference (`docs/developer/post-meta-reference.md` in the plugin repository).

## Settings

All free-plugin options are defined in `Core\Settings::schema()`. Adding a new option requires updating that schema, bumping the DB version constant, and (if the value needs to reach the browser) referencing it in `Settings::frontend_config()`.

## JavaScript

The player stack uses vanilla JS for the frontend (no framework dependency):

- `player-wrapper.js` - detects and wraps video elements with the protection container
- `watermark.js` - renders the watermark canvas overlay
- `tracker.js` - sends session heartbeats every 30 seconds
- `protection.js` - handles right-click blocking, keyboard blocking, and devtools detection

The admin SPA is a React application with hash routing. Blocks are built with `@wordpress/scripts`.

## Testing and CI

The plugin ships a local CI pipeline (`bin/local-ci.sh`) covering PHP lint, WPCS, PHPStan, architecture invariants, end-to-end customer journeys (Playwright), and a scale benchmark for hot-path query budgets. Run `composer ci` for the full gate.
