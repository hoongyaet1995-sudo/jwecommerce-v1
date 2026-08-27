# EasyParcel Open API Sandbox and Fulfillment Closure Design

**Date:** 2026-08-27  
**Status:** approved design; implementation requires this document's review approval  
**Scope:** JW Ecommerce at `ecom.jwaistudio.online`, EasyParcel Open API only

## Purpose

Close the operational hand-off between paid/processing orders and shipment delivery using EasyParcel Open API Sandbox. Preserve the Google AI Studio Shipping & Delivery Management courier and fulfillment DOM/classes from `jw-ecommerce-dashboard (14).zip`; replace only mock state/actions with Laravel/MySQL truth.

The subdomain is a public Sandbox/Staging environment. It receives real SMTP messages for intentional test orders and is the public HTTPS target for later EasyParcel Sandbox webhook testing. It is not a Production courier cutover.

## Non-goals

- Do not integrate direct Pos Laju, J&T, Ninja Van, DHL, or other provider APIs in this phase. They may appear only as real service choices returned by EasyParcel.
- Do not create fake AWBs, wallets, latency, webhooks, pickup records, carrier rates, or production labels.
- Do not implement wallet top-up, auto-routing, automatic pickup, bulk production booking, billing reconciliation, return labels, automatic refund, or automatic restock.
- Do not expose application source, `.env`, OAuth credentials, SMTP credentials, or database credentials in the web root, browser response, Git, logs, screenshots, or design artifacts.
- Do not replace approved ZIP courier UI with a redesign or third-party component.

## Deployment topology

The host is Linux with PHP 8.2. The existing `ecom` web directory contains only the host's default page and has no application data. The main `jwaistudio.online` Laravel application remains untouched.

```text
ecom.jwaistudio.online
  web root: only the release public entry/assets
  application root: private account directory outside the web root
  storage: private, writable by the application
  database: dedicated Sandbox MySQL database
  worker/scheduler: enabled by the hosting control plane or a safe cron entry
```

Use an immutable release directory plus a `current` target (or an equivalent recoverable release switch). The public `ecom` entry point must reference the selected release's `public/` directory. Rollback must switch to the previous release without deleting the release being rolled back from.

The server environment contains the site URL, database connection, mail configuration, queue/cache settings, and Laravel `APP_KEY`. Provider credentials are not committed; their encrypted records are managed in the admin integration UI.

## Catalog and immutable order specification

Add package specifications to each product variant:

- existing `weight_grams`
- `package_length_cm`
- `package_width_cm`
- `package_height_cm`

The Variant Matrix is the only catalog edit source. A single-variant product uses its default variant. On checkout, copy selected variant specs to each order item. Existing checkout order data must remain unchanged when a catalog variant is edited later.

Pending AWB aggregates the item weight by quantity. It pre-fills the employee's actual-package L/W/H from the order snapshot but never assumes a packing algorithm. The employee may edit actual-package dimensions and weight for a combined parcel; that override belongs exclusively to the shipment request/snapshot.

## Provider persistence and secrets

Create a provider model sufficient for multiple providers without making other providers active:

```text
shipping_providers
  provider key, environment, enabled flag, connection state,
  safe public metadata, last verified time/error

shipping_provider_credentials
  provider + environment unique key,
  encrypted OAuth client id, client secret, optional webhook signing secret

shipment_quotes
  order/provider, request and response snapshots, selected service,
  expiry, actual package specification, idempotency reference
```

Use Laravel encrypted casts for credentials. All admin responses return only `configured`/`missing` credential status. The client secret is write-only. Token cache values remain server-side and expire according to the provider response.

Only `easyparcel` + `sandbox` may be enabled in this phase. Provider connection is marked connected only after successful OAuth and a safe API capability/quote call. Any failure records a bounded safe error and keeps the provider disabled.

## ZIP UI mapping

Restore the ZIP's `CourierConfigTab.tsx` and `CourierConfigModal.tsx` structures/classes into the current Shipping dashboard without copying source mock data.

| ZIP surface | Operational binding |
|---|---|
| EasyParcel card | The sole provider card capable of setup, connection test, enablement, quote and booking state. |
| Configure Credentials modal | Sandbox/Production selector; Open API OAuth client id/client secret, write-only save, connection test, provider callback URL. |
| Wallet panel | Render only provider-reported balance; no top-up action in this phase. |
| Other courier cards | Retain source structure but show truthful unconfigured state; no fake production, toggle, wallet, latency, or label action. |
| Pending AWB rows | Processing orders with no provider shipment; product-order snapshots are the only source for recipient, items and specs. |
| Rate selector | Real EasyParcel rate response for the exact confirmed actual-package/address request. |
| AWB/label actions | Available only after the provider reports a created Sandbox booking with a real test AWB/label. |

No client-side array, localStorage, timer, random ID, or mock event may own courier behavior.

## Order and fulfillment state machine

Order state and shipment state deliberately differ.

| Fulfillment view | Truth predicate | Order state | Allowed action |
|---|---|---|---|
| Pending AWB | `processing` order has no provider shipment | Processing & Verified | Review immutable item specs; obtain/refresh a quote. |
| Pending AWB quoted | an unexpired provider quote exists | Processing & Verified | Select service and confirm actual package values. |
| Ready to Ship | provider booking succeeded; real test AWB/label recorded; no collection event | Packed & AWB Generated | Print/download label; await collection/drop-off. |
| In Transit | provider tracking says collected, in transit, or out for delivery | Dispatched / In Transit | Read-only provider event sync. |
| Delivered | provider tracking says delivered | Delivered | Read-only history. |
| Exceptions | provider rejection, failed delivery, return, or mapped exception | retain current order state and flag attention | Inspect truthful provider reason; later explicit human resolution. |

