# Category hierarchy acceptance
Baseline HEAD: 2d215a2b (verify git output); existing CategoryAdmin, CatalogApiController and banner work already dirty, preserved.
Scope: approved hierarchy and product-link sample, localhost only.

## Issue ledger
2026-11-02: planned initial TDD red for new hierarchy/link endpoints; intentional failures excluded from unexpected issue count. Command: php artisan test --compact tests/Feature/Admin/CategoryHierarchyTest.php. Purpose: depth/cycle, product paging/link/unlink and access boundaries. No blind reruns.
Elapsed/token usage: unavailable.

Initial TDD red: 3 expected failures (missing boundary and endpoints). Implemented CategoryHierarchy service and paginated narrow category-product endpoints. First green: 3 tests / 20 assertions. Unexpected failure count 0.
Build started after frontend hierarchy and CategoryProducts implementation. Next: focused frontend tests and browser.

Extended authorization test initially expected 403, received 401 because ClearLegacyCustomerWebSession clears non-admin web users before auth. Test expectation corrected to existing guard behavior; no auth changes. Unexpected test issue count 1, resolved. Next run adds search/picker and non-admin checks.

Backend final: 7 tests / 134 assertions passed (hierarchy + existing banner regressions). Frontend: 5 tests passed (direct children, URL layer, leaf create boundary, existing product editors). Build passed; existing sample-font/missing logistics-image and chunk-size warnings remain. Diff check found two whitespace-only lines; removed.
Browser normal Admin: category 99 Computer Accessories showed 21 products and page 2, created draft child 103, linked product 205 to it, refresh retained parent=103 and assignment; moved product 205 back to original category 99 through picker. No price, SKU, stock or media writes.

Final browser: saved draft leaf 104 under draft child 103 under Computer Accessories 99; third level hides Create subcategory and retains Link Product. Dark mode screenshot tmp/category-hierarchy-integrated-dark.png. Product 205 restored to category 99; count again 21, page 2 shows 21–21. Existing light mode also checked. Two draft QA categories 103/104 retained and not published.
Final frontend tests after source formatting: 5 passed; final build passed. No commits/push/deploy or external API calls. This is local acceptance only.
Implementation: CategoryAdmin producer -> protected CatalogApiController + CategoryHierarchy -> categories.parent_id / products.category_id -> hierarchy list and product list. Invalid cycles/depth/delete/missing products and unauthenticated/non-admin writes covered. Existing catalog banner regression coverage retained.
