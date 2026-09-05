# P1 Browser Acceptance Pack Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (- [ ]) syntax for tracking.

**Goal:** Close P1-016 only when the customer revenue path has one reproducible, evidence-backed browser acceptance pack across guest/signed-in, desktop/mobile, and success/failure journeys.

**Architecture:** This is an acceptance and evidence project, not a redesign or feature build. Laravel/MySQL stays the runtime truth and the approved Google AI Studio DOM/Tailwind stays unchanged. Each journey records prerequisite data, browser steps, final UI state, console/network result, and exact commit SHA before the master checklist may be updated.

**Tech Stack:** Laravel 12/PHP 8.2, MySQL/XAMPP, existing Admin React host, Chrome or Playwright, existing feature/Admin tests.

**Spec:** docs/acceptance/jw-ecommerce-p1-p4-master-checklist.md (P1-016)

## Global Constraints

- Work only in C:\xampp\htdocs\jwstudio-ecommerce for development and first acceptance.
- Do not alter approved Google AI Studio DOM, Tailwind classes, assets, copy, or layouts.
- Do not clear, drop, replace, import over, or destructively manipulate jw_ecommerce.
- Use isolated test databases for automated tests and controlled, non-destructive local browser data.
- Do not create real payment charges, carrier bookings, pickups, or provider wallet changes.
- Do not deploy or synchronize GitHub/Hostinger without separate explicit approval.
- A screenshot alone cannot close a row: browser state and Laravel/MySQL consumers must agree.

## Evidence Output

Create output/acceptance/p1-YYYY-MM-DD/ with a baseline README.md, redacted journey Markdown files, named screenshots, console summaries, and safe network summaries. Never store passwords, tokens, payment data, or customer PII in it.

### Task 1: Lock the baseline and controlled data

**Files:**
- Create: output/acceptance/p1-2026-09-02/README.md
- Create: output/acceptance/p1-2026-09-02/journeys/00-baseline.md

**Produces:** exact SHA, local URL, pre-existing dirty files, controlled product/variant/coupon/rate/account references, and a declaration that this phase will not trigger live providers.

- [ ] Run and record the local baseline.

~~~powershell
git rev-parse HEAD
git status --short
C:\xampp\php\php.exe artisan about
C:\xampp\php\php.exe artisan migrate:status
~~~

- [ ] Use protected Admin/storefront read paths and read-only SQL to locate one in-stock variant, valid coupon, invalid coupon case, eligible shipping rate, safe payment method, and disposable local account. Record identifiers only.
- [ ] Run the existing customer-commerce checks. If a command in package.json differs, record the exact working command instead of guessing.

~~~powershell
C:\xampp\php\php.exe artisan test --testsuite=Feature --filter="Checkout|Storefront|Customer|Payment"
npm --prefix resources/admin run test -- --runInBand
~~~

- [ ] Open /shop, /cart, /checkout, and /account/login; record reachability and browser console state before creating an order.

### Task 2: Accept the guest customer path

**Files:**
- Create: output/acceptance/p1-2026-09-02/journeys/01-guest-happy-path.md
- Create: output/acceptance/p1-2026-09-02/journeys/02-guest-validation-and-coupon-failure.md
- Create: output/acceptance/p1-2026-09-02/screenshots/guest-*.png
- Create: output/acceptance/p1-2026-09-02/console/guest-*.md
- Create: output/acceptance/p1-2026-09-02/network/guest-*.md

**Produces:** guest cart/checkout/success evidence and order references for reconciliation.

- [ ] In a clean session, prove PLP -> PDP -> non-default in-stock variant -> quantity -> cart drawer -> checkout. Verify title, variant, SKU, price, quantity, discount, and totals agree everywhere.
- [ ] Apply the controlled valid coupon in the drawer, verify the server-calculated result at checkout, then separately prove an invalid/expired/ineligible code does not persist a discount or throw a console error.
- [ ] Complete one guest checkout using only a simulated/offline-safe payment method. Verify success page, customer tracking/read model, and protected Admin order view; do not book a courier or call a live hosted payment provider.
- [ ] Attempt missing required contact/address data and an invalid shipping selection where available. Verify inline/server failure, no order creation, and unchanged cart/stock integrity.

### Task 3: Accept the signed-in customer path

**Files:**
- Create: output/acceptance/p1-2026-09-02/journeys/03-signed-in-saved-address.md
- Create: output/acceptance/p1-2026-09-02/journeys/04-account-order-history.md
- Create: output/acceptance/p1-2026-09-02/screenshots/signed-in-*.png
- Create: output/acceptance/p1-2026-09-02/console/signed-in-*.md
- Create: output/acceptance/p1-2026-09-02/network/signed-in-*.md

**Produces:** saved-address, account history, ownership and authorization evidence.

- [ ] Sign in with the disposable local acceptance account. In a separate unauthenticated session, prove protected account/order routes redirect or deny access as designed.
- [ ] Select the controlled saved address at checkout and verify address, eligible shipping, tax, and totals are server-calculated. Do not edit real customer data only for this test.
- [ ] Complete one safe signed-in order and verify account history, order detail/tracking, Admin order, item/variant snapshot, coupon, shipping, tax, payment, and order status agree.
- [ ] Safely test non-owner access to another order only where an isolated fixture exists; verify no information is exposed.

### Task 4: Accept responsive and degraded boundaries

**Files:**
- Create: output/acceptance/p1-2026-09-02/journeys/05-responsive-and-degraded.md
- Create: output/acceptance/p1-2026-09-02/screenshots/mobile-*.png
- Create: output/acceptance/p1-2026-09-02/console/responsive-*.md

**Produces:** desktop/mobile and no-mock-data evidence; observed defects become narrowly scoped items rather than unapproved redesign work.

- [ ] Test PLP, PDP, cart drawer, checkout, success, account history, and tracking at 1440px and 390px widths. Verify key controls and totals remain reachable.
- [ ] Compare displayed catalog/order/coupon/shipping/tax values with the Laravel/MySQL data recorded in Tasks 1-3. Treat stale localStorage/static data as a P1 failure.
- [ ] Compare the approved DOM/Tailwind patterns. Record regressions only; do not rewrite source-faithful layouts during this acceptance phase.

### Task 5: Reconcile and update P1-016

**Files:**
- Create: output/acceptance/p1-2026-09-02/journeys/06-reconciliation.md
- Modify: docs/acceptance/jw-ecommerce-p1-p4-master-checklist.md
- Modify memory only by a new user-authorized addendum if the status materially changes.

**Produces:** one pass/fail decision for P1-016; exact follow-up IDs for any defects; no release action.

- [ ] For each safe acceptance order, reconcile storefront success, account/tracking, Admin order, items, shipping snapshot, tax snapshot, payment state, and inventory movement. Store redacted identifiers only.
- [ ] Mark P1-016 [x] only if every required journey passes with evidence. Otherwise retain [~] and append the exact failed gate and a narrow next action.
- [ ] Validate documentation and report the result.

~~~powershell
git diff --check
git status --short
~~~

Confirm no deployment, database reset, real payment, or real shipment occurred.

## Plan Self-Review

- Scope is limited to P1-016; P2 fulfillment, P3 modules, and P4 hardening are excluded.
- Every closure claim requires browser plus backend-consumer evidence.
- Missing fixtures, bad environment health, or a functional defect are recorded as blockers; do not invent data or patch unrelated behavior.

## Execution Handoff

Run Tasks 1-5 sequentially. Stop after any integrity risk or functional defect and report the smallest affected checklist row. Do not release the phase to GitHub/Hostinger unless the user explicitly approves after reviewing local results.
