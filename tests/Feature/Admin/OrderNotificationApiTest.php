<?php

namespace Tests\Feature\Admin;

use App\Domain\Notifications\NotificationPermission;
use App\Jobs\SendOrderNotificationJob;
use App\Models\Customer;
use App\Models\NotificationDispatch;
use App\Models\NotificationTemplate;
use App\Models\Order;
use App\Models\User;
use App\Services\Notifications\NotificationTemplateDefaults;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        $staff->permissions()->create(['permission' => NotificationPermission::DELIVERIES_VIEW]);
        $this->actingAs($staff)->getJson("/admin/api/orders/{$order->id}/notification-dispatches")->assertOk();
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
            ->assertJsonPath('data.subject', '[JW Studio] Order #JW-NOTIFY-PREVIEW Received - Please Complete Payment')
            ->assertJsonPath('data.variables.order_number', 'JW-NOTIFY-PREVIEW')
            ->assertJsonPath('data.variables.staff_note', 'Please complete payment before Friday.');
    }

    public function test_timeline_is_order_scoped_and_never_leaks_recipients_bodies_or_provider_fields(): void
    {
        $firstOrder = $this->order('FIRST');
        $secondOrder = $this->order('SECOND');
        $first = NotificationDispatch::factory()->create([
            'order_id' => $firstOrder->id,
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
            ->assertJsonPath('data.0.id', $first->public_id);

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

        $this->actingAs($this->staffWith([NotificationPermission::DELIVERIES_RETRY]))
            ->postJson("/admin/api/notification-dispatches/{$failed->public_id}/retry")
            ->assertOk()
            ->assertJsonPath('data.id', $failed->public_id)
            ->assertJsonPath('data.state', NotificationDispatch::STATE_QUEUED)
            ->assertJsonPath('data.subject', 'Frozen retry subject');

        $failed->refresh();
        $this->assertSame('Frozen retry subject', $failed->rendered_subject);
        $this->assertSame('Frozen retry body', $failed->rendered_body);
        $this->assertSame(4, $failed->attempt_count);
        $this->assertNull($failed->failed_at);
        $this->assertNull($failed->last_error_code);
        Queue::assertPushed(SendOrderNotificationJob::class, fn (SendOrderNotificationJob $job): bool => $job->dispatchId === $failed->id);

        $this->actingAs($this->superAdmin())
            ->postJson("/admin/api/notification-dispatches/{$sent->public_id}/retry")
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
        $this->assertNull($dispatch->idempotency_key);
        $this->assertSame('Manual order note', $dispatch->variable_snapshot['staff_note']);

        $this->actingAs($this->superAdmin())
            ->getJson("/admin/api/orders/{$secondOrder->id}/notification-dispatches")
            ->assertOk()
            ->assertJsonCount(0, 'data');
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
