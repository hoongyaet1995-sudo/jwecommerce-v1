# EasyParcel Failed Booking Recovery Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build an auditable EasyParcel failed-booking recovery flow that safely cancels the old provider shipment, re-quotes, creates a replacement booking, and runs reconciliation from a Hostinger minute cron.

**Architecture:** Keep provider observations, internal shipment lifecycle, and recovery authorization as separate facts. Add one recovery aggregate around the existing shipment/quote records, expose narrow protected endpoints, reuse the existing booking modal for the replacement, and provide a bounded Artisan cron command that runs the scheduler and drains the database queue.

**Tech Stack:** Laravel 12, PHP 8.x, MySQL, React/TypeScript Admin, Vite, Laravel database queue, EasyParcel Open API 2026-06.

**Spec:** `docs/superpowers/specs/2026-08-30-easyparcel-failed-booking-recovery-design.md`

## Global Constraints

- Develop and accept first in `C:\xampp\htdocs\jwstudio-ecommerce`.
- Preserve the Google AI Studio shipping UI DOM, Tailwind classes, styling language, and interaction structure.
- Never expose OAuth tokens, addresses, raw provider payloads, or other secrets in API responses, logs, tests, or audit descriptions.
- Never reuse an expired or previously booked quote.
- Provider cancellation is best-effort and its per-item result must be audited; HTTP 200 alone is not success.
- Preserve the user-owned unstaged `AGENTS.md` change.
- Do not push or deploy until localhost tests, build, and authenticated browser acceptance pass.

---

### Task 1: Truthful provider status and recovery disposition

**Files:**
- Modify: `app/Services/Shipping/EasyParcelStatusNormalizer.php`
- Modify: `app/Services/Shipping/EasyParcelShipmentDetailsService.php`
- Modify: `app/Http/Controllers/Admin/ShipmentApiController.php`
- Modify: `config/services.php`
- Modify: `.env.example`
- Test: `tests/Feature/Admin/ShipmentApiTest.php`
- Test: `tests/Feature/EasyParcelWebhookReconciliationTest.php`

**Interfaces:**
- Produces: `EasyParcelStatusNormalizer::normalize(int $code): string` with distinct `cancelled` and `on_hold` values.
- Produces: refresh JSON `data.providerStatusCode`, `data.providerStatus`, `data.checkedAt`, `data.awbAvailable`, `data.recoveryDisposition` where disposition is `none|needs_review|provider_terminal`.

- [ ] **Step 1: Write failing endpoint and normalizer tests**

```php
public function test_status_refresh_returns_truthful_pending_awb_diagnostics(): void
{
    // Fake complete documented Details response with code 7 and null AWB.
    // Assert HTTP 200, changed=false, providerStatusCode=7,
    // providerStatus='Schedule In Arrangement', awbAvailable=false,
    // and recoveryDisposition='needs_review' for an aged shipment.
}

public function test_status_codes_zero_and_eight_preserve_cancelled_and_on_hold_semantics(): void
{
    self::assertSame('cancelled', app(EasyParcelStatusNormalizer::class)->normalize(0));
    self::assertSame('on_hold', app(EasyParcelStatusNormalizer::class)->normalize(8));
}
```

- [ ] **Step 2: Run the focused tests and verify RED**

Run:

```powershell
C:\xampp\php\php.exe artisan test tests/Feature/Admin/ShipmentApiTest.php tests/Feature/EasyParcelWebhookReconciliationTest.php
```

Expected: FAIL because the refresh document lacks the diagnostic fields and codes 0/8 collapse to `failed_delivery`.

- [ ] **Step 3: Implement the minimal diagnostic contract**

Add `EASYPARCEL_AWB_REVIEW_AFTER_MINUTES=10` configuration. Compute `needs_review` only when AWB is absent, provider status is non-terminal, and the shipment age exceeds the threshold. Return sanitized provider fields from the already-authenticated Details lookup; do not add a second provider request.

- [ ] **Step 4: Run focused tests and verify GREEN**

Run the Step 2 command. Expected: PASS.

- [ ] **Step 5: Commit**

