# Category / Collection full-page editors — 2026-11-02

Local scope only. Baseline 2d215a2b, unrelated work preserved.

- Existing modal form contents, fields and original Tailwind CSS reused. Removed overlay, width/max-height constraints; normal page scroll.
- Top-left breadcrumb, outlined Back button and existing Cancel.
- URL category/collection query selects existing entity or new editor. Hydrates from existing catalog API after refresh. Browser popstate restores editor identity.
- Save uses unchanged saveCategory/saveCollection and stays on the saved entity URL. No backend contract changes.
- npm run build (resources/admin): pass, Inspector copy parity pass; existing sample asset and bundle warnings remain.
- ProductPageEditor.test.tsx: 3 passed, including SSR category/collection list rendering without window.
- Normal logged-in browser: category 98 and collection 55 opened through list action menus; each retained same entity on reload; original fields and collection manual picker visible. Same-value save retained editor. Back returned to list. New collection refreshed at collection=new without returning to list. Unsaved field edits are not promised to survive refresh.
- Screenshots: tmp/category-page-acceptance.png and tmp/collection-page-acceptance.png.
- Editing script initially placed early return inside effect cleanup; inspection caught and moved it before component's list return before build; no failed test/build run for that temporary edit.
- No commit, push, deployment or paid API. No new category/collection created. Elapsed/token usage unavailable.
