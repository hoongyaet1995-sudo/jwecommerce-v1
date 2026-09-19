# Final acceptance: six user-defined business scenarios

User-approved completion boundary, 2026-09-14. These scenarios organize the current Marketplace + Basic WMS acceptance; do not add unrelated completion gates. Run against designated Shopee sandbox and Lazada official test sellers, with JW online development acceptance at https://ecom.jwaistudio.online after coherent local verification and approved exact-SHA deployment.

| ID | Business journey | Required observed result | Current result |
|---|---|---|---|
| E2E-01 | Create product on each marketplace, fetch into JW, import as JW product | Persisted JW product/variants and automatic exact provider-ID mappings; provider quantity remains a snapshot, physical quantity remains unknown until counted | Pending concentrated online run |
| E2E-02 | Create JW product, push to each marketplace | Provider-created product/model IDs independently readable; automatic exact mappings persisted | Partial prior Lazada evidence; both online journeys pending |
| E2E-03 | Independently create same-SKU products on JW and marketplaces, manually map products/variants | Employee selects exact identities; mappings survive reload; no stock mutation solely from mapping | Partial prior mapping evidence; combined run pending |
| E2E-04 | Create multiple mapped-product marketplace orders and complete them | Eligible orders reserve once; actual outbound reduces physical once and releases reservation; latest available reaches all mapped enabled test destinations sharing that inventory source | Pending |
| E2E-05 | Create/pay/fulfill JW orders | Same WMS ownership and ledger, no duplicate legacy deduction; latest available read back at mapped test destinations | Local fixtures only; online pending |
| E2E-06 | Change JW inventory using count/inbound/outbound/adjustment | Employee/time/reason/before-after ledger persists; latest available read back at all applicable mapped test destinations | Partial manual WMS evidence; automatic propagation pending |

For every run, record environment/SHA, test marker, provider store/product/model/order IDs, JW product/variant/order IDs, mapping rows, activation cutoff, warehouse/pool, starting physical/reserved/safety/available, movements, ending quantities, sync task/attempt IDs and independent provider readbacks. Mark platform-specific results separately so one provider cannot stand in for the other.

Use shared and independent warehouse fixtures within these journeys: shared destinations consume one balance; independent warehouse destinations receive only the quantity owned by their route. Historical/unmapped/cancelled/duplicate cases check correctness inside the order journeys, not a separate advanced scope.

All six must pass real business flows before goal completion. Existing valid evidence may be reused when it proves the same current behavior. Unit/HTTP fixtures, page loading or deployment alone are not final acceptance. Provider writes stay limited to explicitly enabled designated test stores with server identity verification. No real merchant production writes, logistics booking, transfers or advanced lifecycle scope is implied.
