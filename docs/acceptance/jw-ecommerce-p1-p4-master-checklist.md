# JW Ecommerce P1-P4 Master Acceptance Checklist

> Canonical acceptance tracker for `C:\xampp\htdocs\jwstudio-ecommerce`.
> Last baseline review: 2026-09-02. Baseline commit: `a63d5f938b9ff84b3f9a38a5be0af5062d96b75c`.

## Why this file exists

The project already had module-specific closure mappings, design specifications, tests, and release notes, but no single durable P1-P4 acceptance scope. This file is the master index. A new Codex conversation must read it before proposing the next acceptance task or claiming a phase complete.

This is a living acceptance record, not a feature wish list. Product scope can be added, removed, or re-prioritized only by an explicit user decision.

## Status legend

| Mark | Meaning | Rule |
| --- | --- | --- |
| `[x]` | CLOSED | Producer, Laravel/service boundary, MySQL truth, operational consumer, authorization/failure paths, relevant tests, and real browser acceptance are evidenced. |
| `[~]` | PARTIAL | Useful implementation/evidence exists, but at least one closure gate is still open. |
| `[ ]` | NOT ACCEPTED | In scope but not yet mapped and accepted end to end. |
| `[!]` | BLOCKED | A named external dependency or product decision prevents meaningful progress. |
| `[-]` | DORMANT / EXCLUDED | Deliberately outside the current active scope; this is not a completion claim. |

## Mandatory closure gate

A row may move to `[x]` only when its evidence covers all applicable links:

`Admin/customer producer -> protected Laravel/API/service -> MySQL truth -> operational consumer -> failure/permission handling -> automated test -> real browser acceptance`

Code presence, a database table, an API response, a unit test, or a visual screenshot alone is insufficient. For payments and carrier integrations, acceptance must not create an unintended real charge, booking, pickup, or shipment.

Every status update must add or refresh:

- `Last verified`: date and exact commit SHA.
- `Evidence`: mapping/report/test/browser artifact paths.
- `Open gate / next`: the smallest remaining acceptance action.

## Stable baseline

| ID | Status | Acceptance item | Evidence | Open gate / next |
| --- | --- | --- | --- | --- |
| P0-001 | `[x]` | Laravel + MySQL operational foundation and initial storefront/admin commerce migration | Historical P0 closure record; `tests/Feature`; baseline commit above | Preserve as regression baseline. Do not reinterpret P0 as P1-P4 completion. |
| P0-002 | `[x]` | Google AI Studio ZIP remains the UI/UX source authority | Project `AGENTS.md`; source-fidelity tests and mappings | Continue to bind data/behavior without self-redesign. |

## P1 - Customer revenue path and merchant-owned commerce

Goal: a merchant can publish real catalog content and a customer can discover, configure, purchase, and later inspect an order using Laravel/MySQL truth.

