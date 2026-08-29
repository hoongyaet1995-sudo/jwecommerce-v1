# EasyParcel Sandbox Lifecycle Closure Design

**Date:** 2026-08-29  
**Status:** approved in chat; awaiting written-spec review  
**Scope:** JW Ecommerce EasyParcel Open API quote, booking, AWB, webhook ingestion, authoritative tracking reconciliation, and delivery-state projection

## Purpose

Close the operational path from a paid order through courier selection and EasyParcel booking to customer-visible delivery history, while preserving the existing Google AI Studio Shipping & Delivery Management UI. The implementation must distinguish provider-confirmed behavior from Sandbox simulation and must never claim that a Sandbox parcel physically progressed through a courier network.

The development database currently has no real customers. Existing development products and orders may be used for acceptance testing. Tests must still be recoverable: take a database backup before destructive or stateful browser acceptance, record each order used, and do not bulk-modify unrelated catalog, payment, inventory, customer, or CMS records.

## Confirmed constraints

- `C:\xampp\htdocs\jwstudio-ecommerce` is the first development and acceptance environment.
- GitHub `main` and Hostinger receive the exact locally accepted commit only after local tests, build, and browser proof.
- EasyParcel's public OAuth and webhook callbacks use `https://ecom.jwaistudio.online`; localhost is not registered as a provider callback target.
- The connected account remains the EasyParcel Sandbox account during this phase.
- EasyParcel documents that Sandbox shipment status does not progress and tracking notifications are not sent. Sandbox can prove OAuth, quotations, order submission, test-credit charging, provider references, AWB/label behavior, and webhook transport tests; a controlled Live shipment is required for final carrier-originated Delivered proof.
- The current 2026-06 webhook documentation describes topics and JSON payloads but does not define a signature or HMAC verification contract. An inbound webhook is therefore a notification hint, not authoritative state.
- Existing DOM structure, classes, light/dark treatment, copy hierarchy, and interactions from the approved Google AI Studio source remain unchanged except for data binding and required validation/input states already within the approved design language.

## Decision and alternatives

### Selected: webhook hint plus authenticated reconciliation

An inbound webhook is validated, de-duplicated, safely audited, and acknowledged quickly. It schedules a provider reconciliation job for a known shipment. Only a subsequent OAuth-authenticated EasyParcel Shipment Details or Tracking Status response may mutate shipment events, shipment status, order status, or customer tracking.

Scheduled polling remains the recovery path when a webhook is missing or delayed.

### Rejected: direct webhook mutation

Directly projecting the webhook payload into an order would be low latency, but the documented contract provides no cryptographic sender verification. It would allow a forged public request to mark an order delivered, returned, or cancelled.

### Rejected: polling only

Polling is authoritative and already implemented, but it cannot test EasyParcel's documented webhook topics, produces avoidable latency, and does not exercise the intended provider callback transport.

## Current implementation baseline

The application already contains:

- EasyParcel platform OAuth authorization and callback handling.
- Server-managed encrypted provider credentials and token refresh.
- Sandbox quotation and submit-order endpoints.
- Persistent quotation and shipment snapshots with duplicate-booking protection.
- Tracking Status polling with append-only event de-duplication.
- A unique queue job scheduled every fifteen minutes.
- Projection of authenticated tracking events into shipment, order, customer account, and privacy-gated Order Tracking read models.
- An explicit test proving that no public EasyParcel webhook route is registered without an authenticated mutation boundary.

This phase adds the missing packaging-confirmation and webhook-hint layers and proves the existing provider boundary against the connected Sandbox account.

## Canonical flow

```text
Paid / Processing order
  -> Pending AWB candidate
  -> employee confirms packed weight and L x W x H
  -> OAuth-authenticated EasyParcel quotation
  -> employee selects one returned courier service
  -> idempotent EasyParcel submit order
  -> provider reference + AWB/label reconciliation
  -> Ready to Ship
  -> webhook hint OR scheduled poll
  -> OAuth-authenticated tracking reconciliation
  -> In Transit / Delivered / Exception
  -> shared Admin, Account, and Order Tracking timeline
```

Order state and shipment state remain separate. Creating an AWB does not mean the courier has collected the parcel.

## Phase 0: package and order readiness

### Catalog snapshot source

Each order item retains the purchased variant's immutable:

- `weight_grams`
- `length_cm`
- `width_cm`
- `height_cm`

Catalog changes made after checkout must not rewrite the order item snapshot.

### Packing confirmation

Pending AWB pre-fills actual weight by summing item weight multiplied by quantity. It may pre-fill dimensions only when a defensible single-parcel value exists. It must not invent combined-box dimensions for multiple items or multiple quantities.