`Processing & Verified` creates no fake shipment row; it becomes a Pending AWB candidate. A quote is persisted separately. Confirming a selected quote creates the canonical `shipments` row. The shipment stores provider reference, carrier/service, real tracking/AWB, label reference, currency/cost, quote snapshot, and confirmed package snapshot.

Provider booking uses an order/provider idempotency key and database locking. Duplicate clicks, job retries, and repeated provider events must not create a second booking or second AWB. Provider events are append-only and de-duplicated by provider external event identifier.

Only a verified delivered event may advance the canonical order to delivered. Exceptions never automatically cancel/refund/restock. Existing manual dispatch remains a separate truthful manual path.

## EasyParcel Open API boundary

Use EasyParcel Open API, not the legacy Individual API. The current Developer Hub flow is: register a developer app with platform URL, receive OAuth client credentials, use the Sandbox environment, then promote credentials separately for Production.

The adapter boundary has the following responsibilities:

1. Obtain/cache OAuth access tokens server-side.
2. Request a quote from immutable order address/item data plus employee-confirmed package values.
3. Persist normalized quote choices and raw safe audit response.
4. Create exactly one Sandbox booking for a confirmed quote.
5. Store returned provider reference, carrier service, test AWB/tracking URL, label reference, and actual charge.
6. Poll/ingest tracking events; normalize only known status mappings while retaining safe provider reasons.
7. Verify webhook signatures when the Open API documentation and generated app configuration supply a verification mechanism.

The implementation must follow the current Open API developer documentation rather than carrying over legacy endpoints/payloads. EasyParcel's Developer Hub documents OAuth, REST APIs, webhook integration and a Sandbox environment. [Developer Hub](https://api.easyparcel.com/) and [Open API documentation](https://easyparcel.github.io/OpenAPI) are the primary references.

## Background processing and webhook policy

On the public Sandbox site, outbound quote/booking calls occur synchronously only when the admin explicitly confirms an action and see a bounded response. Tracking reconciliation runs in a queue/job with a bounded retry policy and idempotent event handling.

The public callback endpoint is HTTPS beneath `ecom.jwaistudio.online`. It must return no order/customer details, reject unverified signatures, record a safe failure audit, and tolerate replay. Scheduled polling remains the fallback if a webhook is delayed. No `127.0.0.1` URL is ever presented to EasyParcel.

## Email behavior

Sandbox uses real SMTP for intentional test orders. All outbound order/verification/status messages use the persisted JW Studio shop brand. Test acceptance identifies the exact allowed recipient addresses; no bulk mail or customer data import is part of deployment.

## Security and deployment safeguards

- Rotate any SSH/database credential that was shared in conversation after initial setup; use a dedicated deploy key or limited deployment credential afterwards.
- Create server-side backups before schema/app cutover and use recoverable releases; never recursively delete a broad host directory.
- No production EasyParcel credential is accepted, enabled, or used during Sandbox implementation/acceptance.
- Admin courier actions require the existing shipping permissions and CSRF/session boundary.
- Labels are not public URLs; access is authorized and audit visible.
- Log request identifiers and redacted provider errors only. Never log OAuth tokens, client secrets, database passwords, customer address content, or raw signed webhook secrets.

## Test and acceptance gates

### Automated

1. Variant package fields validate and checkout snapshots them per order item.
2. Pending AWB projection returns only processing orders without provider bookings.
3. OAuth credentials encrypt at rest, never return from admin API, and only unlock enablement after a successful Sandbox verification.
4. Quote request maps immutable order data plus confirmed package data; expired/other-order quotes cannot be booked.
5. Booking is idempotent under duplicate request/retry concurrency.
6. Real provider response creates a shipment with truthful provider reference/AWB/label state.
7. Webhook and polling event de-duplication preserves append-only shipment history.
8. Delivered is the only provider event that advances the order to delivered; exception events do not restock/refund/cancel.
9. Order Tracking and customer account use the same shipment/event truth.
10. Existing manual dispatch tests continue to pass.

### Browser acceptance on `ecom.jwaistudio.online`

1. Sign in as an authorized admin.
2. Create/identify an intentional Sandbox order, pay/verify it, and move it to Processing & Verified.
3. Confirm it appears in Pending AWB with order item weight/dimensions prefilled.
4. Edit actual-package data, request EasyParcel Sandbox quotes, and choose a returned courier service.
5. Confirm a Sandbox booking and prove a single provider reference/AWB/label is recorded after refresh.
6. Verify Ready to Ship, then provider tracking/authorized sync moves it to In Transit and Delivered or a truthful exception.
7. Verify the same state and timeline in Order admin, customer account, and privacy-gated Order Tracking.
8. Capture before/after screenshots for the Google AI Studio UI mapping and operational proof.

Completion is forbidden until the browser workflow succeeds against the deployed site with a real Sandbox provider response. API mocks, static screenshots, direct database changes, and generated tracking strings are not acceptance evidence.
