# RMA Source Extraction Manifest

## Request boundary

- Storefront ZIP: `universal-e-commerce-system-&-schema-suite (15).zip`
- SHA-256: `442981FC9F96235E3957120085DC9AE083388AFDC72056AE2E87441DE3918E17`
- Admin ZIP: `jw-ecommerce-dashboard (24).zip`
- SHA-256: `6BA4A9AC209D6400670951DA44D33D834D710D530B069196A0C2AC90381EA058`
- Target: official `/account#purchase_history` and protected `/admin/orders` surfaces.
- Selected Storefront region: item-level Return / Replace entry, request modal, RMA status card.
- Selected Admin region: Returns filter, row status chip, order drawer RMA workbench.
- Excluded: duplicate bottom customer CTA, duplicate lower Reload button, staff-created RMA entry, QA switchers, fixtures, localStorage, random IDs, simulated success, hard-coded date/SLA/address/carrier/currency.
- Acceptance intent: `OPERATIONAL_CLOSURE` using Laravel + MySQL.

## Baseline

- Both source apps passed `npm run lint` and `npm run build` in isolated temporary directories.
- Desktop and 390px Storefront interactions were inspected in the real browser.
- Known source defects: two customer return CTAs; source accepts four evidence files despite the brief saying five; Admin restores a duplicate Reload control and exposes RMA creation on an unpaid/unfulfilled order.

## Exact selected source

| Source path | Role | Laravel target | Status |
| --- | --- | --- | --- |
| `src/components/rma/ReturnRequestModal.tsx` | customer modal | `resources/views/storefront/account.blade.php` | selected, data adapter required |
| `src/components/rma/RmaStatusCard.tsx` | customer lifecycle card | `resources/views/storefront/account.blade.php` | selected, truthful-copy adapter required |
| `src/components/account/PurchaseHistoryTab.tsx` | insertion patterns | existing purchase-history order cards | selected regions only |
| `src/components/orders/OrderReturnRequestCard.tsx` | employee workbench | Admin Order drawer | selected, API adapter required |
| `src/components/orders/OrderAdmin.tsx` | filter/chip insertion patterns | existing Admin Orders | selected regions only |

## Preserved interactions and ownership

| Flow | Expected result | Data classification |
| --- | --- | --- |
| Customer submits eligible item request with up to five images | Laravel validates ownership/window/quantity and persists request, evidence and first event | backend adapter |
| Customer refreshes Purchase History | Current persisted lifecycle and next action render | backend adapter |
| Employee filters/searches RMAs | API-supplied RMA number/status/SKU drive results | backend adapter |
| Employee approves/rejects/receives/resolves | Transactional transition and audit event persist | backend adapter |
| Refund resolution | Existing refund ledger is used | available adapter |
| Same-SKU replacement | New persisted replacement order is created, without fabricated dispatch/tracking | backend adapter |

## Approval record

- Selected and trimmed extraction boundary approved by the user on 2026-09-03.
- Official existing-region integration approved; GitHub and Hostinger synchronization remain unapproved for this phase.
