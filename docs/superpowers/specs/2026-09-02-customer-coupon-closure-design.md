# Customer Coupon Closure Design

## Decision and boundary

| Boundary | Decision |
| --- | --- |
| Convergence mode | `OPERATIONAL_CLOSURE` |
| Scope | Close the existing `coupon_code` promotion path across Admin, customer account, cart, checkout, order snapshot, and usage ledger. This unblocks P1-016; it does not itself close P1-016. |
| Explicit exclusions | Customer-owned Voucher Wallet, points, cashback, Mix & Match, BOGO, tiered deals, coupon issuance/claim inventory, real payments, shipping bookings, deployment, and GitHub synchronization. |
| Runtime truth | MySQL: `coupons`, `coupon_usages`, `orders`. The cart-selected code is a server-side session value only until checkout creates immutable order and usage snapshots. |
| Admin ownership | Admin Promotions owns only real coupon-code campaigns through `/admin/api/coupons`. Non-coupon promotion labels must not be presented as operationally closed by this scope. |
| Storefront ownership | Laravel computes customer visibility and cart applicability. Blade renders the approved account design; Alpine retains presentation-only tab/copy feedback. |
| UI authority | Existing approved `resources/views/storefront/account.blade.php`; L0 for data/service work and L1 for replacing existing mock state with existing success/error and empty-state patterns. No Google AI Studio export is required for this scope. |
| Lifecycle | Coupons are editable realtime rules before checkout. Coupon usage and order discount/coupon fields are immutable historical records after checkout. No automatic coupon restoration on cancelled/refunded orders is introduced. |
| Release | Local XAMPP acceptance first. No official-route deployment or sync without later explicit approval. |

## Observed problem

The customer account view owns a static `$vouchers` array, fixed badge counts, hard-coded valid claim codes, and an Alpine-only `applyVoucher()` toast. The account payload factory also declares `vouchers` a gap. Those values can disagree with the actual Admin Promotion Coupon API, `coupons`, `coupon_usages`, cart session, and checkout service.

Existing operational assets to retain:

- `Coupon` provides active/date/global-cap eligibility.
- `CouponUsage` provides per-customer historical usage.
- `CheckoutService` locks the coupon, enforces per-customer use limit, writes a usage ledger row, increments global use count, and snapshots coupon data onto the order.
- `CartController::applyCoupon()` owns current cart-session application and minimum-spend validation.

## Customer-facing rule set

1. The account page is **not** a voucher wallet. A code is not claimed, owned, reserved, or transferred to a customer.
2. An account-page coupon is `eligible` when it is active, inside its date window, below its global usage limit, and the signed-in customer has not reached its per-customer limit.
3. Minimum spend, cart contents, shipping, tax, and final discount are cart/checkout-time calculations. The account page may show the configured minimum-spend condition but must not promise final eligibility without a cart.
4. `used` is a real `coupon_usages` record for the authenticated customer, including coupon, order number, used timestamp, and actual discount amount. A coupon may therefore appear once as `eligible` and also have historical `used` entries when its per-customer limit remains available.
5. `expired` contains only rules that are no longer customer-usable because they are disabled, outside the time window, globally exhausted, or have reached the customer's limit. It is not a record of a synthetic wallet expiry.
6. "Apply to Cart" and the code-entry form use a protected Laravel action that reuses the same eligibility and session-writing contract as cart application. Success persists only in the current server-side cart session; it does not create a coupon usage record. Failure leaves the selected cart coupon unchanged.

## Components and contracts

### `CustomerCouponViewModelFactory` (new)

Accepts the authenticated `Customer` and returns the existing account UI shape:

- `eligible`: currently usable coupon cards, with code, title/name, description, discount type/value, configured minimum spend, and deterministic status label.
- `used`: real usage-history cards. These are derived from `coupon_usages`, not static examples.
- `expired`: currently non-usable coupon cards with a truthful reason label.
- `counts`: tab and dashboard counts derived from the same collections.

It must eager-load usage records and perform all identity matching through the canonical customer id, with normalized email retained only for legacy guest-order records. No PII beyond the authenticated customer is exposed.

### `CustomerCouponController` (new protected action)

A customer-authenticated account endpoint accepts one coupon code and calls a shared coupon-application service. It writes the existing `coupon_code` session key only after the coupon is current, within its personal limit, and satisfies the current cart minimum-spend condition. It redirects back to the account route with a server-generated success or validation message.

The endpoint has no arbitrary return URL and no Admin capability. It must not create a `coupon_usages` row, change coupon usage count, create an order, or invoke payment/shipping systems.

### `CustomerCouponEligibilityService` (new)

This Laravel service owns case-insensitive lookup, current rule checks, per-customer quota checks, cart minimum-spend checks, and stable errors. `CartController`, account action, account view-model factory, and checkout use the appropriate portions of this contract. Checkout retains its transaction/row lock as the final authority; account and cart pre-checks are advisory and must not weaken checkout race protection.

### Existing account Blade (binding only)