| ID | Status | Acceptance item | Current evidence | Open gate / next |
| --- | --- | --- | --- | --- |
| P1-001 | `[x]` | Storefront shell, branding, announcement bar, navigation, currency context | `ShopBrandingTest`, `StorefrontAnnouncementBarTest`, `StorefrontCanonicalHeaderTest`, `StorefrontCurrencyContextTest` | Regression only. |
| P1-002 | `[x]` | Catalog CRUD, taxonomy, handles, categories/collections, product publication | `Admin/CatalogApiTest`, `CatalogTaxonomySlugTest`, `CatalogProductHandleTest`, storefront catalog tests | Regression only. |
| P1-003 | `[x]` | Product variants, media lifecycle, PDP content and visual selection | Catalog media/PDP/variant feature and Admin React tests | Regression only; never replace accepted PLP/PDP DOM without approval. |
| P1-004 | `[x]` | PLP filtering, sorting, path resolution and real catalog-only presentation | `StorefrontCatalogFilterTest`, `StorefrontCatalogPathTest`, `StorefrontMockClosureTest` | Regression only. |
| P1-005 | `[x]` | Cart, quantity, variant snapshot, quick add and cart drawer | Checkout/package snapshot and quick-add tests | Keep cart and checkout price snapshots consistent. |
| P1-006 | `[x]` | Coupon application across cart and checkout with server-side recalculation | Checkout commerce rules and verified checkout UI closure | Regression with valid, invalid, expired, minimum-spend and replay cases. |
| P1-007 | `[x]` | Checkout contact/address, saved-address acceleration, shipping/tax quote, final authorization | `CheckoutSavedAddressTest`, `CheckoutCommerceRulesTest`, shipping/tax mappings | Regression only. |
| P1-008 | `[x]` | Shipping-rate configuration, address eligibility, quote, and immutable order snapshot | `docs/superpowers/reports/2026-08-25-shipping-operational-closure-mapping.md` | Regression only. |
| P1-009 | `[x]` | Basic tax-exclusive, shipping-address-based tax classes/zones/rates and checkout snapshot | Tax Admin closure mapping and tax/checkout tests | Advanced lifecycle remains P2-007. |
| P1-010 | `[x]` | Offline payment and payment-proof review without changing order source truth | `docs/operations/payment-proof-order-closure-mapping.md`; offline/payment-proof tests | Regression only. |
| P1-011 | `[x]` | Hosted payment availability, return/webhook verification, retries and persistence contract | Billplz, PayPal, Stripe and hosted-payment feature tests | Live-provider smoke must remain non-destructive and credential-aware. |
| P1-012 | `[x]` | Customer authentication, account operations, guest-order reconciliation and order history | Customer account/auth-related feature tests | Regression only. |
| P1-013 | `[x]` | Customer order tracking read model and verification | `docs/superpowers/reports/2026-08-27-order-tracking-closure-mapping.md`; order tracking tests | Regression only. |
| P1-014 | `[x]` | Product review authorization, persistence and operational moderation | Product review closure tests and Admin review bindings | Regression only. |
| P1-015 | `[x]` | CMS pages, drafts, publication, media and shared public renderer | CMS/Admin page feature tests and Admin React CMS tests | Regression only. |
| P1-016 | `[~]` | Full P1 browser journey matrix: guest + signed-in, desktop + mobile, success + failure | `docs/acceptance/2026-09-03-p1-browser-acceptance-live.md`; `docs/acceptance/2026-09-05-p1-016-minimal-browser-reacceptance.md`; `docs/acceptance/2026-09-05-admin-currency-display.md` | Controlled checkout, correct SKU/cart state, coupon/tax total, and Admin persistence passed. The currency/icon defects are repaired locally and tested; existing-page polling and final customer browser re-acceptance remain to be recorded before full closure. |

## P2 - Post-order operations, fulfilment, accounting correctness

Goal: staff can safely operate orders after checkout, preserve financial/inventory truth, recover from human/provider errors, and retain an auditable lifecycle.

| ID | Status | Acceptance item | Current evidence | Open gate / next |
| --- | --- | --- | --- | --- |
| P2-001 | `[x]` | Order lifecycle, payment-proof review, customer notification and audit trail | Order/payment/notification feature and Admin React tests | Regression only. |
| P2-002 | `[x]` | Manual dispatch and forward-only shipment event audit | Shipping operational closure mapping; shipment tests | Regression only. |
| P2-003 | `[x]` | Unified fulfilment selection: EasyParcel, external courier, manual/in-house | Fulfilment/shipment API and Admin React tests; accepted Google AI Studio flow | Preserve provider-managed vs team-managed truth. |
| P2-004 | `[x]` | Fulfilment correction, details-only amendment, delivery destination amendment, and audit | Shipment correction/operational details/delivery details tests | Delivered shipment stays immutable; regression only. |
| P2-005 | `[x]` | EasyParcel OAuth/environment truth, quote/booking, AWB, webhook, tracking and recovery | `docs/superpowers/reports/2026-09-02-easyparcel-environment-truth-closure-mapping.md`; EasyParcel tests | No wallet/top-up or pickup API claims. Production actions require explicit safe test protocol. |
| P2-006 | `[~]` | Return/RMA, reshipment and exchange lifecycle independent from order cancellation | UI concepts and shipment exception branches exist | Create a dedicated closure map covering inventory, payment/refund, replacement shipment and customer-visible history. |
| P2-007 | `[~]` | Advanced tax lifecycle: enable/publish, disabling used rules, schedules, inactive classes, shipping tax, compound tax | `docs/superpowers/reports/2026-08-26-tax-p1-p2-lifecycle-audit.md` | Implement/accept remaining Admin producers and quote consumers. |
| P2-008 | `[ ]` | Refund and partial-refund accounting with proportional tax/shipping allocation | No unified closure evidence | Define immutable credit/refund ledger, gateway reconciliation and customer/Admin consumers. |
| P2-009 | `[ ]` | Multi-parcel, partial fulfilment, split shipment, backorder and partial cancellation | No unified closure evidence | Map order-line quantities to shipment parcels and inventory reservations. |
| P2-010 | `[~]` | Invoice/receipt generation with tax/payment/shipping evidence | Order/payment snapshots exist | Prove downloadable document, historical immutability and customer/Admin access. |
| P2-011 | `[~]` | Notification coverage for all payment/order/shipment/refund/return milestones | Notification producer/template/security tests exist | Build a milestone coverage matrix and close missing producers/failure retry evidence. |
| P2-012 | `[~]` | Staff authorization matrix and immutable audit coverage for sensitive commerce mutations | Permissions and audit paths exist per module | Produce one role/action matrix and browser-test denied/allowed paths. |
| P2-013 | `[~]` | Consolidated P2 browser acceptance pack | Module-level browser evidence exists | Execute happy/error/recovery journeys against localhost without destructive DB reset or real unintended carrier/payment actions. |

