<?php

namespace App\Services\Marketplace;

final class MarketplaceOrderLogistics
{
    /** @return array{tracking_number:?string,shipping_carrier:?string} */
    public static function fromPayload(array $payload, string $platform): array
    {
        if ($platform === 'lazada') {
            $items = is_array($payload['order_items'] ?? null) ? $payload['order_items'] : [];
            return [
                'tracking_number' => self::oneValue($items, 'tracking_code'),
                'shipping_carrier' => self::oneValue($items, 'shipment_provider'),
            ];
        }

        $packages = is_array($payload['package_list'] ?? null) ? $payload['package_list'] : [];
        return [
            'tracking_number' => self::text($payload['tracking_number'] ?? null)
                ?? self::oneValue($packages, 'tracking_number'),
            'shipping_carrier' => self::text($payload['shipping_carrier'] ?? null)
                ?? self::oneValue($packages, 'shipping_carrier'),
        ];
    }

    private static function oneValue(array $rows, string $key): ?string
    {
        $values = [];
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $value = self::text($row[$key] ?? null);
            if ($value !== null) $values[$value] = true;
        }
        return count($values) === 1 ? array_key_first($values) : null;
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' && mb_strlen($value) <= 255 ? trim($value) : null;
    }
}
