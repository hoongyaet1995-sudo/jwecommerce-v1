# Shopee publishing reference boundary

## Verified local source findings

- Dashboard `app/Services/Commerce/Marketplace/Adapters/ShopeeChannelAdapter.php` declares `supportsPush(): true`, but this declaration is not proof of implemented product creation. Its client is MarketplaceClientStub. Its generic requestBlueprint synthesizes a path from an operation name; do not use that synthesis as a documented Shopee endpoint.
- Dashboard `MarketplaceLiveTransport::send` returns `ok: true`, `execution_path: live_stub` and `response_stub` without an HTTP request. Never port this into Ecommerce publishing success handling.
- Dashboard Shopee catalog logic contains genuine shop API reads plus a fallback generating remote IDs from local products when credentials are absent. Ecommerce must keep its existing fail-closed client and must not copy that fallback.
- Ecommerce `ShopeeClient` currently implements authorization, token refresh, catalog and order reads. No product creation HTTP method exists in the inspected file.
- Dashboard has a separate LazadaProductListingService, not a reusable verified Shopee create implementation. Platform-specific payloads must stay separate.

## Implementation consequence

Keep current signed, credential-safe Ecommerce request infrastructure. Implement Shopee listing preparation and actual creation against official endpoint contracts, including platform category/attribute/logistics metadata, media handling, variant creation, and reconciliation after uncertain outcomes. Keep publishing capability disabled until the employee UI and real Sandbox acceptance prove it.

Next backend work can proceed independently of missing presentation: explicit single-variant import contract with destination product, retained import identity, no local stock initialization, duplicate-SKU prevention, transactionally consistent mapping, and tests. Do not expose the disabled single-variant buttons until the reviewed destination/review interaction is available.

Cron acceptance is deferred by the user's latest prioritization. No Dashboard source or settings were modified during this inspection.