## P3 - Omnichannel operations and operator intelligence

Goal: optional growth/operations modules become real, database-backed capabilities rather than attractive mock dashboards.

| ID | Status | Acceptance item | Current evidence | Open gate / next |
| --- | --- | --- | --- | --- |
| P3-001 | `[-]` | Marketplace/omnichannel order management | Excluded from original P0 boundary | Activate only after explicit product decision and closure interview. |
| P3-002 | `[x]` | Bounded text AI customer support V1 with human handoff and audit | Local closure/model rubric plus `2026-10-07-support-ai-v1-live-acceptance.md`; released repair 9827a3e | Storefront/Facebook/WhatsApp live acceptance passed. Instagram permission limits and TikTok live acceptance excluded; V2/AI attachment analysis excluded. |
| P3-003 | `[-]` | Business reports and analytics using reconciled operational data | Excluded from original P0 boundary | Define metrics, owners, freshness, reconciliation and drill-through sources. |
| P3-004 | `[~]` | Smart routing decisions and operational rule engine | `docs/superpowers/reports/2026-08-31-smart-routing-functional-specification-audit.md` exists locally | The report is currently untracked; obtain product acceptance, implement real producers/consumers, then commit evidence. |
| P3-005 | `[ ]` | Direct non-EasyParcel carrier APIs (J&T, Ninja Van, DHL, GDEX, Pos Laju, etc.) | External courier/manual record supports team-managed operations only | Each provider needs a truthful capability passport, credentials, sandbox/live gates, webhook/reconciliation and failure recovery. |
| P3-006 | `[ ]` | Provider pickup orchestration and pickup audit | EasyParcel pickup intentionally dashboard-managed | Add only where a supported provider API and operational owner exist. |
| P3-007 | `[ ]` | Provider billing/wallet/cost reconciliation | Top-up intentionally remains in provider dashboard | Define read-only cost/charge reconciliation without claiming top-up capability. |
| P3-008 | `[~]` | Advanced promotion/campaign lifecycle and attribution | Coupon/basic promotion paths exist | Map scheduling, stacking, segmentation, budget/usage ledger and reporting truth. |
| P3-009 | `[ ]` | Cross-module operations dashboard with actionable drill-through | Individual Admin surfaces exist | Remove synthetic metrics; bind every card/badge/action to an operational source. |
| P3-010 | `[ ]` | Consolidated P3 browser and data-reconciliation acceptance pack | None | Create only after active P3 scope is explicitly selected. |

## P4 - Production hardening and go-live governance

Goal: prove the system is secure, observable, recoverable, performant, compliant, and reproducibly releasable.

