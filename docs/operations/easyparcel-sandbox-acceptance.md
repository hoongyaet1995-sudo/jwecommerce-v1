# EasyParcel Sandbox Acceptance Runbook

## Purpose and stop conditions

This runbook records evidence without overstating what each environment proves. Keep the five evidence classes below separate in every test record, screenshot name, and release report.

```text
LOCAL_FIXTURE       official payload contract using Http::fake
LOCAL_BROWSER       authenticated localhost UI using local MySQL application data
SANDBOX_API         real EasyParcel Sandbox OAuth/quote/submit/details/tracking response
SANDBOX_WEBHOOK     EasyParcel Developer Hub endpoint validation/test delivery
LIVE_CARRIER        separately approved physical parcel event from an actual courier
```

`LOCAL_FIXTURE` proves the application contract against controlled official-shape payloads; it does not prove EasyParcel connectivity or physical delivery. `LOCAL_BROWSER` proves the authenticated localhost UI and local MySQL projection only. `SANDBOX_API` proves real Sandbox provider responses but Sandbox status progression may remain simulated. `SANDBOX_WEBHOOK` proves Developer Hub endpoint validation or test delivery only; it does not prove physical delivery. Only a separately approved `LIVE_CARRIER` parcel can prove a physical courier event.

Stop immediately if any action would expose an OAuth credential, refresh token, webhook path token, customer PII, or raw webhook body. Do not authorize Live, pay postage, submit a Live booking, or dispatch a parcel without separate explicit approval. Never retry an uncertain submit automatically; record it for reconciliation.

## Required acceptance record

Create one row per evidence class used. Use `not executed` rather than leaving a field ambiguous.

| Evidence class | Accepted order number | Backup filename | Pre-test status | Selected service | EasyParcel shipment/order reference | AWB presence | Webhook receipt ids | Resulting shipment/order state | Screenshots or artifacts | Rollback action |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `LOCAL_FIXTURE` | Fixture-specific; no development order | Not applicable; isolated test database | Fixture-specific | Fixture service from `Http::fake` | Fixture-only reference | Fixture-specific | Fixture-only ids | Asserted by automated tests | Test output in the Task 7 report | Roll back the test transaction/reset `jw_ecommerce_testing`; never restore `jw_ecommerce` |
| `LOCAL_BROWSER` | `JW-SMTP-20260827082409` | `C:\xampp\htdocs\jwstudio-ecommerce\storage\app\backups\jw_ecommerce-before-easyparcel-20260829-155212.sql` (195,830 bytes) | Order `Processing`; shipment `Pending AWB`; package not configured; no AWB | Not selected — the valid package request reached the quote boundary but the Sandbox connection was unavailable | Not created | Absent | None; truthful empty provider-receipt state after the pending local receipt-table migration was applied | Unchanged: order `Processing`, shipment `Pending AWB`, package not configured, no AWB | `.superpowers/sdd/2026-08-29-easyparcel-sandbox-lifecycle-closure/task-7-artifacts/local-browser-package-validation.png`; `local-browser-shipping-queues.png`; `local-browser-webhook-audit-status.png`; `local-browser-webhook-audit-error.png`; `local-browser-webhook-audit-empty.png`; `local-browser-admin-shipment-timeline.png` | None. The failed quote did not persist the temporary package override; backup retained and not restored |
| `SANDBOX_API` | Not executed in the local-only gate | Local backup must be referenced before later execution | Not executed | Not executed | Not executed | Not executed | Not applicable | Not executed | Not executed | Reconcile an uncertain submit; do not resubmit. Restore only documented local data if later acceptance requires it |
| `SANDBOX_WEBHOOK` | Not executed in the local-only gate | Local backup must be referenced before later execution | Not executed | Not applicable | Not executed | Not executed | Not executed | Not executed | Not executed | Disable/remove the Developer Hub endpoint and preserve sanitized audit evidence; do not treat the test delivery as carrier truth |
| `LIVE_CARRIER` | Blocked pending separate explicit approval | Not executed | Not executed | Not executed | Not executed | Not executed | Not executed | Not executed | Not executed | Follow the separately approved carrier cancellation/parcel recovery procedure; never infer refund, restock, or cancellation from tracking |

## Pre-flight safety gate

1. Confirm the application root is `C:\xampp\htdocs\jwstudio-ecommerce`.
2. Confirm PHPUnit resolves `DB_DATABASE=jw_ecommerce_testing`. Abort automated tests if it resolves to `jw_ecommerce` or production.
3. Confirm the only pre-existing worktree change is the user-owned `AGENTS.md`; do not stage it.
4. Confirm no customer PII, access token, refresh token, webhook path token, raw webhook body, or credential is copied into logs, screenshots, this document, or a commit.
5. Before any stateful localhost browser action, create and inspect a timestamped `jw_ecommerce` backup:

```powershell
$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$backupDir = 'C:\xampp\htdocs\jwstudio-ecommerce\storage\app\backups'
New-Item -ItemType Directory -Force -Path $backupDir | Out-Null
& 'C:\xampp\mysql\bin\mysqldump.exe' -u root jw_ecommerce | Out-File -Encoding utf8 "$backupDir\jw_ecommerce-before-easyparcel-$stamp.sql"
Get-Item "$backupDir\jw_ecommerce-before-easyparcel-$stamp.sql" | Select-Object FullName,Length,LastWriteTime
```