```powershell
git add app/Services/Shipping/EasyParcelStatusNormalizer.php app/Services/Shipping/EasyParcelShipmentDetailsService.php app/Http/Controllers/Admin/ShipmentApiController.php config/services.php .env.example tests/Feature/Admin/ShipmentApiTest.php tests/Feature/EasyParcelWebhookReconciliationTest.php
git commit -m "fix: expose truthful EasyParcel recovery status"
```

### Task 2: Audited provider cancellation and failure confirmation

**Files:**
- Create: `database/migrations/2026_08_30_000100_create_easyparcel_shipment_recoveries_table.php`
- Create: `app/Models/EasyParcelShipmentRecovery.php`
- Create: `app/Services/Shipping/EasyParcelCancellationService.php`
- Create: `app/Services/Shipping/EasyParcelFailedBookingRecoveryService.php`
- Modify: `app/Models/Shipment.php`
- Modify: `app/Models/ShipmentQuote.php`
- Modify: `app/Services/Shipping/EasyParcelBookingGuard.php`
- Modify: `app/Services/Shipping/EasyParcelFulfillmentService.php`
- Test: `tests/Feature/EasyParcelFailedBookingRecoveryTest.php`

**Interfaces:**
- Produces: `EasyParcelCancellationService::cancel(string $shipmentNumber, string $remark): EasyParcelCancellationResult`.
- Produces: `EasyParcelFailedBookingRecoveryService::confirm(Shipment $shipment, User $actor, string $reason): EasyParcelShipmentRecovery`.
- Produces: `EasyParcelFailedBookingRecoveryService::completeForReplacement(Shipment $replacement): void`.

- [ ] **Step 1: Write failing recovery-domain tests**

```php
public function test_dispatch_staff_can_confirm_failure_and_provider_cancel_result_is_audited_once(): void
{
    // Use a real order, quote, shipment, user, and Http::fake documented cancel response.
    // Call confirm twice and assert one recovery, one cancellation HTTP request,
    // old shipment status booking_failed/cancelled, actor/reason retained,
    // and old quote booking_state failed.
}

public function test_cancel_error_is_audited_but_employee_confirmation_still_releases_rebooking(): void
{
    // Fake HTTP 200 with data[0].status='error'.
    // Assert provider_cancel_state='error', safe message retained,
    // old shipment booking_failed, and guard permits a fresh quote.
}

public function test_active_or_uncertain_easyparcel_attempt_still_blocks_duplicate_booking(): void
{
    // Assert pending/ready/uncertain attempts remain blocked.
}
```

- [ ] **Step 2: Run recovery test and verify RED**

Run:

```powershell
C:\xampp\php\php.exe artisan test tests/Feature/EasyParcelFailedBookingRecoveryTest.php
```

Expected: FAIL because the recovery model/table and services do not exist.

- [ ] **Step 3: Add the recovery aggregate and strict cancel parser**

The recovery table stores `shipment_id` unique, `order_id`, `confirmed_by`, `state`, `reason`, `provider_cancel_state`, `provider_cancel_message`, `replacement_shipment_id`, and confirmation/completion timestamps. Parse only the matching cancellation item and classify its own `status`; redact/control characters and bound the provider message.

- [ ] **Step 4: Make confirmation transactional and idempotent**

Lock order, shipment, quote, and recovery identity. Persist the employee confirmation before the external call, perform the cancel request once, then persist the sanitized result and terminalize the old attempt. Update booking guards and fulfillment finalization so terminal failed/cancelled history does not block a new booking while active/uncertain attempts still do.

- [ ] **Step 5: Run recovery and existing fulfillment tests**

```powershell
C:\xampp\php\php.exe artisan test tests/Feature/EasyParcelFailedBookingRecoveryTest.php tests/Feature/Admin/EasyParcelFulfillmentApiTest.php
```

Expected: PASS.

- [ ] **Step 6: Commit**

