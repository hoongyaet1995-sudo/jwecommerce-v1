# Phase 1 fixed release checklist

Baseline: approved 2026-09-08 Basic WMS specification and ZIP36. This is one local release batch, followed by synchronization approval and online development acceptance. No architecture reset or redesign.

Only add a blocker for incorrect inventory, inaccessible required operations, failed permissions, or an explicit release requirement. Cosmetic improvements and advanced WMS remain outside this list.

| ID | Required closure | Current evidence and remaining gap |
| --- | --- | --- |
| B1 | Initial shared/independent configuration, warehouse and binding persistence | Backend and adapter evidence exists. Initial configuration used a local service fixture; aggregate browser acceptance still required. |
| B2 | Count, inbound, outbound, adjustment, safety in both modes | Independent browser count 12 and safety 2; previous browser 20 +5 -3 -1 =21. Legacy quantities unchanged. Shared safety entry path remains open. |
| B3 | Mapping and distinct quantity sources | Existing listing 83 re-save and DB readback verified. New mapping and both provider source paths need final acceptance. |
| B4 | Actor/time/reason, unknown values, retry/version protection | Physical ledger browser evidence exists; safety receipt verified. Safety audit retrieval and negative/gate regression remain open. |
| B5 | Advanced mutations disabled server-side | Transfers guarded. Reconciliation and mode conversion require explicit release gates. Verify all relevant entry points together. |
| B6 | Persistent test isolation and bounded sandbox writes | Classification schema/local fixtures exist. General store/SKU/warehouse/order propagation and designated-store write boundary need closure; prior provider evidence is reusable but not proof of this new boundary. |
| B7 | One coherent local acceptance and exact-commit release | Pending B1-B6. Run affected tests/build and authenticated business flow, then request one GitHub/Hostinger synchronization approval. Online acceptance checks environment differences and key flows. |

## Batch progress, 2026-09-08 local evening

- B2: shared balances now reach the existing expandable matrix and Basic safety adapter; adapter tests verify shared identity and reject advanced reconciliation for that pool. Shared-mode end-to-end browser acceptance remains outstanding; do not convert the current independent test database merely to claim it.
- B4: safety events have a protected, SKU-scoped paginated read API. Existing ZIP36 ledger combines safety and physical events chronologically. Chrome on main-1-J7oxJq.js displayed SKU103 safety Unknown to 2, physical Unknown to 12, employee2, reasons and original timestamps. Safety history records zero physical delta. Previous unknown safety/ATP remains unknown in review.
- B5: explicit MARKETPLACE_ADVANCED_WMS_ENABLED=false now gates reconciliation and mode-review controllers and services. Four disabled advanced HTTP mutations returned403; historical advanced behavior remains tested only with explicit enablement. Broader activation/fulfillment boundaries still need the final release review.
- B6: explicit inventory:classify-test command preserves first actor/time/reason and rejects operational relabeling. Test-store classification propagates to existing and new provider order snapshots and newly imported variants; does not reserve or change stock. General provider write eligibility and visible store/warehouse/order markers remain outstanding.
- Regression: 41 related backend tests /325 assertions; 33 frontend tests in19 files; tsc and preview build passed. After classification changes,45 import/ingestion/classification tests /401 assertions passed, plus the new import-provenance regression. These are local test results, not provider or online acceptance.

Release is not yet ready. Preserve this fixed list and close its remaining requirements without restarting design or treating each individual fix as a delivery.

Subsequent classification closure: local fixture verifier checked Lazada store3 seller300163725140/application139209/official_test provenance before calling the classification service. Existing borrowed-seller orders inherited test provenance. Warehouse9 and SKU102 retained their original classification. Store binding and order summary adapters now expose persisted classification using existing text; warehouse and SKU markers were already present. These latest display bindings pass TypeScript and targeted tests; browser verification is still part of B7. Real Lazada /seller/get revalidation succeeded and renewed the official-test lease; no provider quantity was changed. The last post-classification suites passed40 backend tests/360 assertions and32 frontend tests.

Next sequence: implement the separate bounded sandbox-write boundary (both provider identities, exact mapped SKU/location, durable attempt and readback; no enabling general broadcast), then close remaining shared/initial-setup/new-mapping browser evidence and release-route integration. Reuse the completed independent ledger flow. Current local remains uncommitted; no synchronization or deployment approval has been requested because B6/B7 remain open.

