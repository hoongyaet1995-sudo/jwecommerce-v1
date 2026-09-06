# ZIP 30 mapping pagination adapter handoff

## Inspected boundaries

- Visual source: ZIP 30 `components/marketplace/MarketplaceMappingPage.tsx` and `ProductMappingModal.tsx`, already locally extracted under `resources/admin/src/previews/marketplace30`.
- Existing official runtime `resources/admin/src/marketplace/context/AdminContext.tsx` loads the older unpaginated root API; catalog refresh is still unavailable and OMS is an empty array.
- Source mapping page calculates cards/filter counts from the entire in-memory catalog. Feeding a single server page directly into it would misreport store totals and page the data twice.
- Existing authenticated APIs already provide flat and grouped pagination and mapping/unlink. Grouped search preserves sibling variants. The source uses local filters and slice, so interaction bindings still need controlled server-page adaptation.

## Backend gap resolved locally

`GET /admin/api/marketplace/catalog/summary` returns `products`, `variants`, `mapped`, `unmapped` for platform/store/search scope. Page and mapping-status selection do not narrow summary counts. External product IDs reused across stores count separately. Search follows the existing listing query (including mapped JW SKU); a grouped product may retain nonmatching siblings in its detail while summary variant counts refer to matching variants.

The endpoint inherits admin authentication and read gate, emits no credentials/recipient fields, and performs aggregate queries without loading the complete catalog. It changes no mapping or inventory. Typed frontend method added, not yet mounted into UI.

## Still pending

- Source-faithful mapping preview controlled filters, grouped/flat page units, stale request cancellation, selection clearing on scope changes and authoritative summary binding.
- UI binding of mapped JW product/variant display fields and same-store conflict availability beyond a visible page.
- AI suggestion totals remain unavailable: do not invent AI matches or interpret local title similarity as model output.
- No official-route cutover or deployment of this local increment. No frontend DOM/classes changed. This is backend contract work, not completed visual acceptance.

## Additional local contracts completed

- Flat and grouped catalog variants now include `jw_product_id`, `jw_product_title`, `jw_variant_title`, and `mapped_at`, allowing the original modal to preselect an existing association without reconstructing identity from SKU strings.
- `GET /admin/api/marketplace/listings/{listing}/mapping-context?variantIds[]=...` checks persisted same-store mappings for up to 100 candidate JW variant IDs. Returns an allowlisted conflict identity/title/SKU or null for each existing candidate. Missing candidates are omitted and must not be treated as valid by the consumer. Editing the current association does not conflict with itself; another store's association does not conflict.
- The context is advisory and may become stale. Final save must still use the transactional mapping service and database unique constraint; no auto-replacement or stock adjustment.
- Protected endpoint tests cover missing listing, missing/duplicate/oversized IDs, unknown variants, read gate, self-association and cross-store reuse. Catalog/mapping operations remain local for this increment.
- Current local Marketplace/Shopee regression: 78 tests, 549 assertions passed; TypeScript lint passed. Browser integration remains pending.

## Original modal asynchronous persistence adaptation

- ZIP 30 `ProductMappingModal` now awaits mapping/unlink callback completion. A returned false or rejected promise keeps the modal open. Rejection displays the existing toast pattern without leaking backend details.
- Synchronous ref guard prevents same-tick double submission; confirm, unlink and close controls are disabled during persistence. All existing wrapper structure, classes and button labels are retained.
- Uses the type-only ContextBridge path so this component no longer imports the demo model at runtime.
- Two DOM interaction tests exercise save and unlink, deferred completion, false/rejection, retry and successful close. Both passed. These are local jsdom interaction tests, not real provider/browser completion evidence.
- The legacy source callback's void completion remains supported for the private demo. The operational adapter must explicitly return true only after persisted success, false on failure, and surface failure through the existing toast system.

## Official-entry authorization update

- User explicitly approved official Marketplace entry cutover on 2026-09-06, identifying `/admin/marketplace/auth` as their test environment. The prior cutover approval gate is therefore satisfied; do not ask the same question again.
- This authorizes replacement of the merchant entry with the approved real-data implementation, not falsely marking unsupported operations successful. Perform local acceptance before the coherent release. Existing production write flags remain unchanged by this approval.
- Source mapping page now accepts server-controlled totals, summary and query callbacks, and avoids slicing server pages twice. The local DOM interaction test retains its supplied product on page 2 of 22 and emits the correct page query. Full network runtime binding is still pending.
