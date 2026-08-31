# Order Notification Email Automation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the Google AI Studio notification mock with source-faithful Admin screens backed by Laravel, MySQL, and a queued, deduplicated order-milestone email outbox.

**Architecture:** Approved business mutations create a frozen `notification_dispatches` intent in the same database transaction through one `OrderNotificationMilestoneService`; an after-commit Laravel job sends the frozen snapshot and records `queued`, `sending`, `sent`, or `failed`. Protected Admin APIs own template configuration, preview, test email, audit, Retry, Resend, and Send Current Template, while the React Admin retains the selected Google AI Studio DOM/Tailwind structure and replaces browser mocks with narrow Laravel adapters.

**Tech Stack:** PHP 8.2+, Laravel 12, MySQL, Laravel Mail/Queue, React 19, TypeScript 5.8, Tailwind CSS 4, Vitest, PHPUnit 11, Playwright.

**Spec:** `docs/superpowers/specs/2026-08-31-order-notification-email-automation-design.md`

## Global Constraints

- All application changes start in `C:\xampp\htdocs\jwstudio-ecommerce`; localhost acceptance precedes any GitHub or Hostinger synchronization.
- Preserve the selected Google AI Studio ZIP DOM hierarchy, wrappers, Tailwind classes, responsive composition, copy ownership, variable-chip interaction, and desktop/mobile preview.
- Raw ZIP and extracted source stay private and outside Git/deployment.
- Automatic email is business-milestone based; regular milestones send at most once per order/event/channel, while each distinct proof-rejection audit event may send once.
- EasyParcel scan/webhook updates do not each send email; customer email links only to the JW-owned absolute `/order-tracking` route.
- Business state commits even when mail fails; dispatch is queued after commit and remains retryable.
- Staff Retry reuses a failed frozen snapshot; Resend creates a linked dispatch from the original frozen snapshot; Send Current Template renders a new snapshot explicitly.
- WhatsApp template editing/preview persists, but sending and test sending remain `Not connected` and create no success audit.
- Payment-proof re-upload is excluded; enabled templates cannot use `{payment_proof_upload_url}`.
- Do not send real customer mail during localhost acceptance; use `Mail::fake()`, the database queue, or a local mail sink.
- Do not push or deploy this phase until the user gives a new synchronization approval after localhost acceptance.
- Preserve the unrelated untracked file `docs/superpowers/reports/2026-08-31-smart-routing-functional-specification-audit.md`.

## File and Interface Map

### New backend files

- `database/migrations/2026_08_31_000000_create_notification_templates_table.php`: persisted operator template configuration.
- `database/migrations/2026_08_31_000100_create_notification_dispatches_table.php`: transactional outbox, frozen content, retry/resend lineage, and audit state.
- `app/Models/NotificationTemplate.php`: template casts, guarded fields, updater relation, and dispatch relation.
- `database/factories/NotificationTemplateFactory.php`: deterministic template fixtures for renderer/API tests.
- `app/Models/NotificationDispatch.php`: dispatch states/sources, casts, order/template/actor/resend relations.
- `database/factories/NotificationDispatchFactory.php`: queued/sent/failed frozen-snapshot fixtures.
- `app/Domain/Notifications/NotificationPermission.php`: explicit permissions and Super Admin override.
- `app/Domain/Notifications/NotificationEventCatalog.php`: fixed event keys, labels, variable allowlists, automatic dedup scopes, and dormant-channel metadata.
- `app/Domain/Notifications/RenderedNotification.php`: immutable rendered email value object.
- `app/Services/Notifications/NotificationTemplateDefaults.php`: Laravel-owned defaults adapted from the approved ZIP.
- `app/Services/Notifications/NotificationContextFactory.php`: order/customer/payment/shipment/store variable snapshots.
- `app/Services/Notifications/NotificationTemplateRenderer.php`: allowlisted replacement, HTML-safe text, and CTA validation.
- `app/Services/Notifications/OrderNotificationMilestoneService.php`: automatic event intent creation and deduplication.
- `app/Services/Notifications/NotificationDispatchService.php`: preview, test, Retry, Resend, and Send Current Template.
- `app/Jobs/SendOrderNotificationJob.php`: after-commit delivery and bounded retry.
- `app/Mail/OrderStatusNotificationMail.php`: mail envelope/content binding for a frozen dispatch.
- `app/Http/Controllers/Admin/NotificationTemplateApiController.php`: collection/update/reset/test endpoints.
- `app/Http/Controllers/Admin/OrderNotificationApiController.php`: preview, timeline, Retry, Resend, and Send Current Template endpoints.
- `app/Http/Requests/Admin/UpdateNotificationTemplateRequest.php`: template validation.
- `resources/views/mail/order-status-notification.blade.php`: branded HTML mail shell.
- `tests/Feature/Notifications/*`: persistence, rendering, delivery, dedup, and producer coverage.
- `tests/Feature/Admin/NotificationTemplateApiTest.php`: settings API permission/validation tests.
- `tests/Feature/Admin/OrderNotificationApiTest.php`: order-scoped preview/audit/manual action tests.

### New frontend files

- `resources/admin/src/types/notifications.ts`: API-backed notification template, preview, and dispatch contracts.
- `resources/admin/src/integration/notificationTemplateData.ts`: settings API adapter.
- `resources/admin/src/integration/orderNotificationData.ts`: order preview/audit/manual action adapter.
- `resources/admin/src/components/settings/NotificationTemplateSettingAdmin.tsx`: source-faithful settings editor.
- `resources/admin/src/components/settings/NotificationTemplateSettingAdmin.test.tsx`: settings states and interaction tests.
- `resources/admin/src/components/orders/OrderNotificationPanel.tsx`: source-faithful order preview/audit controls extracted from the Order Detail surface.
- `resources/admin/src/components/orders/OrderNotificationPanel.test.tsx`: queued/sent/failed and manual-action tests.

### Existing files modified

