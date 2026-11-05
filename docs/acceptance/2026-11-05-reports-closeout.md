# Reports closeout — 2026-11-05

Scope: seven existing Reports only: Sales, Orders/enquiries, Products/inventory, Customers, Shipping, Payments/reconciliation and Marketing. AI Business Insights is cancelled and excluded. Shared AI Connect and other modules are preserved.

## Verified local release candidate

Exported from GitHub main 8ca8f16bd68492a1e46e16e04e0f46685ae4dfc3 with an explicit 39-file whitelist. Mixed sidebar uses only Reports changes. Existing settings routes remain unchanged; their stale route-test expectations were corrected. No .env, private files, AI lifecycle migration, conversations or expense records are included.

- Backend isolated SQLite: 50 tests / 466 assertions passed, 47.079 seconds.
- Frontend: 14 tests / 4 files passed, 3.43 seconds.
- Admin and SSR production build: exit 0, Vite 19.55 seconds. Existing font/image unresolved-at-build and large-chunk warnings remain.
- Read-only local diagnostic: required schema present. Sales desktop dark page previously observed authenticated, seven Reports tabs, no AI entry, data-coverage warnings visible. Further visual control blocked by Debugger unattached; mobile/light acceptance incomplete. Seven historical timeouts remain High attention; no blind retry.
- Marketing search filtering included; legacy cross-staff override removed and owner-private access regression-tested. Diagnose command no longer references cancelled AI preparation.

## Coverage and truthful limitations

| Report | Verified behavior | Remaining data limits |
| --- | --- | --- |
| Sales | Confirmed receipts/refunds by actual event date; currency/search/CSV and coverage | Historical undated payments excluded with warning; no invented dates |
| Orders/enquiries | Bounded lifecycle/funnel queries and date filtering | Source linkage and historical event coverage govern completeness |
| Products/inventory | Merchandise allocation guards and canonical current shared-stock reader | Missing promotion snapshots/unallocated or tax-bearing partial refunds unavailable; pre-count opening stock unverified |
| Customers | Purchasing/customer cohort scope and guest handling | Missing identity stays guest/unknown; no invented lifetime history |
| Shipping | Carrier/status/cost and verified event-date coverage | No inferred delivery dates or unsupported carrier settlement |
| Payments/reconciliation | Recorded payment/refund/tax evidence and exports | Actual provider fees/settlement, COD receipts, tax remittance, net profit and ROAS not verified |
| Marketing | Existing owner-private campaign analytics with report search | Attribution/source gaps remain explicit; no cross-staff permission expansion |

Local coverage snapshot: 11 confirmed payments missing trustworthy dates = 7 MYR + 4 missing/invalid currency; the latter 4 also have missing amounts. 32 orders lack promotion snapshots; 7 guest orders. No source data repaired. Production counts can differ and must be diagnosed after release. Production-volume performance is unverified; query bounds and unavailable states remain.

## Authorized release targets

GitHub main and both Hostinger applications are explicitly authorized for this phase. Preflight: corporate /home/u762348670/apps/jw-corporate (www.jwaistudio.online) and ecommerce /home/u762348670/apps/jwecommerce (ecom.jwaistudio.online) both clean at base SHA; distinct databases. Each lacks the two reporting date columns. Only 2026_11_04_140000_add_reporting_event_dates.php is authorized per database; nullable indexed fields, no backfill/delete. No general migrate, AI cleanup, credential/grant or environment change.

Release completion requires exact commit comparison and HTTPS verification; outcome is recorded separately after deployment. This document does not claim incomplete visual or external-data checks passed. Elapsed total/token usage unavailable.
