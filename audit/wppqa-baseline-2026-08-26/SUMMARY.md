# wppqa baseline — MediaShield (free) — 2026-08-26

Run as Phase 0 of `/wp-plugin-onboard --refresh`, before the manifest was regenerated.
`wppqa_audit_plugin` on branch `1.3.0`.

## Headline

| Metric | Value |
|---|---:|
| Code-quality score | 72 |
| Tests passed | 176 |
| Tests failed | 44 |
| Tests skipped | 103 |
| Reported critical | 26 |
| Reported high | 49 |
| Reported medium | 166 |

**Read the reported severities with care — see the classification below. The 21
security-scan "criticals" are all false positives, verified individually.**

## Real vs false positive

### security-scan — 21 failures, 0 real

Every finding is the same shape: `$wpdb->query(` or `$wpdb->get_var(` without a
`prepare()` on the same line. The scanner matches the *call shape*, not whether
there is anything to prepare. Spot-checked six of them across every distinct
pattern, and generalised from there:

| Location | What is actually there | Verdict |
|---|---|---|
| `Access/SessionManager.php:77,108,121,188,195` | `$wpdb->query( 'START TRANSACTION' )` and friends — a literal string with no variables | false positive |
| `DB/Schema.php:139`, `uninstall.php:34` | `DROP TABLE IF EXISTS {$wpdb->prefix}{$table}` where `$table` comes from a hardcoded array in the same file. Table identifiers cannot be passed through `prepare()` at all | false positive |
| `CPT/VideoPostType.php:228` | `SELECT platform FROM {$table} WHERE is_active = 1` — static query, table name from `$wpdb->prefix` | false positive |
| `Cron/Cleanup.php:463,532,545,561,566` | transaction statements and hardcoded-identifier DDL | false positive |
| `src/CLI/ScaleCommand.php:120,121,263,266,415-417` | table names from a hardcoded array; the only interpolated value is `$base = self::BASE_UID`, a class constant. WP-CLI only | false positive |

No user-controlled value reaches any flagged query. Every one already carries an
explanatory `phpcs:ignore` naming the reason.

This is the same failure mode as the Shaka packager card (BC#10235380593): a
scanner matching call shape rather than data flow. Worth remembering the next
time this number is quoted at us.

### enum-consistency — 1 failure, REAL

`platform` enum drift. Four files disagree on the allowed values:

- `CPT/Thumbnail.php:209` — `[bunny, vimeo, wistia, youtube]` (no `self`)
- `Player/PlayerWrapper.php:269` — `[bunny, self, vimeo, wistia, youtube]`
- `Player/Renderer.php:207` — `[bunny, self]`

Not carded yet. The fix is the same shape as the protection-level fix already
made in 1.3.0: extract one canonical list and have every caller read it.

### rest-js-contract — 22 passed, 0 failed

Clean. Worth stating explicitly, because this is the check that catches the
silent blank-state bug class, and it is the one the plugin most recently had
trouble with.

### wiring-completeness — 0 issues

Clean.

### Lower-value / noisy categories

- **phpcs (98 skipped, score 0)** — the runner could not invoke phpcs. It passes
  when run directly: `vendor/bin/phpcs --standard=phpcs.xml` reports 0 errors.
- **composer-audit (3 failures)** — advisories against dev-only dependencies.
- **customerExpectations** — detected the plugin's category as "LMS" at 34%
  confidence, matching on the LearnDash/Tutor/LifterLMS *integration* code.
  MediaShield is a video-protection plugin; the resulting 43% "completeness"
  score is measured against LMS features it was never meant to have. Ignore it.
- **ux (3 low)** — non-dismissible admin notices, one of which is in the bundled
  EDD SDK, not our code.

## Also found during this pass (not a wppqa finding)

An untracked stray file `includes/Player/PlayerWrapper 2.php` — a Finder-style
duplicate of an older PlayerWrapper. Not in git, so it never shipped, and the
autoloader could not have loaded it (the filename does not map to a class), but
it polluted every scan. Moved out of the tree. Four equivalents were found and
removed under `mediashield-pro/build/`.

## Action taken

- Enum drift: recorded here, needs a card.
- Everything else: no change — either false positive, dev-only, or a
  misdetected category.
