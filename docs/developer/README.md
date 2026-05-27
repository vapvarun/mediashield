# MediaShield Developer Docs

These docs are for developers extending or integrating MediaShield. If you're using MediaShield as a site owner, you want [`docs/free/`](../free/getting-started.md) and [`docs/pro/`](../pro/getting-started.md).

## What's here

| Document | Purpose |
|----------|---------|
| [hooks-filters-free.md](hooks-filters-free.md) | Every action and filter in the free plugin — params, examples, priority notes |
| [hooks-filters-pro.md](hooks-filters-pro.md) | Pro-specific hooks and the free hooks Pro subscribes to |
| [settings-reference.md](settings-reference.md) | All `ms_*` option keys, types, defaults, validators, grouped by feature area |
| [rest-api.md](rest-api.md) | All REST endpoints for both `mediashield/v1` and `mediashield-pro/v1` namespaces |
| [database-tables.md](database-tables.md) | Schema for all 14 tables (6 free + 8 Pro) with columns, indexes, and retention behavior |
| [post-meta-reference.md](post-meta-reference.md) | Every `_ms_*` post meta key for the `mediashield_video` and `mediashield_playlist` CPTs |
| [extension-architecture.md](extension-architecture.md) | How to extend MediaShield: filter chains, SlotFill, LMS adapters, Pro/Free contract |
| [cron-and-background-jobs.md](cron-and-background-jobs.md) | Every cron hook and Action Scheduler job — name, interval, what it does, how to debug |
| [drm-internals.md](drm-internals.md) | How DRM key generation and packaging work internally |
