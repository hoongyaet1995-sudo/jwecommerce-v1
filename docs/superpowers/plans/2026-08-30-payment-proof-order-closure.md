# Payment Proof Order Closure Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make Bank In / DuitNow payment proof review and payment/shipping origins operational in the existing Admin Orders detail modal.

**Architecture:** Laravel owns private proof streaming, authorization, review decisions, audit persistence, and order status transitions. The existing React Admin order view receives a safe view model and uses ZIP 17 only for approved DOM, Tailwind, and UI states; it does not import the ZIP mock context or mock data.

**Tech Stack:** Laravel 12, PHP, MySQL, private Laravel filesystem disk, React/TypeScript, Vite, PHPUnit, Vitest.

**Spec:** `docs/operations/payment-proof-order-closure-mapping.md`

## Global Constraints

- Work only in `C:\xampp\htdocs\jwstudio-ecommerce` until local acceptance.
- Never expose proof paths, storage keys, or public proof URLs in Admin JSON.
- Do not call real payment or EasyParcel services.
- Preserve ZIP 17 selected UI hierarchy and Tailwind classes; replace mock bindings only.
- No GitHub push, Hostinger deployment, or commit during this phase without explicit user authorization.

---

### Task 1: Persist secure proof and review audit data

**Files:**
- Create: payment-proof migration, `OrderPaymentProofReview` model, payment-proof service and permission class.
- Modify: `CheckoutService.php`, `OrderPayment.php`.
- Test: `tests/Feature/Admin/OrderPaymentProofApiTest.php`.

- [ ] Write a failing test proving a Bank In upload is stored on the private disk, has safe metadata, and retains no public URL.
- [ ] Run the focused test and verify it fails because the private proof contract does not exist.
- [ ] Add the minimal schema/model/service/storage changes.
- [ ] Re-run the focused test and verify it passes.

### Task 2: Protect proof view/download and review transitions

**Files:**
- Create: proof controller and protected routes.
- Modify: `OrderOperationsService.php`, `OrderApiController.php`, route registration.
- Test: `tests/Feature/Admin/OrderPaymentProofApiTest.php`.

- [ ] Write failing tests for reviewer authorization, secure preview/download, verified payment transition, rejection persistence, and rejected generic paid transition.
- [ ] Run the focused test and verify each case fails for the missing protected contract.
- [ ] Implement the smallest protected endpoints and transactional review service integration.
- [ ] Re-run the focused test and verify it passes.

### Task 3: Publish safe payment and shipping-origin view model

**Files:**
- Modify: `OrderDataService.php` and admin TypeScript order types.
- Test: `tests/Feature/Admin/OrderApiTest.php`.

- [ ] Write failing tests for safe payment metadata, no proof path, unbooked shipping source, and EasyParcel booking source.
- [ ] Run the focused test and verify the fields are absent or incorrect.
- [ ] Implement the minimal resource mapping from payment, review, shipping snapshot, and shipment records.
- [ ] Re-run the focused test and verify it passes.

### Task 4: Bind the approved ZIP 17 UI to real Admin APIs

**Files:**
- Create: narrow payment-proof React data client and tests.
- Modify: `OrderAdmin.tsx`, `orderData.ts`, `types/index.ts`.
- Test: focused Vitest tests for URL use, non-mock actions, and visible safe states.

- [ ] Write failing client/component tests proving review calls Laravel and download uses the protected endpoint rather than a generated text file.
- [ ] Run the focused frontend tests and verify they fail.
- [ ] Port only ZIP 17 selected visual regions and bind them to the safe API/resource contract.
- [ ] Re-run the focused frontend tests and verify they pass.

### Task 5: Local acceptance evidence

**Files:**
- Modify only test fixtures or evidence records when required.
- Test: focused PHPUnit, Vitest, Vite build, and authenticated localhost browser flow.

- [ ] Run focused backend and frontend suites, then their relevant broader suites.
- [ ] Build the Admin frontend.
- [ ] Create a local Bank In test order with a fixture proof; verify preview, download, rejection, approval, persistence, and source labels in an authenticated browser.
- [ ] Verify no real EasyParcel or payment request occurred.
