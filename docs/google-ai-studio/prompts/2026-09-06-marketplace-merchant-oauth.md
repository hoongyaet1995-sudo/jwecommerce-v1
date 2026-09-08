# Google AI Studio prompt — Admin Marketplace merchant authorization

Please refine the existing JW Ecommerce ADMIN Marketplace experience using the current project's design system. This is ADMIN ONLY: do not design or modify storefront pages. Keep the existing navigation and Marketplace workspace; you decide the best clean layout and interactions.

## Business goal

Each JW Ecommerce deployment belongs to one company. The company can connect multiple Shopee, Lazada, TikTok Shop and Shopify seller stores. JW provides the developer application; merchants simply log in on the platform's own authorization page and grant access. They never enter their seller password inside JW.

## Connect Store

Make this a merchant-facing connection experience, not a developer configuration form. Include choosing an available platform, country/site where relevant, an optional store nickname, and continuing to platform authorization.

Provide clear states for waiting, cancelled/failed authorization, connected, expired/revoked authorization requiring reconnection, duplicate store connection and disconnect confirmation. Show the actual returned store identity after success. Preserve history on disconnect; explain that synchronization stops.

Remove merchant-facing App Key/Secret, Partner ID/Key, token copy/refresh buttons, webhook secrets, technical scope checkboxes, environment switches, demo reset tools and fake security/latency/quota statistics. These are server-owned concerns.

The first release connects Shopee/Lazada; other platforms should support a truthful unavailable state, not appear already connected or functional.

## Catalog retrieval

Within the existing Product & SKU Mapping area, support fetching the selected store's products with loading/progress, last successful update, empty catalog, partial failure and retry feedback. Fetching does not create JW products or change inventory. Keep large catalogs manageable with search, filters, grouped/flat views and clearly labelled pagination.

## Mapping rule

One external sellable variant links to one JW variant. Within the SAME store, a JW variant can only be used once; across DIFFERENT stores, reuse is allowed. SKU text may differ. Clearly explain same-store conflicts without silently replacing another mapping. Keep variant selection, confirmation and unlink actions straightforward.

Do not expand bundles, AI automation, WMS or reporting. Keep future Import/Publish and inventory-write actions visibly unavailable when backend capability is unavailable; do not remove their existing future design unnecessarily.

Use English customer-facing copy. Reuse the established dark/light visual language and support mobile. Any source-only QA previews must be isolated from employee UI. Export the updated source and identify changed components and dependencies; no real backend/API implementation is required from you.
