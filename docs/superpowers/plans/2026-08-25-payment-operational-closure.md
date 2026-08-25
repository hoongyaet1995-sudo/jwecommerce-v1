# Payment Operational Closure Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (- [ ]) syntax for tracking.

**Goal:** Make checkout display only eligible admin-configured payment methods and safely create, redirect, verify and record offline and hosted-provider payments.

**Architecture:** Configuration metadata remains in store_settings; an encrypted credential record holds secrets. A payment availability service feeds the checkout view, while a payment-attempt service creates immutable local records before an adapter redirects to Stripe, PayPal or Billplz. Webhooks—not return URLs—are authoritative for paid state.

**Tech Stack:** Laravel 12, PHP 8.2, MySQL, Blade/Tailwind, existing React Admin, Stripe Checkout Sessions, PayPal Orders, Billplz Bills.

**Spec:** docs/superpowers/specs/2026-08-25-payment-operational-closure-design.md

## Global Constraints

- Preserve the selected Google AI Studio Bank Transfer DOM/classes; only inject Blade/Alpine data behavior.
- Do not port ZIP mock banks, card fields, fake statuses, USD/tax/order data, or processing simulation.
- Provider credentials use encrypted storage and never appear in JSON, logs or browser HTML.
- Bank allowProofUpload=true means optional receipt upload; false hides its UI. Receipt is never a precondition to create a pending transfer order.
- Stripe uses Test mode first. PayPal and Billplz use Sandbox first. Hosted approval pages only; Laravel never receives card/CVV data.
- payment.return.failure remains an approved UI gap; no invented cancel/failure surface is included.

---

### Task 1: Secure payment persistence

**Files:**
- Create: database/migrations/<timestamp>_create_payment_attempt_operational_tables.php
- Create: app/Models/PaymentGatewayCredential.php
- Create: app/Models/PaymentWebhookEvent.php
- Modify: app/Models/OrderPayment.php
- Test: tests/Feature/Payment/PersistenceContractTest.php

**Interfaces:**
- Produces PaymentGatewayCredential::forGateway(string $gatewayId): ?self and encrypted credentials: array.
- Produces OrderPayment provider, environment, idempotency_key, amount, currency, configuration_snapshot and unique provider reference.
- Produces unique webhook events keyed by provider and provider_event_id.

- [ ] **Step 1: Write the failing test**

~~~
public function test_gateway_credentials_are_encrypted_and_not_in_metadata(): void
{
    $record = PaymentGatewayCredential::query()->create([
        'gateway_id' => 'stripe', 'environment' => 'sandbox',
        'credentials' => ['secretKey' => 'sk_test_private'],
    ]);

    $this->assertSame('sk_test_private', $record->fresh()->credentials['secretKey']);
}
~~~

- [ ] **Step 2: Verify RED**

Run: C:\xampp\php\php.exe artisan test tests/Feature/Payment/PersistenceContractTest.php  
Expected: FAIL because the model/table does not exist.

- [ ] **Step 3: Implement the minimum schema/model**

~~~
$table->string('gateway_id');
$table->string('environment');
$table->text('credentials');
$table->unique(['gateway_id', 'environment']);
// casts(): return ['credentials' => 'encrypted:array'];
~~~

- [ ] **Step 4: Verify GREEN**

Run: C:\xampp\php\php.exe artisan migrate; C:\xampp\php\php.exe artisan test tests/Feature/Payment/PersistenceContractTest.php  
Expected: PASS.

- [ ] **Step 5: Commit**

~~~
git add database/migrations app/Models tests/Feature/Payment/PersistenceContractTest.php
git commit -m "feat: persist secure payment attempts and credentials"
~~~

### Task 2: Admin credential validation without exposure

**Files:**
- Modify: app/Http/Controllers/Admin/CommerceSettingsApiController.php
- Modify: resources/admin/src/types/payment.ts
- Modify: resources/admin/src/components/settings/PaymentGatewayModal.tsx
- Test: tests/Feature/Admin/CommerceConfigurationApiTest.php