- `app/Models/Order.php` and `app/Models/User.php`: notification relations.
- `routes/web.php`: protected Admin notification endpoints with named routes and test-send throttle.
- `app/Http/Controllers/Admin/AdminAppController.php`: bootstrap URLs plus effective notification permissions.
- `app/Services/CheckoutService.php`: `order_created` and Commerce Order cancellation intents.
- `app/Services/Admin/OrderOperationsService.php`: Admin Update & Notify intent and opt-out handling.
- `app/Services/Payments/PaymentProofReviewService.php`: verified/rejected events.
- `app/Services/Payments/PaymentWebhookService.php`: Stripe/Billplz/PayPal verified events.
- `app/Services/ShipmentService.php`: manual dispatch, first carrier acceptance, and delivery events.
- `app/Services/Shipping/EasyParcelFulfillmentService.php` and reconciliation services: first official AWB `ready_to_ship`, acceptance, and delivery events only.
- `app/Services/Admin/OrderDataService.php`: order detail notification summary without bodies or recipient leakage.
- `app/Http/Controllers/Admin/OrderApiController.php`: accepts `notifyCustomer` and returns refreshed dispatch summary.
- `resources/admin/src/types/index.ts`: bootstrap URL/permission fields and Order notification summary.
- `resources/admin/src/integration/orderData.ts`: sends `notifyCustomer` in Admin status updates.
- `resources/admin/src/context/AdminContext.tsx`: exposes real notification mutation results without owning persistence.
- `resources/admin/src/components/settings/ShopSettingsHub.tsx`: mounts the source-faithful settings panel under `settings-email`.
- `resources/admin/src/components/orders/OrderAdmin.tsx`: replaces fabricated timeline entries with `OrderNotificationPanel`.
- `.env.example`: documents queue/mail configuration names with non-secret local defaults.
- `docs/runbooks/order-notification-email-operations.md`: worker, failure recovery, localhost, and later production validation commands.

---

### Task 1: Persist the fixed event catalog and operator templates

**Files:**
- Create: `database/migrations/2026_08_31_000000_create_notification_templates_table.php`
- Create: `app/Models/NotificationTemplate.php`
- Create: `database/factories/NotificationTemplateFactory.php`
- Create: `app/Domain/Notifications/NotificationEventCatalog.php`
- Create: `app/Services/Notifications/NotificationTemplateDefaults.php`
- Modify: `app/Models/User.php`
- Test: `tests/Feature/Notifications/NotificationTemplatePersistenceTest.php`

**Interfaces:**
- Produces: `NotificationEventCatalog::all(): array`, `NotificationEventCatalog::automaticEmailEvents(): array`, `NotificationEventCatalog::get(string $eventKey): array`, `NotificationEventCatalog::variablesFor(string $eventKey): array`.
- Produces: `NotificationTemplateDefaults::synchronize(): void`, called idempotently before template reads and by reset.
- Produces: `NotificationTemplate` fields matching the React `StatusNotificationTemplate` API contract.

- [ ] **Step 1: Write the failing catalog and persistence tests**

```php
public function test_catalog_exposes_seven_automatic_email_events_and_dormant_on_hold(): void
{
    $this->assertSame([
        'order_created', 'payment_verified', 'payment_proof_rejected',
        'ready_to_ship', 'shipped', 'delivered', 'cancelled',
    ], array_keys(NotificationEventCatalog::automaticEmailEvents()));
    $this->assertFalse(NotificationEventCatalog::get('on_hold')['automatic_email']);
}

public function test_defaults_are_idempotently_persisted_without_payment_reupload_claims(): void
{
    app(NotificationTemplateDefaults::class)->synchronize();
    app(NotificationTemplateDefaults::class)->synchronize();

    $this->assertDatabaseCount('notification_templates', 8);
    $this->assertDatabaseMissing('notification_templates', [
        'email_cta_url' => '{payment_proof_upload_url}',
        'email_enabled' => true,
    ]);
}
```

- [ ] **Step 2: Run the focused test and verify the missing classes/table failure**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/Notifications/NotificationTemplatePersistenceTest.php`

Expected: FAIL because the catalog, defaults service, model, and table do not exist.

- [ ] **Step 3: Add the migration with exact constraints and casts**

```php
Schema::create('notification_templates', function (Blueprint $table): void {
    $table->id();
    $table->string('event_key', 64)->unique();
    $table->string('status_name', 120);
    $table->string('color', 32);
    $table->boolean('email_enabled')->default(false);
    $table->string('email_subject', 255)->default('');
    $table->string('email_preheader', 255)->default('');
    $table->string('email_heading', 255)->default('');
    $table->text('email_body');
    $table->string('email_cta_text', 120)->default('');
    $table->string('email_cta_url', 2048)->default('');
    $table->boolean('whatsapp_enabled')->default(false);
    $table->text('whatsapp_message');
    $table->json('whatsapp_cta_buttons')->nullable();
    $table->unsignedInteger('version')->default(1);
    $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
});
```

- [ ] **Step 4: Implement the event catalog and adapted defaults**

Define the eight keys `order_created`, `payment_verified`, `payment_proof_rejected`, `ready_to_ship`, `shipped`, `delivered`, `cancelled`, and `on_hold`. Copy the selected ZIP wording and visual metadata, but change all active CTAs to `{tracking_url}` or `{order_url}`, rewrite the rejection body to contact Support, and force `on_hold.email_enabled=false`. `synchronize()` uses `firstOrCreate` so deploys never overwrite operator edits; reset is implemented later as an explicit update.

- [ ] **Step 5: Run the focused test and migration rollback cycle**

Run: `C:\xampp\php\php.exe artisan migrate:fresh --env=testing`

Run: `C:\xampp\php\php.exe artisan test tests/Feature/Notifications/NotificationTemplatePersistenceTest.php`

Expected: PASS; eight rows exist and enabled templates contain no unavailable upload placeholder.

- [ ] **Step 6: Commit the persistence slice**

```powershell
git add database/migrations/2026_08_31_000000_create_notification_templates_table.php database/factories/NotificationTemplateFactory.php app/Models/NotificationTemplate.php app/Models/User.php app/Domain/Notifications/NotificationEventCatalog.php app/Services/Notifications/NotificationTemplateDefaults.php tests/Feature/Notifications/NotificationTemplatePersistenceTest.php
git commit -m "feat: persist order notification templates"
```

### Task 2: Render safe frozen email content

**Files:**
- Create: `app/Domain/Notifications/RenderedNotification.php`
- Create: `app/Services/Notifications/NotificationContextFactory.php`
- Create: `app/Services/Notifications/NotificationTemplateRenderer.php`
- Test: `tests/Unit/Notifications/NotificationTemplateRendererTest.php`
- Test: `tests/Feature/Notifications/NotificationContextFactoryTest.php`

**Interfaces:**
- Consumes: `NotificationEventCatalog::variablesFor(string): array` and `NotificationTemplate`.
- Produces: `NotificationContextFactory::forOrder(Order $order, array $extra = []): array<string,string>`.
- Produces: `NotificationTemplateRenderer::render(NotificationTemplate $template, array $variables): RenderedNotification`.
- `RenderedNotification` properties: `subject`, `preheader`, `heading`, `body`, `ctaText`, `ctaUrl`, and `variables`.

- [ ] **Step 1: Write failing renderer security tests**

```php
public function test_renderer_escapes_values_and_resolves_only_owned_tracking_url(): void
{
    $rendered = $this->renderer->render($this->template([
        'email_body' => 'Hello {customer_name}',
        'email_cta_url' => '{tracking_url}',
    ]), [
        'customer_name' => '<script>alert(1)</script>',
        'tracking_url' => 'https://ecom.jwaistudio.online/order-tracking',
    ]);

    $this->assertSame('Hello &lt;script&gt;alert(1)&lt;/script&gt;', $rendered->body);
    $this->assertSame('https://ecom.jwaistudio.online/order-tracking', $rendered->ctaUrl);
}