The existing EasyParcel booking modal receives editable, required fields for packed:

- actual weight in kilograms
- length in centimetres
- width in centimetres
- height in centimetres

The confirmed values belong to the quotation/package snapshot, not the catalog product. A new quote is required whenever an employee changes the confirmed package values.

### Test orders

Acceptance uses at least:

1. one prepaid West Malaysia order;
2. one COD order whose amount is within the chosen service's returned COD limits;
3. one invalid/incomplete package case;
4. one duplicate-booking case.

An existing development order may be used if its address, payment, items, and package snapshots are complete. Otherwise create a clearly named Sandbox test order rather than silently rewriting historical order-item snapshots.

### Exit gate

The booking modal shows complete sender/receiver/package data and can request a quote without the current "weight and dimensions must be configured" failure. Invalid or missing values are rejected before contacting EasyParcel.

## Phase 1: OAuth callback regression

The already connected account is not treated as permanent proof. Automated tests cover:

- valid success callback;
- user-denied callback;
- missing, tampered, expired, or replayed state;
- authorization transaction bound to the wrong session;
- access-token refresh and refresh failure;
- provider disconnect/reconnect without exposing secrets.

### Exit gate

Only a valid one-time callback can establish a connected Sandbox provider. Callback retries do not create duplicate provider credentials or change an existing connection after the transaction is consumed.

## Phase 2: quotation

The application sends the exact confirmed package snapshot, immutable order recipient data, configured sender address, parcel value, and payment/COD requirement to `POST /open_api/2026-06/shipment/quotations`.

The normalized quotation persists:

- provider request identity needed for audit;
- provider/environment/configuration version;
- exact package and address fingerprints;
- courier and service identifiers;
- pickup/drop-off attributes;
- COD availability and applicable limits;
- currency and total charge;
- expiry time.

Raw tokens and unnecessary address payloads are not logged. Changing the order address, provider connection, package values, or quote expiry invalidates booking eligibility.

### Exit gate

The modal renders only services returned by EasyParcel for that request. A COD order cannot choose a prepaid-only service. An expired, foreign-order, stale-provider, or altered-package quote cannot be booked.

## Phase 3: booking and AWB

Booking uses the selected provider service and an application-owned idempotency guard. The provider call runs outside a database transaction; the result is reconciled inside a short locked transaction.

The canonical shipment stores:

- EasyParcel shipment and order references;
- courier/service identifiers and names;
- package and selected-quote snapshots;
- charged currency and amount;
- AWB/tracking number when present;
- authorized label reference when present;
- provider configuration version used for booking.

If EasyParcel accepts the order but the HTTP response is lost or AWB is initially null, the order enters reconciliation-required state. It must not issue a second provider booking. Shipment Details and AWB webhook hints are used to recover the missing result.

### Exit gate

One employee action creates at most one EasyParcel shipment. A real Sandbox provider reference is stored. When AWB exists, the Shipping view becomes Ready to Ship and the label action is authorized. Refresh, re-login, double-click, retry, and timeout recovery do not create a second booking or charge.

## Phase 4: webhook ingress

### Endpoint

Add one public HTTPS POST endpoint beneath `ecom.jwaistudio.online`. It is outside browser authentication and CSRF but has:

- POST and JSON-only handling;
- strict request-size and field-length limits;
- allow-listed topic schemas;
- rate limiting and queue back-pressure;
- correlation only to known provider references/AWBs;
- deterministic receipt de-duplication;
- immediate HTTP 200 after durable receipt/queue dispatch;
- no order/customer data in the response.

An opaque endpoint token may reduce unsolicited traffic, but it is not treated as authentication because URLs can appear in logs. The OAuth-authenticated follow-up query is the trust boundary.

### Receipt audit

Persist a sanitized receipt record containing:

- topic;
- provider UUID when present;
- payload hash;
- correlated shipment id when found;
- received/queued/processed timestamps;
- processing outcome and bounded safe error code.

Do not persist full raw customer/address payloads or secrets. Duplicate receipts are acknowledged without queuing duplicate work.

### Topics

| Topic | Action |
| --- | --- |
| `shipment.created` | Correlate provider shipment reference and schedule Shipment Details reconciliation. No direct lifecycle transition. |
| `shipment.awb.update` | Schedule Shipment Details reconciliation to retrieve authoritative AWB, label and tracking references. |
| `shipment.status.update` | Schedule Shipment Details and, when AWB exists, Tracking Status reconciliation. |
| `shipment.tracking.update` | Schedule high-priority Tracking Status reconciliation for the known AWB. |
| `ondemand.status.update` | Acknowledge as unsupported for this phase; do not mutate standard shipments. |

