# Resource pool integration plan

Approved: user accepted `/marketing-source-pool-preview/` and requested integration. Implement locally, inline, without further design approval. Preserve existing dirty work and generation records; no paid generation, scheduling, publication, commits or deployment.

## Contract
- Keep `sources` IDs and `source_scope` backward compatible. Add per-type `source_modes` (`none`, `all`, `items`); legacy selected IDs derive items mode. Global all overrides modes without erasing retained IDs/modes.
- Products from selected category/collection groups are unioned with direct product ranges. Promotions alone never authorize unrelated catalog products. All modes are dynamic and re-evaluate eligibility for every preview/generation.
- Read-only preview returns counts plus at most3 products/2 entries per other type, never a catalog dump. Show true empty/error/loading states, real catalog images, live promotion summaries/dates, and explicit overlap/availability notes. No results load in picker before keyword.
- Keep unavailable selected IDs visible, do not silently broaden or remove selections. Closing the picker discards uncommitted changes; mode toggles preserve remembered selections.
- Prevent stale preview/search responses from replacing newer scopes; preserve versioned saves and durable no-repeat provider states.

## Tasks
- [x] Test source mode semantics, valid/expired/new resources, preview bounds/access/escaping, save reload and old settings.
- [x] Integrate source service, settings validation, preview endpoint and generation revalidation with normalized modes.
- [x] Integrate approved source rows, picker, preview and summary; preserve source labels and selected-ID/session persistence.
- [x] Run relevant PHP/JS/syntax/Blade checks; inspect desktop/mobile actual UI if browser connection permits. No paid calls.

## Evidence
Baseline branch `codex/social-crm-identity`, HEAD7890952aa49a29f87d9d5cd61cf7ceaba32d1384. Relevant current files read before editing. Existing durable issue ledger: `docs/acceptance/2026-11-05-marketing-real-draft-minimum.md`; previous browser screenshot connection blocker and unrelated JS polling failure retained.
