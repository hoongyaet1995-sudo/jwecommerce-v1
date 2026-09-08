# ZIP30 OMS adapter checkpoint

Selected source: resources/admin/src/previews/marketplace30/components/marketplace/MarketplaceOMSPage.tsx. Source identity is recorded in 2026-09-06-marketplace-30-checkpoint.md (ZIP30 SHA256 767ED21BE2485ABF70A158B3B04477B4DB10D06B746AE6C31FAC3F854B134F1A).

No DOM, CSS, copy, assets or source component changed in this checkpoint. No official entry cutover. The source preview remains explicitly simulated, not an operational order page.

Available adapter added: marketplaceOperations.orderDetail(id), consuming the protected existing local order detail endpoint, with nullable price/identity/mapping fields preserved. Regression test first failed for missing adapter, then seven operational frontend tests passed. Test mock reset isolates response fixtures across cases.

## Required before real OMS binding

- Source context holds full MarketplaceOrder objects; the paginated backend summary and lazily loaded detail require separate state ownership and server filters, not a fetch-all workaround.
- Current source READY_TO_SHIP label claims AWB Printed. Real Shopee READY_TO_SHIP does not prove an AWB was printed. Provider state presentation must be adapted truthfully.
- Source dispatch action invents tracking numbers and defaults carriers; production must use actual provider results or an explicitly unavailable operation.
- Source simulateIncomingOrder is developer-only. It must not enter merchant production UI.
- Existing local detail exposes product lines and current mapping only. Authorized recipient/fulfillment data and actual capability declarations are missing for the full drawer; no fake recipient/phone/address may fill these fields.
- Mapping shown in detail is current and advisory; it must not rewrite historic reservation or inventory events.

Per source-fidelity skill, full drawer binding pauses at these backend contract gaps. This is not a request for a redesign. Continue the already approved backend plan, then use the existing source presentation with genuine values and unavailable states. Desktop/mobile real-data browser acceptance remains pending.