```powershell
git add database/migrations/2026_08_30_000100_create_easyparcel_shipment_recoveries_table.php app/Models/EasyParcelShipmentRecovery.php app/Models/Shipment.php app/Models/ShipmentQuote.php app/Services/Shipping/EasyParcelCancellationService.php app/Services/Shipping/EasyParcelFailedBookingRecoveryService.php app/Services/Shipping/EasyParcelBookingGuard.php app/Services/Shipping/EasyParcelFulfillmentService.php tests/Feature/EasyParcelFailedBookingRecoveryTest.php
git commit -m "feat: add audited EasyParcel failed booking recovery"
```

### Task 3: Protected recovery API and existing-modal rebook UX

**Files:**
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/Admin/ShipmentApiController.php`
- Modify: `app/Services/Admin/ShipmentDataService.php`
- Modify: `resources/admin/src/types/shipping.ts`
- Modify: `resources/admin/src/integration/shipmentData.ts`
- Modify: `resources/admin/src/context/AdminContext.tsx`
- Modify: `resources/admin/src/components/shipping/ShippingFulfillmentTab.tsx`
- Test: `tests/Feature/Admin/ShipmentApiTest.php`
- Test: `resources/admin/src/integration/shipmentData.test.tsx`
- Test: `resources/admin/src/components/shipping/ShippingDashboard.test.tsx`

**Interfaces:**
- Consumes: Task 1 refresh diagnostic document and Task 2 `confirm(...)` service.
- Produces: `POST /admin/api/shipping/shipments/{shipment}/easyparcel/recovery` with `{reason}`.
- Produces: `confirmEasyParcelProviderFailure(bootstrap, shipmentId, reason): Promise<EasyParcelRecoveryResult>`.

- [ ] **Step 1: Write failing protected endpoint tests**

```php
public function test_dispatch_staff_can_confirm_provider_failure_and_receives_rebook_instructions(): void
{
    // Fake cancel result, post a bounded reason, then assert replacementOrderId,
    // cancellation state, recovery id, and terminal shipment status.
}

public function test_view_only_staff_cannot_confirm_provider_failure(): void
{
    // Assert 403 and no recovery/audit mutation.
}
```

- [ ] **Step 2: Write failing TypeScript adapter and rendered-behavior tests**

```tsx
it('opens recovery guidance for an aged pending AWB and starts a fresh quote only after confirmation', async () => {
  // Assert the exact protected POST payload contains the employee reason,
  // the warning shows an unconfirmed cancellation outcome,
  // and the existing booking modal is opened for replacementOrderId.
});
```

- [ ] **Step 3: Run PHP and Admin tests and verify RED**

```powershell
C:\xampp\php\php.exe artisan test tests/Feature/Admin/ShipmentApiTest.php
npm --prefix resources/admin test -- --run resources/admin/src/integration/shipmentData.test.tsx resources/admin/src/components/shipping/ShippingDashboard.test.tsx
```

Expected: FAIL because the route, adapter, and recovery dialog behavior are absent.

- [ ] **Step 4: Implement the endpoint and UI bindings without redesign**

Reuse the current modal/card/button primitives and Tailwind classes. Show the exact provider diagnostic, collect a required 3–500 character reason, call the recovery endpoint, display the audited cancellation result, refresh shipment data, and then invoke the existing EasyParcel booking modal for the returned order id. Add `booking_failed`, `cancelled`, and `on_hold` to the existing Exceptions projection.

- [ ] **Step 5: Run focused PHP/Admin tests and verify GREEN**

Run the Step 3 commands. Expected: PASS.

- [ ] **Step 6: Commit**

```powershell
git add routes/web.php app/Http/Controllers/Admin/ShipmentApiController.php app/Services/Admin/ShipmentDataService.php resources/admin/src/types/shipping.ts resources/admin/src/integration/shipmentData.ts resources/admin/src/context/AdminContext.tsx resources/admin/src/components/shipping/ShippingFulfillmentTab.tsx tests/Feature/Admin/ShipmentApiTest.php resources/admin/src/integration/shipmentData.test.tsx resources/admin/src/components/shipping/ShippingDashboard.test.tsx
git commit -m "feat: add EasyParcel recovery and rebook workflow"
```

### Task 4: Hostinger minute cron entry point

**Files:**
- Create: `app/Console/Commands/RunEasyParcelCron.php`
- Modify: `routes/console.php`
- Modify: `README.md`
- Test: `tests/Feature/RunEasyParcelCronTest.php`

**Interfaces:**
- Produces: `php artisan easyparcel:cron --no-interaction`.
- Consumes: Laravel scheduler and database queue.

- [ ] **Step 1: Write failing command behavior tests**

```php
public function test_cron_runs_due_scheduler_then_drains_database_queue_with_a_lock(): void
{
    // Dispatch controlled due jobs, execute easyparcel:cron, and assert their
    // real database side effects plus an empty queue and exit code 0.
}

