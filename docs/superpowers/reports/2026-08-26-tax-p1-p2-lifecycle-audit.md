# Tax P1/P2 Lifecycle Audit

## Audit boundary

- Runtime truth: Laravel + MySQL in `C:\xampp\htdocs\jwstudio-ecommerce`.
- UI truth: `C:\Users\User\Downloads\jw-ecommerce-dashboard (13).zip`.
- Current proven P0: class/zone/rule creation, product assignment, assigned-product link, and shipping-address checkout quote.
- Rule: a database field or API endpoint is not considered operational if the merchant cannot safely control it through an approved UI and its downstream consumers do not honor it.

## Findings

| Capability | Schema/API | Runtime consumer | Approved UI | Status | Required closure |
| --- | --- | --- | --- | --- | --- |
| Master collection enable/publish | `tax_configurations.is_enabled`, `is_published`; API accepts both | `TaxQuoteService` honors both | No control in ZIP | `ADMIN_UI_GAP` / critical | Add explicit collection status and publish controls. |
| Disable a used rule | `tax_rules.is_enabled`; API accepts it | Quote filters disabled rules; used rules cannot be deleted | Status is display-only; modal has no control | `ADMIN_UI_GAP` / critical | Add Enabled toggle and explain disable-vs-delete lifecycle. |
| Effective scheduling | `effective_from`, `effective_until`; API and quote service honor it | Quote filters by current time | No fields in ZIP modal | `ADMIN_UI_GAP` | Add optional start/end datetime fields and scheduled/expired badges. |
| Conflicting rule protection | API previously accepted overlapping enabled rules at equal priority | Resolver picked one by ID | No special UI needed | `CLOSED_BACKEND` | Added stable validation for same class + zone + priority with overlapping periods. |
| Inactive tax class safety | `tax_classes.is_active` exists | Quote previously ignored class status | No class-status control | `CLOSED_BACKEND` for calculation; `ADMIN_UI_GAP` for control | Quote now excludes inactive classes; add class status control. |
| Shipping taxation | `applies_to_shipping` exists | Quote can tax shipping | API hardcodes false; no rule/global UI | `ADMIN_UI_GAP` | Add Applies to Shipping plus a designated Shipping Tax Class. |
| Compound taxes | API/type can emit `compound` | Quote selects one best rule and does not compound | No modal field | `BACKEND_AND_UI_GAP` | Approve compound semantics, expose control, then implement ordered calculation. |
| Address basis | Config schema supports shipping/billing/store-origin | Checkout currently supplies shipping address only; API now rejects unsupported alternatives instead of persisting a false claim | Existing ZIP select exposes all three | `SAFETY_GATED` / `ADMIN_UI_GAP` | Add billing-address and store-origin sources, then lift the server guard after acceptance. |
| Prices include tax | Config schema supports the boolean | Catalog and quote remain tax-exclusive; API now rejects `true` instead of silently mispricing | Existing ZIP checkbox claims gross display | `SAFETY_GATED` / `ADMIN_UI_GAP` | Add gross-price presentation and net/tax extraction across PLP/PDP/cart/checkout/order, then lift the server guard. |
| Live Tax Inspector | Protected `POST /admin/api/tax-settings/preview` now invokes `TaxQuoteService` with integer cents | Backend preview honors config, class, zone, dates, priority, and shipping inputs | Existing ZIP inspector still uses local multiplication | `CLOSED_BACKEND` / `ADMIN_UI_GAP` | Bind the generated inspector UI to the completed endpoint and render returned status/reason/lines. Live MySQL browser proof: class 2 + MY + RM100 returned rule 2 and RM7.00. |
| Refund tax accounting | Generic refund amount is persisted | No refunded-tax allocation or credit-note tax lines | Existing OrderAdmin refund asks only amount/reason | `BACKEND_AND_UI_GAP` | Add item/shipping selection and immutable refund-tax adjustment evidence. |
| Order tax detail | Immutable `order_tax_lines` exists | Admin Order API now exposes immutable tax lines, snapshot class/zone, refunds, and balances | Order detail has no tax-rule breakdown | `CLOSED_BACKEND` / `ADMIN_UI_GAP` | Bind the generated read-only tax evidence panel to the completed API fields. |
| Tax reporting/invoice evidence | Snapshots contain rule/class/zone metadata | Not mapped into tax report/receipt output | Existing report surfaces are static/mock-oriented | `CONSUMER_GAP` | Bind reports/invoices to immutable order and refund tax lines. |

## Safety conclusion

The shipping-address, tax-exclusive P0 path is operational. The entire tax subsystem is not yet fully closed because the existing UI allows two configuration claims that are not honored (`billing_address` / `store_origin`, and `pricesIncludeTax`) and omits the controls required to stop collection or disable a historical rule.

The next UI cutover requires a new Google AI Studio export. Backend work may continue on tests, preview contracts, tax evidence, and conflict safety without inventing presentation.
