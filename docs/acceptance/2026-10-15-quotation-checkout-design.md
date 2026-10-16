# Quotation acceptance and full-payment design

Status: source-grounded design awaiting review. User explicitly selected manual fulfilment with no automatic stock deduction on2026-10-15. No product code, migrations, financial records, provider calls or releases performed for this phase.

## Goal and approved direction

Customer opens a private quotation, accepts one exact version, completes a quotation-specific Checkout, obtains one pending-payment order, and can resume payment on that order. Only verified settlement changes it to paid. The existing enquiry, quotation snapshots and support conversation remain traceable. Full payment only; deposits, instalments, scheduling and provider onboarding excluded.

Local development root C:\xampp\htdocs\jwstudio-ecommerce. Baseline HEAD4241c216382decf28c6c8f84edeebbaa81df233e, existing large dirty tree preserved. Latest quotation phase evidence: 2026-10-15-quotation-integration.md. No commit/push/deploy approved.

## Inspected contracts and risks

- CheckoutService::checkout is cart/SKU-specific: asserts direct-purchase eligibility, reads current variant prices, calculates shipping/tax/coupon and mutates inventory. Quote-mode products cannot simply be passed through it.
- EnquiryQuotationVersion stores immutable independently denominated currency, quantities with up to2 decimals, prices with currency-specific decimals, item scope and total. It does not store per-line SKU mappings.
- OrderItem quantity is an integer in the current order contract; existing order formatting uses base cents plus currency_exchange_rate. These assumptions differ from arbitrary quotation quantities/currency decimals. Conversion must not truncate quantities, silently convert denominations or reapply exchange rates.
- PaymentAttemptService creates persisted pending payments, HostedPaymentGatewayManager starts existing configured providers, and the existing payment-proof/Admin verification path handles bank transfers. These should be reused; a browser success URL must never mark a payment paid.
- Existing public quotation access requires original guest session or authenticated customer owner. A UUID alone is not a portable authentication credential.

## Recommended ownership and state

An explicit quotation acceptance record associates enquiry, exact immutable sent version, accepted_at, authenticated owner when available, and one unique order reference. The quotation remains historically sent; acceptance/payment are separate facts. All state changes are POST with CSRF. Lock enquiry then quotation consistently with save/send/revise. Database uniqueness, not browser disabled buttons, enforces one conversion per accepted quotation. Repeat submissions/resume return the existing order; no duplicate order or payment charge.

Allow acceptance only of the latest sent, unexpired, non-withdrawn version. If a newer draft exists but is not sent, the latest sent version remains the offer until explicitly withdrawn; sent revision supersedes prior offers for new acceptance. Once accepted, commercial changes require an explicit later amendment/cancellation policy; do not silently permit new revisions to replace an accepted order.

## Customer journey and source fidelity

Reuse the approved quotation document, existing Checkout field/payment patterns, payment return/retry states, and success/order views. Add the existing primary-button pattern for Accept & proceed to checkout, plus accepted/pending-payment state. No redesign of cart Checkout or existing industry layouts. Quotation Checkout bypasses cart and coupon actions; existing cart/session and chosen storefront currency stay untouched.

1. Private document identifies exact version, total, scope and expiry.
2. Accept POST records acceptance idempotently and opens its Checkout.
3. Checkout collects contact and only necessary fulfilment details; payment methods come from real enabled configuration and eligibility checks.
4. Submit atomically creates the pending order, immutable quote linkage/snapshot and pending payment. Existing quotation totals are authoritative; tax, delivery and other charges must already be itemised. No extra coupon, shipping quote or surcharge is silently added. An ineligible payment method is rejected.
5. Hosted payment uses existing provider adapters and verified callbacks. Failure/cancel returns to this order and offers retry; ordinary cart return logic must not be used because it requires cart items.
6. Bank transfer stays pending until existing protected Admin verification confirms receipt. Customer receipt upload alone never implies paid.
7. Existing Orders/Admin/customer payment views receive a clear quotation reference and original scope. Enquiry won/lost status is not automatically equated with payment or fulfilment.

## Confirmed inventory decision

User selected the recommended first release: quotations are custom, manually fulfilled scope; conversion does not deduct or reserve SKU stock. This is appropriate for photography and logistics, but must be visibly disclosed to staff before using it for inventory-managed physical goods. Do not infer SKU/quantity from item name, enquiry variant, price or free text.

Alternative: first extend quotation items with explicit optional catalogue SKU mapping, whole-unit stock quantity and delivery classification, then implement the current stock-pool contract and stock reservation/release rules. This is a separate prerequisite, with inventory contract and phase acceptance reads required before implementation. No stock bypass or unsupported physical stock promise.

Checkout therefore requires contact details and provides an optional service/delivery address using existing address inputs. It does not advertise automatic shipping, stock availability or confirmed dates. Scope and any delivery charges are already agreed in the quote; staff handles fulfilment. Existing cart checkout remains unchanged.

## Money and quantity adapter gate

Recommended order representation: one purchased quotation scope/bundle, quantity1, no product_variant_id, price equal to accepted total. The immutable linked quotation retains every original line quantity/unit price/amount and is accessible from customer/Admin order details. A50-bottle quotation is one agreed scope containing50bottles, not a fake catalogue SKU or50units of a guessed stock item. This avoids changing existing integer SKU-quantity semantics or losing fractional service quantities. Checkout still lists the actual quoted breakdown.

Order/payment amounts must preserve the quotation's currency and exact payable amount. For existing two-decimal order/payment contracts, direct-denomination totals use exchange_rate1 so quotation prices are not converted twice. Other currency decimal precisions and provider eligibility must be explicitly mapped and tested before acceptance is enabled; if unsupported, show an actionable validation error before acceptance/order creation rather than rounding. A non-positive quote has no full-payment flow in this phase. No automatic FX, tax or surcharge is introduced.

## Portable links

Existing owner access can complete the local end-to-end flow. If portable WhatsApp/cross-device links are included, use a dedicated high-entropy, expiring, revocable grant, store its hash only, scope it to one quotation/customer journey, exchange it for a bounded session, and redact tokens from logs. Read access via a link is not acceptance. Revocation/expiry and superseded versions must not bypass checkout validity. Keep this separate from the current UUID route; never remove ownership checks globally. No sending to real recipients in this phase.

## Acceptance evidence

- Isolated SQLite TDD: private access and no email matching; latest/superseded/expired/withdrawn acceptance; accepted revision lock; CSRF; concurrent/repeated conversion; money/quantity snapshot accuracy; forged totals/coupons; gateway availability; bank-proof not paid; hosted callback authentication/idempotency; retry uses same order; ordinary cart unaffected.
- Tests must explicitly distinguish fake provider evidence from real provider readiness. No real charge or external message during testing.
- Scoped local MySQL migrations only, after saving catalogue/stock/order baselines. Local test order must be clearly identified and expected in baseline reconciliation; ordinary catalogue/stock changes prohibited unless SKU strategy approved.
- Browser: Admin published quote -> customer acceptance -> Checkout -> pending order -> payment confirmation via approved local test path -> persisted Admin/customer order; mobile and desktop; no console errors; screenshots.
- Reviewer checks pricing/ownership/idempotency/state transitions and stock invariants. Report exact limits and evidence. No completion by code presence or tests alone.

## Next execution boundary

After the inventory decision and review of this design, produce the concrete implementation plan with file/service/schema mapping, monetary/quantity adapter and failure cases. Execute locally only using the approved source. Keep one issue ledger with cumulative failures and3/6/9 escalation. Do not publish or enable real charging as an acceptance shortcut.