Record the absolute backup path and byte size in the acceptance record before continuing. A successful backup is a recovery point, not permission to alter unrelated development data.

## Automated regression gate (`LOCAL_FIXTURE`)

Run from the canonical XAMPP checkout:

```powershell
Set-Location C:\xampp\htdocs\jwstudio-ecommerce
C:\xampp\php\php.exe artisan config:clear
C:\xampp\php\php.exe artisan test tests/Feature/Admin/EasyParcelPlatformOauthTest.php tests/Feature/Admin/EasyParcelProviderApiTest.php tests/Feature/Admin/EasyParcelFulfillmentApiTest.php tests/Feature/EasyParcelTrackingLifecycleTest.php tests/Feature/EasyParcelWebhookIngressTest.php tests/Feature/EasyParcelWebhookReconciliationTest.php tests/Feature/Admin/ShipmentApiTest.php
C:\xampp\php\php.exe artisan test

Set-Location C:\xampp\htdocs\jwstudio-ecommerce\resources\admin
npm test -- --run
npm run lint
npm run build
```

Record every failure exactly. Do not suppress or rename failures. If the two known obsolete `OriginalAccountWorkspacePreviewTest` assertions still expect static `ATELIER` or `COMPLIMENTARY SHIPPING`, classify them as pre-existing unrelated stale account-shell assertions; the full suite is still not green. Investigate and classify every other failure separately.

## Authenticated localhost acceptance (`LOCAL_BROWSER`)

Use the existing signed-in browser session at `http://127.0.0.1:8500/admin/shipping`. Do not reset an administrator password and do not inspect cookies, storage, browser profiles, or password stores. If authentication is unavailable, record the exact blocker and stop the authenticated proof; never substitute fixture or source inspection for browser evidence.

Before selecting or editing an order, record its non-PII order number and pre-test status. Prefer a recorded development order in `Processing` with non-customer test data.

### Package and quote readiness

1. Open the Shipping queue and select the recorded Processing order.
2. Inspect the parcel package fields: weight, length, width, and height.
3. Prove missing or invalid dimensions visibly block quotation readiness.
4. Enter valid package values using the existing UI. Confirm the blocker disappears and the UI can form a quote request.
5. Confirm changing a package invalidates prior quotes and clears stale booking readiness.
6. Confirm quote expiry is visible and expired quotes cannot be booked.
7. Confirm COD services are offered only when the returned service supports the exact COD amount. Do not book or pay for a Live service.
8. Record selected service only if a safe local fixture flow returns one; otherwise record `not selected`.

### Webhook audit truth and refresh

1. Open the existing Tracking/Webhooks area.
2. Observe and record loading truth: loading must not be represented as an empty audit.
3. Observe one of the actual end states: sanitized rows, truthful empty state, or visible load error.
4. Trigger Refresh and record that the loading state appears and the newest completed request owns the result.
5. If cached rows remain after an error, verify they are visibly labelled stale/error rather than current.
6. Record only sanitized numeric receipt ids. Never capture token, payload hash, raw body, address, phone, email, or credentials.

### Queues and shared timeline

1. Inspect all five Shipping queues: `Pending AWB`, `Ready to Ship`, `In Transit`, `Delivered`, and `Exceptions`.
2. Confirm a shipment appears only in the queue implied by its canonical projected state.
3. From a safe recorded order, compare the same customer-safe timeline in Admin Order, customer Account, and `/order-tracking` after refresh.
4. Open a new browser session only if it does not require password reset, cookie/storage inspection, or disclosure of credentials. Otherwise record the session/authentication limitation.
5. Do not use a local webhook fixture as proof that a real webhook or carrier event occurred. Label any UI seeded with HTTP fakes as `LOCAL_BROWSER` plus fixture.

## Later external gates — not part of local acceptance

The following remain blocked until separately authorized and must not be marked complete from local evidence:

- Push the accepted SHA to GitHub `main` and verify the remote SHA.
- Deploy that exact SHA to `/apps/jwecommerce` on Hostinger and verify the deployed SHA and HTTPS health.
- Generate/store a production endpoint token on Hostinger and configure EasyParcel Developer Hub Topics 1–4.
- Execute one real `SANDBOX_API` quote/submit/details/tracking acceptance and Developer Hub `SANDBOX_WEBHOOK` test deliveries.
- Authorize or execute any `LIVE_CARRIER` booking, payment, dispatch, cancellation, or physical parcel event.

Sandbox closure and Live closure are separate decisions. A Sandbox response or static simulated progression must never be reported as a physical carrier delivery.

## Rollback and incident notes

- Package-only local edits: restore the recorded pre-test weight and L/W/H through the same application boundary when safe; record before/after values without customer data.
- Local fixture data: rely on isolated `jw_ecommerce_testing` transactions/migrations; never restore the development dump into the test database.
- Uncertain booking: do not submit again. Record the local order number and sanitized provider reference, then reconcile using authenticated provider Details/Tracking.
- Webhook test endpoint: disable/remove the Developer Hub endpoint only during the later authorized external gate; preserve sanitized receipt evidence.
- Development database: restore the timestamped dump only when data was unintentionally changed and restoration is explicitly approved/documented. Record the restore command, operator, time, and affected data.
- Live parcel: stop. Escalate for explicit operational approval; tracking exceptions/returns never automatically authorize refund, restock, cancellation, or another booking.