#[DataProvider('unsafeCtaProvider')]
public function test_renderer_rejects_unsafe_cta(string $url): void
{
    $this->expectException(ValidationException::class);
    $this->renderer->render($this->template(['email_cta_url' => $url]), []);
}
```

Cover `javascript:`, `data:`, URL credentials, malformed URLs, unknown variables, missing required variables, and `{payment_proof_upload_url}` on enabled templates.

- [ ] **Step 2: Run the renderer tests and verify missing implementation failures**

Run: `C:\xampp\php\php.exe artisan test tests/Unit/Notifications/NotificationTemplateRendererTest.php tests/Feature/Notifications/NotificationContextFactoryTest.php`

Expected: FAIL because the renderer/value object/context factory do not exist.

- [ ] **Step 3: Implement allowlisted token extraction and CTA policy**

Use `preg_match_all('/\{[a-z0-9_]+\}/', ...)` across all configured fields. Reject tokens outside the event allowlist before replacement. Escape every variable with `e((string) $value)`, preserve message newlines for the Blade view, and accept CTA values only when they resolve to an application-owned relative path, HTTPS URL without credentials, or an allowlisted `mailto:` address.

- [ ] **Step 4: Implement order context without private provider data**

`forOrder()` loads customer, latest payment, shipping address, and latest shipment. It returns customer/order/payment/carrier/tracking/store values and both `{tracking_url}` and `{order_url}` as `url('/order-tracking')`; it never returns `label_path`, EasyParcel URLs/tokens, proof paths, or SMTP data. Extra values are restricted to catalog keys such as `staff_note` and `payment_proof_reason`.

- [ ] **Step 5: Run focused tests and formatting**

Run: `C:\xampp\php\php.exe artisan test tests/Unit/Notifications/NotificationTemplateRendererTest.php tests/Feature/Notifications/NotificationContextFactoryTest.php`

Run: `vendor\bin\pint --test app/Domain/Notifications app/Services/Notifications tests/Unit/Notifications tests/Feature/Notifications`

Expected: all tests pass and Pint reports no style violations.

- [ ] **Step 6: Commit the rendering slice**

```powershell
git add app/Domain/Notifications/RenderedNotification.php app/Services/Notifications/NotificationContextFactory.php app/Services/Notifications/NotificationTemplateRenderer.php tests/Unit/Notifications/NotificationTemplateRendererTest.php tests/Feature/Notifications/NotificationContextFactoryTest.php
git commit -m "feat: render safe order notification content"
```

### Task 3: Build the transactional outbox, mail, and queue job

**Files:**
- Create: `database/migrations/2026_08_31_000100_create_notification_dispatches_table.php`
- Create: `app/Models/NotificationDispatch.php`
- Create: `database/factories/NotificationDispatchFactory.php`
- Create: `app/Services/Notifications/OrderNotificationMilestoneService.php`
- Create: `app/Jobs/SendOrderNotificationJob.php`
- Create: `app/Mail/OrderStatusNotificationMail.php`
- Create: `resources/views/mail/order-status-notification.blade.php`
- Modify: `app/Models/Order.php`
- Modify: `app/Models/User.php`
- Test: `tests/Feature/Notifications/OrderNotificationMilestoneTest.php`
- Test: `tests/Feature/Notifications/SendOrderNotificationJobTest.php`

**Interfaces:**
- Produces: `OrderNotificationMilestoneService::record(Order $order, string $eventKey, string $sourceType, string|int $sourceId, ?User $actor = null, array $extra = []): ?NotificationDispatch`.
- Produces: `SendOrderNotificationJob::__construct(int $dispatchId)` with `$tries=4` and `backoff(): array` returning `[60, 300, 900]`.
- Automatic idempotency key: `automatic:email:order:{orderId}:event:{eventKey}` except rejection, which appends `:source:{sourceType}:{sourceId}`.

- [ ] **Step 1: Write failing outbox and transaction tests**

```php
public function test_regular_milestone_creates_one_after_commit_dispatch(): void
{
    Queue::fake();
    $first = $this->service->record($this->order, 'payment_verified', 'order_payment', 41);
    $second = $this->service->record($this->order, 'payment_verified', 'payment_webhook_event', 99);

    $this->assertTrue($first->is($second));
    $this->assertDatabaseCount('notification_dispatches', 1);
    Queue::assertPushed(SendOrderNotificationJob::class, 1);
}

public function test_rolled_back_business_transaction_creates_no_dispatch_or_job(): void
{
    Queue::fake();
    try {
        DB::transaction(function (): void {
            $this->service->record($this->order, 'order_created', 'order', $this->order->id);
            throw new RuntimeException('rollback');
        });
    } catch (RuntimeException) {
    }

    $this->assertDatabaseCount('notification_dispatches', 0);
    Queue::assertNothingPushed();
}
```

Add separate assertions that two distinct `OrderPaymentProofEvent` IDs create two rejection dispatches.

- [ ] **Step 2: Run focused tests and verify schema/service failures**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/Notifications/OrderNotificationMilestoneTest.php tests/Feature/Notifications/SendOrderNotificationJobTest.php`

