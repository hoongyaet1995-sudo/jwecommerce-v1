# Batch append to an existing JW product

Local implementation based on e2b551015ce94f91d0b3258b67cc6b1d3773d8ce. Not committed, pushed or deployed yet.

## Behavior

- In a single store product's Import Product to JW Master review, choose an existing draft or active destination.
- Retain its existing mappings; link matching destination SKUs without replacing their fields; create only missing variants with the existing import service (including image and package metadata).
- Reject another product's mapping/import identity/SKU ownership. The entire source-product batch rolls back on conflict.
- Compare submitted listing IDs with the current complete source product before writing. Retries reuse mappings instead of duplicating variants.
- Keep destination metadata/status and existing stock. New legacy stock stays zero; platform stock remains reference-only. No warehouse or provider stock writes.
- Published destinations have an explicit storefront visibility notice. Existing multi-product new-draft import remains unchanged; append destination selection is for one source product at a time.

## Verification

- PHP MarketplaceImportTest: 31 tests, 306 assertions, passed.
- Vitest: ConnectedImportReviewModal, marketplaceReviewedImport, marketplaceOperations, ConnectedVariantModal: 24 tests passed.
- Scoped Admin build passed (existing large-chunk warning).
- Chrome localhost authenticated browser: local test store #4, draft product #67, `JW QA Batch Append 0914`.
- Initial 2 mapped variants, 14 missing. Review showed 2 retained / 14 new / 0 conflicts. Confirm Append & Link returned 14 new / 2 retained.
- MySQL readback: 16 variants, 16 mappings, all 16 explicitly test-classified, aggregate legacy stock 0, product still draft.
- Live product #52 and its remaining MCC variants were not modified in this acceptance run.

## Release preparation

Scoped source/build copy: `tmp/batch-append-local-0914`; it contains HEAD plus this batch's source, excluding pending unrelated WMS changes. No branch/worktree created.

Admin manifest entry: `main-DkD7-j3I.js`, `InventoryRuntime-BROsl-uW.js`. Reuse unchanged shared assets. Stage only this batch's controller, new MarketplaceBatchAppendService, importer adapter/types, review modal, tests, this document and manifest/assets; exclude unrelated WMS dirty files. No database migration is required.

Re-run commands from canonical root:

```powershell
C:/xampp/php/php.exe artisan test --compact --filter=MarketplaceImportTest
```

From `resources/admin`:

```powershell
npx vitest run src/previews/marketplace32/ConnectedImportReviewModal.test.tsx src/integration/marketplaceReviewedImport.test.ts src/integration/marketplaceOperations.test.ts src/previews/marketplace32/ConnectedVariantModal.test.tsx
```

Test fixture and readback helpers remain local-only in `tmp/batch-append-browser-fixture.php` and `tmp/batch-append-browser-readback.php`. Do not rerun the fixture creation or deploy it.
