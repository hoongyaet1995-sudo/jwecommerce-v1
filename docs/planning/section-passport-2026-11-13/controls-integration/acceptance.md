# Unified Page / Section controls — local acceptance

Date: 2026-11-13. Local only; no commit, push or deployment.

## Coverage
- Mounted all 161 picker sections and 2 additional layouts found in saved pages (163 variants, 35 types).
- Before/after coverage report: Content and Advanced controls unchanged, no gaps; dedicated layout and image controls retained.
- Old database inventory read-only: 65 page documents / 425 sections / 105 saved variants before dedicated QA page creation.
- 483 untouched legacy resolver cases match the captured pre-integration outputs.
- Eyebrow ordering verified in page/light/contrast, default and heading-enabled states (978 combinations). Empty Eyebrow remains hidden; heading-free sections retain exemptions.

## Ownership
- Page Settings owns the single page palette source, shared typography, default buttons, spacing, content width and search visibility.
- Section Design owns surface, local typography and button overrides, image appearance, dedicated layout, spacing and motion.
- Shared fields expose following/custom state, property and role resets, and shared-section reset. Reset preserves content, items, media, actions and layout.
- Legacy values remain stored. Explicit surface:page opts into page colors; missing surface retains legacy rendering. New button styling is opt-in; absent old flag preserves previous behavior.

## Verification
- Latest focused suite: 9 files / 32 tests passed (final-controls.log).
- Final palette-thumbnail-only refinement: 2 files / 5 tests passed (preview-card-final.log).
- PHP CMS suite: 95 tests / 486 assertions passed (php-cms-final.log).
- Canonical new-field round-trip contract: passed (php-contract-final.log).
- Final production build passed; existing large-chunk warning remains.
- Full TypeScript lint is not green: existing repository diagnostics remain, recorded in typecheck.log. Do not treat build success as full lint success.

## Browser acceptance
Dedicated draft: Local QA — Unified Style Controls, page 74. User page 73 untouched.
- Global heading size/weight propagation, local size/color persistence through page palette edits, role reset and shared-section reset verified.
- Button local radius6px/min-height52px; reset uses page0px/36px; authored primary background#123456 survives save/reload.
- Final explicit Follow page saved through canonical server preview: surface=page, background rgb(246,238,230); changing page palette to Clean modern changes background to rgb(255,255,255) while local button6px/52px/#123456 remains.
- Builder exit/reload/reopen and server preview verified. Preview flushes auto-save; browser assertions await state updates.
- Mobile customer viewport380 has scrollWidth380; Page Settings fits editor viewport390; temporary overrides restored.
- Screenshots: page-settings.jpg, section-inspector.jpg, mobile-preview.jpg, mobile-page-settings.jpg. Browser observations also in browser-evidence.json (earlier state before final explicit page test).

## Scope and evidence limits
Existing unrelated dirty work retained. No database migration of old pages, no release actions, no paid AI calls. Full-render browser acceptance uses the dedicated draft; all163 layouts are mounted/scanned and renderer-tested, not individually edited and saved in the browser. Elapsed time and token cost unavailable. Previous issue counts remain in ../checkpoint.md.
