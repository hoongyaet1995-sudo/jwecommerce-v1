# Shared Section library implementation plan

Goal: expose the approved twelve shared families as twelve additional cards (173 total), retaining the 161 original cards and defaults. Use existing visual diagram controls in Add Section and at the top of Inspector Design. Local acceptance only.

Architecture: one shared family catalogue enumerates supported layouts and creates independent canonical starter sections from existing page factories. Preserve current Passport and renderer dispatch. Connected layout switches retain authored content and layout memory; pricing cards and matrix share one self-owned model when inserted independently. No new section types, migrations, published-page rewrites or deployment.

1. Snapshot touched files and dirty baseline; add failing catalogue/search/ID/matrix tests.
2. Add family catalogue and twelve cards, with a diagram selection step. Preserve old card insertion behavior.
3. Reuse VisualChoiceSelect, with actual layout-specific diagrams; move legacy SectionLayoutGroup above all Design settings and replace connected dropdown. Pricing display belongs in this same top group.
4. Verify mounted picker/Inspector, canonical validation and renderer round trips across all new layouts; run relevant existing regressions, TypeScript and client/SSR build.
5. Verify localhost add/edit/save/refresh and public preview using disposable drafts only; preserve existing page data. Record evidence and unresolved limitations. No push/deploy.
