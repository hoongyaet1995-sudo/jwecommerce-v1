# Bank Transfer Payment Proof Closure Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close the Bank In / DuitNow payment-proof lifecycle across checkout, order success, customer Purchase History, and existing Admin verification without changing unrelated storefront UI.

**Architecture:** Laravel owns eligibility, authorization, status, private storage, and audit events. A focused customer upload action calls the existing `PaymentProofService`; a dedicated view-model factory exposes only safe state. The selected Google AI Studio component is translated into one Blade component with data-attribute JavaScript while preserving its DOM, Tailwind classes, responsive variants, and six visible states.

**Tech Stack:** Laravel 12, PHP 8.x, MySQL, Blade, Tailwind CSS 4, vanilla JavaScript, Lucide, PHPUnit, Playwright browser acceptance

**Spec:** `docs/superpowers/specs/2026-09-03-bank-transfer-payment-proof-closure-design.md`

**Execution status:** Completed locally on 2026-09-03. The checklist below is retained as the implementation procedure; completion evidence is recorded in `docs/acceptance/2026-09-03-bank-transfer-payment-proof-closure.md`.

## Global Constraints

- Develop only in `C:\xampp\htdocs\jwstudio-ecommerce`; never modify a backup copy.
- MySQL is the operational source of truth; source mock records never enter runtime.
- Preserve selected Google AI Studio DOM, Tailwind classes, copy, responsive behavior, and interaction hierarchy.
- Store proof files on the private `local` disk; accepted types are JPG, PNG, and PDF up to 10 MB.
- A customer may mutate only an owned order or the exact checkout-session `last_order_id`.
- A verified proof is immutable from customer routes.
- No GitHub push or Hostinger deployment until localhost tests and browser acceptance pass and synchronization is approved.

---

### Task 1: Lock the customer proof state and authorization contract

**Files:**
- Create: `app/Domain/Payments/CustomerPaymentProofPolicy.php`
- Create: `app/Domain/Payments/CustomerPaymentProofViewModelFactory.php`
- Create: `tests/Feature/Payment/CustomerPaymentProofViewModelTest.php`

**Interfaces:**
- Produces: `CustomerPaymentProofPolicy::canUpload(Order $order, OrderPayment $payment): bool`
- Produces: `CustomerPaymentProofPolicy::authorize(Request $request, Order $order, OrderPayment $payment): void`
- Produces: `CustomerPaymentProofViewModelFactory::for(Order $order, ?OrderPayment $payment): ?array`

- [ ] **Step 1: Write failing tests for missing, uploaded, rejected, verified, non-bank, ownership, and session authorization.**

The view model must expose `paymentId`, `paymentMethod`, `proofStatus`, `originalFileName`, `fileSize`, `uploadedAt`, `rejectionReason`, `canUpload`, `canReplace`, `orderNumber`, `orderTotal`, and `currency`, and must never expose `proof_path`, `proof_disk`, or `proof_sha256`.

- [ ] **Step 2: Run the focused test and confirm failure.**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/Payment/CustomerPaymentProofViewModelTest.php --compact`

- [ ] **Step 3: Implement the policy and safe view-model factory.**

Normalize database `none` to source state `missing`; expose `uploaded`, `rejected`, and `verified` unchanged. Compute mutation capability only when gateway is `bank_transfer`, the order is `pending_payment`, the payment is pending, and the proof is not verified.

- [ ] **Step 4: Run the focused test and commit.**

Run the Step 2 command. Expected: PASS.

Commit: `git commit -m "feat(payment): define customer proof state contract"`

### Task 2: Add the secure customer upload mutation

**Files:**
- Create: `app/Http/Controllers/CustomerPaymentProofController.php`
- Modify: `app/Services/Payments/PaymentProofService.php`
- Modify: `routes/web.php`
- Create: `tests/Feature/Payment/CustomerPaymentProofUploadTest.php`

**Interfaces:**
- Consumes: `CustomerPaymentProofPolicy::authorize(...)`
- Produces: `CustomerPaymentProofController::store(Request $request, Order $order, OrderPayment $payment): RedirectResponse|JsonResponse`
- Produces: named route `customer.payment-proofs.store`

- [ ] **Step 1: Write failing upload tests.**

Cover session-authorized success-page upload, authenticated owner upload, different-customer denial, order/payment mismatch, non-bank denial, verified-proof denial, invalid MIME, over-10-MB file, successful rejected-proof resubmission, private-disk persistence, and append-only `uploaded` event creation.

- [ ] **Step 2: Run the focused test and confirm failure.**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/Payment/CustomerPaymentProofUploadTest.php --compact`

