# JW Home local test data reset

Approved by user: replace all dummy products/SKUs and reset local Marketplace listings/mappings/inventory; retain accounts, customers, chat and historical order snapshots. Baseline HEAD 27f495b7c1c5e62653b4125d31a34100271725a4; unrelated dirty source and earlier FAQ/RAG changes preserved. Canonical local MySQL jw_ecommerce. No production or provider writes authorized by this phase.

Data: 10 IKEA Malaysia product references, official names/article numbers/specifications, MYR price snapshots and primary photos; no imported customer reviews or remote availability claims. 30 independently written fictional JW Home service FAQ records. Each answer explicitly labels the test policy. Inventory remains uncounted.

Backup: private full mysqldump 3,168,248 bytes; SHA256 8f1a485dfa6682c167c135d72359c8438bc5f58cd858031f9af91ec95ec4aa32. File storage/app/private/backups/home-demo-20261006-153627/before.sql; not committed or served publicly. Dump credentials were passed via temporary private options file and removed.

Issue ledger:
- 2026-10-06 initial transaction preview: scripts/seed-home-support-demo.php --preview. Purpose: verify reset, preserved-record checksums and fixture insertion, then roll back. Unexpected failure 1: deletion graph includes nullable inventory batch review/back-reference cycle. Transaction rolled back; diagnostic FK scan required before retry. This is a maintenance-script issue, not an application reset failure. Total elapsed/token unavailable.
- Diagnosed all cyclic batch tables as empty. Removed empty tables from the live deletion graph; FK enforcement stays on, nonempty cycles still stop. Preview passed in 0.59s: 10 products/10 SKUs/30 FAQ, zero listings/mappings/balances/jobs/counts; protected record checksums unchanged. Issue count1 resolved. Then applied the same reviewed script against the verified backup. Detailed local receipts: tmp/home-demo-preview-report.json and tmp/home-demo-apply-report.json.

## Local home-support fixture acceptance — 2026-10-06

Apply passed in 0.49s. Distinct diagnostic issues, each unexpected failure count 1 and resolved: CLI AI harness lacked a request session (bound synthetic session); fixture Others category order differed from service normalization (aligned fixture order). Earlier empty-table FK graph cycle count 1 remains resolved. No escalation threshold reached.

Verification: maintenance script PHP syntax passed. artisan test --filter='InventoryModeResetTest|FaqRetrievalModesTest|UnifiedKnowledgeTest|OrderKnowledgeTest' passed 16 tests / 61 assertions in 3.50s. Final fixture/persistence and protected-table checksum check passed: 10 products, 10 SKUs, 30 FAQ; zero listings, mappings, balances and pending jobs.

Four actual configured AI provider samples passed: Chinese Sabah delivery 7-12 working days (6.41s); Malay Sarawak parcel RM29 (6.81s); KALLAX dimensions 76.5 x 39 x 76.5 cm (3.95s); LACK availability explicitly unverified / First Count required (4.65s). These are examples, not an accuracy benchmark. Synthetic conversations rolled back. Persistent AI switches preserved; Storefront remains off.

Authenticated localhost browser: 10 products/prices/photos loaded, 30 questions indexed, 10 Awaiting Count SKUs, PDP source specs and disabled purchase awaiting stock verification. Evidence: output/playwright/home-demo-products-local.png, home-demo-faq-local.png, home-demo-inventory-local.png, home-demo-pdp-local.png. No UI changes; frontend build unnecessary for data-only work.

FAQ payment wording aligned with configured checkout. FAQ policies are fictional JW Home test policies, not IKEA policies; product prices are source snapshots, supplier stock is not JW stock. Existing storefront CMS marketing content/links and old categories/empty collections were not converted in this phase.

Overall elapsed time and token usage unavailable. No commit, push, production reset, deployment or provider writes. Separate release approval required.

## Approved category / collection convergence — 2026-10-06

User approved four categories (Tables, Shelving & Bookcases, Storage & Organisation, Lighting) and three manual collections (Living Room Essentials, Organised Home, Home Office Essentials). Baseline branch codex/social-crm-identity / HEAD 27f495b7c1c5e62653b4125d31a34100271725a4. No unrelated source changes staged or overwritten. Data-only change; existing UI retained.

