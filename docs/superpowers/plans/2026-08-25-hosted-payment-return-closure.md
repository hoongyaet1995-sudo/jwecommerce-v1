# Hosted Payment Return Closure Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:executing-plans` to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Create real Stripe, PayPal and Billplz hosted-payment attempts, show the approved return states, and make verified webhooks the sole authority for payment confirmation.

**Architecture:** Checkout creates an immutable pending `OrderPayment`, then a gateway adapter creates the remote hosted approval session and stores its provider reference. Return URLs present only `redirecting`, `cancelled`, `failed` or `pending`; signed, idempotently recorded webhooks mutate order/payment status.

**Tech Stack:** Laravel 12, PHP 8.2, MySQL, Laravel HTTP client, Blade, Alpine.js.

**Spec:** `docs/superpowers/specs/2026-08-25-payment-operational-closure-design.md`

## Global Constraints

- Visual source ZIP SHA-256 is `E507FFF4DB45D09060A6502A5F3F59C2C3894BB46A1F2FE0ECA533C52643234C`.
- Selected component is `src/components/pages/CheckoutPage.tsx`, SHA-256 `1732D0EF62E699C510DEE9B7253B326E2CA6F7B39AEADE027332675C709DCDD9`.
- Port the four hosted return states and their direct Tailwind hierarchy; do not port the prototype state switcher.
- Stripe Test mode first; PayPal and Billplz Sandbox first. Card/CVV data never enter Laravel.
- Return URLs never mark orders paid. Only a verified unique webhook can transition a payment to paid.
- Credentials remain encrypted and absent from Blade, API responses and logs.

---

### Task 1: Hosted provider attempts and external redirect

**Files:**
- Create: `app/Contracts/HostedPaymentGatewayAdapter.php`
- Create: `app/Services/Payments/StripeCheckoutAdapter.php`
- Create: `app/Services/Payments/PayPalOrdersAdapter.php`
- Create: `app/Services/Payments/BillplzBillAdapter.php`
- Create: `app/Services/Payments/HostedPaymentGatewayManager.php`
- Modify: `app/Services/CheckoutService.php`
- Modify: `app/Http/Controllers/CheckoutController.php`
- Test: `tests/Feature/Payment/HostedPaymentCheckoutTest.php`

**Interfaces:**
- `HostedPaymentGatewayAdapter::begin(OrderPayment $payment, Order $order): string` returns an approval URL and persists the remote reference.
- `HostedPaymentGatewayManager::begin(OrderPayment $payment, Order $order): string` chooses the adapter by `$payment->gateway`.
- Checkout returns a local order/payment plus nullable external approval URL.

- [ ] Write a failing feature test using `Http::fake()` for a Stripe Checkout Session response with `id=cs_test_123` and an external `url`; assert checkout redirects to that URL and persists `reference=cs_test_123`.
- [ ] Run `C:\xampp\php\php.exe artisan test tests/Feature/Payment/HostedPaymentCheckoutTest.php --filter=stripe`; expect RED because checkout still redirects locally.
- [ ] Implement Stripe Checkout Session creation with saved-environment credentials, local order number in provider metadata, and `success_url`/`cancel_url` that carry only a local payment identifier. Implement the same manager contract for PayPal Orders and Billplz Bills.
- [ ] Run the entire hosted-attempt test; assert exact outbound request parameters for Stripe, PayPal and Billplz, and pending local order state.
- [ ] Commit: `feat: redirect checkout to hosted payment providers`.

### Task 2: Approved hosted payment return states

**Files:**
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/CheckoutController.php`
- Modify: `resources/views/storefront/checkout.blade.php`
- Test: `tests/Feature/Payment/HostedPaymentReturnViewTest.php`

**Interfaces:**
- `GET /checkout/payment-return/{payment}` supplies trusted `hostedReturnState`, `hostedProvider` and `orderReference` to checkout.
- States are `redirecting`, `cancelled`, `failed`, `pending`; no production preview/toggle state exists.

- [ ] Write failing view tests for the exact approved copy: cancelled, failed, redirecting and pending state with order number.
- [ ] Run `C:\xampp\php\php.exe artisan test tests/Feature/Payment/HostedPaymentReturnViewTest.php`; expect RED because route/view data are absent.
- [ ] Port the source panel inside the existing payment selector without changing class hierarchy. Bind real provider name/icon/reference with Blade/Alpine; omit `Preview Hosted State`. `Try again` returns to method selection and never mutates order status.
- [ ] Run the view test plus `C:\xampp\php\php.exe artisan view:cache`; expect GREEN and assert the preview copy is absent.
- [ ] Commit: `feat: render trusted hosted payment return states`.

### Task 3: Signed and idempotent provider webhooks

**Files:**
- Create: `app/Http/Controllers/PaymentWebhookController.php`
- Create: `app/Services/Payments/PaymentWebhookService.php`
- Modify: `routes/web.php`
- Modify: `app/Services/CheckoutService.php`
- Test: `tests/Feature/Payment/PaymentWebhookTest.php`

**Interfaces:**
- `POST /payments/webhooks/{provider}` verifies raw body plus provider headers.
- `PaymentWebhookService::handle(string $provider, Request $request): void` records one event and performs a valid order transition.

- [ ] Write a failing test: invalid Stripe signature returns 403; a valid paid event returns 200, records one webhook event and marks the order paid; replay returns 200 without a second event.
- [ ] Run `C:\xampp\php\php.exe artisan test tests/Feature/Payment/PaymentWebhookTest.php`; expect RED because route/service are absent.
- [ ] Verify Stripe with encrypted `webhookSecret`; use provider Sandbox verification contracts for PayPal and Billplz. Lock the payment by provider reference, record raw/normalized event once, and transition status only after verification.
- [ ] Run test suite; confirm invalid signatures fail, duplicate events are idempotent, and verified paid status is the only path to `paid`.
- [ ] Commit: `feat: verify hosted payment webhooks`.

### Task 4: Regression and browser acceptance

**Files:**
- Test: `tests/Feature/Payment/HostedPaymentCheckoutTest.php`
- Test: `tests/Feature/Payment/HostedPaymentReturnViewTest.php`
- Test: `tests/Feature/Payment/PaymentWebhookTest.php`

- [ ] Run `C:\xampp\php\php.exe artisan test tests/Feature/Payment tests/Feature/CheckoutCommerceRulesTest.php`; record result.
- [ ] Run `C:\xampp\php\php.exe artisan test`; report any existing unrelated failures separately.
- [ ] With configured Stripe Test keys, submit checkout, verify the external redirect, return cancellation, then submit a verified webhook exactly once. Capture 1440px and 390px screenshots; do not claim browser proof if provider keys or local webhook reachability are unavailable.

## Plan self-review

- Covers all four approved source states, the three hosted providers, redirect persistence and webhook truth.
- Excludes the prototype state switcher and any raw payment input.
- Return state cannot falsely mark an order paid.