Expected: FAIL because the dispatch table, service, job, and Mailable do not exist.

- [ ] **Step 3: Add the dispatch migration**

Create a UUID `public_id`, nullable `order_id` for isolated test sends, nullable template/actor/resend foreign keys, channel/event/source fields, nullable unique `idempotency_key`, recipient and complete rendered snapshots, JSON variable snapshot, state/source enums represented as indexed strings, attempts/timestamps, safe error code, provider message ID, and timestamps. Add indexes on `(order_id, created_at)`, `(state, queued_at)`, and `(event_key, channel)`.

- [ ] **Step 4: Implement automatic intent creation inside the caller transaction**

Use `firstOrCreate(['idempotency_key' => $key], $snapshot)` for automatic events. If Email is disabled or the event is dormant, return `null`. Dispatch `SendOrderNotificationJob::dispatch($dispatch->id)->afterCommit()` only when the row was newly created. Do not catch transaction errors or send Mail directly.

- [ ] **Step 5: Implement the frozen Mailable and bounded job state machine**

```php
public function handle(Mailer $mailer): void
{
    $dispatch = NotificationDispatch::query()->findOrFail($this->dispatchId);
    if ($dispatch->state === 'sent') return;

    $dispatch->forceFill([
        'state' => 'sending',
        'attempt_count' => $dispatch->attempt_count + 1,
    ])->save();
    try {
        $mailer->to($dispatch->recipient_email, $dispatch->recipient_name)
            ->send(new OrderStatusNotificationMail($dispatch));
        $dispatch->forceFill(['state' => 'sent', 'sent_at' => now(), 'last_error_code' => null])->save();
    } catch (Throwable $error) {
        $dispatch->forceFill([
            'state' => 'failed',
            'failed_at' => now(),
            'last_error_code' => $this->safeErrorCode($error),
        ])->save();
        throw $error;
    }
}
```

Add a private `safeErrorCode(Throwable $error): string` method that maps transport/authentication, timeout, and unknown failures to `transport`, `timeout`, or `unknown` without persisting exception text. The Blade view renders only frozen fields, uses `nl2br` on already escaped body text, includes preheader text, and never loads the current template.

- [ ] **Step 6: Prove send, failure, retry bounds, and no private links**

Use `Mail::fake()` for successful assertions and a throwing mailer fake for failure. Assert business rows remain committed, `last_error_code` is safe/classified, full exception text is absent, and rendered HTML contains `/order-tracking` but no `easyparcel.com`, `label_path`, token, or proof storage path.

- [ ] **Step 7: Run focused tests and commit**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/Notifications/OrderNotificationMilestoneTest.php tests/Feature/Notifications/SendOrderNotificationJobTest.php`

```powershell
git add database/migrations/2026_08_31_000100_create_notification_dispatches_table.php database/factories/NotificationDispatchFactory.php app/Models/NotificationDispatch.php app/Models/Order.php app/Models/User.php app/Services/Notifications/OrderNotificationMilestoneService.php app/Jobs/SendOrderNotificationJob.php app/Mail/OrderStatusNotificationMail.php resources/views/mail/order-status-notification.blade.php tests/Feature/Notifications/OrderNotificationMilestoneTest.php tests/Feature/Notifications/SendOrderNotificationJobTest.php
git commit -m "feat: add queued order notification outbox"
```

### Task 4: Add protected template, preview, audit, Retry, and Resend APIs

**Files:**
- Create: `app/Domain/Notifications/NotificationPermission.php`
- Create: `app/Services/Notifications/NotificationDispatchService.php`
- Create: `app/Http/Requests/Admin/UpdateNotificationTemplateRequest.php`
- Create: `app/Http/Controllers/Admin/NotificationTemplateApiController.php`
- Create: `app/Http/Controllers/Admin/OrderNotificationApiController.php`
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/Admin/AdminAppController.php`
- Test: `tests/Feature/Admin/NotificationTemplateApiTest.php`
- Test: `tests/Feature/Admin/OrderNotificationApiTest.php`

**Interfaces:**
- Permissions: `notifications.templates.view`, `notifications.templates.manage`, `notifications.test.send`, `notifications.deliveries.view`, `notifications.deliveries.retry`, `notifications.deliveries.resend`, `notifications.current.send`.
- Settings endpoints: `GET /admin/api/notification-templates`, `PUT /admin/api/notification-templates/{eventKey}`, `POST /admin/api/notification-templates/reset`, `POST /admin/api/notification-templates/{eventKey}/test`.
- Order endpoints: `GET /admin/api/orders/{order}/notification-dispatches`, `POST /admin/api/orders/{order}/notifications/preview`, `POST /admin/api/orders/{order}/notifications/send-current`, `POST /admin/api/notification-dispatches/{dispatch}/retry`, `POST /admin/api/notification-dispatches/{dispatch}/resend`.

- [ ] **Step 1: Write failing authorization and validation tests**

```php
public function test_staff_need_specific_template_permission(): void
{
    $staff = User::factory()->create(['is_admin' => true]);
    $this->actingAs($staff)->getJson('/admin/api/notification-templates')->assertForbidden();
    $staff->permissions()->create(['permission' => NotificationPermission::TEMPLATES_VIEW]);
    $this->actingAs($staff)->getJson('/admin/api/notification-templates')->assertOk();
}

public function test_enabled_template_rejects_unavailable_variable(): void
{
    $this->actingAs($this->superAdmin())->putJson('/admin/api/notification-templates/payment_proof_rejected', [
        'emailEnabled' => true,
        'emailCtaUrl' => '{payment_proof_upload_url}',
    ])->assertUnprocessable();
}
```

Also cover unauthenticated access, ordinary Admin access, Super Admin override, event-key mismatch, unsafe CTA, reset actor/version, and `throttle:6,1` on email tests.

- [ ] **Step 2: Write failing Retry/Resend semantics tests**

