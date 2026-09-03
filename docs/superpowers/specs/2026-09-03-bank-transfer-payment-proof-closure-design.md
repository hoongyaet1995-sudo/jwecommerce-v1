# Bank Transfer Payment Proof Operational Closure

Date: 2026-09-03

## Decision

Bank In / DuitNow orders may be created before a receipt exists because the order number is generated only after checkout. A payment proof is nevertheless mandatory before an order can be verified or marked paid.

The customer-facing recovery path will exist on both the order-success page and the authenticated Purchase History. The checkout upload remains a convenience for customers who already transferred funds; it is not the only upload opportunity.

Google AI Studio source is the visual and interaction authority for the new compound upload/status regions. Codex must not invent those regions. Laravel and MySQL remain the authority for eligibility, ownership, validation, storage, status changes, and audit history.

## Root Cause

- Admin stores `requireProofUpload`, but checkout currently maps it only to `allowProofUpload`.
- The checkout request validates `payment_proof` as nullable.
- The current checkout copy explicitly labels the proof optional.
- The success page and customer account have no proof-upload mutation.
- Admin correctly refuses to mark a Bank In / DuitNow payment paid until its proof status is verified.

## Lifecycle

1. Customer selects Bank In / DuitNow and a destination account.
2. Customer may attach a receipt during checkout if payment has already been made.
3. Laravel creates the pending order and payment, assigns the order number, and stores any attached receipt privately.
4. If no receipt exists, the success page presents an action-required upload state with the order number and payment instructions.
5. The authenticated customer can later upload or resubmit from Purchase History.
6. Admin reviews the latest submitted proof and either verifies or rejects it with a reason.
7. A verified proof locks customer replacement and allows the existing paid transition.
8. A rejected proof exposes the rejection reason and allows a new submission.

## Capability Mapping

| Capability | Runtime truth | Producer | Consumer | Eligibility | UI level | Initial status |
| --- | --- | --- | --- | --- | --- | --- |
| Checkout proof attachment | `order_payments` private proof fields and proof events | Storefront checkout | Admin Orders review | Bank transfer, upload enabled, valid file | Existing approved component | `REAL_DATA_BINDING` |
| Success-page proof recovery | Same payment record and private storage | Session-authorized order success | Admin Orders review | Last session order, bank transfer, pending/unverified | L2 Google AI Studio source required | `STOREFRONT_UI_GAP` |
| Account proof recovery | Same payment record and private storage | Authenticated Purchase History | Admin Orders review | Customer owns order, bank transfer, pending/unverified | L2 Google AI Studio source required | `STOREFRONT_UI_GAP` |
| Proof verification gate | `order_payments.proof_status` and proof events | Authorized Admin reviewer | Order paid transition | Latest proof verified | Existing Admin UI | `CLOSED` after end-to-end retest |
| Proof resubmission | Latest proof pointer plus append-only proof events | Customer | Admin review | Missing/uploaded/rejected; never verified | L2 Google AI Studio source required | `BACKEND_CONTRACT_GAP` |

## Backend Contract

### Upload mutation

Use a customer-facing POST endpoint that accepts `payment_proof` and resolves authorization through one of two explicit paths:

- authenticated customer owns the order; or
- the order is the exact `last_order_id` retained in the checkout session.

The endpoint must reject:

- an order/payment ownership mismatch;
- a non-bank-transfer payment;
- a paid, cancelled, or otherwise ineligible order;
- a verified proof replacement;
- unsupported content or a file over 10 MB;
- a payment that is not the active bank-transfer attempt for the order.

Accepted content is JPG, PNG, or PDF verified from server-observed MIME. Files remain on the private local disk with randomized names. Store the replacement successfully before switching the database pointer; do not leave the payment pointing to a failed upload. Append an audit event for every accepted customer submission.

### Read model

Success and account view models expose only customer-safe fields:

- payment ID and method label;
- proof status: `missing`, `uploaded`, `rejected`, or `verified`;
- original filename and uploaded timestamp;
- rejection reason when present;
- `canUpload` and `canReplace` booleans computed by Laravel;
- payment expiry and selected bank-account snapshot where applicable.

They must not expose private storage paths, hashes, Admin identity, or unrestricted proof URLs.

## UI Source Boundary

Google AI Studio must return source for exactly two regions:

1. the Bank In / DuitNow payment-proof panel inserted into the existing `/order/success` order summary;
2. the payment-proof status/action block inside an expanded Bank In / DuitNow order in `/account#purchase_history`.

It must also update the existing checkout upload copy so it accurately explains that upload can happen now or after the order number is created. Adjacent page structure, navigation, cards, typography, spacing, colors, responsive behavior, and existing interactions remain unchanged.

Required visual states are `missing`, `selected/uploading`, `uploaded awaiting verification`, `rejected`, `validation failure`, `network/storage failure`, and `verified locked`. Desktop and mobile source are required.

## Error Handling

- Validation errors remain next to the source upload control and do not clear a valid current proof.
- A failed replacement leaves the previous proof and status intact.
- Duplicate clicks are disabled while uploading.
- Authorization failures return 403/404 without disclosing another customer's order.
- Verified proofs cannot be replaced through UI or direct requests.

## Acceptance

- A Bank In order without checkout proof is created pending and receives an order number.
- The same session can upload from success and Admin sees the private proof after refresh.
- The owning signed-in customer can upload from Purchase History after a new session.
- A different customer cannot upload to the order.
- JPG, PNG, and PDF succeed; invalid MIME and files over 10 MB fail visibly.
- Admin rejection returns a customer-safe reason and enables resubmission.
- Admin verification locks resubmission and allows the paid transition.
- Refresh and re-login preserve every state from MySQL.
- Desktop and mobile browser screenshots match the approved Google AI Studio source with no console errors or horizontal overflow.

## Release Gate

Implementation starts in `C:\xampp\htdocs\jwstudio-ecommerce`. Customer-visible integration may begin only after the latest Google AI Studio ZIP is supplied, its exact source regions are identified, and the source baseline is accepted. Local tests and browser acceptance precede any GitHub push or Hostinger deployment.
