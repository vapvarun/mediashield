# wppqa baseline — mediashield (free)

**Date:** 2026-05-11
**Plugin:** mediashield 1.1.0
**Run by:** wp-plugin-onboard skill, Phase 0

## Per-check results

| Check | Passed | Failed | Skipped |
|---|---:|---:|---:|
| `plugin-dev-rules` | 8 | **1** | 0 |
| `rest-js-contract` | 22 | 0 | 0 |
| `wiring-completeness` | — | — | 1 (no `templates/` dir) |

## Findings

### HIGH (1) — must fix before release

1. **Nonce check without capability check** — `includes/Admin/Menu.php:174`
   - Code: `nonce-no-cap`
   - Nonces prevent CSRF but do NOT authorize. Pair with `current_user_can()`.

### MEDIUM (2) — fix this sprint

2. **Inline `onclick` attribute** — `includes/CPT/VideoPostType.php:382`
3. **Inline `onclick` attribute** — `includes/CPT/VideoPostType.php:396`
   - Inline handlers fight Interactivity API + event delegation + CSP. Replace with `data-wp-on--click` or addEventListener.

### LOW (2) — backlog

4. **Tap-target 32px < 40px** — `assets/css/player.css:179`
5. **Tap-target 18px < 40px** — `assets/css/player.css:202`
   - Touch-device a11y. Bump button height to 40px minimum.

## Release-readiness verdict

🚫 **NOT release-ready.** Phase 0 hard rule: any `failed > 0` blocks release. The single HIGH (nonce-no-cap) needs a fix or explicit cap addition before tagging 1.1.0.

## Notes for the manifest

- The nonce-no-cap finding belongs in `static_analysis.cap_drift` (Phase 2.5.3a) under "enforced_but_undeclared" if `Menu.php:174` checks a cap that no `add_cap` registers — verify during Phase 2.
- Inline-onclick findings should surface in `static_analysis.js_only_activation` adjacent context — these are a fragility class even if they're not strictly that detector.
- No `templates/` directory means wiring-completeness gate is permanently skipped on this plugin. Consider whether the player's frontend rendering should move templates out of the CPT class for reusability.