public function test_overlapping_cron_exits_cleanly_without_double_processing(): void
{
    // Hold the same cache lock, execute the command, and assert no job side effect.
}
```

- [ ] **Step 2: Run the command test and verify RED**

```powershell
C:\xampp\php\php.exe artisan test tests/Feature/RunEasyParcelCronTest.php
```

Expected: FAIL because `easyparcel:cron` does not exist.

- [ ] **Step 3: Implement bounded shared-host execution**

Acquire a 55-second atomic cache lock. Invoke `schedule:run --no-interaction`, then `queue:work database --queue=default --stop-when-empty --tries=1 --timeout=45 --max-time=50`. Release the lock in `finally`. Change `SweepEasyParcelReconciliation` to `everyMinute()` and retain tracking at 15 minutes.

- [ ] **Step 4: Document the exact Hostinger command**

```sh
cd /home/u762348670/apps/jwecommerce && php artisan easyparcel:cron --no-interaction
```

- [ ] **Step 5: Run command and scheduling tests and verify GREEN**

```powershell
C:\xampp\php\php.exe artisan test tests/Feature/RunEasyParcelCronTest.php tests/Feature/EasyParcelReconciliationRecoveryTest.php
```

Expected: PASS.

- [ ] **Step 6: Commit**

```powershell
git add app/Console/Commands/RunEasyParcelCron.php routes/console.php README.md tests/Feature/RunEasyParcelCronTest.php
git commit -m "ops: add bounded EasyParcel Hostinger cron"
```

### Task 5: Full local acceptance and exact-commit release

**Files:**
- Verify only: all changed files and built Admin assets

**Interfaces:**
- Consumes: Tasks 1–4.
- Produces: one verified Git commit shared by localhost, GitHub `main`, and Hostinger.

- [ ] **Step 1: Run complete backend and Admin verification**

```powershell
C:\xampp\php\php.exe artisan test
npm --prefix resources/admin run lint
npm --prefix resources/admin test -- --run
npm --prefix resources/admin run build
git diff --check
```

Expected: all commands exit 0 with no warnings attributable to this change.

- [ ] **Step 2: Run localhost migrations and clear caches**

```powershell
C:\xampp\php\php.exe artisan migrate --force
C:\xampp\php\php.exe artisan optimize:clear
```

- [ ] **Step 3: Perform authenticated localhost browser acceptance**

Verify the aged pending-AWB diagnostic, required reason, idempotent confirmation, cancellation-result warning, fresh quotation, replacement booking, replacement sequence, and recovery audit. Confirm unrelated shipping tabs and storefront routes remain visually unchanged.

- [ ] **Step 4: Commit built accepted source if build outputs are tracked**

```powershell
git status --short
git add public/build-admin
git commit -m "build: publish EasyParcel recovery admin assets"
```

If `git status --short public/build-admin` is empty because build assets are not tracked, skip this commit rather than adding ignored output.

- [ ] **Step 5: Push and deploy the exact commit after local acceptance**

```powershell
git push origin main
```

Deploy that exact SHA to `/home/u762348670/apps/jwecommerce`, then run production migration, cache clear, and one `easyparcel:cron` smoke execution. Do not copy localhost `.env`.

- [ ] **Step 6: Verify commit parity and live HTTPS behavior**

Compare local `git rev-parse HEAD`, GitHub `main`, and Hostinger `git rev-parse HEAD`. Verify the protected live flow and provide the user the Hostinger cron command only after the deployed command exists.
