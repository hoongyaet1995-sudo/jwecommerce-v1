# Text toolbar scoped release — 2026-11-18

Approved targets: www and ecom. Baseline: 9e64c7bda067889bdbefd667a0788d4fc30ccf88.

Adds the approved compact right-click text toolbar to the existing Page Builder. Typography and alignment reuse canonical Passport fields; Emphasis reuses selected title ranges. Inspector and toolbar share state and draft persistence. No migrations, environment changes, page normalization, template/default changes or content publication.

Existing tracked changes: PageAdmin toolbar adapter, SectionInspector selected-role/rendered-value feedback, TypographySettings read-only rendered-value display, and SectionRenderer native text-selection guard only. Unreleased statistic-counter work and all sample applications are excluded.

Shared new owners: cms/textToolbar, FloatingTextToolbar and BuilderTextToolbar, with component tests. Existing role boundaries, fonts, colors, layouts and saves remain owned by Passport.

Local verification: 7 component tests; TypeScript noEmit; clean isolated admin/public/SSR build; 173 starter canonical save/reload/runtime test (349 assertions). Existing large-chunk and sample-font resolution warnings retained. Isolated PHP test bootstrap remapped shared Composer App/Tests namespaces to the release worktree; direct test file avoids unrelated suite discovery collisions.

Deployment: exact GitHub main commit via jw-hostinger; fast-forward pull, cache clear, guarded public asset installation. Retain old hashed assets for active sessions. Pre/post deployment fingerprints cover pages, sections/documents, menus/items, store/shell settings and environment hash. Authenticated live acceptance uses new unpublished QA drafts only; no existing page is edited or set as home.

Evidence: canonical output/text-toolbar-sample-20261118/formal-acceptance.md and release/ logs/screenshots. Live outcome and exact commit are reported after deployment, not assumed from local tests.
