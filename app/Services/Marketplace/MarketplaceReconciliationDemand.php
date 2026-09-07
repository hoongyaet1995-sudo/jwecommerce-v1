<?php

namespace App\Services\Marketplace;

/** Read-only classification. Quantities are not instructions to allocate stock. */
class MarketplaceReconciliationDemand
{
    public static function inspect(string $status, array $payload): array
    {
        $error = fn (string $code) => ['error'=>$code, 'kind'=>null, 'lines'=>[]];
        $kind = match ($status) {
            'READY_TO_SHIP', 'PROCESSED' => 'outstanding',
            'UNPAID', 'IN_CANCEL' => 'conditional',
            'SHIPPED' => 'shipped_review',
            default => null,
        };
        if ($kind === null) return $error('unsupported_order_status');
        if (($payload['fulfillment_flag'] ?? null) !== 'fulfilled_by_local_seller' || !empty($payload['advance_package'])) {
            return $error('unsupported_fulfillment');
        }
        $items = $payload['item_list'] ?? null;
        if (!is_array($items) || !array_is_list($items) || !$items) return $error('invalid_order_lines');
        $lines = [];
        foreach ($items as $item) {
            if (!is_array($item) || !is_int($item['item_id'] ?? null) || $item['item_id'] < 1
                || !is_int($item['model_id'] ?? null) || $item['model_id'] < 0
                || !is_int($item['model_quantity_purchased'] ?? null) || $item['model_quantity_purchased'] < 1) {
                return $error('invalid_order_lines');
            }
            if (!empty($item['is_fulfillment_mapping']) || !empty($item['components'])
                || (isset($item['active_qty']) && $item['active_qty'] !== $item['model_quantity_purchased'])
                || !empty($item['cancelled_qty']) || !empty($item['returned_qty'])) {
                return $error('unsupported_order_composition');
            }
            $key = $item['item_id'].':'.$item['model_id'];
            if (isset($lines[$key])) return $error('duplicate_order_line');
            $lines[$key] = ['key'=>$key, 'quantity'=>$item['model_quantity_purchased']];
        }
        return ['error'=>null, 'kind'=>$kind, 'lines'=>array_values($lines)];
    }
}