```php
public function test_resend_uses_original_frozen_snapshot_after_template_edit(): void
{
    $sent = NotificationDispatch::factory()->sent()->create(['rendered_subject' => 'Original subject']);
    $sent->template->update(['email_subject' => 'Edited subject']);

    $response = $this->actingAs($this->superAdmin())
        ->postJson("/admin/api/notification-dispatches/{$sent->public_id}/resend")
        ->assertCreated();

    $response->assertJsonPath('data.subject', 'Original subject');
    $response->assertJsonPath('data.resendOfId', $sent->public_id);
}
```

Cover failed-only Retry, sent-only Resend, current-template manual send, order/dispatch ownership, and no automatic idempotency collision.

- [ ] **Step 3: Run the API tests and verify route/class failures**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/Admin/NotificationTemplateApiTest.php tests/Feature/Admin/OrderNotificationApiTest.php`

Expected: FAIL with missing routes/controllers/services.

- [ ] **Step 4: Implement explicit permission checks and stable DTOs**

`NotificationPermission::allows(User $user, string $permission): bool` returns true for Super Admin or an exact `user_permissions.permission` row. Controllers return only approved DTO fields; errors never include rendered bodies, recipients from other orders, stack traces, provider URLs, or mail credentials.

- [ ] **Step 5: Implement manual dispatch semantics**

```php
public function retry(NotificationDispatch $dispatch, User $actor): NotificationDispatch;
public function resend(NotificationDispatch $dispatch, User $actor): NotificationDispatch;
public function sendCurrent(Order $order, string $eventKey, User $actor, array $extra = []): NotificationDispatch;
public function test(NotificationTemplate $template, string $recipientEmail, User $actor): NotificationDispatch;
```

Retry resets the same failed row to `queued` without changing snapshot fields. Resend inserts a fresh `manual_resend` row linked by `resend_of_id`. Send Current Template creates `admin_current_template`; test creates `test` with nullable `order_id` and sample variables, and neither appears in another order's timeline. All queue dispatches use `afterCommit()`.

- [ ] **Step 6: Add named routes, bootstrap URLs, permission DTO, and rate limiting**

Add routes inside the existing `auth + admin` group. Add `notificationTemplatesUrl`, `orderNotificationsUrl`, and the effective `notificationPermissions` array to `AdminAppController`; do not expose SMTP settings or template bodies in the HTML bootstrap.

- [ ] **Step 7: Run the API tests and commit**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/Admin/NotificationTemplateApiTest.php tests/Feature/Admin/OrderNotificationApiTest.php`

```powershell
git add app/Domain/Notifications/NotificationPermission.php app/Services/Notifications/NotificationDispatchService.php app/Http/Requests/Admin/UpdateNotificationTemplateRequest.php app/Http/Controllers/Admin/NotificationTemplateApiController.php app/Http/Controllers/Admin/OrderNotificationApiController.php app/Http/Controllers/Admin/AdminAppController.php routes/web.php tests/Feature/Admin/NotificationTemplateApiTest.php tests/Feature/Admin/OrderNotificationApiTest.php
git commit -m "feat: expose protected notification operations"
```

### Task 5: Connect every approved business milestone producer

**Files:**
- Modify: `app/Services/CheckoutService.php`
- Modify: `app/Services/Admin/OrderOperationsService.php`
- Modify: `app/Services/Payments/PaymentProofReviewService.php`
- Modify: `app/Services/Payments/PaymentWebhookService.php`
- Modify: `app/Services/ShipmentService.php`
- Modify: `app/Services/Shipping/EasyParcelFulfillmentService.php`
- Modify: `app/Services/Shipping/EasyParcelBookingReconciliationService.php`
- Modify: `app/Services/Shipping/EasyParcelWebhookReconciliationService.php`
- Modify: `app/Http/Controllers/Admin/OrderApiController.php`
- Modify: `app/Services/Admin/OrderDataService.php`
- Test: `tests/Feature/Notifications/NotificationProducerCoverageTest.php`
- Test: existing payment and EasyParcel feature tests touched by the integrations.

**Interfaces:**
- Consumes: `OrderNotificationMilestoneService::record(...)` from Task 3.
- `OrderOperationsService::updateStatus(...)` gains trailing `bool $notifyCustomer = true`.
- Admin PATCH body gains `notifyCustomer: boolean`; omission remains `true` for compatibility.
- Producer source IDs use persisted audit truth: order ID, payment ID/webhook event ID, proof event ID, shipment ID/event ID, or reconciliation task ID.

- [ ] **Step 1: Add a table-driven failing producer coverage test**

```php
public function test_duplicate_provider_updates_do_not_duplicate_business_milestones(): void
{
    Queue::fake();
    $this->reconcileEasyParcel($this->shipment, statusCode: 3);
    $this->reconcileEasyParcel($this->shipment, statusCode: 3);

    $this->assertDatabaseCount('notification_dispatches', 1);
    $this->assertDatabaseHas('notification_dispatches', [
        'order_id' => $this->shipment->order_id,
        'event_key' => 'shipped',
        'source' => 'automatic',
    ]);
}
```

Add cases for checkout creation, Admin Update & Notify checked/unchecked, proof verified, two distinct proof rejections, Stripe, Billplz, PayPal, manual dispatch, first official AWB, first carrier acceptance, delivery, repeated webhook/poll, EasyParcel cancellation/rebook, On Hold, Returned, and Commerce Order cancellation.

- [ ] **Step 2: Run producer tests and confirm missing dispatches**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/Notifications/NotificationProducerCoverageTest.php`

Expected: FAIL because existing producers do not call the milestone service.

- [ ] **Step 3: Wire checkout, Admin transitions, proof review, and hosted payment webhooks**

Call `record()` inside each producer's existing `DB::transaction` after its authoritative state/audit row is written. For rejected proof, use the newly created `OrderPaymentProofEvent` ID. Pass Admin `customerCommentInput` as `staff_note`; when `notifyCustomer=false`, mutate/audit the order but skip only the Admin-requested notification.

- [ ] **Step 4: Wire manual shipping and EasyParcel milestones without scan spam**

Record `ready_to_ship` only when an EasyParcel shipment first gains an official label/AWB. Record `shipped` only for manual dispatch or first confirmed carrier acceptance; if first observed provider truth is already in transit/delivered, backfill `shipped` once. Record `delivered` once. Never record mail for status `0` AWB cancellation, `6` Returned, `8` On Hold, `11` Drop-off Required, repeat scans, location updates, polls without a new milestone, or rebooking alone.

- [ ] **Step 5: Ensure Commerce cancellation is distinct from AWB cancellation**

Only an authoritative Commerce Order transition to `cancelled` creates the `cancelled` dispatch. Assert cancelling an EasyParcel shipment leaves the Commerce order active and produces no cancellation email.

- [ ] **Step 6: Run producer and regression suites**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/Notifications/NotificationProducerCoverageTest.php tests/Feature/Admin/OrderApiTest.php tests/Feature/Admin/OrderPaymentProofApiTest.php tests/Feature/Payment tests/Feature/EasyParcelTrackingLifecycleTest.php tests/Feature/EasyParcelWebhookReconciliationTest.php tests/Feature/EasyParcelReconciliationRecoveryTest.php`

