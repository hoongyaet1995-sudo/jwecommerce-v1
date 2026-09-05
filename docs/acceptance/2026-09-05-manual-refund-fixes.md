# Manual refund correction batch

Mode: OPERATIONAL_CLOSURE (local verification only; no release claim).

Boundary: existing RMA manual refund only, no provider refund API or historical ledger rewrite. L1 existing input style reused for actual refunded amount in the historical order currency. Employees determine the externally agreed refund; JW records it, not tax policy or automatic allocation.

| Capability | Producer / MySQL / consumer | Evidence | Status |
|---|---|---|---|
| Actual amount | RMA confirmation / order_refunds / payment status | PHP API validation, amount incl tax, historical FX, excess and duplicate rejection | BACKEND_CONTRACT_GAP: browser mutation of new input still pending |
| Restock | RMA receive / product_variants.stock and return_request_events / catalog | PHP stock 4 -> 5, repeated receipt rejected | BACKEND_CONTRACT_GAP: browser stock replay pending |
| Payment labels | order_payments / OrderDataService / Admin Orders | browser confirmed COD and Partially Refunded | CLOSED |
| Checkout quote | shipping quote / submit label / Checkout | 320,375,390,768,1280 px: no overflow, Apply preserved, total updated, icon retained | CLOSED |

11 PHP tests / 83 assertions; 33 focused Admin tests; TypeScript and Admin build passed. No external money moved. Old demo refund remains MYR275, not retroactively rewritten to291.50. Previously received returns are not backfilled into stock. Legacy items with no live product_variant_id retain condition-only receipt; do not infer inventory restoration for deleted catalog variants.

Client manual remains the prior demonstration edition pending a new complete UI flow and updated screenshots. Synchronization requires separate user approval.
