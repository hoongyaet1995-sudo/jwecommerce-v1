# Shared Section library — local acceptance

Scope: original 161 Add Page Section cards plus 12 shared-family cards, total 173. No new semantic Section types. No GitHub push, Hostinger changes, production publication, homepage/navigation changes, or bulk document rewrite.

The twelve families are Hero, Preview Tabs, Metrics, Sticky Stack, Process, Comparison, Logos, Closing CTA, Cards, FAQ, Story, Pricing. They expose 19 concrete layouts through the existing VisualChoiceSelect diagram controls. Search covers family and layout names. Connected families retain their existing Passport; pricing uses the existing unified pricing contract.

Both connected and legacy Section layout groups appear first in Inspector Design. Pricing's duplicate Content display selector was removed; its visual choice now lives in the same top group. Legacy pricing and logo layout controls are grouped there as well. Existing disclosure preferences remain UI-only. Layout switches retain authored title/items/media; connected layout memory remains unchanged.

Standalone pricing matrices own independent plan IDs, feature groups and cell mappings, rather than referencing another page's pricing. Standalone section action anchors pointing to sibling factory sections are changed to the existing support action; merchant-authored pages are never rewritten.

## Evidence

- 38 focused frontend tests passed: actual picker insertion across twelve families, diagram layout placement/switching, all 19 layout canonical/renderer round trips, all 173 renderer regression, pricing, search and existing diagram behavior.
- Original 161 cards/order/defaultData compared exactly to pre-phase source: passed. Temporary baseline modules removed after retaining evidence.
- TypeScript check passed; full client and SSR build passed (19.34s), with existing asset/chunk warnings.
- SQLite-isolated backend acceptance: 19 layouts create/edit/save/refresh/publish and public runtime payload, 60 assertions passed. An existing page's hash stayed unchanged.
- Chrome actual localhost Builder: created independent draft page 340, title `Local QA — Shared Section Library 20261116`, inserted every family through Add Section; Hero title and matrix plan name edited, Hero layouts switched A/B/A. Preview flush/save, directory reload/re-entry, front preview refresh retained edits. Added old Editorial Quote to confirm legacy diagram placement. Page remains a draft with 13 sections; active homepage unchanged.
- Saved front preview: http://127.0.0.1:8500/admin/pages/340/preview . Eleven connected families plus Pricing rendered; no browser console errors. Mobile preview document client width equals scroll width (380px with scrollbar).
- Screenshots, tests, before snapshots, baseline status and scope-only diff: `output/jw-cms-integration-20261114/section-library-20261116/`.

## Broader test limitations

The pre-existing UnifiedInspector suite was checked against exact pre-phase source copies: 512 passed and 39 failed. These include outdated media-layout set assertions, generic control selectors and an incomplete AdminContext mock. Running its legacy flat-Passport assumptions on twelve newly registered grouped-Passport cards adds incompatible assertions (including expecting generic repeated fields for slot-owned details); dedicated new tests cover those grouped capabilities. These tests still need a separate audit update. Full-suite green is not claimed. One new picker test hit the 5s aggregate timeout under parallel load; splitting it into twelve independent per-family cases resolved it without raising the timeout. No unrelated product repairs were included.

Initial test-only mistakes (missing mock pages, checking raw HTML instead of decorated DOM text, shadowing DOM document, assuming metrics-row renders a section heading) were corrected and recorded in the phase ledger. The generated AI copy manifest stayed byte-identical to its pre-phase snapshot; no AI capability expansion was included. Elapsed/token usage is unavailable except measured test/build durations.

Follow-up: the 39 baseline failures and 19 newly exposed cases were audited and closed locally in docs/acceptance/2026-11-16-section-tests-173-local.md. That follow-up supersedes the earlier unresolved-test status above; historical evidence is retained.