Malformed, oversized, unknown-topic, and unknown-shipment payloads fail closed without mutating commerce state. The response policy must avoid creating provider retry storms while preserving an operator-visible audit outcome.

### Exit gate

EasyParcel Developer Hub validates the deployed endpoint. Test deliveries for Topics 1 through 4 receive HTTP 200, appear once in the sanitized receipt audit, and schedule at most one reconciliation job.

## Phase 5: authoritative lifecycle projection

Webhook-triggered and scheduled reconciliation share the same service. Only OAuth-authenticated provider responses append `source=easyparcel` shipment events.

| EasyParcel code | Provider meaning | Shipping view | Order projection |
| --- | --- | --- | --- |
| 2, 7 | To be collected / scheduled | Ready to Ship when AWB exists | remain Processing |
| 3, 11 | Collected / dropped off | In Transit | advance Processing to Shipped |
| 4 | Delivery in transit | In Transit | remain Shipped |
| 5 | Delivered | Delivered | advance Shipped to Delivered |
| 0 | Cancelled | Exceptions | retain current order status for human resolution |
| 8 | On hold | Exceptions | retain current order status for human resolution |
| 6 | Returned | Exceptions / Returned | retain current order status for human resolution |
| unknown | Provider update | preserve safe timeline note | no order transition; alert/observe |

Rules:

- Events are append-only and de-duplicated by a stable identity derived from provider, AWB, timestamp, code, description, and location.
- Out-of-order events may enrich history but cannot regress a terminal shipment or order state.
- Delivered may first advance a still-Processing order through Shipped and then Delivered in the same transaction so audit history remains valid.
- Cancelled, On Hold, Returned, failed delivery, and unknown states never automatically refund, restock, cancel payment, or cancel the order.
- A stale OAuth account/configuration response or response for a replaced AWB cannot mutate state.

### Exit gate

Admin Shipping tabs, Admin Order history, customer Account, and privacy-gated Order Tracking all show the same canonical shipment state and timeline after refresh and in a new session.

## Phase 6: Sandbox acceptance

### Provider-originated proof

Using the connected Sandbox account:

1. obtain a real quotation;
2. submit one prepaid Sandbox booking;
3. prove the provider shipment/order reference and any returned AWB/label;
4. repeat with COD only when a returned service explicitly supports the amount;
5. register the deployed webhook endpoint and issue Developer Hub test deliveries for supported topics;
6. prove webhook receipts trigger OAuth-authenticated reconciliation;
7. prove scheduled polling remains operational when no webhook is delivered.

### Deterministic lifecycle proof

Because Sandbox shipment status remains static, official 2026-06 payload fixtures and authenticated Tracking Status response fixtures cover codes 2, 3, 4, 5, 6, 7, 8 and 11. These tests prove normalization, idempotency, ordering, state projection, and UI rendering. They are labelled simulated lifecycle evidence, not provider-originated delivery evidence.

### Negative and resilience matrix

- duplicate click and duplicate provider response;
- quote expiry and package/address changes;
- provider accepted booking with client timeout;
- AWB initially null and later recovered;
- duplicate, replayed, out-of-order, stale, malformed, oversized, and unknown webhook payloads;
- unknown AWB/provider reference;
- token expiry and refresh;
- provider disconnect during an in-flight request;
- EasyParcel 4xx, 5xx, timeout, malformed JSON, and partial batch response;
- queue retry and scheduled polling overlap;
- repeated Delivered event;
- Return/On Hold without refund, restock, payment cancellation, or order cancellation.

### Exit gate

All automated tests pass in the isolated `jw_ecommerce_testing` database. Browser proof on localhost covers package confirmation and UI state. Provider callback/webhook proof runs on the deployed HTTPS site. Every acceptance artifact identifies whether it is local fixture, Sandbox API, Developer Hub webhook test, or actual Live carrier evidence.

## Phase 7: controlled Live closure

Sandbox completion does not authorize an automatic production cutover. After a separate approval:

1. authorize the Live EasyParcel account;
2. use a low-value prepaid order with an approved sender/recipient;
3. choose and pay for one real courier booking;
4. apply/print its real AWB and hand it to the courier;
5. observe webhook receipt plus OAuth-authenticated tracking reconciliation through pickup, transit, and Delivered;
6. verify Admin Shipping, Order history, Account, Order Tracking, notification behavior, and no duplicate events;
7. repeat with COD only after prepaid closure is accepted.

Only this phase may be described as a real carrier-to-Delivered operational closure.

