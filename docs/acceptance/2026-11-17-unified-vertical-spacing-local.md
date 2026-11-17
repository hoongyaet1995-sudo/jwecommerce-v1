# Unified Vertical Spacing — local acceptance 2026-11-17

Scope: existing Passport and SectionLayoutControls; all 173 registered cards expose Follow page spacing / Compact / Normal / Spacious. No new setting fields, no migrations, no GitHub synchronization or deployment.

## Repairs
- Removed the old spacing=false capability gate for 25 cards while retaining layout-specific alignment choices.
- Shared sectionSpacingStyle binds ignored paddingY to the existing outer roots/content layers. No added DOM wrappers, text/media/CTA/item restructuring, typography or palette changes.
- Actual mobile measurements additionally found 41 existing layouts whose clamp minimum made all choices identical. Opt-in shared CSS targets exact outer roots only, overriding their mobile rules, including six !important wide/split rules. Internal card/media padding remains unchanged.
- Corporate Split Hero required a real root binding despite differing SSR variables.
- Unified Pricing previously responded only at the bottom edge; explicit spacing now affects the top too.
- resolveStyledSection remains authoritative for page inheritance; explicit section paddingY wins, Follow page spacing removes only that field. No duplicate setting/control.

## Preservation boundary
Existing merchant documents were not rewritten. Missing section and page spacing leaves original native padding untouched (test asserts absence of forced spacing attribute/variable). Existing explicit paddingY values that were previously ignored now take effect; their saved values remain unchanged. This is the requested behavior repair, not a data migration. Do not claim pixel identity for previously ignored explicit values.

## Verification
- Mounted all 173 actual Inspectors; clicked all three options and Follow reset, verified exact emitted objects and retained unrelated fields.
- Five focused tests passed, plus final strengthened no-field/page-inheritance renderer test passed. TypeScript noEmit and Inspector contract/client/SSR builds passed.
- Real Laravel draft API: all 173 sections create/edit/save/read/runtime round trips, including reset and retained old fields; 43 deep assertions, isolated in-memory SQLite. No production/local merchant DB reset.
- Real browser CSS measurement: 173 x 3 x 1280/390 = 1,038 states. Top and bottom independently have three distinct padding signatures for every card at both sizes; zero remaining failures. Full renderer HTML with current production CSS was measured, not merely SSR string changes. This is geometry regression, not a claim of manual review of every screenshot.
- Authenticated local Builder QA page347: Connected Pricing added; Spacious saved, exited, refreshed/reopened (pressed=true). Follow page spacing saved/refreshed (pressed=true), Page Settings Compact inherited. Explicit Spacious overrides Page Compact after refresh. Real draft frontend computed pricing top/bottom160px desktop/mobile; screenshots retained. Viewports restored.
- 148 non-QA page documents fingerprint-identical across local browser acceptance. QA347 is a new private draft, not home and not published.

## Evidence / known diagnostics
`output/spacing-audit-20261117/`: inventory.json, api-reloaded.json, render-states.json, desktop-geometry-final.json, mobile-geometry-final.json, final-summary.json, inspector-spacious.png, front-desktop.png, front-mobile.png. Local static measurement pages are diagnostic only, never release artifacts.
Known geometry issue history retained: 1 Corporate split, 2 mobile clamp41, 3 remaining non-Corporate roots13, 4 !important roots6, 5 pricing top-only gap; resolved with source/DOM evidence, no blind retries. Harness cwd artifact-path and Python encoding transport errors documented separately. Initial expected TDD red25 verified. Elapsed/token accounting unavailable. Build has pre-existing sample asset/font and chunk-size warnings.

Local review: http://127.0.0.1:8500/admin/pages (search Local QA — Vertical Spacing 20261117); frontend http://127.0.0.1:8500/admin/pages/347/preview.