**Interfaces:**
- Consumes Task 1 credentials.
- Produces PUT /admin/api/commerce-settings validation: enabled Stripe requires publishable/secret keys; PayPal requires client ID/secret; Billplz requires API key/collection/signature key for selected environment.
- Produces redacted response credentials: [] and credentialStatus: configured|missing.

- [ ] **Step 1: Write the failing feature tests**

~~~
$this->actingAs($admin)->putJson('/admin/api/commerce-settings', $enabledStripeWithoutKeys)
    ->assertUnprocessable()
    ->assertJsonValidationErrors('paymentGateways.0.credentials.secretKey');
$this->actingAs($admin)->putJson('/admin/api/commerce-settings', $enabledStripeWithKeys)
    ->assertOk()
    ->assertJsonPath('data.paymentGateways.0.credentialStatus', 'configured')
    ->assertJsonMissing(['secretKey' => 'sk_test_private']);
~~~

- [ ] **Step 2: Verify RED**

Run: C:\xampp\php\php.exe artisan test tests/Feature/Admin/CommerceConfigurationApiTest.php  
Expected: FAIL because enabled gateway credential completeness is not checked.

- [ ] **Step 3: Implement validation and redaction**

Store submitted secrets only in PaymentGatewayCredential; persist safe public metadata in payment_gateways. Keep the existing modal DOM and show Laravel validation errors through its existing error/toast pattern.

- [ ] **Step 4: Verify GREEN**

Run: C:\xampp\php\php.exe artisan test tests/Feature/Admin/CommerceConfigurationApiTest.php; npm --prefix resources/admin run build  
Expected: PASS.

- [ ] **Step 5: Commit**

~~~
git add app/Http/Controllers/Admin resources/admin/src tests/Feature/Admin/CommerceConfigurationApiTest.php
git commit -m "feat: validate and secure payment gateway configuration"
~~~

### Task 3: Config-driven eligibility and offline pricing

**Files:**
- Create: app/Services/PaymentMethodAvailabilityService.php
- Modify: app/Services/PaymentConfigurationService.php
- Modify: app/Services/CartPricingService.php
- Test: tests/Feature/Payment/PaymentMethodAvailabilityTest.php

**Interfaces:**
- Produces checkoutMethods(array $checkoutContext): array with id, label, kind and Bank Transfer accounts only when eligible.
- Produces codSurcharge(array $address, int $subtotal): int or validation error.

- [ ] **Step 1: Write failing eligibility tests**

~~~
$this->assertArrayHasKey(
    'bank_transfer',
    app(PaymentMethodAvailabilityService::class)->checkoutMethods($this->context())
);
$this->assertArrayNotHasKey(
    'stripe',
    app(PaymentMethodAvailabilityService::class)->checkoutMethods($this->contextWithoutStripeCredentials())
);
~~~

- [ ] **Step 2: Verify RED**

Run: C:\xampp\php\php.exe artisan test tests/Feature/Payment/PaymentMethodAvailabilityTest.php  
Expected: FAIL because service/methods do not exist.

- [ ] **Step 3: Implement saved-settings eligibility**

Map stripe, paypal, billplz, bank_transfer and cod. Reject disabled methods, incomplete online credentials, unsupported currency/amount, inactive bank accounts and failing COD region/amount rules. Delegate legacy PaymentConfigurationService compatibility methods to this service.

- [ ] **Step 4: Verify GREEN**

Run: C:\xampp\php\php.exe artisan test tests/Feature/Payment/PaymentMethodAvailabilityTest.php tests/Feature/CheckoutCommerceRulesTest.php  
Expected: PASS.

- [ ] **Step 5: Commit**

~~~
git add app/Services tests/Feature/Payment/PaymentMethodAvailabilityTest.php tests/Feature/CheckoutCommerceRulesTest.php
git commit -m "feat: derive checkout methods from payment settings"
~~~

### Task 4: Transactional offline order creation

**Files:**
- Create: app/Services/PaymentAttemptService.php
- Modify: app/Services/CheckoutService.php
- Modify: app/Http/Controllers/CheckoutController.php
- Test: tests/Feature/Payment/OfflineCheckoutTest.php