- [ ] **Step 3: Implement route/controller/service behavior.**

Validate with:

```php
['payment_proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240']]
```

Store the new file before updating the payment pointer. On success, set `proof_status=uploaded`, clear `proof_reviewed_at`, append an `uploaded` event, and remove a replaced private file only after the database mutation succeeds. On JSON requests return the refreshed safe view model; on form requests redirect back with a status message.

- [ ] **Step 4: Run upload and existing Admin proof tests.**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/Payment/CustomerPaymentProofUploadTest.php tests/Feature/Admin/OrderPaymentProofApiTest.php --compact`

- [ ] **Step 5: Commit.**

Commit: `git commit -m "feat(payment): accept secure customer proof uploads"`

### Task 3: Capture and translate the approved AI Studio panel

**Files:**
- Create: `resources/views/components/storefront/bank-payment-proof-panel.blade.php`
- Modify: `resources/js/app.js`
- Create: `tests/Feature/Payment/BankPaymentProofPanelSourceTest.php`
- Update: `docs/google-ai-studio/manifests/2026-09-03-bank-payment-proof-source.md`

**Interfaces:**
- Consumes: safe proof view model and named upload route
- Produces: `<x-storefront.bank-payment-proof-panel :proof="$proof" variant="hero|compact" />`
- Produces: `installBankPaymentProofPanels()` in `resources/js/app.js`

- [ ] **Step 1: Run the ZIP source and capture source baselines.**

Run `npm run dev` in the private extracted ZIP. Capture desktop and 390 px mobile screenshots for hero and compact variants across missing, selected/uploading, uploaded, rejected, verified, and validation-error states. Record paths in the manifest.

- [ ] **Step 2: Write a failing source-contract test.**

Assert the Blade component contains the source state headings, exact file constraints, hero/compact variant classes, accessible file input, transfer-reference copy control, inline error region, and no QA state bar or mock order values.

- [ ] **Step 3: Translate only `BankPaymentProofPanel.tsx`.**

Preserve source wrappers and classes. Replace React conditionals with Blade/data-state regions and callbacks with the real form endpoint. Reuse the existing `lucide` package; add only source icons absent from the current icon registry. Set the production equivalent of `showDemoStateBar` to false.

- [ ] **Step 4: Implement source-equivalent browser behavior.**

`installBankPaymentProofPanels()` must validate file type/size client-side, support picker/keyboard/drop, display selected filename/size, submit with CSRF and same-origin credentials, disable double submission, render inline 422/network errors, transition to server-returned state, copy the order reference, and never fabricate upload success.

- [ ] **Step 5: Run component contract and build.**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/Payment/BankPaymentProofPanelSourceTest.php --compact`

Run: `npm run build`

- [ ] **Step 6: Commit.**

Commit: `git commit -m "feat(storefront): port payment proof panel source"`

### Task 4: Bind checkout and order success

**Files:**
- Modify: `resources/views/storefront/checkout.blade.php`
- Modify: `app/Http/Controllers/CheckoutController.php`
- Modify: `resources/views/storefront/success.blade.php`
- Modify: `tests/Feature/Payment/OfflineCheckoutTest.php`
- Create: `tests/Feature/Payment/OrderSuccessPaymentProofTest.php`

**Interfaces:**
- Consumes: `CustomerPaymentProofViewModelFactory::for(...)`
- Consumes: hero Blade proof component
- Produces: session-authorized proof upload from `/order/success`

- [ ] **Step 1: Write failing checkout-copy and success-state tests.**

Assert the old `(optional)` copy is absent, approved explanatory copy is present, non-bank success omits the panel, missing bank proof renders hero/action-required, uploaded renders pending verification, rejected renders reason, and verified renders locked without an upload action.