Expected: all pass; duplicate delivery/provider calls preserve exactly one automatic dispatch per regular key.

- [ ] **Step 7: Commit the producer integration**

```powershell
git add app/Services/CheckoutService.php app/Services/Admin/OrderOperationsService.php app/Services/Payments/PaymentProofReviewService.php app/Services/Payments/PaymentWebhookService.php app/Services/ShipmentService.php app/Services/Shipping/EasyParcelFulfillmentService.php app/Services/Shipping/EasyParcelBookingReconciliationService.php app/Services/Shipping/EasyParcelWebhookReconciliationService.php app/Http/Controllers/Admin/OrderApiController.php app/Services/Admin/OrderDataService.php tests/Feature/Notifications/NotificationProducerCoverageTest.php
git commit -m "feat: emit deduplicated order email milestones"
```

### Task 6: Add typed React API adapters

**Files:**
- Create: `resources/admin/src/types/notifications.ts`
- Create: `resources/admin/src/integration/notificationTemplateData.ts`
- Create: `resources/admin/src/integration/notificationTemplateData.test.ts`
- Create: `resources/admin/src/integration/orderNotificationData.ts`
- Create: `resources/admin/src/integration/orderNotificationData.test.ts`
- Modify: `resources/admin/src/types/index.ts`
- Modify: `resources/admin/src/integration/adminBootstrap.ts`
- Modify: `resources/admin/src/integration/orderData.ts`
- Modify: `resources/admin/src/integration/orderData.test.ts`

**Interfaces:**
- Produces typed functions `fetchNotificationTemplates`, `saveNotificationTemplate`, `resetNotificationTemplates`, `sendNotificationTest`, `fetchOrderNotificationDispatches`, `previewOrderNotification`, `retryNotificationDispatch`, `resendNotificationDispatch`, and `sendCurrentOrderNotification`.
- `updateOrderStatusData(..., note?: string, notifyCustomer?: boolean): Promise<Order>` includes `notifyCustomer` in JSON.

- [ ] **Step 1: Write failing adapter URL/body/error tests**

```ts
it('sends the staff notification choice with the order mutation', async () => {
  await updateOrderStatusData(bootstrap, '17', 'paid', 'processing', 'Packed', false);
  expect(fetch).toHaveBeenCalledWith('/admin/api/orders/17/status', expect.objectContaining({
    body: JSON.stringify({ paymentStatus: 'paid', fulfillmentStatus: 'processing', note: 'Packed', notifyCustomer: false }),
  }));
});

it('uses separate retry and resend endpoints', async () => {
  await retryNotificationDispatch(bootstrap, 'dispatch-1');
  await resendNotificationDispatch(bootstrap, 'dispatch-2');
  expect(fetch).toHaveBeenNthCalledWith(1, '/admin/api/notification-dispatches/dispatch-1/retry', expect.anything());
  expect(fetch).toHaveBeenNthCalledWith(2, '/admin/api/notification-dispatches/dispatch-2/resend', expect.anything());
});
```

- [ ] **Step 2: Run Vitest and verify missing exports/types**

Run: `npm --prefix resources/admin test -- --run src/integration/notificationTemplateData.test.ts src/integration/orderNotificationData.test.ts src/integration/orderData.test.ts`

Expected: FAIL because the new adapters/contracts do not exist.

- [ ] **Step 3: Implement stable DTO types and same-origin adapters**

Use `credentials: 'same-origin'`, `Accept: application/json`, `Content-Type: application/json`, and bootstrap CSRF on writes. Convert 403/422/429/5xx responses to safe user-facing `Error` messages while retaining validation field messages. Do not store templates or dispatches in `localStorage`.

- [ ] **Step 4: Run adapter tests and TypeScript lint**

Run: `npm --prefix resources/admin test -- --run src/integration/notificationTemplateData.test.ts src/integration/orderNotificationData.test.ts src/integration/orderData.test.ts`

Run: `npm --prefix resources/admin run lint`

Expected: PASS with no TypeScript errors.

- [ ] **Step 5: Commit the adapter slice**

```powershell
git add resources/admin/src/types/notifications.ts resources/admin/src/types/index.ts resources/admin/src/integration/adminBootstrap.ts resources/admin/src/integration/notificationTemplateData.ts resources/admin/src/integration/notificationTemplateData.test.ts resources/admin/src/integration/orderNotificationData.ts resources/admin/src/integration/orderNotificationData.test.ts resources/admin/src/integration/orderData.ts resources/admin/src/integration/orderData.test.ts
git commit -m "feat: bind admin notification APIs"
```

### Task 7: Port the source-faithful Settings template editor

**Files:**
- Create: `resources/admin/src/components/settings/NotificationTemplateSettingAdmin.tsx`
- Create: `resources/admin/src/components/settings/NotificationTemplateSettingAdmin.test.tsx`
- Modify: `resources/admin/src/components/settings/ShopSettingsHub.tsx`
- Modify: `resources/admin/src/context/AdminContext.tsx`

**Interfaces:**
- Consumes Task 6 adapters and bootstrap permissions.
- Saves one current template with optimistic `version`; resets all templates only after confirmation.
- Email test queues a real test; WhatsApp test returns no dispatch and displays `Not connected`.

- [ ] **Step 1: Copy the selected source component into the canonical project unchanged**

