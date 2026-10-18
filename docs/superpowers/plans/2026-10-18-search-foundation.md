# Search Foundation Implementation Plan

User approved execution inline2026-10-18. Goal: shared automatic SEO/AI foundation and accurate actual storefront output, including legacy regeneration. Specs: acceptance/2026-10-18-search-foundation-p0-{design,v2-spec,v3-catalog}. Primary root only; no deployment until explicit sync. Baseline848d89e; unrelated inventory/CRLF files preserved.

Architecture: shared Search settings/entity metadata resolver, durable revision-bound AI tasks/audit, owner-protected APIs, shared Admin controls and storefront head. Canonical CMS render uses shared React renderer via prebuilt server bundle; do not duplicate content into an SEO-only renderer. Metadata and discovery computed from current public eligibility; no arbitrary remote fetch. Inline execution, no agents.

- [ ] Task1: migrations/settings/base metadata/AI contract and tests. Files app/Services/Search/*, app/Models/Search*, database/migrations/2026_10_18_06*, tests/Feature/SearchFoundationTest.php. Initial TDD red declares missing metadata resolver; test private boundaries, overrides and failed/stale tasks. Migration no generation or index-policy changes.
- [ ] Task2: protected APIs/jobs/model-save producers/AI Connect Search consumer. Durable tasks, bounded retry/quota, atomic revision checks and legacy snapshots. No use of Support permission.
- [ ] Task3: shared storefront metadata, canonical/sitemap/robots/index middleware and redirect lifecycle. Tests all entity types, private/noindex and published-vs-draft behavior; don't canonicalise pagination to page1.
- [ ] Task4: shared SSR entry/process/cache and source-faithful takeover; prove main text in initialHTML, JS/browser parity; honest503 on render failure.
- [ ] Task5: integrate approved site/CMS/Product/Category/Collection controls, legacy scoped batch and before/after views, publication checks. Mounted API/advanced-state tests and build.
- [ ] Task6: real selected-provider bounded existing-catalog regeneration. Read-only count first; source-fact checks, failed-only recovery, actualusage, front HTML; no secret logs. Screenshot/real browser gate when available; disabled browser explicitly remains open gate, never claim complete visual QA.

Review focus: stale saves, global mode off during task, human override midflight, private/draft content in caches, dummySEO removed without inventing facts, provider failures cannot erase prior output, publication late completion cannot publishCMS, batch scopes don't grow during execution. Meaningful tests pin each boundary; no unchanged reruns. Record every test run in phase ledger; unexpected issues3/6/9 escalation. No frequent commits contrary to user local-phase cadence.
