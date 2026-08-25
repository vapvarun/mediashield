# MediaShield Developer Docs

These docs are for developers extending or integrating MediaShield. If you're using MediaShield as a site owner, you want [`docs/free/`](../free/getting-started.md) and [`docs/pro/`](../pro/getting-started.md).

## What's here

| Document | Purpose |
|----------|---------|
| [hooks-filters-free.md](hooks-filters-free.md) | Every action and filter in the free plugin - params, examples, priority notes, plus the DOM events the player dispatches |
| [hooks-filters-pro.md](hooks-filters-pro.md) | Pro-specific hooks and the free hooks Pro subscribes to |
| [settings-reference.md](settings-reference.md) | Every free `ms_*` option key from `Settings::schema()` - type, default, validator - grouped by feature area. Pro's options are listed in hooks-filters-pro.md. |
| [rest-api.md](rest-api.md) | All REST endpoints for both `mediashield/v1` and `mediashield-pro/v1` namespaces |
| [database-tables.md](database-tables.md) | Schema for all 13 tables (6 free + 7 Pro) with columns, indexes, and retention behavior |
| [post-meta-reference.md](post-meta-reference.md) | Every `_ms_*` post meta key for the `mediashield_video` and `mediashield_playlist` CPTs |
| [extension-architecture.md](extension-architecture.md) | How to extend MediaShield: filter chains, admin SPA routes, SlotFill, LMS adapters, upload drivers, Pro/Free boot order |
| [cron-and-background-jobs.md](cron-and-background-jobs.md) | Every cron hook and Action Scheduler job - name, interval, what it does, how to debug - plus the WP-CLI maintenance commands |
| [drm-internals.md](drm-internals.md) | How DRM key generation, packaging and license serving work internally, and which parts are not yet wired up |

All of the above is written against **1.3.0**. Cited file paths, class names and line numbers are from that tag.