Update after real provider batch: the Lazada half of B6 now has durable attempts, independent flag, guarded stage/dispatch/inspect API, existing ZIP36 single-store button and actual provider/browser readback evidence. See 2026-09-08-basic-wms-sandbox-write.md. Original provider-location82 changed to19 and independently confirmed; browser then confirmed19 again. Test flag returned false and browser Push Stock disabled. Outstanding B6 is Shopee transport, persistent attempt discovery in the existing read model and online credential renewal; do not repeat the proven Lazada write simply to accumulate checks.

Evidence levels are distinct: API tests do not prove page wiring; page wiring does not prove end-to-end persistence; localhost evidence does not prove online deployment. Existing detailed evidence remains in the adjacent Basic WMS documents. None of the open rows implies production takeover, automatic order deductions, transfers or full merchant OAuth is enabled.

## Consolidated checkpoint — 2026-09-08 21:16 MYT

- Latest user boundary: small UI integration fixes are authorized; large UI/UX changes require notifying the user and retaining Google AI Studio as design source. This checkpoint changed no presentation code.
- Fresh local checks: `php artisan test --compact --filter='BasicWms|SandboxInventoryWriteGuardTest|ShopeeClientTest'` passed 51 tests / 198 assertions; `npx vitest run src/previews/marketplace36 --reporter=dot` passed 20 tests in 13 files; `npm run lint` exited 0.
- Shopee bounded transport and durable sandbox history now exist in local source. The checks above are automated evidence, not proof of a real Shopee write or browser history rendering. Earlier B6 wording that transport implementation is outstanding is superseded by this distinction.
- Chrome still exposes the local ZIP36 preview with Basic operations enabled and the general single-store/bulk stock-write controls disabled. The durable history browser flow and first shared-pool configuration remain unverified.
- Shopee OAuth has not recovered: the authorization tab remains at the sandbox authorization URL after the previously observed client block. No new authorization or provider write was attempted in this checkpoint.
- No commit, push, deployment, official-route cutover, database-mode conversion, or existing inventory reset was performed. B1–B7 remain the fixed release scope; passing these checks does not close the overall goal.

## Shared lifecycle and durable audit — 2026-09-08 21:21 MYT

- Added `BasicWmsLifecycleTest`: both modes traverse authenticated HTTP initial configuration, warehouse creation, unknown balance, rejected pre-count delta, count 20 with idempotent confirmation, safety 2, inbound +5, outbound -3, adjustment -1, and rejected overdraw. Final physical21 / available19 / legacy17, accounting remains disabled, no allocations created. Two scenarios passed70 assertions. This is isolated SQLite HTTP integration evidence, not MySQL/browser shared-mode evidence.
- Authenticated a fresh local Playwright browser at port8500 using the authorized superadmin. Opened ZIP36, searched `JW-SBX-LZ-SHIRT-001`, then opened the existing Stock Ledger & Audit Trail button.
- Browser now displays both persisted Lazada attempts: platform82→19 at20:42:47 and19→19 at20:50:01, each confirmed, Employee#2, reason, exact location and durable attempt UUID. They appear above physical warehouse movements and show zero physical delta. This closes durable-attempt discovery in B4/B6; no new platform write was performed.
- Shared-mode browser setup, new mapping, Shopee connection and exact-commit release remain open. Existing independent MySQL records were preserved.

## Shared MySQL browser closure — 2026-09-08 21:36 MYT

- Prepared empty local MySQL `jw_basic_wms_shared_acceptance_0908` through the guarded manual fixture script. Copied only the authorized owner identity and created one classified draft SKU with legacy17; no provider tokens/stores or operating stock copied. Temporarily selected it at the same8500 URL, then restored `.env` to `jw_ecommerce`.
- Actual browser initial configuration selected Shared Pool while all physical balances were unknown. Initial count20 and safety2 succeeded, with receipt81970862-2153-4b51-8e04-519704e70dd7.
- Browser found a real loading race: editing before balance fetch completed was discarded when the loaded modal remounted, so two intended negative adjustments became default+10. Retained those failed attempts and the original screenshot `basic-wms-shared-ledger.png`; they are failure evidence, not acceptance.
- Fixed existing source modal controls to remain disabled while authoritative balance loads or a request is pending. Regression failed before the fix and passed afterward. Also corrected mode-toggle reason selection so a cycle count cannot silently retain an inbound reason. DOM/classes/layout preserved. Initial architecture copy now describes configuration without claiming order-rule migration; shared balance badge no longer claims a physical warehouse.
- On rebuilt main-Dd7zqOc1.js, browser recounted the isolated fixture45→20 with an explicit corrective reason, then confirmed20→25→22→21. Every server review was inspected before confirmation. Safety2/available19; overdraw-22 disabled review. Corrected screenshot: `basic-wms-shared-ledger-fixed.png`.
- `basic-wms-shared-readback.php` verified MySQL physical21/safety2/version9, legacy17, shared mode, accountingfalse, no allocations, and matching server actor2/reasons/timestamps. Old failed ledger records remain intact. Original database readback after restore retained balance10 physical21/safety2/version5 and both original confirmed Lazada attempts.
- Final targeted frontend regression:7 passed; TypeScript passed before the final reason-only handler fix; preview rebuilt afterward. B2 shared operational chain is now proven through browser+MySQL. Remaining work includes warehouse/binding/new-mapping acceptance, Shopee connection, release-route integration and approved synchronization. Full goal remains incomplete.

