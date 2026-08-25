# Payment Operational Closure Design

## Status

Proposed after source extraction. This design is not an implementation or release claim.

## Boundary decisions

| Boundary | Decision |
| --- | --- |
| Convergence mode | `OPERATIONAL_CLOSURE` |
| Scope | Checkout payment configuration, COD, Direct Bank Transfer / DuitNow, Stripe, PayPal, Billplz, transaction lifecycle and webhook handling |
| Runtime truth | MySQL: secure gateway credentials, payment attempts, callback audit events, bank/COD configuration snapshots and order payment fields |
| Admin ownership | Gateway availability, environment, credentials, bank accounts, COD rules and manual verification are operator-managed |
| External integration | Stripe production/Test mode first; PayPal Sandbox and Billplz Sandbox contracts; hosted provider checkout only |
| UI authority | Bank Transfer panel is L2 and sourced from ZIP `451A…FBB`; existing payment cards, Admin modal/toast and COD card are L1 reuse; online cancel/failure return remains a UI gap |
| Historical semantics | Order payment method, selected bank account snapshot, payment amount, currency, provider identifiers and events are immutable once the attempt exists |
| Cutover | Official `/checkout` after admin-to-storefront, provider, persistence and browser evidence pass |

## Architecture

### Provider model

`PaymentMethodAvailabilityService` builds the Checkout view model from persisted configurations. A method is eligible only when it is enabled, complete for its environment, supports the checkout currency/amount, and meets its special rules. Ineligible methods never render.

`PaymentGatewayAdapter` provides provider-specific creation, verification and normalization. The first adapters are `StripeCheckoutAdapter`, `PayPalOrdersAdapter`, and `BillplzBillAdapter`. All use provider-hosted approval pages; card numbers and CVVs never enter Laravel.

Gateway metadata remains in `store_settings.payment_gateways`. Secrets are stored separately using Laravel encrypted casts and are excluded from all Admin read responses. Enabling an online gateway without the environment's required credentials returns a Laravel validation response; the existing Admin modal/toast reports it and the toggle cannot persist as enabled.

### Transaction lifecycle

1. Checkout validates the selected eligible method and creates an immutable pending order/payment attempt in one transaction with an idempotency key and stock reservation.
2. Online adapters create a remote provider session/order/bill with the local order reference in provider metadata. The browser redirects to its approval URL.
3. Return URLs only locate and display the pending outcome. A verified webhook is the authority that marks payment succeeded, failed, cancelled or expired.
4. Every inbound callback is signature-verified and idempotently recorded. Provider IDs are unique.
5. Scheduled expiration releases unpaid reservations according to global payment rules; it never rewrites paid historical facts.

### Offline methods

Direct Bank Transfer appears only when `bank_transfer.isEnabled` and at least one active account exists. The accepted source uses `allowProofUpload`: when true, a receipt is optional; when false, its control is hidden. A transfer order remains pending verification whether or not a receipt was supplied. The selected account and readable payment instructions are snapshotted on the order.

COD appears only when its enabled amount and region rules pass. Its surcharge is calculated server-side, stored in the order payment snapshot, and included in the displayed total. It creates an unpaid collection state, not a captured online payment.

## Mapping contract

| Feature ID | Owner | Current source | MySQL truth | Producer -> consumer | Closure status | Acceptance evidence |
| --- | --- | --- | --- | --- | --- | --- |
| `payment.gateway.credentials` | operator-managed | Admin sends credentials then API clears them | encrypted credentials store | Admin Configure Keys -> adapter | `BACKEND_CONTRACT_GAP` | permission, encryption/non-exposure and missing-key tests |
| `payment.gateway.availability` | operator-managed | fixed alias map | saved gateway config plus credential completeness | Admin toggle -> Checkout method grid | `BACKEND_CONTRACT_GAP` | refresh persistence and disabled method hidden in browser |
| `payment.stripe.session` | system-managed | simulated card result | payment attempts and provider reference | Checkout -> Stripe Checkout -> signed webhook -> Order | `BACKEND_CONTRACT_GAP` | Stripe test-mode browser and webhook proof |
| `payment.paypal.order` | system-managed | fixed PayPal label | payment attempts and provider reference | Checkout -> PayPal Sandbox -> signed webhook -> Order | `BACKEND_CONTRACT_GAP` | Sandbox adapter/webhook tests |
| `payment.billplz.bill` | system-managed | no adapter | payment attempts and provider reference | Checkout -> Billplz Sandbox -> signed callback -> Order | `BACKEND_CONTRACT_GAP` | Sandbox adapter/callback tests |
| `payment.bank_transfer` | operator-managed | React mock configuration | bank transfer setting plus order snapshot | Admin accounts -> Checkout L2 panel -> pending verification order | `BACKEND_CONTRACT_GAP` | source-fidelity browser, order snapshot and manual verification tests |
| `payment.bank_transfer.receipt` | operator-managed | React local file state | owned payment receipt media | Checkout upload -> payment attempt | `BACKEND_CONTRACT_GAP` | MIME/size/ownership/authorization and browser upload proof |
| `payment.cod` | operator-managed | Admin-only config | COD setting plus order snapshot | Admin rules -> Checkout L1 card -> pending collection order | `BACKEND_CONTRACT_GAP` | region/amount/surcharge tests and browser proof |
| `payment.return.failure` | presentation-only | no accepted Laravel source | pending attempt state | provider return -> Checkout result | `STOREFRONT_UI_GAP` | approved source plus cancelled/failed browser proof |

## UI source constraints

- Port only the selected Direct Bank Transfer / DuitNow source region and its direct structural dependencies.
- Replace `DEFAULT_BANK_TRANSFER_CONFIG` with a Laravel adapter. Do not port mock banks, payment statuses, address autofill, card fields, USD, tax, fake provider activity, order/tracking records, or arbitrary mock copy.
- COD uses the existing payment-card structure with its current icon/card styling; no new information hierarchy is introduced.
- Reuse existing Admin Configure Keys modal, test connection button and toast pattern for missing credential validation.
- Do not invent an online-provider cancellation or failure result surface. It blocks final online closure until a source boundary is approved.

## Security and data handling

- Encrypt provider secrets at rest; redact secrets from logs, API resources and audit records.
- Require administrator authorization for all gateway configuration and manual verification changes.
- Use CSRF-protected checkout mutations; store provider webhooks as raw payload plus verified normalized fields, with restricted access.
- Accept only PDF, JPEG and PNG payment proofs; enforce server-side MIME inspection, maximum size, per-order ownership and non-public storage.
- Do not store payment card data, CVV or provider authorization credentials in orders.

## Acceptance plan

1. Admin: credential save, missing-key rejection, sandbox/live persistence and public non-exposure.
2. Checkout: refresh after enable/disable and validate eligible methods are visible/hidden.
3. Offline: create COD and Bank Transfer orders, snapshot all configuration, test receipt upload and manual approval.
4. Online: Stripe Test mode end-to-end with signed webhook; PayPal and Billplz Sandbox creation, callback and idempotency tests.
5. Browser: desktop and mobile before/after comparison of the selected Bank Transfer source region, plus account select/copy/upload/remove behavior.
6. Only then request official-route acceptance; report PayPal/Billplz production credentials as dormant until configured.

## Explicit exclusions

- Payment Element/custom card UI, marketplace payment settlement, split payments, subscriptions, refunds/disputes automation, and real provider production rollout for PayPal/Billplz.
