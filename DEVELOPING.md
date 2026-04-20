# Developing MediaShield — Local-Only Workflow

All quality gates run locally. No GitHub Actions. You are the CI.

## Setup (one-time)

```bash
# Install PHP dev dependencies (phpcs, phpstan, wp-coding-standards).
composer install

# Install JS dependencies for the admin SPA build.
npm install
```

## Running checks

One command runs the full local gate:

```bash
composer check
```

That runs these three in order; any failure aborts the chain:

| Step | Command | What it catches |
|---|---|---|
| PHP Lint | `composer lint` | Syntax errors across `includes/**/*.php` |
| WPCS | `composer phpcs` | WordPress Coding Standards (errors block, warnings pass) |
| PHPStan | `composer phpstan` | Static analysis at level 5 with WordPress stubs |

Run individually when you know which gate you need:

```bash
composer lint     # fastest — PHP parse errors only
composer phpcs    # WPCS check against phpcs.xml
composer phpstan  # type analysis against phpstan.neon
composer phpcbf   # auto-fix fixable WPCS issues
```

## Running the admin build

```bash
npm run build     # Production bundle to build/
npm run start     # Dev watch mode (rebuilds on save)
```

## Running the plugin

MediaShield runs on any standard WordPress install. For development:

- **Local by Flywheel** — works out of the box, drop the plugin into `wp-content/plugins/`
- **wp-env** — `npx wp-env start` with `.wp-env.json` pointing at this directory

Auto-login helper: this site ships with a mu-plugin at `wp-content/mu-plugins/dev-auto-login.php` that accepts `?autologin=1` on any URL to auto-login as user ID 1. Never ship this to production.

## Running functional flow tests

The end-to-end flow test script lives at `/tmp/ms-flow-tests.php` (generated during release prep). It exercises Settings REST, DevTools detection, VPN detection, session lifecycle, milestone + tag, access control, shortcode, Weekly Digest SQL, multi-IP suspicious detection, GDPR, and concurrent stream limit.

```bash
wp eval-file /tmp/ms-flow-tests.php
```

Expected output: `All functional flow tests PASSED` with `pass | 0 fail | 0 skip`.

## Pre-push checklist

Before opening a PR or pushing to main:

- [ ] `composer check` passes
- [ ] `npm run build` completes with zero errors (warnings about bundle size are OK)
- [ ] Relevant flow tests pass if you touched session/milestone/access code
- [ ] Manual browser test of any admin page you changed
- [ ] Readme/doc updates if a feature claim changed
- [ ] `.pot` regenerated if you added translatable strings: `wp i18n make-pot . languages/mediashield.pot --slug=mediashield --domain=mediashield --skip-audit`

## Why local-only CI

- Faster feedback loop (no push → wait → fail → fix → push again)
- No workflow minutes cost
- Checks run in the same environment as production (your server matches the contributor's machine, roughly)
- Forces discipline: you can't "just push and see" — you own the gate

If you want a git hook to enforce `composer check` on every commit, drop this into `.git/hooks/pre-push`:

```bash
#!/usr/bin/env bash
set -e
composer check
```

`chmod +x .git/hooks/pre-push` and you're covered.

## Release checklist

See `plan/QA_RELEASE_PROSPECT.md` for the full release-day walkthrough. Short version:

1. `composer check` passes
2. `npm run build` produces a fresh bundle
3. Regenerate `.pot` file
4. Bump version in all five places (plugin header, `MEDIASHIELD_VERSION`, `readme.txt` Stable tag, `composer.json`, `package.json`)
5. Update `readme.txt` changelog
6. Create dist zip excluding `node_modules/`, `plan/`, `tests/`, `.git*`, `*.md` except `readme.txt`
7. `git tag v1.0.0 && git push origin v1.0.0`