| ID | Status | Acceptance item | Current evidence | Open gate / next |
| --- | --- | --- | --- | --- |
| P4-001 | `[~]` | Repeatable localhost -> GitHub `main` -> Hostinger exact-SHA release | Delivery contract and multiple verified releases, including baseline SHA | Turn release verification into a checked release record for every production phase. |
| P4-002 | `[ ]` | Database backup, restore drill, rollback and recovery-time evidence | Backups have been manually restored before | Create non-production restore drill; never prove this by clearing the active database. |
| P4-003 | `[~]` | Migration safety and production rollback strategy | Migration and MariaDB rollback tests exist | Audit every outstanding migration; document forward-fix/rollback procedure and backup gate. |
| P4-004 | `[ ]` | Security review: auth, roles, CSRF, rate limits, uploads, SSRF/XSS/SQL injection, secrets | Module-level authorization tests exist | Run a dedicated threat model and security acceptance report. |
| P4-005 | `[ ]` | Privacy, retention, consent, data export/deletion and legal copy | No consolidated evidence | Define Malaysia/customer-data obligations and operational owners before implementation. |
| P4-006 | `[~]` | Queue, scheduler, webhook retries, idempotency and dead-letter/reconciliation operations | Payment/EasyParcel/notification retry tests exist | Prove production scheduler/worker configuration, alerting and manual replay SOP. |
| P4-007 | `[ ]` | Observability: structured logs, error monitoring, health checks, alert ownership | No consolidated evidence | Define SLOs, dashboards, alert routes and redaction rules. |
| P4-008 | `[ ]` | Performance/load acceptance for storefront, Admin APIs, checkout and provider calls | Build/test evidence only | Set budgets; measure p95/p99, query count, cache behavior and concurrency/idempotency. |
| P4-009 | `[~]` | Accessibility, responsive and browser/device matrix | Source designs and ad hoc browser checks exist | Run WCAG-oriented keyboard, contrast, form, screen-reader and mobile viewport acceptance. |
| P4-010 | `[ ]` | Disaster recovery and operational continuity runbook | No consolidated evidence | Document incident roles, DNS/Hostinger/provider failure procedures and restoration rehearsal. |
| P4-011 | `[ ]` | Dependency, PHP/Node runtime, CVE and license governance | CI/build usage exists | Add repeatable dependency/security audit and supported runtime policy. |
| P4-012 | `[ ]` | Production smoke suite with safe payment/carrier boundaries | Manual live checks exist | Automate non-destructive HTTPS checks and separate sandbox/live action permissions. |
| P4-013 | `[ ]` | Final go-live sign-off pack and owner approval | None | Requires all required P1-P4 rows `[x]` or explicitly accepted `[-]`, with named residual risks. |

## Current phase summary

- P0: closed historical foundation.
- P1: functionality is broadly closed; consolidated cross-device/browser acceptance pack remains open (`P1-016`).
- P2: fulfilment and EasyParcel core are closed; returns/refunds, advanced tax, split fulfilment, invoices, permissions and consolidated acceptance remain open.
- P3: mostly dormant or not accepted; smart routing is only partial and its report is currently untracked.
- P4: release discipline exists, but full hardening/recovery/security/observability/performance/compliance sign-off is open.

## Update and retirement protocol

1. At the start of any new JW Ecommerce acceptance conversation, read this file and select the smallest open row(s).
2. Create or update a module closure mapping using the stable ID(s); do not silently widen scope.
3. Implement first in `C:\xampp\htdocs\jwstudio-ecommerce`, verify localhost, and update evidence/date/SHA here.
4. Synchronize to GitHub/Hostinger only after explicit phase release approval.
5. Never mark a row closed because UI exists or tests pass in isolation.
6. Never delete completed rows. They remain regression obligations.
7. Keep the memory pointer active until every required P1-P4 row is `[x]` or the user explicitly accepts it as `[-]`.
8. Only after final `P4-013` sign-off may a user-authorized memory update retire this active checklist pointer. Do not remove it automatically.

## Evidence index

- `docs/operations/payment-proof-order-closure-mapping.md`
- `docs/decisions/express-one-click-checkout.md`
- `docs/superpowers/reports/2026-08-25-shipping-operational-closure-mapping.md`
- `docs/superpowers/reports/2026-08-26-tax-admin-closure-mapping.md`
- `docs/superpowers/reports/2026-08-26-tax-p1-p2-lifecycle-audit.md`
- `docs/superpowers/reports/2026-08-27-order-tracking-closure-mapping.md`
- `docs/superpowers/reports/2026-09-02-easyparcel-environment-truth-closure-mapping.md`
- `docs/superpowers/reports/2026-08-31-smart-routing-functional-specification-audit.md` (currently untracked)
- `tests/Feature`
- `resources/admin/src/**/*.test.ts(x)`