Copy only the approved `NotificationTemplateSettingAdmin.tsx` DOM/classes/interactions from the private extraction. Do not import the source ZIP `AdminContext`; temporarily leave its data calls uncompilable so the next test proves the adapter boundary is required.

- [ ] **Step 2: Write failing source-contract and behavior tests**

```tsx
it('renders payment rejection and dormant on-hold in the source status matrix', async () => {
  const host = document.createElement('div');
  const root = createRoot(host);
  await act(async () => { root.render(<NotificationTemplateSettingAdmin />); });
  expect(host.textContent).toContain('Payment Proof Rejected');
  expect(host.textContent).toContain('On Hold');
  expect(host.textContent).toContain('DORMANT');
  root.unmount();
});

it('never simulates WhatsApp delivery', async () => {
  const host = document.createElement('div');
  const root = createRoot(host);
  await act(async () => { root.render(<NotificationTemplateSettingAdmin />); });
  const whatsapp = Array.from(host.querySelectorAll('button')).find((button) => button.textContent?.includes('WhatsApp Message')) as HTMLButtonElement;
  await act(async () => { whatsapp.click(); });
  expect(host.textContent).toContain('Not connected');
  expect(host.textContent).not.toContain('successfully sent');
  root.unmount();
});
```

Also test API load/save/reset confirmation, validation display, variable insertion at cursor, desktop/mobile preview toggle, queued email-test state, and persistence after remount.

- [ ] **Step 3: Run component tests and confirm missing-binding failures**

Run: `npm --prefix resources/admin test -- --run src/components/settings/NotificationTemplateSettingAdmin.test.tsx`

Expected: FAIL because source mock handlers/context fields are not operational adapters.

- [ ] **Step 4: Replace only the mock ownership boundaries**

Keep the component wrappers/classes/copy and bind list/save/reset/test to Task 6. Remove `localStorage` and simulated toasts. Add approved micro-states using adjacent badge/button classes: `Queued`, validation error, `Not connected`, and dormant Email disabled. Filter variable chips by the API-provided event allowlist so `{payment_proof_upload_url}` cannot be inserted into enabled templates.

- [ ] **Step 5: Mount under the existing settings route**

Change the tab label to `Auto Email & WhatsApp Template` and return the new component for `currentSection === 'settings-email'` inside the existing Settings page shell. Do not alter unrelated tabs or layout.

- [ ] **Step 6: Run tests, lint, and production build**

Run: `npm --prefix resources/admin test -- --run src/components/settings/NotificationTemplateSettingAdmin.test.tsx src/integration/notificationTemplateData.test.ts`

Run: `npm --prefix resources/admin run lint`

Run: `npm --prefix resources/admin run build`

Expected: PASS; only pre-existing Vite chunk-size or Node-engine warnings are acceptable.

- [ ] **Step 7: Commit the settings UI**

```powershell
git add resources/admin/src/components/settings/NotificationTemplateSettingAdmin.tsx resources/admin/src/components/settings/NotificationTemplateSettingAdmin.test.tsx resources/admin/src/components/settings/ShopSettingsHub.tsx resources/admin/src/context/AdminContext.tsx
git commit -m "feat: add source-faithful notification settings"
```

### Task 8: Bind Order Detail preview, real audit, Retry, Resend, and manual send

**Files:**
- Create: `resources/admin/src/components/orders/OrderNotificationPanel.tsx`
- Create: `resources/admin/src/components/orders/OrderNotificationPanel.test.tsx`
- Modify: `resources/admin/src/components/orders/OrderAdmin.tsx`
- Modify: `resources/admin/src/components/orders/OrderAdmin.test.ts`
- Modify: `resources/admin/src/context/AdminContext.tsx`

**Interfaces:**
- Consumes Task 6 order notification adapters.
- Emits Admin status mutation with `notifyCustomerCheck` and refreshes the selected order plus dispatch audit.
- Retry is shown only for `failed`; Resend only for `sent`; Send Current Template is always a separate authorized action.

- [ ] **Step 1: Write failing Order Detail behavior tests**

```tsx
it('shows real queued state instead of fabricated sent timeline text', async () => {
  const host = document.createElement('div');
  const root = createRoot(host);
  await act(async () => { root.render(<OrderNotificationPanel order={order} eventKey="payment_verified" />); });
  expect(host.textContent).toContain('Queued');
  expect(host.textContent).not.toContain('instant customer notification');
  root.unmount();
});

it('keeps Retry and Resend semantically separate', async () => {
  const host = document.createElement('div');
  const root = createRoot(host);
  await act(async () => { root.render(<OrderNotificationPanel order={orderWithFailedAndSentDispatches} eventKey="shipped" />); });
  const labels = Array.from(host.querySelectorAll('button')).map((button) => button.textContent?.trim());
  expect(labels.filter((label) => label === 'Retry')).toHaveLength(1);
  expect(labels.filter((label) => label === 'Resend')).toHaveLength(1);
  expect(labels).toContain('Send Current Template');
  root.unmount();
});
```

Also cover Email-only checkbox copy, unchecked status update, preview current note/order values, refresh after mutation, permission-disabled buttons, and WhatsApp `Not connected`.

- [ ] **Step 2: Run focused tests and verify missing panel/real audit failures**

Run: `npm --prefix resources/admin test -- --run src/components/orders/OrderNotificationPanel.test.tsx src/components/orders/OrderAdmin.test.ts`

Expected: FAIL because Order Detail still owns simulated notification events.

- [ ] **Step 3: Extract and bind the approved Order Detail notification region**

Preserve the ZIP's `UPDATE ORDER STATUS & DISPATCH CUSTOMER NOTIFICATION` frame and preview interaction. Replace its checkbox copy with Email-only wording; status selection requests server preview, `Update & Notify` passes `notifyCustomerCheck`, and mutation response shows `Queued` when an intent exists.

- [ ] **Step 4: Remove fabricated notification timeline writes**

Delete client-created `email_sent` and `whatsapp_sent` events. Render dispatch audit from the protected API with subject, channel, state, source, queued/sent/failed timestamps, and safe error label only. Keep existing order status history separately intact.

- [ ] **Step 5: Bind Retry, Resend, and Send Current Template**

After each action, refetch the order's dispatch audit. Resend confirmation explicitly says it uses original content. Send Current Template confirmation says it uses the current preview. No button changes the automatic dedup row or reports `Sent` before the worker succeeds.

