# Order Notification Email Operations

## Scope and safety boundary

This runbook operates the Laravel/MySQL Email notification outbox on the official protected Admin routes. It does not connect WhatsApp, create a payment-proof re-upload flow, authorize an EasyParcel action, or send a production message. Local acceptance must use a dedicated `@example.test` fixture and a process-only `MAIL_MAILER=array` or `MAIL_MAILER=log` override. Never print `.env`, transport credentials, rendered bodies, recipient addresses, EasyParcel URLs/tokens, or provider message identifiers into command output or evidence.

Local development truth is `C:\xampp\htdocs\jwstudio-ecommerce`. GitHub and Hostinger synchronization require a fresh approval after localhost acceptance.

## Configuration contract

The non-secret configuration names are documented in `.env.example`:

```dotenv
QUEUE_CONNECTION=database
DB_QUEUE_CONNECTION=
DB_QUEUE_TABLE=jobs
DB_QUEUE=default
DB_QUEUE_RETRY_AFTER=90
QUEUE_FAILED_DRIVER=database-uuids

MAIL_MAILER=log
MAIL_SCHEME=null
MAIL_HOST=127.0.0.1
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_LOG_CHANNEL=null
MAIL_FROM_ADDRESS="hello@example.com"
MAIL_FROM_NAME="${APP_NAME}"
```

`MAIL_MAILER=log` is a non-external local default. The Task 9 smoke harness overrides it with `array` for its owned Laravel server, but it does not start a queue worker or execute a delivery action. Production transport values stay outside Git and are not part of this runbook.

## Local migration and worker operations

From `C:\xampp\htdocs\jwstudio-ecommerce`:

```powershell
C:\xampp\php\php.exe artisan optimize:clear
C:\xampp\php\php.exe artisan migrate
C:\xampp\php\php.exe artisan queue:work --queue=default --tries=4 --backoff=60,300,900
```

Expected observations:

- `optimize:clear` removes stale cached environment/configuration state.
- `migrate` applies pending migrations without deleting existing rows.
- the worker stays attached to the `default` database queue and processes `SendOrderNotificationJob` after the business transaction commits;
- a successful local sink delivery changes the owned `notification_dispatches` row from `queued` to `sent`, sets `sent_at`, increments `attempt_count`, and removes its `jobs` row;
- bounded delivery failures store only `transport`, `timeout`, or `unknown` on the dispatch. Queue publication failures use `queue`. Raw exception text is not a dispatch or log field.

Stop an interactive worker with `Ctrl+C`. After a deploy or configuration change, restart the worker so it loads the accepted code and environment.

## Failure inspection and the two retry paths

List Laravel failed jobs without exposing the serialized payload:

```powershell
C:\xampp\php\php.exe artisan queue:failed
```

Laravel infrastructure retry uses the UUID from `failed_jobs`:

```powershell
C:\xampp\php\php.exe artisan queue:retry <failed-job-uuid>
```

This is different from the Admin application's **Retry** action:

| Operation | Owner/key | Preconditions | Result |
| --- | --- | --- | --- |
| Admin **Retry** | existing `notification_dispatches.public_id` | exact `notifications.deliveries.retry` permission and dispatch state `failed` | resets the same frozen dispatch to `queued` and publishes a fresh queue job |
| `queue:retry` | existing `failed_jobs.uuid` | infrastructure issue corrected and exact job UUID selected | requeues Laravel's serialized failed job; it does not create a new dispatch or edit its frozen snapshot |

Use one recovery path deliberately. Do not bulk-retry all failed jobs. Match the safe dispatch ID and failed-job UUID operationally, correct the transport/worker cause, execute one retry, and verify the resulting single dispatch state before taking another action.

**Resend** is not retry. It accepts only a `sent` dispatch, creates a new `manual_resend` row linked by `resend_of_id`, and copies the original frozen content. **Send Current Template** is a separate authorized action that renders and freezes the currently persisted template.

## Local acceptance sequence

