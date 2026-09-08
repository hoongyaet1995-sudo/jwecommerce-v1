# Inventory & WMS: approved-page completion brief

Keep `/admin/marketplace/inventory` as the inventory management entry. Build on the latest accepted Google AI Studio source, preserving adjacent modules, page hierarchy, styling and responsive language. Do not redesign the dashboard.

## Observed implementation gaps

- Existing runtime context supplies an empty inventory array; the screen is not an operational inventory read model yet.
- `channelMappings` is keyed by platform and can represent only one relationship per platform, but the approved product model permits several Shopee or Lazada stores.
- Physical stock, reservations and availability must be distinguished from channel-reported stock. No platform snapshot may initialize physical stock automatically.
- Existing broadcast/adjustment buttons are not implemented. A connected OAuth store does not imply enabled inventory writes.

## Required merchant journey — presentation only

1. Keep the current SKU matrix and summary cards. Let merchants inspect every connected store's association for a SKU, including multiple stores on the same platform. Show store name, platform, external SKU, connection state, synchronization state, last confirmed pushed quantity/time and failures. Do not collapse stores into one platform value.
2. Provide explicit store-scoped inventory activation after reconciliation. Review current JW stock, outstanding orders, mappings and any required channel warehouse location. Show disabled, awaiting reconciliation, enabled, paused, authorization expired and failed states. Connecting a store alone never activates stock writes.
3. Separate physical quantity, reserved quantity, safety buffer and available-to-promise. Existing unreconciled legacy quantities must be marked as requiring reconciliation, not labeled as verified physical stock. Unknown quantities must not appear as zero.
4. Preserve stock adjustment and audit interactions. Use the authenticated employee identity (not a freely entered operator name), a reason and an explicit review before changing physical stock. Explain reservation constraints and failed adjustments.
5. Broadcast/retry must clearly identify affected eligible stores and excluded stores. Show queued, processing, confirmed, failed and uncertain outcomes; request submission is not success. No promise that synchronization eliminates all overselling risk.
6. Include an order exception entry for unmapped SKUs, insufficient stock and orders predating inventory activation. Reading/importing historical orders must not imply inventory was deducted.

Release 1: Shopee and Lazada only. No marketplace shipping/AWB actions or new-product publishing here. TikTok/Shopify remain unavailable. Preserve desktop/mobile behavior; choose the cleanest existing design patterns rather than introducing a separate inventory module.

Laravel owns all calculations, authorization, conflict rules and persistence. This brief requests missing merchant interaction source, not simulated provider success or backend algorithms.
