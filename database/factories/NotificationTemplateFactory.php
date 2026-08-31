<?php

namespace Database\Factories;

use App\Models\NotificationTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<NotificationTemplate> */
class NotificationTemplateFactory extends Factory
{
    protected $model = NotificationTemplate::class;

    public function definition(): array
    {
        $eventKey = Str::lower(fake()->unique()->bothify('notification-event-####'));

        return [
            'event_key' => $eventKey,
            'status_name' => Str::headline($eventKey),
            'color' => 'indigo',
            'email_enabled' => true,
            'email_subject' => '[{shop_name}] Order #{order_number}',
            'email_preheader' => 'An update is available for your order.',
            'email_heading' => 'Order Update',
            'email_body' => 'Dear {customer_name}, your order #{order_number} has been updated.',
            'email_cta_text' => 'View Order Status',
            'email_cta_url' => '{order_url}',
            'whatsapp_enabled' => true,
            'whatsapp_message' => 'Hi {customer_name}, your order #{order_number} has been updated.',
            'whatsapp_cta_buttons' => [[
                'id' => 'btn_1',
                'text' => 'View Order',
                'type' => 'url',
                'value' => '{order_url}',
            ]],
            'version' => 1,
            'updated_by' => null,
        ];
    }
}