The committed Playwright test is intentionally a minimal read-only browser gate. It owns a unique Admin/order/dispatch fixture in the dedicated MySQL database `jw_ecommerce_testing`, refuses any other database name, uses only `@example.test` addresses, starts its own localhost server with `MAIL_MAILER=array`, captures sanitized smoke evidence under `output/playwright/`, and removes only its exact owned rows during teardown. It does not send a test Email, start a worker, mutate an order/template, Retry, Resend, Send Current, or claim cross-session persistence.

The database safety gate must resolve to exactly:

```text
APP_ENV=testing
DB_CONNECTION=mysql
DB_DATABASE=jw_ecommerce_testing
QUEUE_CONNECTION=database
MAIL_MAILER=array
```

After clearing cached configuration, prove the resolved runtime before any migration. The harness `probe` mode is read-only and exits non-zero unless all five values match the gate above:

```powershell
$env:APP_ENV='testing'
$env:DB_CONNECTION='mysql'
$env:DB_DATABASE='jw_ecommerce_testing'
$env:QUEUE_CONNECTION='database'
$env:MAIL_MAILER='array'
$probe = & C:\xampp\php\php.exe resources\admin\tests\fixtures\order-notification-email-harness.php probe | ConvertFrom-Json
if ($probe.environment -ne 'testing' -or $probe.connection -ne 'mysql' -or $probe.database -ne 'jw_ecommerce_testing' -or $probe.queue -ne 'database' -or $probe.mailer -ne 'array') {
    throw 'Unsafe notification acceptance runtime; migration refused.'
}
C:\xampp\php\php.exe artisan migrate --env=testing --force --no-interaction

Set-Location resources\admin
npx playwright test tests/order-notification-email.spec.ts --project=chromium
Set-Location ..\..
```

For routine acceptance, use the non-destructive `artisan migrate --env=testing --force` command used by the Playwright harness. Run `migrate:fresh` only after the read-only probe has resolved the exact dedicated database in the same shell session. Never run `migrate:fresh`, drop, truncate, or fixture cleanup against `jw_ecommerce`. If the resolved connection is not MySQL database `jw_ecommerce_testing` (or a separately approved isolated SQLite database), stop.

## Localhost fidelity evidence

The approved Google AI Studio source baseline is recorded in the implementation specification. Task 9 did not change the notification Settings or Order Detail DOM/classes. The smoke gate confirms that the accepted composition loads with real Laravel/MySQL fixture data on protected routes. `output/playwright/` is local acceptance evidence and is not a release asset.

| Artifact | Capture state | Material comparison result |
| --- | --- | --- |
| `output/playwright/order-notification-email/settings-desktop-smoke.png` | `/admin/settings/email`, 1440×1000 | source-derived navigation, status matrix, editor, and customer preview load from the protected app |
| `output/playwright/order-notification-email/settings-mobile-smoke.png` | protected Settings route at 390×844 | mobile viewport smoke is captured; the existing fixed Admin sidebar clips the main Settings surface and remains a pre-existing shell warning, not a Task 9 redesign |
| `output/playwright/order-notification-email/order-detail-desktop-smoke.png` | `/admin/orders`, dedicated order, read-only preview opened | Email notification frame, current preview, WhatsApp `Not connected`, failed/sent audit shells, and authorized controls are visible without executing them |
| `output/playwright/order-notification-email/order-detail-mobile-smoke.png` | same protected Order Detail at 390×844 | the notification modal remains attached and renderable at the mobile viewport; no lifecycle action is executed |

The browser intercepted and aborted the pre-existing external Unsplash Admin-avatar request before network. No EasyParcel/provider URL or identifier appeared in the DOM, request URLs, or HTML/JSON response bodies.

## Hostinger release prerequisite

No production worker/configuration change is authorized by this localhost phase. Before a later release can be called operational, Hostinger must have a durable, monitored worker for the accepted SHA and environment. Its effective queue policy must process the `default` database queue with four attempts and backoff `60,300,900`, restart after deploys/failures, and expose safe failed-job monitoring. The existing bounded `easyparcel:cron` queue drain uses a different one-attempt policy and is not, by itself, proof of the notification delivery requirement.

After deployment of the exact accepted commit, the Hostinger acceptance gate must cover production MySQL persistence; the durable Hostinger worker; actual SMTP delivery to a dedicated test mailbox; Settings save/refresh/re-login; queued-to-sent delivery; failed Retry; sent Resend with the frozen original; Send Current Template; action permissions and sensitive-data non-exposure; and desktop/mobile browser evidence. Use unique production-test fixtures and clean only those exact rows.

