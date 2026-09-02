# Customer Coupon Closure — Local Acceptance Evidence

Date: 2026-09-03

## Scope closed

The customer account no longer models a customer-owned Voucher Wallet. It now renders:

- Coupon rules currently usable by the signed-in customer.
- The signed-in customer's persisted `coupon_usages` redemption history.
- Rules that are no longer usable, labeled as unavailable.

`coupons` and `coupon_usages` are the runtime source of truth. Applying a code from Account or Cart only stores `coupon_code` in the session; it never creates a redemption. Checkout retains the locked, transactional redemption write.

## Rule path

`Admin Coupon API` → `coupons` → `CustomerCouponEligibilityService` → Account / Cart session → Checkout transaction → `coupon_usages`

The service applies active dates, global usage limits, customer usage limits (including legacy email-only usages), and cart minimum spend. Checkout independently rechecks the rule while locked before it persists an order and usage record.

## Automated evidence

Command:

```powershell
C:\xampp\php\php.exe artisan test tests\Feature\CustomerCouponClosureTest.php tests\Feature\OriginalAccountWorkspacePreviewTest.php tests\Feature\CheckoutCommerceRulesTest.php --compact
```

Result: 36 tests passed, 223 assertions. `git diff --check` also passed.

The dedicated closure coverage proves:

- account application is session-only and creates no usage row;
- a customer-exhausted code cannot replace a cart code from either Account or Cart;
- Admin Coupon API create/update data appears in the customer account and becomes unavailable when disabled;
- cents are formatted as storefront money rather than raw values;
- the account has a real form submission and no mock allowlist or wallet-success copy.

## Browser evidence on the canonical local app

The temporary local Laravel server at `127.0.0.1:8501` served the canonical working tree for the browser check. The Apache `:8500` virtual host is also configured for `C:\xampp\htdocs\jwstudio-ecommerce\public`; no backup directory was used or modified.

- The authenticated Admin Promotions view listed the actual `MERDEKA20` rule.
- The authenticated customer account showed `Available Coupons & Discounts`, `Eligible & Active (1)`, `Redeemed History (0)`, and `Unavailable (0)`.
- The rendered `MERDEKA20` card showed `RM 20.00`, `Min. spend RM 100.00`, and `ACTIVE`—not raw cents or a fabricated expiry.
- Submitting `MERDEKA20` with an empty cart stayed at `#vouchers` and displayed the server response: `Coupon requires a minimum spend of RM 100.00.`
- No real payment, order placement, shipment, or production-side action was performed.

## Release state

This is local development work only. No commit, GitHub push, or Hostinger deployment has been performed.
