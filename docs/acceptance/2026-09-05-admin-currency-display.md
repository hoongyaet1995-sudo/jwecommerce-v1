# Admin currency display — local verification

Mode: REAL_DATA_BINDING. Changes are local and are not released.

## Ownership and mapping

Currency Settings (`currenciesSetting`, persisted by the existing commerce settings API) supplies the default code, left/right symbol, and decimal places to the shared Admin formatter.

| Surface | Amount interpretation | Result |
| --- | --- | --- |
| Catalog, product editor, collections, customer totals, Dashboard, command search, Commerce Hub | Base major units; format in the configured default without applying FX to editable values | Bound to shared formatter |
| Admin Orders list, item prices and summary | Base major units plus order currency and saved checkout FX rate | Bound to historical snapshot |
| Account latest purchase, history and invoice amounts | Base cents plus order currency and saved checkout FX rate | Bound to historical snapshot; PHP rendered-response regression passes |
| CMS inspector and generic product/price previews | Default currency from Currency Settings instead of the separate shop-settings string or a dollar literal | Bound to configured code |
| Provider quotes, marketplace orders and explicitly MYR-denominated fields (e.g. billing `varianceRM`, promotion `currentSpentMyr`) | Currency belongs to the provider/record/field contract | Existing explicit currency preserved; not blindly relabelled |

This change does not migrate the numerical base value of existing catalog or aggregate records when the default currency changes. Historical orders are not relabelled as the new default. Currency-specific report/provider contracts are not claimed as a completed multi-currency accounting system.

## Verification

- Admin TypeScript check passed.
- Complete Admin suite: 54 files / 351 tests passed, no unhandled errors in the final run.
- PHP currency/account suite: 17 tests / 137 assertions passed in the final focused run. Additional CMS/runtime/account run passed 33 tests / 359 assertions.
- Both Admin and storefront production builds passed. Existing Node-version and bundle-size warnings remain.
- Real authenticated localhost Admin Orders showed `JW-20260905094937-2161` as `RM 270.30`. Older USD orders retained their dollar symbol.
- Real authenticated localhost Catalog showed `RM 389.00`, `RM 650.00`, etc., using the current MYR default.
- The customer account browser session expired during rerun; its final formatting is verified by rendered Laravel response tests, including a USD historical rate of 0.25 while the shopper selects MYR. This is not represented as completed customer browser re-acceptance.
- Bank/COD icons are now registered in the storefront Lucide bundle.
- The full suite exposed a delayed copy-feedback timer in EasyParcelOAuthModal after unmount. Moving its lifetime into an effect with cleanup removed the unhandled error.

P1-016 remains partial: this scoped currency repair does not substitute for the outstanding existing-page order-polling and complete customer browser matrix proof.