**Interfaces:**
- Produces PaymentAttemptService::begin(Order $order, string $method, array $configuration): OrderPayment.
- Bank Transfer/COD create pending attempts; neither invokes an online adapter.

- [ ] **Step 1: Write the failing checkout test**

~~~
$this->withSession(['cart' => [$variant->id => 1]])
    ->post('/checkout', $this->bankTransferData())
    ->assertRedirect('/order/success');
$this->assertDatabaseHas('order_payments', ['gateway' => 'bank_transfer', 'status' => 'pending']);
$this->assertDatabaseHas('orders', ['status' => 'pending_payment']);
~~~

- [ ] **Step 2: Verify RED**

Run: C:\xampp\php\php.exe artisan test tests/Feature/Payment/OfflineCheckoutTest.php  
Expected: FAIL because bank_transfer and cod are rejected by validation.

- [ ] **Step 3: Implement transaction, snapshot and server calculations**

Validate operational method IDs. Call availability before stock decrement, include COD surcharge in the server summary, snapshot selected Bank/COD configuration in OrderPayment, and generate idempotency keys. Clear cart only after a local attempt is committed.

- [ ] **Step 4: Verify GREEN**

Run: C:\xampp\php\php.exe artisan test tests/Feature/Payment/OfflineCheckoutTest.php tests/Feature/CheckoutCommerceRulesTest.php  
Expected: PASS.

- [ ] **Step 5: Commit**

~~~
git add app/Services/PaymentAttemptService.php app/Services/CheckoutService.php app/Http/Controllers/CheckoutController.php tests/Feature/Payment/OfflineCheckoutTest.php
git commit -m "feat: create operational offline payment attempts"
~~~

### Task 5: Source-faithful Bank Transfer panel

**Files:**
- Modify: resources/views/storefront/checkout.blade.php
- Create: resources/views/storefront/partials/checkout-bank-transfer.blade.php
- Test: tests/Feature/Payment/BankTransferCheckoutViewTest.php

**Interfaces:**
- Consumes bank_transfer view model from Task 3.
- Produces fields bank_account_id and optional payment_proof with source selection/copy/remove behavior.

- [ ] **Step 1: Write failing view test**

~~~
$this->withSession(['cart' => [$variant->id => 1]])->get('/checkout')
    ->assertSee('Bank / DuitNow')
    ->assertSee('Please complete payment within 24 hours')
    ->assertSee('Payment Receipt / Slip (optional)');
~~~

- [ ] **Step 2: Verify RED**

Run: C:\xampp\php\php.exe artisan test tests/Feature/Payment/BankTransferCheckoutViewTest.php  
Expected: FAIL because the selected ZIP region is absent.

- [ ] **Step 3: Port only selected ZIP source**

Preserve the selected source DOM/classes. Blade renders active saved accounts/instructions. Narrow JavaScript switches selection, copies values, previews/removes only the local selected file. Render receipt control only when allowProofUpload is true.

- [ ] **Step 4: Verify GREEN plus visual source proof**

Run: C:\xampp\php\php.exe artisan test tests/Feature/Payment/BankTransferCheckoutViewTest.php  
Expected: PASS. Capture desktop and 390px screenshots, compare the selected region against the source ZIP baseline.

- [ ] **Step 5: Commit**

~~~
git add resources/views/storefront/checkout.blade.php resources/views/storefront/partials/checkout-bank-transfer.blade.php tests/Feature/Payment/BankTransferCheckoutViewTest.php
git commit -m "feat: add source-faithful bank transfer checkout panel"
~~~

### Task 6: Receipt upload and manual verification

**Files:**
- Create: migration and model for PaymentProof
- Create: app/Http/Controllers/PaymentProofController.php
- Create: app/Http/Controllers/Admin/BankTransferVerificationController.php
- Modify: routes/web.php
- Test: tests/Feature/Payment/BankTransferProofTest.php