Keep wrappers, classes, responsive grid, tabs, controls, icons, and visual hierarchy. Replace only business constants and client-only mock branches with Blade view-model bindings and protected forms. Reuse the already-approved account success/error panel and the existing account empty-state visual pattern; do not introduce a new component or redesign.

The source currently contains unrelated mock account concepts such as points, 2FA, sessions, and returns. They are not modified or claimed closed by this batch; they remain separately visible scope risks.

### Admin Coupon API (no visual rewrite)

`/admin/api/coupons` remains the only Admin producer for this scope. The implementation must verify that an Admin create/edit/disable action changes the account view after refresh and cannot expose deleted or unavailable data.

## Mapping contract

| Feature ID | Producer -> contract -> MySQL -> consumer | Eligibility/time | UI level | Current status | Closure evidence |
| --- | --- | --- | --- | --- | --- |
| `coupon.admin-rule` | Admin Promotions -> `CouponApiController` -> `coupons` -> account view model, cart, checkout | Admin-editable realtime before checkout | L0 | `CLOSED` producer; downstream proof pending | API/authorization tests plus Admin-to-account browser refresh |
| `coupon.account-availability` | `CustomerCouponViewModelFactory` -> `coupons` + customer usages -> account eligible cards/counts | active/date/global cap/per-customer cap; realtime | L1 existing cards | `MOCK_ONLY` | feature test + authenticated desktop/mobile browser evidence |
| `coupon.account-history` | `CouponUsage` -> view model -> account used cards | customer ownership; immutable order usage | L1 existing cards | `MOCK_ONLY` | ownership/non-leak test, order/usage reconciliation, browser evidence |
| `coupon.account-unavailable` | rule evaluator -> account expired cards | disabled/date/global cap/customer cap; realtime | L1 existing tabs/empty state | `MOCK_ONLY` | feature test and browser evidence |
| `coupon.account-apply` | protected account action -> shared service -> session `coupon_code` -> cart/checkout | authenticated customer, current rule, personal cap, cart minimum spend | L1 existing claim/apply controls | `BACKEND_CONTRACT_GAP` | valid/invalid/empty-cart/personal-limit tests plus browser interaction |
| `coupon.checkout-finalization` | `CheckoutService` -> locked `Coupon`/`CouponUsage`/`Order` -> success, account history, Admin logs | transaction-time final check; immutable after order | L0 | `CLOSED` regression; cross-surface proof pending | focused regression tests plus safe simulated/offline acceptance order |

## Error, authorization, and empty behavior

| Condition | Required behavior |
| --- | --- |
| Guest opens account/coupon apply route | Existing customer-auth redirect/denial; no coupon data or session mutation. |
| Unknown, disabled, expired, exhausted, or personally exhausted code | Account retains any existing cart coupon and shows a server-backed error in the approved status panel. |
| Valid code below minimum spend | Account retains any existing cart coupon and shows the same server-calculated minimum-spend message as cart/checkout. |
| Valid code accepted | Sets the existing session coupon code; after refresh, cart/checkout display the server-calculated result. No usage count changes until successful checkout. |
| No eligible/used/expired cards | Use the existing account empty-state visual language, with truthful zero counts and no fabricated coupon cards. |
| Admin disables/edits/deletes a coupon between account display and checkout | Checkout rejects it under lock; no order/usage/stock mutation is committed. |

## Test and browser evidence plan

1. Add focused failing Laravel feature tests before implementation for account view-model classification, customer ownership, protected apply action, personal/global/date eligibility, minimum spend, session persistence, and no usage mutation before checkout.
2. Retain and extend checkout/coupon regression coverage for transaction-time rejection and immutable `orders`/`coupon_usages` records.
3. Add Admin API-to-customer-consumer proof: create/update/disable a coupon in isolated tests, then assert the customer account payload changes and another customer cannot see the first customer's usage history.
4. Build Admin React only if API bindings change; otherwise do not alter its approved UI.
5. Browser acceptance on localhost: authenticated desktop and 390px account page; copy/apply valid code; invalid and below-minimum failures; cart and checkout reconciliation; one safe simulated/offline order; account history and protected Admin order; console errors; screenshots. Do not use real payment or carrier actions.
6. Only after all rows become `CLOSED` may the account Coupon capability be reported as operationally closed. P1-016 remains partial until its separate full journey matrix is rerun.

## Google AI Studio decision

No Google AI Studio prompt is required now: the page already contains the required card, tabs, code entry, success/error panel, and reusable empty-state language. This is L0/L1 data-and-behavior closure.

If an implementation audit proves the existing account markup cannot truthfully render the required zero-card state without a new compound section, stop before adding UI and request a Google AI Studio export. The prompt must target only the Voucher tab's empty state and preserve every adjacent region.

## Acceptance and release gate

- No database reset/import/drop, real charge, real carrier booking/pickup, wallet action, GitHub push, or Hostinger deployment.
- Local source is modified only after an implementation plan is reviewed.
- After local verification, report the exact scope, tests, browser artifacts, remaining mock account surfaces, and changed files. Ask separately before any commit/synchronization/deployment.
