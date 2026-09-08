# Shopee Sandbox browser preflight — 2026-09-06

## Latest: user-created fixture verified in Chrome

User created a product, superseding the empty-catalog observation below. Read-only verification on tab 346035393, Seller Center All products list:

- Product: JW Sandbox PS5; Item ID `846055463`; Parent SKU `JWSTORE1PS5`.
- Variant: White,500GB; Model ID `12983425`; Seller SKU **`JWSTORE1PS5-500GB`** (present in current browser, although user's earlier screenshot showed a dash).
- Price RM1,400.00; stock 100. Listing count 1 Product.
- Live badge still reads 0; do not infer API status NORMAL from existence in All. Verify returned status during API pull before concluding the existing NORMAL-only fetch covers it.
- Suitable fixture for basic product/model pull, explicit mapping and duplicate-fetch tests after OAuth. One variant does not cover multi-variant completeness or conflicts by itself. No actual JW API pull has occurred.
- Do not publish the unfinished JW-TEST notebook merely to duplicate this fixture. No changes were made to the user-created product during this inspection.

Evidence: actual user-authorized Chrome session, not HTTP fixtures.

- Open Platform Test Account-Sandbox v2 lists one Local MY test shop, Shop ID `227482706`.
- Used its official **Login Seller Center** button, as required by the console notice. Login succeeded on `seller.sandbox.test-stable.shopee.com.my`.
- My Products: **0 Product**, Live 0, Violation 0, Under Review 0, Unpublished 0, No Product Found.
- My Orders, All view: **0 Orders**. UI explicitly says no orders after 07/09/2024; this does not prove all historical orders are absent.
- No test account/product/order created, deleted or edited. No credential exported to this report. No Live store or application configuration changed.

This proves Sandbox Seller Center access, NOT JW OAuth authorization, API credentials validity, catalog API behavior or order API behavior.

Next dependencies: finish the protected HTTPS JW OAuth release candidate and obtain its specific synchronization approval. Sandbox test product/order creation approval requested separately so non-empty SKU/order acceptance can be exercised, rather than treating empty lists as full acceptance.

## Approved Sandbox preparation in progress

User subsequently approved creation of JW-TEST products and official test orders in this Sandbox only. Opened the official Add a New Product form; it is **not saved/published**.

- Chrome tab `346035389`, `https://seller.sandbox.test-stable.shopee.com.my/portal/product/new`, marked for continuation.
- Unsaved name: `JW-TEST Sandbox Notebook SKU Mapping`; parent SKU `JW-TEST-NOTEBOOK`.
- Selected Stationery > Notebooks & Papers > Notebooks & Notepads.
- Entered test parcel weight 0.1 kg, 10 × 15 × 1 cm, enabled variations.
- Still required: test image/promotion image, description, mandatory Brand, variation options/SKUs/prices/stock and shipping validation. Do not assume the current unfinished form is a created product.
- Seller form initially loaded sections progressively; the suggestion module reports failure, but category and variation controls are usable. Use fresh browser state before continuing. No actual listing or order identity exists yet.
