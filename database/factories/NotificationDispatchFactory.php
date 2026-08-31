<?php

namespace Database\Factories;

use App\Models\NotificationDispatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<NotificationDispatch> */
class NotificationDispatchFactory extends Factory
{
    protected $model = NotificationDispatch::class;

    public function definition(): array
    {
        return [
            'order_id' => null,
            'notification_template_id' => null,
            'channel' => 'email',
            'event_key' => 'order_created',
            'source_type' => 'test',
            'source_id' => null,
            'idempotency_key' => null,
            'recipient_email' => fake()->safeEmail(),
            'recipient_name' => fake()->name(),
            'rendered_subject' => 'Your order update',
            'rendered_preheader' => 'An update is available for your order.',
            'rendered_heading' => 'Order Update',
            'rendered_body' => 'Your order status has been updated.',
            'rendered_cta_text' => 'View Order Status',
            'rendered_cta_url' => url('/order-tracking'),
            'template_version' => 1,
            'variable_snapshot' => ['shop_name' => 'JW Studio'],
            'state' => NotificationDispatch::STATE_QUEUED,
            'source' => NotificationDispatch::SOURCE_TEST,
            'attempt_count' => 0,
            'delivery_lease_token' => null,
            'delivery_lease_expires_at' => null,
            'queued_at' => now(),
            'sent_at' => null,
            'failed_at' => null,
            'last_error_code' => null,
            'provider_message_id' => null,
            'requested_by' => null,
            'resend_of_id' => null,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn (): array => [
            'state' => NotificationDispatch::STATE_SENT,
            'sent_at' => now(),
            'attempt_count' => 1,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'state' => NotificationDispatch::STATE_FAILED,
            'failed_at' => now(),
            'attempt_count' => 1,
            'last_error_code' => 'unknown',
        ]);
    }
}