**Interfaces:**
- Produces owner/admin protected proof upload/read and admin verification routes.
- Accepts PDF/JPEG/PNG only, server-inspected, maximum 10 MB, private storage tied to one payment attempt.

- [ ] **Step 1: Write failing upload/authorization test**

~~~
$this->post('/checkout', $this->bankTransferData([
    'payment_proof' => UploadedFile::fake()->create('slip.pdf', 200, 'application/pdf'),
]))->assertRedirect('/order/success');
$this->assertDatabaseHas('payment_proofs', ['order_payment_id' => $payment->id]);
$this->get(route('payment-proofs.show', $proof))->assertForbidden();
~~~

- [ ] **Step 2: Verify RED**

Run: C:\xampp\php\php.exe artisan test tests/Feature/Payment/BankTransferProofTest.php  
Expected: FAIL because proof persistence/access policy is absent.

- [ ] **Step 3: Implement private storage and verifier transition**

Store media privately, add owner/admin authorization and let authorized admin mark a pending transfer paid using existing order transition/audit paths. Never expose public proof URLs.

- [ ] **Step 4: Verify GREEN**

Run: C:\xampp\php\php.exe artisan test tests/Feature/Payment/BankTransferProofTest.php  
Expected: PASS.

- [ ] **Step 5: Commit**

~~~
git add app/Http/Controllers routes/web.php database/migrations app/Models tests/Feature/Payment/BankTransferProofTest.php
git commit -m "feat: secure bank payment proof and verification"
~~~

### Task 7: Hosted provider redirects

**Files:**
- Create: app/Contracts/HostedPaymentGatewayAdapter.php
- Create: app/Services/Payments/StripeCheckoutAdapter.php
- Create: app/Services/Payments/PayPalOrdersAdapter.php
- Create: app/Services/Payments/BillplzBillAdapter.php
- Create: app/Services/Payments/HostedPaymentGatewayManager.php
- Modify: app/Http/Controllers/CheckoutController.php
- Test: tests/Feature/Payment/HostedPaymentCheckoutTest.php

**Interfaces:**
- HostedPaymentGatewayAdapter::begin(OrderPayment $attempt): string returns approval URL.
- HostedPaymentGatewayManager::begin(string $provider, OrderPayment $attempt): string selects an adapter.

- [ ] **Step 1: Write failing redirect contract test**

~~~
Http::fake(['api.stripe.com/*' => Http::response([
    'id' => 'cs_test_1', 'url' => 'https://checkout.stripe.test/session',
], 200)]);
$this->post('/checkout', $this->stripeData())
    ->assertRedirect('https://checkout.stripe.test/session');
$this->assertDatabaseHas('order_payments', ['gateway' => 'stripe', 'reference' => 'cs_test_1']);
~~~

- [ ] **Step 2: Verify RED**

Run: C:\xampp\php\php.exe artisan test tests/Feature/Payment/HostedPaymentCheckoutTest.php  
Expected: FAIL because no hosted adapters/redirect behavior exist.

- [ ] **Step 3: Implement adapter payloads**

Use selected-environment credentials. Send local order number/idempotency key in provider metadata and persist reference after provider creation. Return URL identifies only a pending attempt; it never confirms payment.

- [ ] **Step 4: Verify GREEN**

Run: C:\xampp\php\php.exe artisan test tests/Feature/Payment/HostedPaymentCheckoutTest.php  
Expected: PASS with exact request assertions for Stripe, PayPal and Billplz.

- [ ] **Step 5: Commit**

~~~
git add app/Contracts app/Services/Payments app/Http/Controllers/CheckoutController.php tests/Feature/Payment/HostedPaymentCheckoutTest.php
git commit -m "feat: redirect checkout to hosted payment providers"
~~~

### Task 8: Signed webhooks and idempotent transitions

**Files:**
- Create: app/Http/Controllers/PaymentWebhookController.php
- Create: app/Services/Payments/PaymentWebhookService.php
- Modify: routes/web.php
- Test: tests/Feature/Payment/PaymentWebhookTest.php