Before mutation: inspected 11 category rows, 4 collection rows and all FK consumers (products, category parent relation and collection_product); verified 10 existing reference product handles. Full private MySQL backup storage/app/private/backups/home-demo-20261006-155141/before.sql, 1,695,584 bytes, SHA256 00dfa09a9bec9c1a9d6f709bf3206911b017232803911aec7c97bea5b010641b. Backup metadata tmp/home-taxonomy-backup.json.

Issue ledger: first --preview failed with expected membership count incorrectly entered as 18; rolled back completely. Root cause arithmetic: approved collection sizes are 5+7+5=17. Unexpected failure count 1; corrected count assertion to 17 before rerun. Preview passed in 0.103s, then --apply passed in 0.124s. Unrelated table checksums passed (all tables except taxonomy, pivot and permitted products.category_id/updated_at). PHP syntax check passed. No blind reruns; resolved.

Persistent result: categories 2/4/3/1 products, collections 5/7/5 members, 17 memberships. Removed eight old categories and all four previous collections including the temporary all-products test collection. Covers reuse local reference photos; category names_cn, descriptions and SEO fields populated. Fixture labels and approved taxonomy recorded in database/fixtures/ikea-malaysia-home-demo.json; bounded maintenance script scripts/converge-home-taxonomy.php guards canonical local environment, backup, baseline and protected data, rolls back previews, and forbids stray provider HTTP.

Final tmp/home-demo-final-check.php passed: 10 products, 10 SKUs, 30 FAQ and original protected-record checksums unchanged; listings/mappings/balances/jobs remain zero. All seven local category/collection URLs returned HTTP200. Authenticated Admin browser showed four categories and three collections with correct counts; all three collection cover images loaded. Public Organised Home page displayed exactly seven assigned products and correct category filters. Evidence output/playwright/home-categories-local.png, home-collections-local.png, home-collection-public-local.png.

Existing storefront marketing banner/footer and fallback header navigation remain outside this data-only taxonomy change; public listing also retains existing RM0 compare-price display behavior. No claim of complete storefront industry conversion. No frontend build required (no application/UI code changed); no provider write, commit, push or deployment. Overall elapsed/token usage unavailable.
Public browser interaction additionally passed: selecting Storage & Organisation within Organised Home retained collection scope and reduced the seven-item collection to exactly three assigned products (two RASKOG and DRONA).

## Release approval and preflight — 2026-10-07 MYT

User explicitly approved synchronization of FAQ/RAG improvements, the 10-product reference dataset, dummy catalog/SKU/mapping/stock reset, and four-category/three-collection convergence to GitHub main and Hostinger. Release excludes unrelated CheckoutService, inventory code/tests, generated bundle deletions and earlier documentation edits. Initial local/origin/main/production SHA all 27f495b7c1c5e62653b4125d31a34100271725a4. Verified live canonical root, production APP_URL host and database identity without exposing secrets. Production baseline 26 products /94 variants /4219 listings /126 mappings /8 categories /3 collections /0 FAQ. One unrelated EasyParcel reconciliation job retained; never run it as part of reset.

New release preparation: explicit production maintenance/root/host/database guards added to local maintenance script; imported approved taxonomy in the same transaction; protected queue payloads retained; unlink order variant pointers before FK-consumer guard while retaining order snapshots. Local release preview on the current accepted dataset passed in 0.47s, all protected checksums unchanged and rolled back. Backup helper syntax passed; uses private options file and shell mysqldump, no plaintext credentials in logs/source. Production preflight PHP shell_exec unavailable (diagnostic failure1); removed process invocation from audit helper and read Git SHA through SSH instead, resolved. crontab binary unavailable; no retry or cron mutation. Maintenance gates and queue inspection remain required.

Final application regression: 127 tests /853 assertions PASS14.79s (tmp/home-release-regression.log). Tests cover FAQ modes, unified knowledge, support ownership/pause/media, private orders, product/taxonomy/discount knowledge and stock reset. No UI/application asset changes or migrations required. Existing current dirty UI bundles are excluded from release. Overall elapsed/token unavailable. Actual deployment/data-cutover results to be recorded after execution.