## Warehouse and binding browser closure — 2026-09-08 21:47 MYT

- In original local `jw_ecommerce`, authenticated ZIP36 browser created warehouse10 `QA-BASIC-PEN-0908`, name `LOCAL TEST Basic Penang`, active, address/state supplied through existing form. Classified it test through the guarded service with actor2 and explicit reason.
- Browser saved JW website binding to warehouse10. Readback script `basic-wms-routing-readback.php` verified route1/version1, `is_enabled=0`, accountingfalse and general broadcastfalse. Existing Lazada route remains warehouse9/locationMY4NA1T7CK-WH-10007. No provider write or stock adjustment occurred.
- Fixed existing binding summaries to count supplied channel records and mapping percentage, describe saved location IDs without claiming provider synchronization, and render string-backed warehouse addresses without `undefined`. Existing explanation now states bindings do not activate deductions/writes. Mode explanation no longer claims active transfer reservations. Kept source DOM/classes.
- Binding read-model regression failed before fix and passed after. TypeScript and preview build passed. Actual browser on main-CJjImL9b.js after reload displayed JW1/Shopee0/Lazada1,2/2 routes, Penang/Selangor locations, persisted website warehouse10 and unchanged Lazada warehouse9.
- B1 warehouse-create/binding-save paths now have browser+MySQL proof. New SKU mapping, Shopee real connection/write and formal-route/release integration remain open; this is not a deployment or overall completion.

## First mapping and release integration seam — 2026-09-08 21:50 MYT

- B3 first-time mapping completed through ZIP36 browser and MySQL: listing70/store3/external variant128508288530 mapped to classified test variant103/actor2. Legacy17 and physical12/safety2/version2 unchanged; sibling listing69 remains unmapped. See basic-wms-mapping-gap.md for evidence limits.
- Formal route still resolves AdminAppController → MarketplaceWorkspace → older MarketplaceInventoryPage. ZIP36 runtime currently lives in marketplace33/main.tsx with createRoot and preview-specific navigation/banner. Next implementation must extract the reusable connected runtime, preserve Admin shell/navigation and ZIP36 visuals, and stage it behind a default-closed release switch. Merely redirecting the formal route to the preview would not satisfy shell integration.
- Official cutover and GitHub/Hostinger synchronization remain approval boundaries. Preparing the disabled integration and local proof is authorized. Shopee OAuth recovery still pending; no new attempt was made against the client-blocked page.

## Admin shell integration — 2026-09-08 21:58 MYT

- Extracted connected `InventoryRuntime` from marketplace33/main.tsx without duplicating inventory business logic. Standalone preview retains its mounting/style entry; Admin MarketplaceWorkspace lazy-loads the same ZIP36 runtime and supplies existing Admin navigation.
- Added independent default-false `MARKETPLACE_BASIC_WMS_UI_ENABLED`. Only local environment accepts `?basic_wms_preview=1` as explicit shell preview; production ignores it. Feature tests first failed on missing bootstrap contract, then passed2 tests/8 assertions. Operational/write gates remain independent. Authenticated employee identity is supplied to existing modal adapters through escaped root data attributes.
- Actual browser exposed missing Admin Vite base when lazy imports requested /assets instead of /build-admin/assets. Fixed build base and rebuilt. TypeScript, main build and standalone preview build passed. Final Admin main-Ci7AEHyd.js / lazy InventoryRuntime-BTXkpU4b.js.
- Authenticated browser at `/admin/marketplace/inventory?basic_wms_preview=1` rendered existing sidebar/header and ZIP36 inventory, searched test SKU103, displayed its actual safety and physical ledger, and navigated using Product & SKU Mapping to `/admin/marketplace/mapping` with actual49-item catalog. Screenshot basic-wms-admin-shell.png. No stock mutation occurred in this shell check.
- Explicitly navigated back to unparameterized official inventory URL to verify the default-closed boundary. No .env UI release enablement, commit, push or deployment performed. Main-shell interaction coverage and release approval remain distinct from the earlier standalone business-flow evidence.