**Interfaces:**
- Produces POST /payments/webhooks/{provider}, raw-body signature verification and normalized paid|failed|cancelled|expired transitions.

- [ ] **Step 1: Write failing callback test**

~~~
$this->postJson('/payments/webhooks/stripe', $payload, ['Stripe-Signature' => 'invalid'])
    ->assertForbidden();
$this->postJson('/payments/webhooks/stripe', $signedPayload, $headers)->assertOk();
$this->postJson('/payments/webhooks/stripe', $signedPayload, $headers)->assertOk();
$this->assertDatabaseCount('payment_webhook_events', 1);
~~~

- [ ] **Step 2: Verify RED**

Run: C:\xampp\php\php.exe artisan test tests/Feature/Payment/PaymentWebhookTest.php  
Expected: FAIL because webhook route/service is absent.

- [ ] **Step 3: Implement verification before mutation**

Read unmodified raw body, verify with encrypted credentials, lock the attempt, record each event once, and call CheckoutService::transition only for valid status changes.

- [ ] **Step 4: Verify GREEN**

Run: C:\xampp\php\php.exe artisan test tests/Feature/Payment/PaymentWebhookTest.php  
Expected: PASS.

- [ ] **Step 5: Commit**

~~~
git add app/Http/Controllers/PaymentWebhookController.php app/Services/Payments/PaymentWebhookService.php routes/web.php tests/Feature/Payment/PaymentWebhookTest.php
git commit -m "feat: verify and record payment webhooks"
~~~

### Task 9: Expiry, full regression and browser acceptance

**Files:**
- Create: app/Console/Commands/ExpirePendingPayments.php
- Modify: routes/console.php
- Test: tests/Feature/Payment/ExpirePendingPaymentsTest.php

**Interfaces:**
- Produces payments:expire-pending, cancelling expired unpaid attempts and restoring stock through existing cancellation transition.

- [ ] **Step 1: Write failing expiry test**

~~~
$this->travel(25)->hours();
$this->artisan('payments:expire-pending')->assertExitCode(0);
$this->assertDatabaseHas('orders', ['id' => $order->id, 'status' => 'cancelled']);
$this->assertDatabaseHas('order_payments', ['id' => $attempt->id, 'status' => 'expired']);
~~~

- [ ] **Step 2: Verify RED**

Run: C:\xampp\php\php.exe artisan test tests/Feature/Payment/ExpirePendingPaymentsTest.php  
Expected: FAIL because command/expiry metadata are absent.

- [ ] **Step 3: Implement scheduled expiration**

Use immutable payment snapshot window/global rule, lock each unpaid attempt, mark expired once and invoke established cancellation to restore stock.

- [ ] **Step 4: Verify all**

Run: C:\xampp\php\php.exe artisan test; npm --prefix resources/admin run build  
Expected: PASS. Browser-test admin enable/disable and missing credential errors; Bank Transfer/COD orders; Stripe Test-mode redirect and signed webhook; PayPal/Billplz Sandbox contracts; desktop/390px source comparison. Record screenshots and the unresolved cancel/failure UI boundary honestly.

- [ ] **Step 5: Commit**

~~~
git add app/Console routes/console.php tests/Feature/Payment
git commit -m "feat: expire unpaid payment attempts safely"
~~~

## Plan self-review

- Spec coverage: Tasks 1-4 cover configuration, eligibility, snapshots, COD and Bank Transfer; Tasks 5-6 cover selected UI and proof/verification; Tasks 7-8 cover hosted providers/webhooks; Task 9 covers expiration and acceptance.
- The cancellation/failure result UI remains excluded, exactly as approved.
- Placeholder scan is clean. Each later interface is defined by the preceding task.
- Canonical setting is allowProofUpload; legacy requireProofUpload requires one-time migration only if existing operator data has that older key.

## Execution Handoff

Plan complete and saved to docs/superpowers/plans/2026-08-25-payment-operational-closure.md.

1. **Subagent-Driven (recommended)** — dispatch a fresh subagent per task and review between tasks.
2. **Inline Execution** — execute tasks in this session with checkpoints.