- [ ] **Step 6: Run focused tests, lint, and build**

Run: `npm --prefix resources/admin test -- --run src/components/orders/OrderNotificationPanel.test.tsx src/components/orders/OrderAdmin.test.ts src/integration/orderNotificationData.test.ts`

Run: `npm --prefix resources/admin run lint`

Run: `npm --prefix resources/admin run build`

Expected: all pass; Order Detail contains no simulated notification success strings.

- [ ] **Step 7: Commit the Order Detail integration**

```powershell
git add resources/admin/src/components/orders/OrderNotificationPanel.tsx resources/admin/src/components/orders/OrderNotificationPanel.test.tsx resources/admin/src/components/orders/OrderAdmin.tsx resources/admin/src/components/orders/OrderAdmin.test.ts resources/admin/src/context/AdminContext.tsx
git commit -m "feat: show real order notification delivery audit"
```

### Task 9: Document queue operations and prove full localhost closure

**Files:**
- Modify: `.env.example`
- Create: `docs/runbooks/order-notification-email-operations.md`
- Create: `tests/Feature/Notifications/OrderNotificationSecurityTest.php`
- Create: `resources/admin/tests/order-notification-email.spec.ts`

**Interfaces:**
- Documents local `QUEUE_CONNECTION=database` and non-secret Mail configuration names.
- Browser test uses authenticated Admin state and a dedicated test order/mail sink; it never contacts a real customer mailbox.

- [ ] **Step 1: Add failing security/non-exposure assertions**

```php
public function test_public_and_admin_shell_responses_do_not_leak_private_notification_data(): void
{
    $dispatch = NotificationDispatch::factory()->create([
        'rendered_body' => 'private body',
        'recipient_email' => 'private@example.test',
    ]);

    $this->get('/order-tracking')->assertDontSee('private@example.test');
    $this->actingAs($this->superAdmin())->get('/admin/orders')
        ->assertDontSee('private body')
        ->assertDontSee('private@example.test');
}
```

Also assert logs exclude full body, SMTP secrets, EasyParcel URLs/tokens, and PII; unauthorized users cannot enumerate template or dispatch data.

- [ ] **Step 2: Run the security test and fix only verified leaks**

Run: `C:\xampp\php\php.exe artisan test tests/Feature/Notifications/OrderNotificationSecurityTest.php`

Expected before fixes: any leaked DTO/bootstrap/log field fails with its exact value; after narrowing serializers/log context, PASS.

- [ ] **Step 3: Write the operations runbook**

Document these exact localhost commands and expected observations:

```powershell
C:\xampp\php\php.exe artisan migrate
C:\xampp\php\php.exe artisan queue:work --queue=default --tries=4 --backoff=60,300,900
C:\xampp\php\php.exe artisan queue:failed
C:\xampp\php\php.exe artisan queue:retry <failed-job-uuid>
C:\xampp\php\php.exe artisan optimize:clear
```

Document that application Retry is for a `notification_dispatches` row and differs from Laravel's failed-job retry. Document Hostinger's durable worker as a release prerequisite without inserting production credentials.

- [ ] **Step 4: Add authenticated desktop/mobile Playwright acceptance**

Cover Settings load/save/refresh, rejection and dormant On Hold rows, variable insertion, desktop/mobile preview, real queued test email, WhatsApp `Not connected`, Order Detail preview, unchecked status update, queued-to-sent worker transition, failed Retry, sent Resend using original content, Send Current Template, refresh/re-login persistence, and no private provider URL in DOM/network JSON.

- [ ] **Step 5: Run all backend verification**

Run: `C:\xampp\php\php.exe artisan migrate:fresh --env=testing`

Run: `C:\xampp\php\php.exe artisan test`

Run: `vendor\bin\pint --test`

Expected: all tests pass; no migration rollback error; no style violations.

- [ ] **Step 6: Run all Admin verification**

Run: `npm --prefix resources/admin test -- --run`

Run: `npm --prefix resources/admin run lint`

Run: `npm --prefix resources/admin run build`

Expected: all tests and TypeScript checks pass; production assets build successfully.

- [ ] **Step 7: Run authenticated localhost browser acceptance**

Run the local Laravel server and queue worker in separate terminals, then run:

`npx --prefix resources/admin playwright test resources/admin/tests/order-notification-email.spec.ts --project=chromium`

Expected: desktop and mobile acceptance pass; MySQL rows survive refresh/re-login; mail progresses only when the worker runs.

- [ ] **Step 8: Verify scope and worktree hygiene**

Run: `git status --short`

Run: `git diff --check`

Run: `git diff --name-only HEAD~8..HEAD`

Expected: only notification-phase files plus pre-existing unrelated user files are present; the Smart Routing report is untouched; no ZIP extraction/raw source is tracked.

- [ ] **Step 9: Commit runbook and acceptance coverage**

```powershell
git add .env.example docs/runbooks/order-notification-email-operations.md tests/Feature/Notifications/OrderNotificationSecurityTest.php resources/admin/tests/order-notification-email.spec.ts
git commit -m "test: close localhost order email automation"
```

- [ ] **Step 10: Stop at the release approval gate**

Report exact test counts, build result, browser screenshots/artifacts, current local SHA, remaining warnings, and capability mapping states. Ask for a fresh explicit approval before pushing GitHub `main`, changing Hostinger `.env`, starting a production worker, or sending a production test email.

## Self-Review Results

- Spec coverage: all seven automatic events, repeated proof rejection, webhook dedup, tracking-only EasyParcel scans, dormant WhatsApp, safe variables/CTA, outbox transactionality, failure state, Retry, Resend, Send Current Template, test mail, permissions, source fidelity, browser persistence, and release gate are assigned to tasks.
- Boundary coverage: EasyParcel AWB cancellation is explicitly separated from Commerce cancellation; On Hold/Returned/Drop-off Required remain tracking/exception-only; payment proof re-upload remains excluded.
- Type consistency: backend event keys, dispatch states/sources, endpoint names, React DTO fields, and Retry/Resend semantics match across Tasks 1–9.
- Placeholder scan: the plan contains no deferred implementation markers; production credentials and GitHub/Hostinger changes are intentionally gated, not omitted.