Actual SMTP sending is not authorized by this runbook or the localhost result. Immediately before the production mail test, obtain a fresh explicit user authorization naming the dedicated test mailbox and accepted commit. Never use a real customer address.

Do not add credentials to Git, a process command, screenshots, or this document. Production worker setup, SMTP configuration, a production test recipient, GitHub synchronization, and Hostinger deployment each remain behind explicit approval.

## Commerce closure mapping

Boundary: `OPERATIONAL_CLOSURE`; runtime truth is Laravel + MySQL; Admin ownership is operator-managed where stated; dispatch snapshots are immutable history; official routes remain `/admin/settings/email` and `/admin/orders`; production cutover/synchronization is not approved.

| Feature ID | Business owner / MySQL truth | Producer -> service/API -> consumer | Time / eligibility | Status | Acceptance evidence | Exclusion or blocker | Official route state |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `notifications.template.manage` | operator-managed; `notification_templates` | Settings editor -> protected template API -> persisted editor/preview | realtime editable version; exact permissions | `REAL_DATA_BINDING` | authorization/versioning/component tests; authenticated Settings smoke only | production save/refresh/re-login still required | localhost official smoke verified; production unsynchronized |
| `notifications.email.render` | system-managed; frozen `notification_dispatches` fields | context factory + renderer -> preview/Mailable -> protected preview and Email HTML | current preview; immutable after dispatch | `SYSTEM_MANAGED` | renderer/escaping tests and read-only Order Detail preview smoke | no delivery exercised locally under revised gate | localhost official smoke verified; production unsynchronized |
| `notifications.email.automatic` | system-managed; `notification_dispatches` + `jobs` | seven milestone producers -> outbox/job -> customer Email | deduplicated automatic intent; proof rejection source-scoped | `REAL_DATA_BINDING` | Laravel producer/outbox/job tests | Hostinger worker and actual SMTP delivery pending | localhost tests only; production operational gate open |
| `notifications.email.audit` | internal-only; `notification_dispatches` | job lifecycle -> protected dispatch API -> Order Detail audit | realtime bounded 100-row view; exact view permission | `INTERNAL_ONLY` | security DTO/log tests and authenticated audit-shell smoke | queued-to-sent production evidence pending | localhost official smoke verified; production unsynchronized |
| `notifications.email.retry` | operator-managed; same failed dispatch row | Order Detail Retry -> protected retry API -> database queue | failed-only; frozen snapshot reused | `REAL_DATA_BINDING` | service/API/permission tests; Retry control visible in smoke | post-deploy failed -> queued -> sent acceptance pending | localhost action not executed; production operational gate open |
| `notifications.email.resend` | operator-managed; new linked dispatch | Order Detail Resend -> protected resend API -> queue/audit | sent-only; immutable original copied | `REAL_DATA_BINDING` | frozen-snapshot/API tests; Resend control visible in smoke | post-deploy frozen-original delivery pending | localhost action not executed; production operational gate open |
| `notifications.email.test` | operator-managed; nullable-order `test` dispatch | Settings modal -> throttled protected API -> mail transport | validated dedicated test recipient | `REAL_DATA_BINDING` | rate-limit/permission/queue tests; Settings smoke | requires fresh approval and named dedicated mailbox before production send | localhost action not executed; production operational gate open |
| `notifications.whatsapp.templates` | operator-managed; WhatsApp fields on `notification_templates` | Settings editor -> template API -> preview only | editable preview; no delivery eligibility | `DORMANT` | browser `Not connected`; provider/action absent in tests | provider/action intentionally absent | visible dormant state; no cutover |
| `notifications.proof_reupload` | none by approved design | none -> none -> rejection Email links only to JW tracking/Support | unavailable | `DORMANT` | catalog/renderer tests retain tracking/Support links without an upload claim | explicitly excluded from phase | no route or cutover |

No capability is marked `CLOSED` by the revised localhost gate. Production operational closure remains open until the exact-commit Hostinger matrix above passes under fresh deployment and mail-send authorization.
