# Marketplace import and publishing: complete the merchant journeys

Please refine the existing JW Ecommerce Marketplace design rather than redesigning the module. Retain its visual language, official platform logos, English copy, desktop/mobile quality, and mapping workspace navigation. You decide the most understandable layout and interactions; the following are business requirements, not prescribed wireframes.

## Context

One company owns each independently deployed JW Ecommerce instance and may connect multiple seller stores. Shopee Malaysia is first, Lazada follows. Other platforms must not appear operational before their integrations are ready. App credentials are server-side; merchants never enter Partner IDs, secrets, or seller passwords in JW.

## 1. Import an external product into JW

The whole-product import action already has a real backend. It creates a JW draft and its variants, with zero local stock, then links the external variants. Platform stock is reference information, not warehouse stock initialization. Importing does not publish to the storefront or change the seller listing.

Provide a clear review of what will be created, what is already linked, missing/duplicate SKU codes, incompatible currency, and recoverable failures. Do not silently merge with a similarly named product. Existing SKU conflicts should direct the merchant to link the existing variant. Repeating a completed import should open its existing result, not duplicate it or restore links the employee removed.

## 2. Create Master SKU & Link / single-variant import

This is not an alias for importing every sibling variant. Show the selected external product and variant, and let the merchant understand its JW product destination: an appropriate existing product or a new draft product. If a prior import already establishes the parent relationship, show that relationship clearly. Do not silently choose a different product by name.

Review SKU, variant attributes, price/currency and destination before creating. Missing or duplicate codes require explicit correction or selection of an existing SKU. Show success, retry-safe failure, and the resulting association. Creating a missing variant must not overwrite the existing product's title, publication state, price settings, other variants, or inventory.

One external variant maps to one JW variant. Within one store, one JW variant maps to at most one external variant; reuse across different stores is allowed. A conflicting selection must be blocked and explained, not silently replaced.

## 3. Push JW Master Product to Store

The current PublishProductModal only selects a store/product, markup and a free-text category name. Extend it into a merchant-ready listing workflow. Backend capability and platform metadata will drive available fields; do not use invented hardcoded category/attribute/brand/logistics options as production facts.

Allow review of destination store and environment, channel title/description, actual platform category selection, category-dependent required attributes and brand choices, media, variant options/SKUs and channel prices, and applicable package/weight/logistics/warehouse requirements. Exact required fields differ by platform and category; the interface must accommodate backend validation rather than claim every listed field is universally required.

Pre-fill existing JW data but keep channel changes separate from the JW master. Show missing information and per-field errors before submission. Check existing external listings, including unlinked listings: an unmapped product is not necessarily absent from the marketplace. Offer review/link existing where appropriate instead of casually creating duplicates.

Remove promises of immediate inventory broadcasting. Inventory write sync is separately enabled after initial-stock and warehouse review. Clearly distinguish listing quantity required for an explicit publish from enabling ongoing inventory synchronization; never silently copy physical stock or enable ongoing sync.

Publishing is asynchronous: queued, processing, successful with real external IDs, rejected, and outcome uncertain. A timeout must tell the merchant that the result is being checked, not encourage blind duplicate publication. Partial variant failures need specific outcomes and safe recovery. Only link returned external variant identities after actual creation succeeds. Submission accepted is not publication completed.

## 4. Shared feedback and boundaries

For bulk actions, show selected scope (current page versus all filtered results), per-item success/failure, remaining failed selection and safe retries. Keep large catalogs searchable and paginated. Show disabled capability reasons in a merchant-readable way.

AI Suggestions may recommend legal matching candidates with explanations, but never directly create mappings without merchant confirmation and backend conflict validation. Do not present an uncalibrated model score as accuracy.

Do not implement real API calls in the design export. Keep demo data visibly isolated; provide complete interactive states for integration. Avoid fake seller authorization pages, fixed token-validity claims, and fabricated success messages in operational flows.

Deliver updated source components and their dependencies, including desktop/mobile states, while preserving unrelated pages. Indicate the new and changed components so the Laravel integration can extract only this scope.

## Local source evidence (not live-platform capability claims)

- `resources/admin/src/marketplace/components/marketplace/PublishProductModal.tsx`: category free text, markup, mock delay, disabled publishing, immediate-stock-sync copy.
- `resources/admin/src/marketplace/components/marketplace/ProductMappingModal.tsx`: disabled Create Master SKU & Link action.
- `app/Services/Marketplace/MarketplaceImportService.php`: whole-product transaction, draft/zero-stock creation, duplicate-SKU and partial-mapping rejection, retained import identity.
