# Content Width and legacy Marketplace CTA — local acceptance

Scope: canonical development root only. No commit, push, deployment or production data writes. Existing unrelated changes remain untouched.

## Root causes and fixes

- Unified Header/Footer CSS hardcoded 1440px and Builder canvas omitted website width variables. Both now consume the shared width/gutter variables. Standard=1280px, Wide=1440px, Full=100%. Page defaults and explicit page/section overrides remain authoritative. Header/Footer outer padding no longer doubles the inner gutter.
- PHP geometry validation omitted `illustrated-panel-right` from the foreground CTA artwork exceptions. Added only that supported preset. Background-image geometry restrictions remain intact.
- Clarified Content Width helper copy to describe inheritance and retained overrides.

## Verification

Evidence: `output/jw-cms-integration-20261114/width-cta-fix-20261115/`.

- Created independent local drafts 135–151 from the 17 source documents; original content retained. Canonical save/refresh round-trip of Standard/Wide/Full/Follow website and first-section Full bleed succeeded for all 17; restored exact baseline hashes.
- Actual browser frontend checked all 17 pages / 123 section containers at website Standard: headers and footers 1280px; six Follow website pages inherited 1280px; three implicit Standard pages remained 1280px; eight explicit Full pages retained 100%. No frame mismatches.
- Website Full saved through real settings UI: Follow website body and shell became 100%; explicit Standard stayed 1280px. Builder Follow website canvas and shell also read 100%. Website Wide restored through UI: frontend shell and Follow website body read 1440px.
- Actual Marketplace draft 143 Inspector changed ratio=1:1, width=75%, shape=Rounded. Reloaded Inspector retained all three selections; refreshed frontend rendered aspect-ratio 1/1, width 75%, border-radius 24px. Initial observation before autosave completion showed prior radius; subsequent fresh preview confirmed persisted result.
- Representative old Home, Marketplace and new Home at actual CDP mobile viewport 390px: document width=scrollWidth=390. Cleared temporary metrics afterward. Browser-wide viewport capability had not changed the tab dimensions, so those earlier 1526px observations were excluded from mobile evidence.
- PHP: 8 tests / 24 assertions passed, including save/runtime geometry and negative background-geometry coverage. Frontend: 8 tests / 4 suites passed. TypeScript passed; client/SSR build passed (existing bundle-size warning only). Scoped diff whitespace check passed.
- `restoration.json`: all 17 QA documents restored; original rows in pages, page_documents, menus, menu_items, store_settings and storefront_shell_settings unchanged byte-for-byte. Global shell settings restored to original Wide, including original row metadata. Homepage and navigation unchanged.

## Local review

- Marketplace: http://127.0.0.1:8500/admin/pages/143/preview#section-marketplace-s08-closing
- New Home: http://127.0.0.1:8500/admin/pages/144/preview
- Inspector: http://127.0.0.1:8500/admin/pages (search `local-qa-width-cta-20261115`)

This verifies the two requested fixes across the 17 page documents. It does not claim every unrelated Inspector control has passed a new exhaustive audit. Other previously listed audit issues remain outside this repair scope.
