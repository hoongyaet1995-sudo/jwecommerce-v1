<?php

namespace Tests\Feature\Admin;

use App\Domain\Notifications\NotificationPermission;
use App\Jobs\SendOrderNotificationJob;
use App\Models\Customer;
use App\Models\NotificationDispatch;
use App\Models\NotificationTemplate;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\StoreSetting;
use App\Models\User;
use App\Services\Notifications\NotificationTemplateDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class OrderNotificationApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(NotificationTemplateDefaults::class)->synchronize();
    }

    public function test_order_notification_routes_require_authentication_and_the_exact_action_permission(): void
    {
        $order = $this->order('AUTH');
        $staff = User::factory()->create(['is_admin' => true, 'is_super_admin' => false]);

        $this->getJson("/admin/api/orders/{$order->id}/notification-dispatches")->assertUnauthorized();
        $this->actingAs($staff)->getJson("/admin/api/orders/{$order->id}/notification-dispatches")->assertForbidden();

        $staff->permissions()->create(['permission' => NotificationPermission::DELIVERIES_VIEW.'.extra']);
        $this->actingAs($staff)->getJson("/admin/api/orders/{$order->id}/notification-dispatches")->assertForbidden();
        $this->actingAs($staff)->postJson("/admin/api/orders/{$order->id}/notifications/preview", [
            'eventKey' => 'order_created',
        ])->assertForbidden();

        $staff->permissions()->create(['permission' => NotificationPermission::DELIVERIES_VIEW]);
        $this->actingAs($staff)->getJson("/admin/api/orders/{$order->id}/notification-dispatches")->assertOk();
        $this->actingAs($staff)->postJson("/admin/api/orders/{$order->id}/notifications/preview", [
            'eventKey' => 'order_created',
        ])->assertOk();
        $this->actingAs($this->superAdmin())->postJson("/admin/api/orders/{$order->id}/notifications/preview", [
            'eventKey' => 'order_created',
        ])->assertOk();
    }

    public function test_preview_uses_the_authorized_order_context_and_current_staff_note(): void
    {
        $order = $this->order('PREVIEW');

        $this->actingAs($this->superAdmin())->postJson("/admin/api/orders/{$order->id}/notifications/preview", [
            'eventKey' => 'order_created',
            'staffNote' => 'Please complete payment before Friday.',
        ])->assertOk()
            ->assertJsonPath('data.eventKey', 'order_created')
            ->assertJsonPath('data.statusName', 'Pending Payment')
            ->assertJsonPath('data.emailEnabled', true)
            ->assertJsonPath('data.subject', '[JW Studio] Order #JW-NOTIFY-PREVIEW Received - Please Complete Payment')
            ->assertJsonPath('data.variables.order_number', 'JW-NOTIFY-PREVIEW')
            ->assertJsonPath('data.variables.staff_note', 'Please complete payment before Friday.');
    }

    public function test_preview_allows_exact_send_current_permission_without_granting_delivery_audit_access(): void
    {
        $order = $this->order('CURRENT-PREVIEW');
        $sender = $this->staffWith([NotificationPermission::CURRENT_SEND]);

        $this->actingAs($sender)->postJson("/admin/api/orders/{$order->id}/notifications/preview", [
            'eventKey' => 'payment_verified',
        ])->assertOk()
            ->assertJsonPath('data.statusName', 'Processing & Verified')
            ->assertJsonPath('data.emailEnabled', true);

        $this->actingAs($sender)
            ->getJson("/admin/api/orders/{$order->id}/notification-dispatches")
            ->assertForbidden();

        $caseVariant = $this->staffWith([strtoupper(NotificationPermission::CURRENT_SEND)]);
        $this->actingAs($caseVariant)->postJson("/admin/api/orders/{$order->id}/notifications/preview", [
            'eventKey' => 'payment_verified',
        ])->assertForbidden();
    }

    public function test_preview_exposes_dormant_email_truth_and_send_current_still_rejects_it(): void
    {
        $order = $this->order('DORMANT');
        StoreSetting::query()->create(['key' => 'support_phone', 'value' => ['data' => '+60312345678']]);

        $this->actingAs($this->staffWith([NotificationPermission::CURRENT_SEND]))
            ->postJson("/admin/api/orders/{$order->id}/notifications/preview", [
                'eventKey' => 'on_hold',
            ])->assertOk()
            ->assertJsonPath('data.statusName', 'On Hold (Review Required)')
            ->assertJsonPath('data.emailEnabled', false);

        $this->actingAs($this->staffWith([NotificationPermission::CURRENT_SEND]))
            ->postJson("/admin/api/orders/{$order->id}/notifications/send-current", [
                'eventKey' => 'on_hold',
            ])->assertUnprocessable()
            ->assertJsonValidationErrors('eventKey');

        NotificationTemplate::query()->where('event_key', 'on_hold')->update(['email_enabled' => true]);
        $this->actingAs($this->staffWith([NotificationPermission::CURRENT_SEND]))
            ->postJson("/admin/api/orders/{$order->id}/notifications/send-current", [
                'eventKey' => 'on_hold',
            ])->assertUnprocessable()
            ->assertJsonValidationErrors('eventKey');
    }

    public function test_timeline_is_order_scoped_and_never_leaks_recipients_bodies_or_provider_fields(): void
    {
        $firstOrder = $this->order('FIRST');
        $secondOrder = $this->order('SECOND');
        $first = NotificationDispatch::factory()->create([
            'order_id' => $firstOrder->id,
            'snapshot_version' => NotificationDispatch::SNAPSHOT_VERSION_LEGACY_ESCAPED,
            'rendered_subject' => 'Shipped via J&amp;T for O&#039;Brien',
            'recipient_email' => 'first-secret@example.com',
            'rendered_body' => 'First private body',
            'provider_message_id' => 'provider-secret-first',
        ]);
        NotificationDispatch::factory()->create([
            'order_id' => $secondOrder->id,
            'recipient_email' => 'second-secret@example.com',
            'rendered_body' => 'Second private body',
            'provider_message_id' => 'provider-secret-second',
        ]);
        NotificationDispatch::factory()->create(['order_id' => null, 'source' => NotificationDispatch::SOURCE_TEST]);

        $response = $this->actingAs($this->superAdmin())
            ->getJson("/admin/api/orders/{$firstOrder->id}/notification-dispatches")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $first->public_id)
            ->assertJsonPath('data.0.subject', "Shipped via J&T for O'Brien");

        $encoded = json_encode($response->json(), JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('first-secret@example.com', $encoded);
        $this->assertStringNotContainsString('First private body', $encoded);
        $this->assertStringNotContainsString('provider-secret', $encoded);
        $this->assertStringNotContainsString('second-secret@example.com', $encoded);
    }

    public function test_retry_requeues_the_same_failed_frozen_dispatch_and_rejects_non_failed_dispatches(): void
    {
        Queue::fake();
        $order = $this->order('RETRY');
        $failed = NotificationDispatch::factory()->failed()->create([
            'order_id' => $order->id,
            'rendered_subject' => 'Frozen retry subject',
            'rendered_body' => 'Frozen retry body',
            'attempt_count' => 4,
        ]);
        $sent = NotificationDispatch::factory()->sent()->create(['order_id' => $order->id]);
        $sending = NotificationDispatch::factory()->create([
            'order_id' => $order->id,
            'state' => NotificationDispatch::STATE_SENDING,
            'delivery_lease_token' => 'a56a79e3-d177-4180-adcb-9c2800262d25',
            'delivery_lease_expires_at' => now()->addMinute(),
        ]);
        $queued = NotificationDispatch::factory()->create(['order_id' => $order->id]);

        $this->actingAs($this->staffWith([NotificationPermission::DELIVERIES_RETRY]))
            ->postJson("/admin/api/notification-dispatches/{$failed->public_id}/retry")
            ->assertOk()
            ->assertJsonPath('data.id', $failed->public_id)
            ->assertJsonPath('data.state', NotificationDispatch::STATE_QUEUED)
            ->assertJsonPath('data.subject', 'Frozen retry subject');

        $failed->refresh();
        $this->assertSame('Frozen retry subject', $failed->rendered_subject);
        $this->assertSame('Frozen retry body', $failed->rendered_body);
        $this->assertSame(0, $failed->attempt_count);
        $this->assertNull($failed->queue_attempt);
        $this->assertNull($failed->failed_at);
        $this->assertNull($failed->last_error_code);
        Queue::assertPushed(SendOrderNotificationJob::class, fn (SendOrderNotificationJob $job): bool => $job->dispatchId === $failed->id);

        $this->actingAs($this->superAdmin())
            ->postJson("/admin/api/notification-dispatches/{$sent->public_id}/retry")
            ->assertUnprocessable();
        $this->actingAs($this->superAdmin())
            ->postJson("/admin/api/notification-dispatches/{$sending->public_id}/retry")
            ->assertUnprocessable();
        $this->actingAs($this->superAdmin())
            ->postJson("/admin/api/notification-dispatches/{$queued->public_id}/retry")
            ->assertUnprocessable();
    }

    public function test_resend_uses_the_original_frozen_snapshot_after_template_edit(): void
    {
        Queue::fake();
        $order = $this->order('RESEND');
        $template = NotificationTemplate::query()->where('event_key', 'order_created')->firstOrFail();
        $sent = NotificationDispatch::factory()->sent()->create([
            'order_id' => $order->id,
            'notification_template_id' => $template->id,
            'event_key' => 'order_created',
            'rendered_subject' => 'Original subject',
            'rendered_body' => 'Original frozen body',
            'idempotency_key' => "automatic:email:order:{$order->id}:event:order_created",
        ]);
        $template->update(['email_subject' => 'Edited subject', 'version' => 2]);

        $response = $this->actingAs($this->staffWith([NotificationPermission::DELIVERIES_RESEND]))
            ->postJson("/admin/api/notification-dispatches/{$sent->public_id}/resend")
            ->assertCreated()
            ->assertJsonPath('data.subject', 'Original subject')
            ->assertJsonPath('data.resendOfId', $sent->public_id)
            ->assertJsonPath('data.source', NotificationDispatch::SOURCE_MANUAL_RESEND);

        $resend = NotificationDispatch::query()->where('public_id', $response->json('data.id'))->firstOrFail();
        $this->assertSame($order->id, $resend->order_id);
        $this->assertSame('Original frozen body', $resend->rendered_body);
        $this->assertSame($sent->id, $resend->resend_of_id);
        $this->assertNull($resend->idempotency_key);
        $this->assertDatabaseHas('notification_dispatches', [
            'id' => $sent->id,
            'idempotency_key' => "automatic:email:order:{$order->id}:event:order_created",
            'state' => NotificationDispatch::STATE_SENT,
        ]);
        Queue::assertPushed(SendOrderNotificationJob::class, fn (SendOrderNotificationJob $job): bool => $job->dispatchId === $resend->id);
    }

    public function test_resend_rejects_a_failed_dispatch(): void
    {
        $failed = NotificationDispatch::factory()->failed()->create(['order_id' => $this->order('FAILED-RESEND')->id]);

        $this->actingAs($this->superAdmin())
            ->postJson("/admin/api/notification-dispatches/{$failed->public_id}/resend")
            ->assertUnprocessable();
    }

    public function test_resend_preserves_a_legacy_snapshot_format_and_decodes_it_once_for_the_api(): void
    {
        Queue::fake();
        $order = $this->order('RESEND-LEGACY');
        $legacy = NotificationDispatch::factory()->sent()->create([
            'order_id' => $order->id,
            'snapshot_version' => NotificationDispatch::SNAPSHOT_VERSION_LEGACY_ESCAPED,
            'rendered_subject' => 'J&amp;T &amp; O&#039;Brien &lt;b&gt;safe&lt;/b&gt;',
            'rendered_body' => 'Legacy &lt;img src=x onerror=alert(1)&gt;',
            'variable_snapshot' => ['shop_name' => 'J&amp;T &lt;script&gt;bad&lt;/script&gt;'],
        ]);

        $response = $this->actingAs($this->staffWith([NotificationPermission::DELIVERIES_RESEND]))
            ->postJson("/admin/api/notification-dispatches/{$legacy->public_id}/resend")
            ->assertCreated()
            ->assertJsonPath('data.subject', "J&T & O'Brien <b>safe</b>");

        $resend = NotificationDispatch::query()->where('public_id', $response->json('data.id'))->firstOrFail();
        $this->assertSame(NotificationDispatch::SNAPSHOT_VERSION_LEGACY_ESCAPED, $resend->snapshot_version);
        $this->assertSame($legacy->rendered_subject, $resend->rendered_subject);
        $this->assertSame($legacy->rendered_body, $resend->rendered_body);
        $this->assertStringNotContainsString('&amp;amp;', $response->getContent());
    }

    public function test_send_current_template_creates_a_new_order_owned_manual_snapshot_without_automatic_idempotency(): void
    {
        Queue::fake();
        $firstOrder = $this->order('CURRENT');
        $secondOrder = $this->order('OTHER');
        NotificationTemplate::query()->where('event_key', 'order_created')->update([
            'email_subject' => 'Current template for #{order_number}',
            'version' => 9,
        ]);

        $response = $this->actingAs($this->staffWith([NotificationPermission::CURRENT_SEND]))
            ->postJson("/admin/api/orders/{$firstOrder->id}/notifications/send-current", [
                'eventKey' => 'order_created',
                'staffNote' => 'Manual order note',
            ])->assertCreated()
            ->assertJsonPath('data.subject', 'Current template for #JW-NOTIFY-CURRENT')
            ->assertJsonPath('data.source', NotificationDispatch::SOURCE_ADMIN_CURRENT_TEMPLATE)
            ->assertJsonPath('data.state', NotificationDispatch::STATE_QUEUED);

        $dispatch = NotificationDispatch::query()->where('public_id', $response->json('data.id'))->firstOrFail();
        $this->assertSame($firstOrder->id, $dispatch->order_id);
        $this->assertSame(9, $dispatch->template_version);
        $this->assertSame(NotificationDispatch::SNAPSHOT_VERSION_PLAIN_TEXT, $dispatch->snapshot_version);
        $this->assertNotNull($dispatch->queue_generation);
        $this->assertNull($dispatch->idempotency_key);
        $this->assertSame('Manual order note', $dispatch->variable_snapshot['staff_note']);

        $this->actingAs($this->superAdmin())
            ->getJson("/admin/api/orders/{$secondOrder->id}/notification-dispatches")
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_current_preview_and_send_use_the_same_latest_renderer_context(): void
    {
        Queue::fake();
        $order = $this->order('CONVERGE');
        $order->customer()->update(['email' => 'preferred-converge@example.com']);
        Shipment::query()->create([
            'order_id' => $order->id,
            'sequence' => 1,
            'source' => 'easyparcel',
            'carrier_name' => 'J&T Context Carrier',
            'tracking_number' => 'JT-CONTEXT-9001',
            'status' => 'shipped',
            'currency' => 'MYR',
        ]);
        $sender = $this->staffWith([NotificationPermission::CURRENT_SEND]);
        $action = ['eventKey' => 'shipped', 'staffNote' => 'Current context note.'];

        $preview = $this->actingAs($sender)
            ->postJson("/admin/api/orders/{$order->id}/notifications/preview", $action)
            ->assertOk()
            ->assertJsonPath('data.variables.customer_email', 'preferred-converge@example.com')
            ->assertJsonPath('data.variables.carrier_name', 'J&T Context Carrier')
            ->assertJsonPath('data.variables.tracking_number', 'JT-CONTEXT-9001')
            ->json('data');

        $send = $this->actingAs($sender)
            ->postJson("/admin/api/orders/{$order->id}/notifications/send-current", $action)
            ->assertCreated();
        $dispatch = NotificationDispatch::query()->where('public_id', $send->json('data.id'))->firstOrFail();

        $this->assertSame($preview['subject'], $dispatch->rendered_subject);
        $this->assertSame($preview['preheader'], $dispatch->rendered_preheader);
        $this->assertSame($preview['heading'], $dispatch->rendered_heading);
        $this->assertSame($preview['body'], $dispatch->rendered_body);
        $this->assertSame($preview['ctaText'], $dispatch->rendered_cta_text);
        $this->assertSame($preview['ctaUrl'], $dispatch->rendered_cta_url);
        $this->assertSame('J&T Context Carrier', $dispatch->variable_snapshot['carrier_name']);
        $this->assertSame('preferred-converge@example.com', $dispatch->variable_snapshot['customer_email']);
    }

    public function test_each_manual_action_requires_its_own_permission(): void
    {
        Queue::fake();
        $order = $this->order('PERMISSIONS');
        $failed = NotificationDispatch::factory()->failed()->create(['order_id' => $order->id]);
        $sent = NotificationDispatch::factory()->sent()->create(['order_id' => $order->id]);
        $viewer = $this->staffWith([NotificationPermission::DELIVERIES_VIEW]);

        $this->actingAs($viewer)->postJson("/admin/api/notification-dispatches/{$failed->public_id}/retry")->assertForbidden();
        $this->actingAs($viewer)->postJson("/admin/api/notification-dispatches/{$sent->public_id}/resend")->assertForbidden();
        $this->actingAs($viewer)->postJson("/admin/api/orders/{$order->id}/notifications/send-current", [
            'eventKey' => 'order_created',
        ])->assertForbidden();
    }

    public function test_timeline_eager_loads_resend_sources_with_a_bounded_query_count(): void
    {
        $order = $this->order('TIMELINE-EAGER');
        $originals = NotificationDispatch::factory()->count(3)->sent()->create(['order_id' => $order->id]);

        foreach ($originals as $original) {
            NotificationDispatch::factory()->sent()->create([
                'order_id' => $order->id,
                'resend_of_id' => $original->id,
                'source' => NotificationDispatch::SOURCE_MANUAL_RESEND,
            ]);
        }

        $queries = 0;
        DB::listen(static function () use (&$queries): void {
            $queries++;
        });

        $this->actingAs($this->staffWith([NotificationPermission::DELIVERIES_VIEW]))
            ->getJson("/admin/api/orders/{$order->id}/notification-dispatches")
            ->assertOk()
            ->assertJsonCount(6, 'data');

        $this->assertLessThanOrEqual(6, $queries, 'The timeline must not query each resend source individually.');
    }

    private function order(string $suffix): Order
    {
        $customer = Customer::query()->create([
            'email' => strtolower($suffix).'@example.com',
            'first_name' => 'Alex',
            'last_name' => $suffix,
        ]);
        $order = Order::query()->create([
            'number' => 'JW-NOTIFY-'.$suffix,
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'currency' => 'MYR',
            'status' => 'pending_payment',
            'subtotal' => 10000,
            'discount_amount' => 0,
            'shipping_amount' => 0,
            'tax_amount' => 0,
            'total' => 10000,
        ]);
        $order->items()->create([
            'sku' => 'NOTIFY-'.$suffix,
            'title' => 'Notification Product',
            'variant_title' => 'Default',
            'unit_price' => 10000,
            'quantity' => 1,
            'line_total' => 10000,
        ]);
        $order->addresses()->create([
            'type' => 'shipping',
            'first_name' => 'Alex',
            'last_name' => $suffix,
            'line_1' => '10 Studio Road',
            'city' => 'Kuala Lumpur',
            'postal_code' => '50000',
            'country' => 'MY',
        ]);
        $order->payments()->create([
            'gateway' => 'bank_transfer',
            'status' => 'pending',
            'reference' => 'PAY-'.$suffix,
        ]);

        return $order;
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['is_admin' => true, 'is_super_admin' => true]);
    }

    /** @param list<string> $permissions */
    private function staffWith(array $permissions): User
    {
        $staff = User::factory()->create(['is_admin' => true, 'is_super_admin' => false]);

        foreach ($permissions as $permission) {
            $staff->permissions()->create(['permission' => $permission]);
        }

        return $staff;
    }
}
