# Offline payment settings persistence mapping

## Boundary decisions

| Boundary | Value |
| --- | --- |
| Convergence mode | `REAL_DATA_BINDING` |
| Scope | Admin persistence for Direct Bank Transfer / DuitNow and COD configuration; Checkout availability consumption |
| Runtime truth | MySQL `store_settings` keys `bank_transfer` and `cod_config` |
| Admin ownership | Operator-managed |
| UI authority | L0: existing Admin forms, buttons and toast patterns are unchanged |
| External integration | None; offline payment methods |
| Release surface | Official Admin and `/checkout` routes |

## Capability mapping

| Feature ID | Admin/system producer | Service/API contract | MySQL truth | Storefront consumer | Eligibility | Status | Evidence |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `payment.bank_transfer.persist` | Admin Bank Transfer settings and account actions | `PUT /admin/api/commerce-settings/bank-transfer` | `store_settings.bank_transfer` | `PaymentMethodAvailabilityService` -> `/checkout` | Enabled plus at least one active bank account | CLOSED | Admin feature test proves persistence despite invalid online gateway; checkout test proves visibility |
| `payment.cod.persist` | Admin COD settings | `PUT /admin/api/commerce-settings/cod` | `store_settings.cod_config` | `PaymentMethodAvailabilityService` -> `/checkout` | Enabled, amount and region rules | CLOSED | Admin feature test proves persistence despite invalid online gateway; checkout test proves visibility/eligibility |
| `payment.online.credentials` | Online gateway Configure Keys/enable action | Existing full commerce settings endpoint | `payment_gateway_credentials` | Hosted payment availability | Complete credentials for selected environment | SYSTEM_MANAGED | Existing credential validation tests remain green; offline saves no longer invoke this validation |
| `payment.bank_transfer.live-merchant-config` | Merchant re-saves the actual account details after prior rejected save | New bank-transfer endpoint | `store_settings.bank_transfer` | `/checkout` Bank / DuitNow card | Actual enabled config with an active account | BACKEND_CONTRACT_GAP resolved; operator action pending | Current production document is disabled with zero saved accounts after the former transaction rollback |
| `payment.cod.live-merchant-config` | Merchant re-saves COD rules after prior rejected save | New COD endpoint | `store_settings.cod_config` | `/checkout` COD card | Current cart must meet amount/region rules | SYSTEM_MANAGED | Current screenshot's RM50 maximum intentionally excludes the RM423.52 checkout |

## Current user-visible result

The repeated credential notice was caused by the previous full-document save: enabled Stripe and PayPal without credentials produced five validation errors and rolled back Bank Transfer/COD in the same transaction. Offline settings now save independently and only report success after the MySQL write succeeds.

No customer-visible layout changes were introduced. To make Bank / DuitNow appear in the live checkout, the merchant must save the intended bank account again because the earlier rejected request did not persist it. To make COD appear for the RM423.52 cart, set the maximum to `0` (unlimited) or at least that amount.
