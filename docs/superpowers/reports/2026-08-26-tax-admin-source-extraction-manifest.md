# Tax Admin Source Extraction Manifest

## Request boundary

- ZIP archive path: `C:\Users\User\Downloads\jw-ecommerce-dashboard (13).zip`
- ZIP SHA-256: `4929b08396a1248ea3e1857335059553b684bcee4a4af917066d4b842ee460a2`
- Target Laravel project and route: Admin React entry at `/admin/catalog/products` and `/admin/settings/tax-zones`
- Requested page/region/function: Product Editor Tax Class select; Tax Classes and Geo Tax Zones add actions and persisted counts.
- Explicitly excluded ZIP regions: all other Catalog, Tax Engine, Reports, Storefront layouts, and unrelated dirty worktree changes.
- Acceptance intent: operational tax configuration closure, proven in the actual port 8500 Admin and storefront browsers.

## Exact selected source

| Source path | SHA-256 | Role | Laravel target | Status |
| --- | --- | --- | --- | --- |
| `src/components/catalog/ProductEditorModal.tsx` | `40c352ad564e9d5cf83b2cbfb7d1763fe5f043ecb1c162d2da5b623540785ab3` | Tax Class select below Primary Category and above Base Retail Price | `resources/admin/src/components/catalog/ProductEditorModal.tsx` | adapted with MySQL-backed options |
| `src/components/settings/TaxZonesSettingAdmin.tsx` | `0dfb77d0eba7767a1e7d7a511e66e19139219dfcec858c86ba328273f5d16f2f` | Add buttons, fields, tables, and rate-modal shell | `resources/admin/src/components/settings/TaxZonesSettingAdmin.tsx` | adapted with MySQL-backed class/zone actions |
| `src/types/index.ts` | `5b7f03d8166fcb5681e067ac4505ecc58a682fd6340d5fd2c8ab7d4d3f18b1eb` | `Product.taxClassId` source contract | `resources/admin/src/types/index.ts` | adapted |
| `src/types/oscommerceSettings.ts` | `f416f59e07ecce0314c788fe80864cacd3123cac5f7cf553ec05aef0c7d57de3` | Tax Class/Geo Zone view model | existing matching types | existing-compatible |

## Dependency map and preserved interactions

| Dependency or flow | Decision |
| --- | --- |
| Tax Classes options and assigned product count | existing adapter: protected `/admin/api/tax-settings`, `tax_classes` with `products_count` |
| Product classification save | existing adapter extended: protected `/admin/api/catalog/products`, `products.tax_class_id` |
| Add Tax Class and Add Geo Tax Zone buttons | existing ZIP handlers were inert because their modal JSX is absent. The approved existing Tax Rate modal shell/classes are reused for the missing controls; no new visual system introduced. |
| Checkout calculation | existing operational adapter: `TaxQuoteService` and persisted tax snapshots; browser acceptance proved RM45.50 tax on RM650.00 for the created 7% class/zone/rule. |

## Approval record

- Initial source boundary approved by: delegated user request, 2026-08-26.
- Dependency expansion approved by: same request, limited to the existing Tax Rate modal shell because the selected ZIP defines state/actions but omits the Class/Zone modal JSX.
- Official route cutover approved by: delegated user request; browser evidence gate passed on 2026-08-26.
