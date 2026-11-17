# Header glass and shrink scoped release — 2026-11-17

Approved target: GitHub main and www only. ecom/social are not deployment targets. No data, settings, menus, pages, schema or configuration writes. Footer convergence remains a later discussion.

Source allowlist: public/css/cms-shell-design.css, tests/javascript/header-scroll-state.test.cjs, www-only install-header-glass-public-20261117.php, this acceptance record and rebuilt asset references. The canonical working tree remains dirty; release starts from origin/main82943066. Explicitly excludes the unrelated local Privacy choices CSS rule and statistic-counter feature. Header scroll JS stays byte-identical to main, preserving64/8 anti-anchoring thresholds.

Behavior: normal top opaque page-paper background; scrolled88% palette paper with16px backdrop blur and transparent inner row. Outer padding and shadow animate0.3s,92→72px. Reduced-motion disables transition. Existing transparent/translucent overlay-at-top behavior, user palette, content-width, menus and logos remain unchanged.

Local evidence: output/header-sample-parity-20261117. Real desktop/browser shrink trace, top/scrolled computed background and blur,390px mobile no-overflow/menu checks. Node header-scroll-state4/4 passed. Isolated frontend/SSR build and installerPHPsyntax passed. Old hashed assets retained for already-open sessions. Release evidence/target SHA and before-after protected-table/env fingerprints are recorded under output/header-glass-release-20261117 after deployment; no unverified production-completion claim in this pre-release record.
