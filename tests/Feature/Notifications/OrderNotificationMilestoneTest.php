<?php

namespace Tests\Feature\Notifications;

use App\Jobs\SendOrderNotificationJob;
use App\Mail\OrderStatusNotificationMail;
use App\Models\Customer;
use App\Models\NotificationTemplate;
use App\Models\Order;
use App\Models\OrderPayment;
use App\Models\OrderPaymentProofEvent;
use App\Services\Notifications\OrderNotificationMilestoneService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\TestCase;

class OrderNotificationMilestoneTest extends TestCase
{
    use RefreshDatabase;

    private Order $order;

    private OrderNotificationMilestoneService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $customer = Customer::query()->create([
            'email' => 'amina@example.test',
            'first_name' => 'Amina',
            'last_name' => 'Buyer',
            'phone' => '+60123456789',
        ]);
        $this->order = Order::query()->create([
            'number' => 'JW-NOTIFY-3001',
            'customer_id' => $customer->id,
            'email' => $customer->email,
            'currency' => 'MYR',
            'status' => 'processing',
            'subtotal' => 19900,
            'discount_amount' => 0,
            'shipping_amount' => 0,
            'tax_amount' => 0,
            'total' => 19900,
        ]);

        $this->template('order_created');
        $this->template('payment_verified');
        $this->template('payment_proof_rejected', [
            'email_body' => 'Payment review: {payment_proof_reason}',
        ]);
        $this->template('on_hold');

        $this->service = app(OrderNotificationMilestoneService::class);
    }

    public function test_regular_milestone_creates_one_dispatch_and_queues_only_after_commit(): void
    {
        Queue::fake();
        $first = null;
        $second = null;

        DB::transaction(function () use (&$first, &$second): void {
            $first = $this->service->record($this->order, 'payment_verified', 'order_payment', 41);
            $second = $this->service->record($this->order, 'payment_verified', 'payment_webhook_event', 99);

            Queue::assertNothingPushed();
        });

        $this->assertNotNull($first);
        $this->assertTrue($first->is($second));
        $this->assertDatabaseCount('notification_dispatches', 1);
        $this->assertSame(
            "automatic:email:order:{$this->order->id}:event:payment_verified",
            $first->idempotency_key,
        );
        $this->assertSame('order_payment', $first->source_type);
        $this->assertSame('41', $first->source_id);
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

    public function test_distinct_payment_proof_rejections_each_create_one_dispatch(): void
    {
        Queue::fake();
        $payment = OrderPayment::query()->create([
            'order_id' => $this->order->id,
            'gateway' => 'duitnow',
            'status' => 'pending',
        ]);
        $firstProofEvent = OrderPaymentProofEvent::query()->create([
            'order_id' => $this->order->id,
            'order_payment_id' => $payment->id,
            'event_type' => 'rejected',
            'reason' => 'Receipt is unclear.',
        ]);
        $secondProofEvent = OrderPaymentProofEvent::query()->create([
            'order_id' => $this->order->id,
            'order_payment_id' => $payment->id,
            'event_type' => 'rejected',
            'reason' => 'Reference does not match.',
        ]);

        $first = $this->service->record(
            $this->order,
            'payment_proof_rejected',
            'order_payment_proof_event',
            $firstProofEvent->id,
            extra: ['payment_proof_reason' => $firstProofEvent->reason],
        );
        $firstDuplicate = $this->service->record(
            $this->order,
            'payment_proof_rejected',
            'order_payment_proof_event',
            $firstProofEvent->id,
            extra: ['payment_proof_reason' => $firstProofEvent->reason],
        );
        $second = $this->service->record(
            $this->order,
            'payment_proof_rejected',
            'order_payment_proof_event',
            $secondProofEvent->id,
            extra: ['payment_proof_reason' => $secondProofEvent->reason],
        );

        $this->assertTrue($first->is($firstDuplicate));
        $this->assertFalse($first->is($second));
        $this->assertDatabaseCount('notification_dispatches', 2);
        $this->assertSame(
            "automatic:email:order:{$this->order->id}:event:payment_proof_rejected:source:order_payment_proof_event:{$firstProofEvent->id}",
            $first->idempotency_key,
        );
        $this->assertSame(
            "automatic:email:order:{$this->order->id}:event:payment_proof_rejected:source:order_payment_proof_event:{$secondProofEvent->id}",
            $second->idempotency_key,
        );
        Queue::assertPushed(SendOrderNotificationJob::class, 2);
    }

    public function test_email_disabled_and_dormant_events_create_no_dispatch(): void
    {
        Queue::fake();
        NotificationTemplate::query()
            ->where('event_key', 'payment_verified')
            ->update(['email_enabled' => false]);

        $disabled = $this->service->record($this->order, 'payment_verified', 'order_payment', 41);
        $dormant = $this->service->record($this->order, 'on_hold', 'order', $this->order->id);

        $this->assertNull($disabled);
        $this->assertNull($dormant);
        $this->assertDatabaseCount('notification_dispatches', 0);
        Queue::assertNothingPushed();
    }

    public function test_automatic_dispatch_rejects_literal_easyparcel_customer_links(): void
    {
        Queue::fake();
        NotificationTemplate::query()
            ->where('event_key', 'payment_verified')
            ->update([
                'email_body' => 'Private status: https://tracking.easyparcel.com/order/secret?token=provider-token',
            ]);

        try {
            $this->service->record($this->order, 'payment_verified', 'order_payment', 41);
            $this->fail('The automatic dispatch accepted a private EasyParcel URL.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }

        $this->assertDatabaseCount('notification_dispatches', 0);
        Queue::assertNothingPushed();
    }

    public function test_automatic_mail_without_template_cta_always_contains_frozen_jw_tracking_url(): void
    {
        Queue::fake();
        NotificationTemplate::query()
            ->where('event_key', 'payment_verified')
            ->update([
                'email_body' => 'Your payment is verified.',
                'email_cta_text' => '',
                'email_cta_url' => '',
            ]);

        $dispatch = $this->service->record($this->order, 'payment_verified', 'order_payment', 41);
        $html = (new OrderStatusNotificationMail($dispatch))->render();

        $this->assertSame(url('/order-tracking'), $dispatch->variable_snapshot['tracking_url']);
        $this->assertStringContainsString(url('/order-tracking'), $html);
        $this->assertStringNotContainsString('easyparcel.com', $html);
        $this->assertStringNotContainsString('provider-token', $html);
        $this->assertStringNotContainsString('label_path', $html);
        $this->assertStringNotContainsString('proof_path', $html);
        Queue::assertPushed(SendOrderNotificationJob::class, 1);
    }

    private function template(string $eventKey, array $changes = []): NotificationTemplate
    {
        return NotificationTemplate::query()->create(array_merge([
            'event_key' => $eventKey,
            'status_name' => 'Order update',
            'color' => 'indigo',
            'email_enabled' => true,
            'email_subject' => 'Order status update',
            'email_preheader' => 'A new order milestone is available.',
            'email_heading' => 'Order update',
            'email_body' => 'Your order has been updated.',
            'email_cta_text' => 'View Order Status',
            'email_cta_url' => '{order_url}',
            'whatsapp_enabled' => false,
            'whatsapp_message' => '',
            'version' => 3,
        ], $changes));
    }
}
