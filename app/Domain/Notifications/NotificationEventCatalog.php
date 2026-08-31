<?php

namespace App\Domain\Notifications;

use InvalidArgumentException;

class NotificationEventCatalog
{
    private const EVENTS = [
        'order_created' => [
            'event_key' => 'order_created',
            'status_name' => 'Pending Payment',
            'color' => 'amber',
            'automatic_email' => true,
            'variables' => ['{order_number}', '{order_total}', '{order_date}', '{order_items_summary}', '{order_url}', '{status_name}', '{staff_note}', '{customer_name}', '{customer_first_name}', '{customer_email}', '{customer_phone}', '{payment_method}', '{payment_reference}', '{shop_name}', '{shop_email}', '{shop_phone}', '{shop_whatsapp}'],
        ],
        'payment_verified' => [
            'event_key' => 'payment_verified',
            'status_name' => 'Processing & Verified',
            'color' => 'indigo',
            'automatic_email' => true,
            'variables' => ['{order_number}', '{order_total}', '{order_date}', '{order_items_summary}', '{order_url}', '{status_name}', '{staff_note}', '{customer_name}', '{customer_first_name}', '{customer_email}', '{customer_phone}', '{payment_method}', '{payment_reference}', '{shipping_address}', '{shop_name}', '{shop_email}', '{shop_phone}', '{shop_whatsapp}'],
        ],
        'payment_proof_rejected' => [
            'event_key' => 'payment_proof_rejected',
            'status_name' => 'Payment Proof Rejected',
            'color' => 'rose',
            'automatic_email' => true,
            'variables' => ['{order_number}', '{order_total}', '{order_date}', '{order_items_summary}', '{order_url}', '{status_name}', '{staff_note}', '{customer_name}', '{customer_first_name}', '{customer_email}', '{customer_phone}', '{payment_method}', '{payment_reference}', '{payment_proof_reason}', '{shop_name}', '{shop_email}', '{shop_phone}', '{shop_whatsapp}'],
        ],
        'ready_to_ship' => [
            'event_key' => 'ready_to_ship',
            'status_name' => 'Packed & AWB Generated',
            'color' => 'cyan',
            'automatic_email' => true,
            'variables' => ['{order_number}', '{order_total}', '{order_date}', '{order_items_summary}', '{order_url}', '{status_name}', '{staff_note}', '{customer_name}', '{customer_first_name}', '{customer_email}', '{customer_phone}', '{shipping_address}', '{carrier_name}', '{tracking_number}', '{tracking_url}', '{shop_name}', '{shop_email}', '{shop_phone}', '{shop_whatsapp}'],
        ],
        'shipped' => [
            'event_key' => 'shipped',
            'status_name' => 'Dispatched (In Transit)',
            'color' => 'blue',
            'automatic_email' => true,
            'variables' => ['{order_number}', '{order_total}', '{order_date}', '{order_items_summary}', '{order_url}', '{status_name}', '{staff_note}', '{customer_name}', '{customer_first_name}', '{customer_email}', '{customer_phone}', '{shipping_address}', '{carrier_name}', '{tracking_number}', '{tracking_url}', '{shop_name}', '{shop_email}', '{shop_phone}', '{shop_whatsapp}'],
        ],
        'delivered' => [
            'event_key' => 'delivered',
            'status_name' => 'Delivered & Completed',
            'color' => 'emerald',
            'automatic_email' => true,
            'variables' => ['{order_number}', '{order_total}', '{order_date}', '{order_items_summary}', '{order_url}', '{status_name}', '{staff_note}', '{customer_name}', '{customer_first_name}', '{customer_email}', '{customer_phone}', '{shipping_address}', '{carrier_name}', '{tracking_number}', '{tracking_url}', '{shop_name}', '{shop_email}', '{shop_phone}', '{shop_whatsapp}'],
        ],
        'cancelled' => [
            'event_key' => 'cancelled',
            'status_name' => 'Cancelled & Restocked',
            'color' => 'rose',
            'automatic_email' => true,
            'variables' => ['{order_number}', '{order_total}', '{order_date}', '{order_items_summary}', '{order_url}', '{status_name}', '{staff_note}', '{customer_name}', '{customer_first_name}', '{customer_email}', '{customer_phone}', '{payment_method}', '{payment_reference}', '{shop_name}', '{shop_email}', '{shop_phone}', '{shop_whatsapp}'],
        ],
        'on_hold' => [
            'event_key' => 'on_hold',
            'status_name' => 'On Hold (Review Required)',
            'color' => 'purple',
            'automatic_email' => false,
            'variables' => ['{order_number}', '{order_total}', '{order_date}', '{order_items_summary}', '{order_url}', '{status_name}', '{staff_note}', '{customer_name}', '{customer_first_name}', '{customer_email}', '{customer_phone}', '{shop_name}', '{shop_email}', '{shop_phone}', '{shop_whatsapp}'],
        ],
    ];

    public static function all(): array
    {
        return self::EVENTS;
    }

    public static function automaticEmailEvents(): array
    {
        return array_filter(self::EVENTS, static fn (array $event): bool => $event['automatic_email']);
    }

    public static function get(string $eventKey): array
    {
        if (! array_key_exists($eventKey, self::EVENTS)) {
            throw new InvalidArgumentException("Unknown notification event [{$eventKey}].");
        }

        return self::EVENTS[$eventKey];
    }

    public static function variablesFor(string $eventKey): array
    {
        return self::get($eventKey)['variables'];
    }
}
