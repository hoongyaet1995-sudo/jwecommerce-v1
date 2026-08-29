# EasyParcel Authenticated Details Status Projection

**Goal:** Close the observed Sandbox divergence where EasyParcel Shipment Details reports Delivered while JW Ecommerce remains pending, without trusting the unsigned webhook payload as lifecycle truth.

**Authority:** `docs/superpowers/specs/2026-08-29-easyparcel-sandbox-lifecycle-closure-design.md` permits only OAuth-authenticated Shipment Details or Tracking Status responses to mutate EasyParcel shipment truth. The webhook remains a hint that schedules reconciliation.

## Global Constraints

- Implement and verify first in `C:\xampp\htdocs\jwstudio-ecommerce`.
- Preserve the existing Google AI Studio Shipping DOM, classes, copy, light/dark behavior, and interactions. This task requires no UI changes.
- Never project lifecycle state directly from an unsigned webhook payload.
- Require exact provider shipment identity, current OAuth configuration, and unchanged AWB/provider state before committing.
- Record provider facts as append-only, idempotent `source=easyparcel` shipment events and reuse the existing `ShipmentService` lifecycle projector.
- Do not expose or persist OAuth credentials, raw webhook bodies, customer PII, or private endpoint tokens.
- Automated tests run against `jw_ecommerce_testing`; preserve the user-owned dirty `AGENTS.md`.

### Task 1: Project OAuth-Authenticated Shipment Details Status

**Files:**

- Modify: `app/Services/Shipping/EasyParcelShipmentDetailsService.php`
- Modify or create a small shared status normalizer only if required to keep Details and Tracking mappings identical.
- Modify: `tests/Feature/EasyParcelWebhookReconciliationTest.php`
- Modify additional focused lifecycle tests only when needed for the observable contract.

**Required behavior:**

1. Add a failing feature test proving an OAuth-authenticated Shipment Details response with exact shipment identity, AWB, `shipment_status_code=5`, and `shipment_status=Delivered` creates exactly one EasyParcel Delivered event, advances the shipment to Delivered, and advances a Processing order through Shipped to Delivered.
2. Prove idempotency: reconciling the same authenticated Details status again creates no duplicate event or order history.
3. Prove the webhook remains only a hint: its status code or description cannot be projected when authenticated Details disagrees, is malformed, has stale provider configuration, mismatched shipment identity, or the shipment/AWB changes while the request is in flight.
4. Normalize documented codes consistently with Tracking: `0,8 -> failed_delivery`; `2,7 -> pending`; `3,11 -> shipped`; `4 -> in_transit`; `5 -> delivered`; `6 -> returned`; unknown integer -> `provider_update`.
5. Use only the authenticated Details `shipment_status_code` and `shipment_status` as status/description truth. Because Shipment Details has no lifecycle event timestamp, use reconciliation time for the first observation, but derive a stable external event identity from provider + shipment number + status code + normalized description so repeated reconciliation is idempotent.
6. Persist reference/AWB/label/tracking updates and the lifecycle event atomically under the existing locked shipment/order boundary. A failed event projection must roll back all Details mutations.
7. Keep the Tracking Status endpoint as the richer historical timeline source; its later non-terminal events must not regress a terminal Details-confirmed Delivered state.

**Verification:**

- Run the focused new test first and capture the expected RED failure.
- Run `C:\xampp\php\php.exe artisan test tests/Feature/EasyParcelWebhookReconciliationTest.php tests/Feature/EasyParcelTrackingLifecycleTest.php` after implementation.
- Run `git diff --check`.

### Task 2: Local Acceptance, Release, and Existing Sandbox Reconciliation

**Files:**

- Modify: `docs/operations/easyparcel-sandbox-acceptance.md`

**Required behavior:**

1. Record the real Sandbox evidence without secrets or PII: quotation, one Booking, provider shipment/order references, AWB, authenticated Details Delivered code 5, Tracking API static pending, and processed AWB/status webhook receipts.
2. Run relevant PHP tests, Admin tests/lint/build if any Admin source changed, and authenticated localhost browser checks. Do not relabel fixture evidence as provider evidence.
3. Commit the accepted localhost source, push the exact commit to GitHub `main`, deploy that exact SHA to `/apps/jwecommerce`, and verify all three SHAs match.
4. Reconcile the existing Sandbox shipment through the deployed code without submitting another booking. Verify production MySQL, Admin Shipping queues, Admin order history, and provider receipt audit show Delivered consistently.
5. Report the result as Sandbox closure, not physical carrier delivery. Live remains out of scope without separate approval.