## Components and ownership

| Unit | Responsibility |
| --- | --- |
| Package confirmation UI | Capture employee-confirmed packed measurements without altering order/catalog snapshots. |
| Fulfillment service | Quote and book using immutable request snapshots and idempotency guards. |
| Webhook receiver | Validate, sanitize, de-duplicate, audit, acknowledge, and enqueue only. |
| Reconciliation job/service | Fetch authenticated Shipment Details/Tracking Status and normalize authoritative facts. |
| Shipment service | Append events and project legal shipment/order transitions transactionally. |
| Tracking scheduler | Fifteen-minute fallback and bounded retries for active EasyParcel shipments. |
| Shipping read model | Project Pending AWB, Ready to Ship, In Transit, Delivered, and Exceptions from MySQL truth. |
| Customer tracking read models | Reuse the same shipment/order truth without separate browser state. |

No React fixture, localStorage entry, timer, random tracking string, direct database edit, or manual Admin event counts as EasyParcel acceptance evidence.

## Observability and operations

- Record sanitized provider request IDs, webhook receipt hashes, shipment correlation, attempt count, duration, and stable error code.
- Never log tokens, client secret, database credentials, full webhook raw bodies, or unnecessary customer address data.
- Expose operator-visible states for queued, reconciled, ignored duplicate, unknown shipment, retrying, and terminal failure.
- Alert on repeated webhook failures, tracking sync age beyond threshold, token refresh failure, and a booked shipment without AWB beyond a bounded interval.
- Provide a safe, authorized retry/reconcile action. It must not call submit-order again.

## Test layers

1. **Unit:** payload normalization, status mapping, receipt identity, package validation, quote invalidation.
2. **Feature:** permissions, callback replay resistance, quote/booking idempotency, webhook ingress, queue dispatch, reconciliation projection, failure safety.
3. **Integration with HTTP fakes:** official request/response contracts for quotation, submit order, Shipment Details, Tracking Status, token refresh, and webhook topic fixtures.
4. **Local browser:** existing Google AI Studio modal, package fields, rate selection, booking states, status tabs, and shared tracking timeline.
5. **Sandbox API:** real OAuth token, quote, submit order, test credits, provider reference, AWB/label when supplied.
6. **Deployed webhook:** EasyParcel Developer Hub endpoint validation and test topic deliveries on `ecom.jwaistudio.online`.
7. **Live pilot:** one physical prepaid parcel through Delivered; COD follows only after approval.

## Release sequence

1. Back up the localhost development database and identify the acceptance orders.
2. Implement and test only in `C:\xampp\htdocs\jwstudio-ecommerce` using `jw_ecommerce_testing` for automated tests.
3. Build Admin assets and run authenticated localhost browser acceptance.
4. Commit the accepted source and push the same commit to GitHub `main`.
5. Deploy that exact commit to `/apps/jwecommerce` on Hostinger.
6. Verify local, GitHub, and Hostinger commit identity.
7. Configure/test the EasyParcel webhook URL only after the deployed endpoint is available over HTTPS.
8. Run Sandbox provider acceptance and retain rollback/database backup evidence.

## Completion definition

Sandbox closure requires real EasyParcel quotation, booking, provider reference/AWB behavior, webhook endpoint validation/test delivery, authenticated reconciliation, deterministic lifecycle/state-machine proof, and consistent Admin/customer read models. It must be reported as Sandbox closure.

Production delivery closure additionally requires a controlled Live parcel whose actual EasyParcel/courier events progress through collection, transit, and Delivered. No Sandbox fixture or direct webhook payload may substitute for that final proof.

## Primary references

- [EasyParcel Sandbox account and limitations](https://github.com/easyparcel/OpenAPI/blob/main/source/includes/2026-06/_sandbox.md)
- [EasyParcel environment selection](https://github.com/easyparcel/OpenAPI/blob/main/source/includes/2026-06/_environment_diff.md)
- [EasyParcel shipment quotations](https://github.com/easyparcel/OpenAPI/blob/main/source/includes/2026-06/_quotation.md)
- [EasyParcel submit order](https://github.com/easyparcel/OpenAPI/blob/main/source/includes/2026-06/_submitorder.md)
- [EasyParcel shipment details](https://github.com/easyparcel/OpenAPI/blob/main/source/includes/2026-06/_shipmentdetail.md)
- [EasyParcel tracking status](https://github.com/easyparcel/OpenAPI/blob/main/source/includes/2026-06/_tracking_status.md)
- [EasyParcel webhooks](https://github.com/easyparcel/OpenAPI/blob/main/source/includes/2026-06/_webhooks.md)
