# Tax Admin Closure Mapping

| Feature ID | Business owner | MySQL truth | Admin producer | Service/API | Operational consumer | UI source | Status | Acceptance evidence |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `tax.classes.create` | Operator-managed | `tax_classes` | Tax Zones, Add Tax Class | `POST /admin/api/tax-settings/classes` | selectable product classification and rule binding | ZIP TaxZonesSettingAdmin, L2 | CLOSED | Authenticated browser created `Closure Proof Goods 7%` (id 2); it remained after reload. Screenshot: `output/playwright/after-8500-tax-class-count-link-final.png`. |
| `tax.zones.create` | Operator-managed | `tax_zones` | Tax Zones, Add Geo Tax Zone | `POST /admin/api/tax-settings/zones` | rule jurisdiction matching | ZIP TaxZonesSettingAdmin, L2 | CLOSED | Authenticated browser created nationwide MY zone `Closure Proof Malaysia Zone` (id 2); it remained after reload. |
| `tax.rules.create` | Operator-managed | `tax_rates` | Tax Rates, Add Tax Rate | `POST /admin/api/tax-settings/rates` | class-plus-zone rate resolution | ZIP TaxZonesSettingAdmin, L1 binding | CLOSED | Browser created active `Closure Proof SST 7%`, bound to class 2 and zone 2. Screenshot: `output/playwright/after-8500-tax-rule-live.png`. |
| `tax.products.classification` | Operator-managed | `products.tax_class_id` | Product Editor Tax Class select | `POST/PATCH /admin/api/catalog/products` | `TaxQuoteService` selects an explicit rule | ZIP ProductEditorModal, L2 | CLOSED | Browser assigned Chronos Calibre 9 to class 2; editor placement and saved selection were verified after reload. Screenshot: `output/playwright/after-8500-product-tax-class.png`. |
| `tax.classes.assigned_count` | System-managed | count of `products.tax_class_id` | Tax Classes table | `GET /admin/api/tax-settings` with `withCount(products)` | operational admin result | ZIP TaxZonesSettingAdmin, L1 binding | CLOSED | Browser reload displayed linked `1 Product`; clicking it opened `/admin/catalog/products?taxClassId=2` and showed only Chronos. Screenshots: `output/playwright/after-8500-tax-class-count-link-final.png`, `output/playwright/after-8500-tax-class-filtered-products.png`. |
| `tax.checkout.quote` | System-managed | published configuration/rules/classes, order snapshots | tax configuration/rule admin controls | `TaxQuoteService` | Cart/Checkout tax summary and final order lines | existing approved storefront binding | CLOSED | Customer browser checkout for MY / Kuala Lumpur / 55100: RM650.00 + RM45.50 tax (7%) = RM695.50; console had 0 errors. Screenshot: `output/playwright/after-8500-checkout-tax.png`. |

## Gate result

Convergence mode: `OPERATIONAL_CLOSURE`.

The requested P0 path is closed by authenticated browser evidence on the actual XAMPP runtime at port 8500. The browser flow created a class, nationwide MY zone, and 7% rule; assigned a product through the ZIP-faithful Product Editor field; followed the live count link; and obtained the matching customer checkout quote. The checkout’s stale global-rate browser override was removed so the rendered amount remains the `TaxQuoteService` result.

This does not widen the closure claim to source-absent policy surfaces such as inclusive pricing, compound taxes, taxable shipping, date scheduling, or refund tax adjustments. Those remain separate P1/P2 work and require an approved UI source or explicit UI authorization.