- [ ] **Step 2: Run the focused tests and confirm failure.**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/Payment/OfflineCheckoutTest.php tests/Feature/Payment/OrderSuccessPaymentProofTest.php --compact`

- [ ] **Step 3: Bind the existing checkout copy and success hero component.**

Keep the checkout upload region in its exact current position. Build the success proof view model from the order's latest bank-transfer payment and pass it to the `hero` variant. Do not import ZIP mock order number, currency, totals, timestamps, or rejection reasons.

- [ ] **Step 4: Run focused tests and commit.**

Run the Step 2 command plus `npm run build`. Expected: PASS.

Commit: `git commit -m "feat(storefront): close success proof recovery"`

### Task 5: Bind authenticated Purchase History recovery

**Files:**
- Modify: `app/Domain/Accounts/OriginalAccountPayloadFactory.php`
- Modify: `resources/views/storefront/account.blade.php`
- Modify: `tests/Feature/OriginalAccountWorkspacePreviewTest.php`
- Create: `tests/Feature/Payment/AccountPaymentProofRecoveryTest.php`

**Interfaces:**
- Consumes: `CustomerPaymentProofViewModelFactory::for(...)`
- Consumes: compact Blade proof component
- Produces: safe `paymentProof` field per Bank In order in the account payload

- [ ] **Step 1: Write failing account read-model and rendering tests.**

Assert only Bank In orders receive the field/panel, a different customer's data never appears, rejected reasons render, verified state is locked, and runtime output contains no mock proof or private-storage values.

- [ ] **Step 2: Run focused tests and confirm failure.**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/Payment/AccountPaymentProofRecoveryTest.php tests/Feature/OriginalAccountWorkspacePreviewTest.php --compact`

- [ ] **Step 3: Add the safe payload and compact source region.**

Place the compact panel beneath the order items and above the metadata summary, matching the approved `PurchaseHistoryTab.tsx` region without changing the existing card, badges, review buttons, or Buy Again controls.

- [ ] **Step 4: Run focused tests and commit.**

Run the Step 2 command plus `npm run build`. Expected: PASS.

Commit: `git commit -m "feat(account): add payment proof recovery"`

### Task 6: Prove end-to-end closure and source fidelity

**Files:**
- Update: `docs/google-ai-studio/manifests/2026-09-03-bank-payment-proof-source.md`
- Create: `docs/acceptance/2026-09-03-bank-transfer-payment-proof-closure.md`
- Create screenshots under: `test-results/payment-proof-closure/`

**Interfaces:**
- Consumes: all previous tasks
- Produces: local closure evidence and release decision

- [ ] **Step 1: Run the focused payment/account/Admin suite.**

Run:

```powershell
C:\xampp\php\php.exe artisan config:clear
C:\xampp\php\php.exe artisan test tests/Feature/Payment/OfflineCheckoutTest.php tests/Feature/Payment/CustomerPaymentProofViewModelTest.php tests/Feature/Payment/CustomerPaymentProofUploadTest.php tests/Feature/Payment/BankPaymentProofPanelSourceTest.php tests/Feature/Payment/OrderSuccessPaymentProofTest.php tests/Feature/Payment/AccountPaymentProofRecoveryTest.php tests/Feature/Admin/OrderPaymentProofApiTest.php tests/Feature/OriginalAccountWorkspacePreviewTest.php --compact
npm run build
```

- [ ] **Step 2: Run real-browser desktop and mobile acceptance.**

With isolated test orders, verify checkout copy; upload from success; refresh persistence; login and upload/resubmit from Purchase History; Admin preview/reject; customer rejection reason/resubmit; Admin verify; verified customer lock; and paid transition. Confirm no console errors, no horizontal overflow, and no real payment or provider booking.

- [ ] **Step 3: Compare source and Laravel screenshots.**

Inspect hero and compact states at desktop and 390 px. Record any deliberate adapter-only differences; material visual differences block completion.

- [ ] **Step 4: Run the complete Laravel suite.**

Run: `C:\xampp\php\php.exe artisan test --compact`

- [ ] **Step 5: Write the acceptance report and commit the coherent local phase.**

Commit: `git commit -m "test(payment): prove payment proof closure"`

Do not push or deploy until synchronization approval is explicit.
